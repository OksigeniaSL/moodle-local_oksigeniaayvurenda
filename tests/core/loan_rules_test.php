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

namespace local_oksigeniaayvurenda\core;

/**
 * Tests for the core class loan_rules.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\loan_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(loan_rules::class)]
final class loan_rules_test extends \basic_testcase {
    /** @var array[] Rules as a school might have them. */
    private const RULES = [
        ['readertype' => 'student', 'itemtype' => 'normal', 'loandays' => 7, 'maxloans' => 1, 'renewals' => 2, 'renewdays' => 7],
        ['readertype' => 'teacher', 'itemtype' => '*', 'loandays' => 30, 'maxloans' => 0, 'renewals' => 1, 'renewdays' => 15],
        ['readertype' => '*', 'itemtype' => 'reference', 'loandays' => 0, 'maxloans' => 0, 'renewals' => 0, 'renewdays' => 0],
        ['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 2, 'renewals' => 0, 'renewdays' => 0],
    ];

    /**
     * The most specific rule wins: exact, then any item, then any reader, then anything.
     */
    public function test_pick(): void {
        $this->assertSame(7, loan_rules::pick(self::RULES, 'student', 'normal')['loandays']);
        $this->assertSame(30, loan_rules::pick(self::RULES, 'teacher', 'reference')['loandays']);
        $this->assertSame(0, loan_rules::pick(self::RULES, 'student', 'reference')['loandays']);
        $this->assertSame(14, loan_rules::pick(self::RULES, 'family', 'device')['loandays']);
        $this->assertNull(loan_rules::pick([self::RULES[0]], 'teacher', 'normal'));
    }

    /**
     * A reader may borrow while under the limit; a rule with no days lends nothing.
     */
    public function test_can_lend(): void {
        $student = loan_rules::pick(self::RULES, 'student', 'normal');
        $this->assertTrue(loan_rules::can_lend($student, 0));
        $this->assertFalse(loan_rules::can_lend($student, 1));
        $this->assertTrue(loan_rules::can_lend(loan_rules::pick(self::RULES, 'teacher', 'normal'), 40));
        $this->assertFalse(loan_rules::can_lend(loan_rules::pick(self::RULES, 'student', 'reference'), 0));
        $this->assertFalse(loan_rules::can_lend(null, 0));
    }

    /**
     * The due date is the end of the day, moved to Monday when it falls on a weekend.
     */
    public function test_due(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $friday = (new \DateTimeImmutable('2026-10-23 11:00', $tz))->getTimestamp();
        $this->assertSame((new \DateTimeImmutable('2026-10-30 23:59:59', $tz))->getTimestamp(), loan_rules::due($friday, 7, $tz));
        $monday = (new \DateTimeImmutable('2026-10-19 11:00', $tz))->getTimestamp();
        $this->assertSame((new \DateTimeImmutable('2026-10-26 23:59:59', $tz))->getTimestamp(), loan_rules::due($monday, 5, $tz));
        $this->assertSame(
            (new \DateTimeImmutable('2026-10-24 23:59:59', $tz))->getTimestamp(),
            loan_rules::due($monday, 5, $tz, false)
        );
        // Closed from Monday 26 to Wednesday 28: it falls due on Thursday 29; and a closed Monday after a weekend, Tuesday.
        $closed = ['2026-10-26' => true, '2026-10-27' => true, '2026-10-28' => true];
        $this->assertSame(
            (new \DateTimeImmutable('2026-10-29 23:59:59', $tz))->getTimestamp(),
            loan_rules::due($monday, 5, $tz, true, $closed)
        );
        $this->assertSame(
            (new \DateTimeImmutable('2026-10-27 23:59:59', $tz))->getTimestamp(),
            loan_rules::due($monday, 5, $tz, true, ['2026-10-26' => true])
        );
    }

    /**
     * Renewals: as many as the rule says, counted from today or from the old due date as the centre chooses, and always
     * more time: a count that falls on or before the later of the two counts from that one.
     */
    public function test_renew(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $student = loan_rules::pick(self::RULES, 'student', 'normal');
        $this->assertTrue(loan_rules::can_renew($student, 1));
        $this->assertFalse(loan_rules::can_renew($student, 2));
        $this->assertFalse(loan_rules::can_renew(loan_rules::pick(self::RULES, 'family', 'device'), 0));
        $at = fn(string $when) => (new \DateTimeImmutable($when, $tz))->getTimestamp();
        $due = $at('2026-10-30 23:59:59');
        $today = loan_rules::FROM_TODAY;
        $fromdue = loan_rules::FROM_DUE;
        // Two days early: a week from today, or a week from its date.
        $this->assertSame($at('2026-11-04 23:59:59'), loan_rules::renewed_due($due, $at('2026-10-28 09:00'), $student, $tz));
        $this->assertSame(
            $at('2026-11-06 23:59:59'),
            loan_rules::renewed_due($due, $at('2026-10-28 09:00'), $student, $tz, [], [6, 7], $fromdue)
        );
        // Four days late: a week from today (the same weekday), or the date it was owed.
        $this->assertSame($at('2026-11-10 23:59:59'), loan_rules::renewed_due($due, $at('2026-11-03 09:00'), $student, $tz));
        $this->assertSame(
            $at('2026-11-06 23:59:59'),
            loan_rules::renewed_due($due, $at('2026-11-03 09:00'), $student, $tz, [], [6, 7], $fromdue)
        );
        // So late that its date plus a week has passed: from today.
        $this->assertSame(
            $at('2026-11-27 23:59:59'),
            loan_rules::renewed_due($due, $at('2026-11-20 09:00'), $student, $tz, [], [6, 7], $fromdue)
        );
        // So early that a week from today is before its date: from its date.
        $this->assertSame(
            $at('2026-11-06 23:59:59'),
            loan_rules::renewed_due($due, $at('2026-10-20 09:00'), $student, $tz, [], [6, 7], $today)
        );
    }

    /**
     * A rule by hours lends even with no days; its loans are due so many hours later, at the same minute, moved past the
     * days the library is closed; until the end of the day is due as a loan by days is; its renewals are hours too.
     */
    public function test_by_hours(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $at = fn(string $when) => (new \DateTimeImmutable($when, $tz))->getTimestamp();
        $laptop = ['readertype' => '*', 'itemtype' => 'device', 'loandays' => 0, 'maxloans' => 1, 'renewals' => 1,
            'renewdays' => 0, 'loanhours' => 2, 'renewhours' => 1];
        $this->assertTrue(loan_rules::can_lend($laptop, 0));
        $this->assertFalse(loan_rules::can_lend($laptop, 1));
        $this->assertFalse(loan_rules::can_lend(['loanhours' => 13] + $laptop, 0), 'past twelve, not hours');
        $this->assertSame(2, loan_rules::hourly($laptop));
        $this->assertSame(24, loan_rules::hours(['loanhours' => 24] + $laptop));
        $this->assertSame(0, loan_rules::hourly(['loanhours' => 24] + $laptop), 'the end of the day is late as days are');

        // Monday at ten, for two hours: at noon; until the end of the day: at its last second.
        $this->assertSame($at('2026-11-23 12:00'), loan_rules::due_hours($at('2026-11-23 10:00'), 2, $tz));
        $this->assertSame($at('2026-11-23 23:59:59'), loan_rules::due_hours($at('2026-11-23 10:00'), 24, $tz));
        // Overnight from a Friday: past the weekend, at the same time; past a closed Tuesday too.
        $this->assertSame($at('2026-11-30 08:00'), loan_rules::due_hours($at('2026-11-27 20:00'), 12, $tz));
        $this->assertSame(
            $at('2026-11-25 08:00'),
            loan_rules::due_hours($at('2026-11-23 20:00'), 12, $tz, ['2026-11-24' => true])
        );

        // Renewed by its renewal hours: from now, or from the hour it was due; never less than now.
        $due = $at('2026-11-23 12:00');
        $this->assertTrue(loan_rules::can_renew($laptop, 0));
        $this->assertFalse(loan_rules::can_renew($laptop, 1));
        $this->assertSame($at('2026-11-23 12:30'), loan_rules::renewed_due($due, $at('2026-11-23 11:30'), $laptop, $tz));
        $this->assertSame(
            $at('2026-11-23 13:00'),
            loan_rules::renewed_due($due, $at('2026-11-23 11:30'), $laptop, $tz, [], [6, 7], loan_rules::FROM_DUE)
        );
        $this->assertSame(
            $at('2026-11-23 15:00'),
            loan_rules::renewed_due($due, $at('2026-11-23 14:00'), $laptop, $tz, [], [6, 7], loan_rules::FROM_DUE)
        );
        // With no renewal hours, as many as it lends for.
        $this->assertSame(2, loan_rules::renew_hours(['renewhours' => 0] + $laptop));
        $this->assertSame(
            $at('2026-11-23 13:30'),
            loan_rules::renewed_due($due, $at('2026-11-23 11:30'), ['renewhours' => 0] + $laptop, $tz)
        );
        // Until the end of the day, renewed by days.
        $day = ['loanhours' => 24, 'renewdays' => 1] + $laptop;
        $this->assertTrue(loan_rules::can_renew($day, 0));
        $this->assertSame(
            $at('2026-11-24 23:59:59'),
            loan_rules::renewed_due($at('2026-11-23 23:59:59'), $at('2026-11-23 18:00'), $day, $tz)
        );
    }

    /**
     * Days late count whole days after the due date.
     */
    public function test_days_late(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $due = (new \DateTimeImmutable('2026-10-30 23:59:59', $tz))->getTimestamp();
        $this->assertSame(0, loan_rules::days_late($due, $due - 3600, $tz));
        $this->assertSame(1, loan_rules::days_late($due, (new \DateTimeImmutable('2026-10-31 08:00', $tz))->getTimestamp(), $tz));
        $this->assertSame(4, loan_rules::days_late($due, (new \DateTimeImmutable('2026-11-03 18:00', $tz))->getTimestamp(), $tz));
    }
}
