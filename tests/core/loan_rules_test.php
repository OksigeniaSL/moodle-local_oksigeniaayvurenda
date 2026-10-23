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
    }

    /**
     * Renewals: as many as the rule says, counted from the later of today and the old due date.
     */
    public function test_renew(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $student = loan_rules::pick(self::RULES, 'student', 'normal');
        $this->assertTrue(loan_rules::can_renew($student, 1));
        $this->assertFalse(loan_rules::can_renew($student, 2));
        $this->assertFalse(loan_rules::can_renew(loan_rules::pick(self::RULES, 'family', 'device'), 0));
        $due = (new \DateTimeImmutable('2026-10-30 23:59:59', $tz))->getTimestamp();
        $early = (new \DateTimeImmutable('2026-10-28 09:00', $tz))->getTimestamp();
        $this->assertSame(
            (new \DateTimeImmutable('2026-11-06 23:59:59', $tz))->getTimestamp(),
            loan_rules::renewed_due($due, $early, $student, $tz)
        );
        $late = (new \DateTimeImmutable('2026-11-03 09:00', $tz))->getTimestamp();
        $this->assertSame(
            (new \DateTimeImmutable('2026-11-10 23:59:59', $tz))->getTimestamp(),
            loan_rules::renewed_due($due, $late, $student, $tz)
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
