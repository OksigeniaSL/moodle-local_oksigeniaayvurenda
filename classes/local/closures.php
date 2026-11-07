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
 * The days a centre's libraries are closed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Holidays, a summer, a strike, a move: whatever the centre closes, by ranges of days with a name. Nothing falls due on a
 * closed day (a loan, a book set aside): it moves to the next open one, as weekends already do. Kept in the centre's
 * settings, in its own zone, as days ('Y-m-d').
 */
final class closures {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How many ranges a centre keeps. */
    public const MOST = 60;

    /** @var int The longest range, in days (a summer fits). */
    public const LONGEST = 120;

    /**
     * A centre's closed ranges, the earliest first.
     *
     * @param int $entityid
     * @return array[] from, to ('Y-m-d') and label.
     */
    public static function of(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $ranges = array_values(array_filter((array) ($settings['closures'] ?? []), fn($r) => is_array($r)
            && self::day((string) ($r['from'] ?? '')) && self::day((string) ($r['to'] ?? ''))));
        usort($ranges, fn($a, $b) => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);
        return array_map(fn($r) => ['from' => (string) $r['from'], 'to' => (string) $r['to'],
            'label' => (string) ($r['label'] ?? '')], $ranges);
    }

    /**
     * The closed days of a centre, from yesterday on, as a set for loan_rules::due().
     *
     * @param int $entityid
     * @param int|null $now
     * @return array 'Y-m-d' => true.
     */
    public static function days(int $entityid, ?int $now = null): array {
        $tz = zones::of($entityid);
        $since = (new \DateTimeImmutable('@' . (($now ?? time()) - DAYSECS)))->setTimezone($tz)->format('Y-m-d');
        $days = [];
        foreach (self::of($entityid) as $range) {
            if ($range['to'] < $since) {
                continue;
            }
            $day = new \DateTimeImmutable($range['from'], $tz);
            for ($i = 0; $i <= self::LONGEST && $day->format('Y-m-d') <= $range['to']; $i++) {
                $days[$day->format('Y-m-d')] = true;
                $day = $day->modify('+1 day');
            }
        }
        return $days;
    }

    /**
     * How many closed days there were after the day a book was due, up to today (the days late that do not count).
     *
     * @param int $entityid
     * @param int $due
     * @param int $now
     * @return int
     */
    public static function between(int $entityid, int $due, int $now): int {
        $tz = zones::of($entityid);
        $after = (new \DateTimeImmutable('@' . $due))->setTimezone($tz)->format('Y-m-d');
        $until = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->format('Y-m-d');
        if ($until <= $after) {
            return 0;
        }
        $days = [];
        foreach (self::of($entityid) as $range) {
            if ($range['to'] <= $after || $range['from'] > $until) {
                continue;
            }
            $day = new \DateTimeImmutable(max($range['from'], $after), $tz);
            for ($i = 0; $i <= self::LONGEST && $day->format('Y-m-d') <= min($range['to'], $until); $i++) {
                if ($day->format('Y-m-d') > $after) {
                    $days[$day->format('Y-m-d')] = true;
                }
                $day = $day->modify('+1 day');
            }
        }
        return count($days);
    }

    /**
     * Adds a range: the dates in either order, at most LONGEST days, and at most MOST ranges kept (the past go first).
     *
     * @param int $entityid
     * @param string $from 'Y-m-d'
     * @param string $to 'Y-m-d'
     * @param string $label
     * @return bool Whether it was kept.
     */
    public static function add(int $entityid, string $from, string $to, string $label): bool {
        if (!self::day($from) || !self::day($to)) {
            return false;
        }
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $length = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
        if ($length > self::LONGEST) {
            return false;
        }
        $ranges = self::of($entityid);
        $ranges[] = ['from' => $from, 'to' => $to, 'label' => \core_text::substr(trim(clean_param($label, PARAM_TEXT)), 0, 60)];
        usort($ranges, fn($a, $b) => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);
        self::keep($entityid, array_slice($ranges, -self::MOST));
        return true;
    }

    /**
     * Takes away a range.
     *
     * @param int $entityid
     * @param string $from
     * @param string $to
     */
    public static function remove(int $entityid, string $from, string $to): void {
        self::keep($entityid, array_values(array_filter(
            self::of($entityid),
            fn($r) => !($r['from'] === $from && $r['to'] === $to)
        )));
    }

    /**
     * Whether a text is a day that exists, 'Y-m-d'.
     *
     * @param string $day
     * @return bool
     */
    private static function day(string $day): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Writes the ranges in the centre's settings.
     *
     * @param int $entityid
     * @param array[] $ranges
     */
    private static function keep(int $entityid, array $ranges): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $settings['closures'] = $ranges;
        if (!$ranges) {
            unset($settings['closures']);
        }
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }
}
