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
 * Tests for the library's report of a school year.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\memoir
 */
#[\PHPUnit\Framework\Attributes\CoversClass(memoir::class)]
final class memoir_test extends \advanced_testcase {
    /**
     * The year's figures beside the year before, by stage and by group, what went out most, and the team's words kept
     * for the year: added up, never by pupil.
     */
    public function test_memoir(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $tz = \core_date::get_server_timezone_object();
        $year = $gen->create_year(['entityid' => $entityid] +
            \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', $tz));
        $last = $gen->create_year(['entityid' => $entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            (int) $year->timestart - DAYSECS,
            '09-01',
            $tz
        ));
        $ana = $gen->create_reader(['entityid' => $entityid]);
        $leo = $gen->create_reader(['entityid' => $entityid]);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $year->id, 'groupname' => '3º A', 'stage' => 'Primaria']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $year->id, 'groupname' => '3º A', 'stage' => 'Primaria']);
        $book = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        foreach ([[$ana, $year], [$ana, $year], [$leo, $last]] as [$reader, $when]) {
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $book->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'yearid' => $when->id, 'groupname' => '3º A', 'timeloaned' => (int) $when->timestart + DAYSECS]);
        }
        $report = memoir::of($library, $year);
        $this->assertSame([2, 1, 1, 2], [$report['figures']['loans'], $report['figures']['borrowers'],
            $report['figures']['came'], $report['figures']['readers']]);
        $this->assertSame([1, 1], [$report['before']['loans'], $report['before']['borrowers']]);
        $this->assertSame($last->name, $report['before']['name']);
        $this->assertSame([['stage' => 'Primaria', 'readers' => 2, 'borrowers' => 1, 'loans' => 2]], $report['stages']);
        $this->assertSame([['id' => (int) $book->id, 'title' => 'El faro dormido', 'loans' => 2]], $report['top']);
        $this->assertNull($report['bank']);
        memoir::save_notes($entityid, (int) $year->id, ' Un club de lectura. ', 'Más horas.');
        $this->assertSame(['done' => 'Un club de lectura.', 'next' => 'Más horas.'], memoir::of($library, $year)['notes']);
        $this->assertSame(['done' => '', 'next' => ''], memoir::notes($entityid, (int) $last->id));
    }
}
