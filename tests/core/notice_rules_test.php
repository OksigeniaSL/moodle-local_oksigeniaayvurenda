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
 * Tests for the core class notice_rules.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\notice_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notice_rules::class)]
final class notice_rules_test extends \basic_testcase {
    /**
     * Days of the year: sorted, once each, a whole date keeps its month and day, what is not a day goes.
     */
    public function test_dates(): void {
        $this->assertSame(
            ['03-25', '06-20', '12-20'],
            notice_rules::dates(['12-20', '2027-03-25', '06-20', '12-20', '02-30', 'tomorrow', ''])
        );
        $this->assertSame(['02-29'], notice_rules::dates(['02-29']));
    }

    /**
     * A rhythm counts from the last notice (an hour of slack); only by hand, never; on the library's days, the latest one
     * that came less than a week ago, if they were not told since, also a day at the end of December seen in January.
     */
    public function test_moment(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $at = fn(string $when) => (new \DateTimeImmutable($when, $tz))->getTimestamp();
        $now = $at('2026-11-10 08:00');
        $week = ['noticeevery' => 'week', 'noticedates' => []];
        $this->assertSame($now, notice_rules::moment($week, 0, $now, $tz));
        $this->assertSame($now, notice_rules::moment($week, $at('2026-11-03 08:30'), $now, $tz));
        $this->assertNull(notice_rules::moment($week, $at('2026-11-05 08:00'), $now, $tz));
        $this->assertNull(notice_rules::moment(['noticeevery' => 'hand', 'noticedates' => ['11-10']], 0, $now, $tz));
        $dates = ['noticeevery' => 'dates', 'noticedates' => ['11-08', '03-25']];
        $this->assertSame($at('2026-11-08 00:00'), notice_rules::moment($dates, 0, $now, $tz));
        $this->assertNull(notice_rules::moment($dates, $at('2026-11-09 08:00'), $now, $tz));
        $this->assertNull(notice_rules::moment($dates, 0, $at('2026-11-20 08:00'), $tz));
        $this->assertSame(
            $at('2026-12-30 00:00'),
            notice_rules::moment(['noticeevery' => 'dates', 'noticedates' => ['12-30']], 0, $at('2027-01-02 08:00'), $tz)
        );
    }

    /**
     * A school's three days: the end of each third of its classes, the last one their last day.
     */
    public function test_thirds(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $start = (new \DateTimeImmutable('2026-09-01 00:00', $tz))->getTimestamp();
        $end = (new \DateTimeImmutable('2027-06-21 00:00', $tz))->getTimestamp();
        $this->assertSame(['03-15', '06-20', '12-07'], notice_rules::thirds($start, $end, $tz));
    }
}
