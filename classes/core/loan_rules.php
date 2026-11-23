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
 * Loan rules.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * What a reader may borrow and until when, from the rules of a library by reader type and item type, as Abies has them.
 * A rule is an array with readertype, itemtype ("*" for any), loandays (0: not lent), maxloans (0: no limit), renewals
 * and renewdays; and, for a loan by hours (a laptop, a charger, a camera), loanhours (1 to 12, or 24 for the end of the
 * day) and renewhours.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class loan_rules {
    /** @var string The renewal days count from the day the loan is renewed: the reader comes back the same weekday. */
    public const FROM_TODAY = 'today';

    /** @var string The renewal days count from the date the loan was due: its dates follow the loan's. */
    public const FROM_DUE = 'due';

    /** @var int The hours of a rule that lends until the end of the day. */
    public const END_OF_DAY = 24;

    /** @var int The most hours a rule lends for, short of the end of the day. */
    public const MAX_HOURS = 12;

    /**
     * The hours a rule lends for: 1 to 12, or 24 for the end of the day; 0 when it lends by days.
     *
     * @param array|null $rule
     * @return int
     */
    public static function hours(?array $rule): int {
        $hours = (int) ($rule['loanhours'] ?? 0);
        return $hours === self::END_OF_DAY || ($hours >= 1 && $hours <= self::MAX_HOURS) ? $hours : 0;
    }

    /**
     * The rule that applies: an exact match first, then a rule for any item, then for any reader, then for anything.
     *
     * @param array[] $rules
     * @param string $readertype
     * @param string $itemtype
     * @return array|null
     */
    public static function pick(array $rules, string $readertype, string $itemtype): ?array {
        $best = null;
        $bestscore = -1;
        foreach ($rules as $rule) {
            $rule = (array) $rule;
            $readerok = $rule['readertype'] === $readertype || $rule['readertype'] === '*';
            $itemok = $rule['itemtype'] === $itemtype || $rule['itemtype'] === '*';
            if (!$readerok || !$itemok) {
                continue;
            }
            $score = ($rule['readertype'] === $readertype ? 2 : 0) + ($rule['itemtype'] === $itemtype ? 1 : 0);
            if ($score > $bestscore) {
                $best = $rule;
                $bestscore = $score;
            }
        }
        return $best;
    }

    /**
     * Whether one more loan can be made under a rule.
     *
     * @param array|null $rule
     * @param int $openloans Loans the reader has out under that rule.
     * @return bool
     */
    public static function can_lend(?array $rule, int $openloans): bool {
        if ($rule === null || ((int) $rule['loandays'] <= 0 && self::hours($rule) === 0)) {
            return false;
        }
        return (int) $rule['maxloans'] === 0 || $openloans < (int) $rule['maxloans'];
    }

    /**
     * The due moment: the end of the day the given days later, moved to the next open day when it falls on a day the
     * libraries close every week or on a day the centre is closed.
     *
     * @param int $from
     * @param int $days
     * @param \DateTimeZone $tz
     * @param bool $skipweekend Whether the days they close every week are skipped.
     * @param array $closed The centre's closed days, 'Y-m-d' => true.
     * @param int[] $rest The days they close every week, by their ISO number: Saturday and Sunday unless the centre says.
     * @return int
     */
    public static function due(
        int $from,
        int $days,
        \DateTimeZone $tz,
        bool $skipweekend = true,
        array $closed = [],
        array $rest = [6, 7]
    ): int {
        $date = (new \DateTimeImmutable('@' . $from))->setTimezone($tz)->modify('+' . $days . ' days');
        // A year of holidays at most: past it, the day as it fell.
        for ($i = 0; $i < 366; $i++) {
            $weekend = $skipweekend && in_array((int) $date->format('N'), $rest, true);
            if (!$weekend && !isset($closed[$date->format('Y-m-d')])) {
                break;
            }
            $date = $date->modify('+1 day');
        }
        return $date->setTime(23, 59, 59)->getTimestamp();
    }

    /**
     * The hours a loan under a rule is lent and renewed by: 1 to 12; 0 for a rule by days or until the end of the day,
     * which is late, and renewed, as a loan by days is.
     *
     * @param array|null $rule
     * @return int
     */
    public static function hourly(?array $rule): int {
        $hours = self::hours($rule);
        return $hours < self::END_OF_DAY ? $hours : 0;
    }

    /**
     * The due moment of a loan by hours: so many hours later, at the same minute; on the end of the day for 24. A moment
     * that falls on a day the library is closed moves to the next open day, at the same time.
     *
     * @param int $from
     * @param int $hours 1 to 12, or 24.
     * @param \DateTimeZone $tz
     * @param array $closed The centre's closed days, 'Y-m-d' => true.
     * @param int[] $rest The days they close every week, by their ISO number.
     * @return int
     */
    public static function due_hours(int $from, int $hours, \DateTimeZone $tz, array $closed = [], array $rest = [6, 7]): int {
        if ($hours >= self::END_OF_DAY) {
            return self::due($from, 0, $tz, true, $closed, $rest);
        }
        $date = (new \DateTimeImmutable('@' . ($from + max(1, $hours) * 3600)))->setTimezone($tz);
        for ($i = 0; $i < 366; $i++) {
            if (!in_array((int) $date->format('N'), $rest, true) && !isset($closed[$date->format('Y-m-d')])) {
                break;
            }
            $date = $date->modify('+1 day');
        }
        return $date->getTimestamp();
    }

    /**
     * Whether a loan can be renewed once more.
     *
     * @param array|null $rule
     * @param int $renewalsdone
     * @return bool
     */
    public static function can_renew(?array $rule, int $renewalsdone): bool {
        // A loan by hours is renewed by its renewal hours or, when it has none, by as many as it was lent for.
        $more = self::hourly($rule) > 0 ? self::renew_hours($rule) : (int) ($rule['renewdays'] ?? 0);
        return $rule !== null && $more > 0 && $renewalsdone < (int) $rule['renewals'];
    }

    /**
     * The new due moment of a renewed loan: the renewal days count from today (FROM_TODAY: whoever renews on a Thursday
     * comes back on a Thursday) or from the old due date (FROM_DUE: the date it was owed), as the centre chooses. Either way
     * it is more time: a count that falls on or before the later of today and the old due date (a loan renewed long before
     * its date, or long after it) counts from that one.
     *
     * @param int $currentdue
     * @param int $now
     * @param array $rule
     * @param \DateTimeZone $tz
     * @param array $closed The centre's closed days, 'Y-m-d' => true.
     * @param int[] $rest The days the libraries close every week, by their ISO number.
     * @param string $from FROM_TODAY or FROM_DUE.
     * @return int
     */
    public static function renewed_due(
        int $currentdue,
        int $now,
        array $rule,
        \DateTimeZone $tz,
        array $closed = [],
        array $rest = [6, 7],
        string $from = self::FROM_TODAY
    ): int {
        $later = max($currentdue, $now);
        // A loan by hours gets hours more: from now, or from the hour it was due.
        if (self::hourly($rule) > 0) {
            $hours = self::renew_hours($rule);
            $due = self::due_hours($from === self::FROM_DUE ? $currentdue : $now, $hours, $tz, $closed, $rest);
            return $due > $later ? $due : self::due_hours($later, $hours, $tz, $closed, $rest);
        }
        $days = (int) $rule['renewdays'];
        $due = self::due($from === self::FROM_DUE ? $currentdue : $now, $days, $tz, true, $closed, $rest);
        return $due > $later ? $due : self::due($later, $days, $tz, true, $closed, $rest);
    }

    /**
     * The hours a renewal adds to a loan by hours: the rule's renewal hours, or as many as it lends for.
     *
     * @param array $rule
     * @return int
     */
    public static function renew_hours(array $rule): int {
        return self::hourly(['loanhours' => (int) ($rule['renewhours'] ?? 0)]) ?: self::hourly($rule);
    }

    /**
     * Whole days a loan is late: 0 while it is not.
     *
     * @param int $due
     * @param int $now
     * @param \DateTimeZone $tz
     * @return int
     */
    public static function days_late(int $due, int $now, \DateTimeZone $tz): int {
        if ($now <= $due) {
            return 0;
        }
        $duedate = (new \DateTimeImmutable('@' . $due))->setTimezone($tz)->setTime(0, 0);
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0);
        return (int) $duedate->diff($today)->days;
    }
}
