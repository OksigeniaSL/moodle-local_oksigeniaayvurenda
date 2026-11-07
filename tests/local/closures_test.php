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

namespace local_oksigeniaayvurenda\local;

/**
 * The days a centre is closed.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\closures
 */
#[\PHPUnit\Framework\Attributes\CoversClass(closures::class)]
final class closures_test extends \advanced_testcase {
    /**
     * Ranges kept in order, in either order of their dates, never longer than a summer nor with days that do not exist;
     * the days from yesterday on; taken away one by one; and another centre's are its own.
     */
    public function test_ranges(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $gen->create_library()->entityid;
        $other = (int) $gen->create_library()->entityid;
        $now = (new \DateTimeImmutable('2026-12-01 12:00', zones::of($entityid)))->getTimestamp();
        $this->assertTrue(closures::add($entityid, '2027-01-07', '2026-12-23', 'Navidad'));
        $this->assertTrue(closures::add($entityid, '2026-12-08', '2026-12-08', ''));
        $this->assertTrue(closures::add($entityid, '2026-11-01', '2026-11-02', 'Pasado'));
        $this->assertFalse(closures::add($entityid, '2026-02-30', '2026-03-02', 'No existe'));
        $this->assertFalse(closures::add($entityid, '2026-06-01', '2026-12-31', 'Demasiado'));
        $ranges = closures::of($entityid);
        $this->assertSame(['2026-11-01', '2026-12-08', '2026-12-23'], array_column($ranges, 'from'));
        $this->assertSame('2027-01-07', $ranges[2]['to']);
        $days = closures::days($entityid, $now);
        $this->assertCount(1 + 16, $days);
        $this->assertArrayHasKey('2026-12-31', $days);
        $this->assertArrayNotHasKey('2026-11-01', $days);
        $this->assertSame([], closures::days($other, $now));

        // A book lent on 20 December for 14 days would be due on Sunday 3 January: it is due on Friday 8.
        $lent = (new \DateTimeImmutable('2026-12-20 10:00', zones::of($entityid)))->getTimestamp();
        $due = \local_oksigeniaayvurenda\core\loan_rules::due($lent, 14, zones::of($entityid), true, $days);
        $this->assertSame('2027-01-08', zones::date($due, '%Y-%m-%d', $entityid));

        // Due on 22 December, back on 10 January: nineteen days late, three of them open (8, 9 and 10 January).
        $tz = zones::of($entityid);
        $due = (new \DateTimeImmutable('2026-12-22 23:59:59', $tz))->getTimestamp();
        $back = (new \DateTimeImmutable('2027-01-10 10:00', $tz))->getTimestamp();
        $this->assertSame(19, \local_oksigeniaayvurenda\core\loan_rules::days_late($due, $back, $tz));
        $this->assertSame(16, closures::between($entityid, $due, $back));
        $this->assertSame(0, closures::between($entityid, $back, $back));

        closures::remove($entityid, '2026-12-23', '2027-01-07');
        $this->assertSame(['2026-11-01', '2026-12-08'], array_column(closures::of($entityid), 'from'));
    }

    /**
     * The last day of class: nothing a reading library lends falls due after it nor is given more time past it, the end of
     * the year comes weeks before it, and the reminder to bring books back goes as the library's, not the bank's.
     */
    public function test_last_day(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $span = \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', zones::of($entityid));
        $year = $gen->create_year(['entityid' => $entityid] + $span);
        $this->assertNull(closures::last_day($entityid, $now));
        $this->assertFalse(closures::set_last_day($entityid, '2027-02-30'));
        $day = zones::date(min($now + 3 * DAYSECS, (int) $year->timeend - 2 * DAYSECS), '%Y-%m-%d', $entityid);
        $this->assertTrue(closures::set_last_day($entityid, $day));
        $last = closures::last_day($entityid, $now);
        $this->assertSame((new \DateTimeImmutable($day . ' 23:59:59', zones::of($entityid)))->getTimestamp(), $last);
        $this->assertSame('end', \local_oksigeniaayvurenda\core\school_year::moment(
            $now,
            (int) $year->timestart,
            (int) $year->timeend,
            $last
        ));

        $user = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'userid' => $user->id]);
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'Zeta del último día']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => '500001K']);
        $desk = new desk($library, 2, $now);
        $desk->scan('10000H');
        $this->assertSame('lent', $desk->scan('500001K', (int) $reader->id)['kind']);
        $loan = $DB->get_record('local_oksigeniaayvurenda_loan', ['readerid' => $reader->id], '*', MUST_EXIST);
        $this->assertSame($last, (int) $loan->timedue);
        $this->assertNull($desk->renewable((int) $loan->id));

        // The reminder of the end of the course, as the library's.
        $sink = $this->redirectMessages();
        $this->assertSame(1, bankround::remind_readers($library, $now));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('yearreturn', $messages[0]->eventtype);
        $this->assertSame(get_string('yearreturnsubject', 'local_oksigeniaayvurenda'), $messages[0]->subject);

        $this->assertTrue(closures::set_last_day($entityid, ''));
        $this->assertNull(closures::last_day($entityid, $now));
    }
}
