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
 * Tests for the change of school year.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\year_change
 * @covers     \local_oksigeniaayvurenda\local\years
 */
#[\PHPUnit\Framework\Attributes\CoversClass(year_change::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(years::class)]
final class year_change_test extends \advanced_testcase {
    /**
     * Last year two groups borrowed; this year one reader is in a new group, one is in none with a book, and one left.
     */
    public function test_status(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $last = $gen->create_year(['entityid' => $entityid, 'name' => '2025-2026', 'timestart' => make_timestamp(2025, 9, 1),
            'timeend' => make_timestamp(2026, 9, 1)]);
        $now = make_timestamp(2026, 9, 20, 12);
        $year = years::around($entityid, $now);
        $this->assertSame('2026-2027', $year->name);
        $ana = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Ana', 'lastname' => 'Inventada']);
        $leo = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Leo', 'lastname' => 'Inventado']);
        $noa = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Noa', 'lastname' => 'Inventada',
            'status' => 'left']);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $last->id, 'groupname' => '3º A']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $last->id, 'groupname' => '6º A']);
        $gen->create_member(['readerid' => $noa->id, 'yearid' => $last->id, 'groupname' => '3º A']);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $year->id, 'groupname' => '4º A']);
        $lend = fn($reader, $group, $returned) => $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id,
            'yearid' => $last->id, 'groupname' => $group, 'itemid' => $gen->create_item(['libraryid' => $library->id])->id,
            'timeloaned' => make_timestamp(2026, 3, 1), 'timedue' => make_timestamp(2026, 3, 15),
            'timereturned' => $returned ? make_timestamp(2026, 3, 10) : null]);
        $lend($ana, '3º A', true);
        $lend($ana, '3º A', true);
        $lend($leo, '6º A', false);
        $lend($noa, '3º A', false);
        $lend($noa, '3º A', false);

        $status = year_change::status($entityid, $now);
        $this->assertSame('start', $status['moment']);
        $this->assertSame(1, $status['groups']);
        $this->assertSame(1, $status['notutor']);
        $this->assertSame(3, $status['out']);
        $this->assertSame([
            ['name' => 'Noa Inventada', 'group' => '3º A', 'books' => 2],
            ['name' => 'Leo Inventado', 'group' => '6º A', 'books' => 1],
        ], $status['left']);
        $last = $status['last'];
        $this->assertSame(5, $last['loans']);
        $this->assertSame(3, $last['borrowers']);
        $this->assertSame(3, $last['members']);
        $this->assertSame(['group' => '3º A', 'members' => 2, 'borrowers' => 2, 'loans' => 4], $last['groups'][0]);
    }

    /**
     * A new start day applies from the next year on, which begins where this one ends.
     */
    public function test_new_start(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $this->setTimezone('Europe/Madrid');
        $year = years::around($entityid, make_timestamp(2026, 10, 1, 12));
        $this->assertFalse(year_change::set_start($entityid, 2, 30));
        $this->assertTrue(year_change::set_start($entityid, 3, 1));
        $this->assertSame('03-01', $DB->get_field('local_oksigeniaayvurenda_entity', 'yearstart', ['id' => $entityid]));
        $this->assertEquals($year->id, years::around($entityid, make_timestamp(2027, 5, 1, 12))->id);
        $next = years::around($entityid, make_timestamp(2027, 10, 1, 12));
        $this->assertSame('2027', $next->name);
        $this->assertEquals($year->timeend, $next->timestart);
        $this->assertEquals(make_timestamp(2028, 3, 1), $next->timeend);
    }
}
