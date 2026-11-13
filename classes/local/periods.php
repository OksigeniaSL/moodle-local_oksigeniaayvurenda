<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The periods a report is looked at by: sheets that are touched, not dates typed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A report of a period had six lists of day, month and year (with years from 2016) and a «Show». Now the periods are a row
 * of sheets, each with its dates under its name: this week, this month, each term of the sheet of the year, the year,
 * last year, and «Another», which opens the strip of the twelve months to touch the first and the last. Exact days come
 * from the sheet of the year. All are links: a report is something looked at.
 */
final class periods {
    /** @var string[] The periods, in their order. */
    public const KEYS = ['semana', 'mes', 't1', 't2', 't3', 'curso', 'pasado'];

    /**
     * The moments a period starts and ends (the end is the start of the next day), and which period it is.
     *
     * @param \stdClass $library
     * @param int $now
     * @param string $key One of KEYS, or ''.
     * @param string $desde A first month, 'Y-m', or a first day, 'Y-m-d'.
     * @param string $hasta A last month or day.
     * @return array [from, to, key]: key is the period, «otro», or the year by default.
     */
    public static function resolve(\stdClass $library, int $now, string $key, string $desde, string $hasta): array {
        $zone = zones::of($library);
        $day = fn(string $text) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? new \DateTimeImmutable($text . ' 00:00', $zone)
            : null;
        $month = fn(string $text) => preg_match('/^\d{4}-\d{2}$/', $text) ? new \DateTimeImmutable($text . '-01 00:00', $zone)
            : null;
        $start = $day($desde) ?? $month($desde);
        if ($start) {
            $end = $day($hasta) ? $day($hasta)->modify('+1 day')
                : ($month($hasta) ? $month($hasta)->modify('first day of next month') : $start->modify('first day of next month'));
            if ($end > $start) {
                return [$start->getTimestamp(), $end->getTimestamp(), 'otro'];
            }
        }
        foreach (self::all($library, $now) as $period) {
            if ($period['key'] === $key) {
                return [$period['from'], $period['to'], $key];
            }
        }
        $year = years::around((int) $library->entityid, $now);
        return [(int) $year->timestart, (int) $year->timeend, 'curso'];
    }

    /**
     * The periods of a library now: key, from, to and what is said of them.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[]
     */
    public static function all(\stdClass $library, int $now): array {
        $entityid = (int) $library->entityid;
        $zone = zones::of($library);
        $at = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($zone);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $short = fn(int $time) => \core_text::strtolower(userdate($time, '%b'));
        $periods = [];
        $week = zones::week_start($library, $now);
        $periods[] = ['key' => 'semana', 'from' => $week, 'to' => $at($week)->modify('+7 days')->getTimestamp(),
            'name' => $str('periodweek'), 'dates' => $str('periodweekdates', (object) [
                'from' => (int) $at($week)->format('j'),
                'to' => (int) $at($week)->modify('+6 days')->format('j'),
                'month' => $short($at($week)->modify('+6 days')->getTimestamp()),
            ])];
        $first = $at($now)->modify('first day of this month')->setTime(0, 0);
        $periods[] = ['key' => 'mes', 'from' => $first->getTimestamp(),
            'to' => $first->modify('first day of next month')->getTimestamp(), 'name' => $str('periodmonth'),
            'dates' => userdate($first->getTimestamp() + DAYSECS, '%B')];
        $year = years::around($entityid, $now);
        if (!skins::no_classes($entityid)) {
            $ends = challenges::ends($year);
            $from = (int) $year->timestart;
            foreach ($ends as $i => $end) {
                $to = $end + 1;
                // A term counts from the day after the one before, but it is named by the month it opens in: the second
                // starts after the winter closure, in January, not on the last days of December.
                $periods[] = ['key' => 't' . ($i + 1), 'from' => $from, 'to' => $to, 'name' => $str('periodterm' . ($i + 1)),
                    'dates' => $str('periodrange', (object) ['from' => $short(self::opens($library, $from)),
                        'to' => $short($end)])];
                $from = $to;
            }
        }
        $periods[] = ['key' => 'curso', 'from' => (int) $year->timestart, 'to' => (int) $year->timeend,
            'name' => $str(skins::words($entityid, 'periodyear')), 'dates' => (string) $year->name];
        $last = years::around($entityid, (int) $year->timestart - DAYSECS);
        $periods[] = ['key' => 'pasado', 'from' => (int) $last->timestart, 'to' => (int) $last->timeend,
            'name' => $str(skins::words($entityid, 'periodlastyear')), 'dates' => (string) $last->name];
        return $periods;
    }

    /**
     * The first day from a moment on that the centre opens: not a day of rest of its week, not in a closure.
     *
     * @param \stdClass $library
     * @param int $time
     * @return int Noon of that day, or of the day of the moment if none opens within a month.
     */
    public static function opens(\stdClass $library, int $time): int {
        $zone = zones::of($library);
        $rest = zones::rest_days($library);
        $ranges = closures::of((int) $library->entityid);
        $first = (new \DateTimeImmutable('@' . $time))->setTimezone($zone)->setTime(12, 0);
        $day = $first;
        for ($i = 0; $i < 31; $i++) {
            $date = $day->format('Y-m-d');
            $shut = in_array((int) $day->format('N'), $rest, true);
            foreach ($ranges as $range) {
                $shut = $shut || ($date >= $range['from'] && $date <= $range['to']);
            }
            if (!$shut) {
                return $day->getTimestamp();
            }
            $day = $day->modify('+1 day');
        }
        return $first->getTimestamp();
    }

    /**
     * The strip of the months of the year for «Another»: with nothing chosen, each month starts; with a first month,
     * those it can end in (from it to this month) are links, the rest are off.
     *
     * @param \stdClass $library
     * @param int $now
     * @param string $desde The first month chosen, 'Y-m', or ''.
     * @param callable $url Makes the address of a month: fn(array $params): string.
     * @return array[] label, url (or '' when off), first (the first month chosen).
     */
    public static function months(\stdClass $library, int $now, string $desde, callable $url): array {
        $zone = zones::of($library);
        $year = years::around((int) $library->entityid, $now);
        $month = (new \DateTimeImmutable('@' . (int) $year->timestart))->setTimezone($zone)->modify('first day of this month');
        $current = (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m');
        $desde = preg_match('/^\d{4}-\d{2}$/', $desde) ? $desde : '';
        $strip = [];
        for ($i = 0; $i < 12; $i++) {
            $key = $month->format('Y-m');
            $on = $key <= $current && ($desde === '' || $key >= $desde);
            $strip[] = [
                'label' => \core_text::strtolower(userdate($month->getTimestamp() + DAYSECS, '%b')),
                'url' => $on ? $url($desde === '' ? ['desde' => $key, 'otro' => 1] : ['desde' => $desde, 'hasta' => $key]) : '',
                'first' => $key === $desde,
            ];
            $month = $month->modify('first day of next month');
        }
        return $strip;
    }
}
