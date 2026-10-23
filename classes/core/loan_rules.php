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
 * and renewdays.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class loan_rules {
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
        if ($rule === null || (int) $rule['loandays'] <= 0) {
            return false;
        }
        return (int) $rule['maxloans'] === 0 || $openloans < (int) $rule['maxloans'];
    }

    /**
     * The due moment: the end of the day the given days later, moved to the next Monday when it falls on a weekend.
     *
     * @param int $from
     * @param int $days
     * @param \DateTimeZone $tz
     * @param bool $skipweekend
     * @return int
     */
    public static function due(int $from, int $days, \DateTimeZone $tz, bool $skipweekend = true): int {
        $date = (new \DateTimeImmutable('@' . $from))->setTimezone($tz)->modify('+' . $days . ' days');
        if ($skipweekend) {
            $weekday = (int) $date->format('N');
            if ($weekday >= 6) {
                $date = $date->modify('+' . (8 - $weekday) . ' days');
            }
        }
        return $date->setTime(23, 59, 59)->getTimestamp();
    }

    /**
     * Whether a loan can be renewed once more.
     *
     * @param array|null $rule
     * @param int $renewalsdone
     * @return bool
     */
    public static function can_renew(?array $rule, int $renewalsdone): bool {
        return $rule !== null && (int) $rule['renewdays'] > 0 && $renewalsdone < (int) $rule['renewals'];
    }

    /**
     * The new due moment of a renewed loan: the renewal days count from today or from the old due date, the later.
     *
     * @param int $currentdue
     * @param int $now
     * @param array $rule
     * @param \DateTimeZone $tz
     * @return int
     */
    public static function renewed_due(int $currentdue, int $now, array $rule, \DateTimeZone $tz): int {
        return self::due(max($currentdue, $now), (int) $rule['renewdays'], $tz);
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
