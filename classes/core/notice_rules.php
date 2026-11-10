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
 * When late books are told about.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * When a late book is told about, as each library chooses: a school does not want a message for a day or two late, and
 * tells at the end of each term; a university tells the next day and every week. How long late before the first notice,
 * how often after it (a rhythm, some days of the year, or only when the desk says «Tell»), and to whom.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class notice_rules {
    /** @var int[] The rhythms, by the days between two notices to the same person. */
    public const EVERY = ['week' => 7, 'fortnight' => 14, 'month' => 30, 'twomonths' => 61, 'term' => 91];

    /** @var string On days the library sets, the same every year (the end of each term). */
    public const DATES = 'dates';

    /** @var string Never by itself: only when someone at the desk says «Tell». */
    public const HAND = 'hand';

    /** @var int How long one of the library's days still counts once it has passed: a morning the task did not run. */
    private const LATE_DAY = 7 * 86400;

    /** @var array[] Starting points of a touch, by kind of library, retouched after (a school's days come from its year). */
    public const PRESETS = [
        'school' => ['noticeafter' => 15, 'noticeevery' => self::DATES, 'noticereaders' => true, 'noticefamilies' => true,
            'noticetutors' => true],
        'university' => ['noticeafter' => 1, 'noticeevery' => 'week', 'noticereaders' => true, 'noticefamilies' => false,
            'noticetutors' => false],
        'public' => ['noticeafter' => 3, 'noticeevery' => 'week', 'noticereaders' => true, 'noticefamilies' => false,
            'noticetutors' => false],
    ];

    /**
     * Every rhythm a library can choose, in the order they are offered.
     *
     * @return string[]
     */
    public static function rhythms(): array {
        return array_merge(array_keys(self::EVERY), [self::DATES, self::HAND]);
    }

    /**
     * Days of the year as MM-DD, sorted and without repeats; a whole date (Y-m-d, from a date field) keeps its month and
     * day; what is not a day goes (29 February is one). Eight at most.
     *
     * @param array $dates
     * @return string[]
     */
    public static function dates(array $dates): array {
        $out = [];
        foreach ($dates as $date) {
            $date = trim((string) $date);
            if (preg_match('/^\d{4}-(\d{2}-\d{2})$/', $date, $match)) {
                $date = $match[1];
            }
            if (preg_match('/^(\d{2})-(\d{2})$/', $date, $match) && checkdate((int) $match[1], (int) $match[2], 2024)) {
                $out[$date] = true;
            }
        }
        $out = array_keys($out);
        sort($out);
        return array_slice($out, 0, 8);
    }

    /**
     * The moment to judge a late book at, when it is time to tell someone told last at $last: now, once the days of the
     * rhythm have gone by (an hour less, so a morning a few minutes earlier than last week's still counts); one of the
     * library's days, once it has come and not a week ago, if they were not told since (a book late enough that day is
     * told; one that became late after it waits for the next); never, when the library tells only by hand.
     *
     * @param array $settings noticeevery and noticedates, as sanction_rules::settings() gives them.
     * @param int $last When they were told last, 0 never.
     * @param int $now
     * @param \DateTimeZone $tz
     * @return int|null
     */
    public static function moment(array $settings, int $last, int $now, \DateTimeZone $tz): ?int {
        $every = (string) $settings['noticeevery'];
        if (isset(self::EVERY[$every])) {
            return $last <= $now - self::EVERY[$every] * 86400 + 3600 ? $now : null;
        }
        if ($every !== self::DATES) {
            return null;
        }
        $day = self::last_day((array) $settings['noticedates'], $now, $tz);
        return $day !== null && $last < $day ? $day : null;
    }

    /**
     * The start of the latest of the library's days that has come, if it was not more than a week ago.
     *
     * @param string[] $dates MM-DD.
     * @param int $now
     * @param \DateTimeZone $tz
     * @return int|null
     */
    public static function last_day(array $dates, int $now, \DateTimeZone $tz): ?int {
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
        $best = null;
        foreach (self::dates($dates) as $date) {
            [$month, $day] = array_map('intval', explode('-', $date));
            // This year's, or last year's for a day at the end of December seen in the first week of January.
            foreach ([(int) $today->format('Y'), (int) $today->format('Y') - 1] as $year) {
                if (!checkdate($month, $day, $year)) {
                    continue;
                }
                $start = $today->setDate($year, $month, $day)->setTime(0, 0)->getTimestamp();
                if ($start <= $now && $start > $now - self::LATE_DAY) {
                    $best = max($best ?? $start, $start);
                }
            }
        }
        return $best;
    }

    /**
     * Three days for a school, from the span of its classes: the end of each third of it, where terms often end, as MM-DD.
     *
     * @param int $start
     * @param int $end
     * @param \DateTimeZone $tz
     * @return string[]
     */
    public static function thirds(int $start, int $end, \DateTimeZone $tz): array {
        $span = max(1, $end - $start);
        $days = [];
        foreach ([1, 2, 3] as $part) {
            // The last moment of the third, not the first of the next.
            $days[] = (new \DateTimeImmutable('@' . (int) ($start + intdiv($span * $part, 3) - 1)))->setTimezone($tz)
                ->format('m-d');
        }
        return self::dates($days);
    }
}
