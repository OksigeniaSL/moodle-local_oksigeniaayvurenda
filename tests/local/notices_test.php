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
 * Tests for notices of late books, tutors, the list of late books and the daily copy.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\notices
 * @covers     \local_oksigeniaayvurenda\local\tutors
 * @covers     \local_oksigeniaayvurenda\local\late
 * @covers     \local_oksigeniaayvurenda\local\copies
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notices::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tutors::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(late::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(copies::class)]
final class notices_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a group, a tutor, two readers with accounts and one without; three late loans and one on time.
     *
     * @return array
     */
    private function library(): array {
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $year = $gen->create_year(['entityid' => $library->entityid]);
        $now = make_timestamp(2026, 11, 10, 12);
        $ana = $dg->create_user(['firstname' => 'Ana', 'lastname' => 'Inventada']);
        $leo = $dg->create_user(['firstname' => 'Leo', 'lastname' => 'Inventado']);
        $tutor = $dg->create_user(['firstname' => 'Irene', 'lastname' => 'Tutora']);
        $readers = [
            'ana' => $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id]),
            'leo' => $gen->create_reader(['entityid' => $library->entityid, 'userid' => $leo->id]),
            'noa' => $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Noa', 'lastname' => 'Sin Cuenta']),
        ];
        foreach ($readers as $reader) {
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '3º A']);
        }
        $loan = fn($reader, $title, $due) => $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'itemid' => $gen->create_item(['libraryid' => $library->id, 'recordid' =>
            $gen->create_record(['entityid' => $library->entityid, 'title' => $title])->id])->id,
            'timeloaned' => $due - 14 * DAYSECS, 'timedue' => $due]);
        $loan($readers['ana'], 'El faro dormido', $now - 3 * DAYSECS);
        $loan($readers['ana'], 'La nube de tinta', $now - 1 * DAYSECS);
        $loan($readers['noa'], 'Mapas del sur', $now - 10 * DAYSECS);
        $loan($readers['leo'], 'A tiempo', $now + 3 * DAYSECS);
        tutors::set((int) $year->id, '3º A', (int) $tutor->id);
        return [$library, $year, $now, $readers, $tutor];
    }

    /**
     * The reader hears with the titles, the tutor without them; a day later nobody hears again, a week later they do.
     */
    public function test_notices(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [, , $now, , $tutor] = $this->library();
        $sink = $this->redirectMessages();
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($now));
        $messages = $sink->get_messages();
        $sink->clear();
        $this->assertCount(2, $messages);
        $byname = [];
        foreach ($messages as $message) {
            $byname[$message->eventtype] = $message;
        }
        $this->assertStringContainsString('El faro dormido', $byname['overdue']->fullmessage);
        $this->assertStringContainsString('La nube de tinta', $byname['overdue']->fullmessage);
        $this->assertStringNotContainsString('A tiempo', $byname['overdue']->fullmessage);
        $tutormessage = $byname['overduetutor'];
        $this->assertEquals($tutor->id, $tutormessage->useridto);
        $this->assertStringContainsString('Noa Sin Cuenta', $tutormessage->fullmessage);
        $this->assertStringContainsString('Ana Inventada', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('faro', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('Mapas', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('Leo', $tutormessage->fullmessage);

        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($now + DAYSECS));
        // A week later, Ana hears again and Leo, whose book is now late, for the first time.
        $this->assertSame(['readers' => 2, 'tutors' => 1], notices::run($now + 7 * DAYSECS));
        $sink->close();
    }

    /**
     * Next year, the tutor of a group with the same name is proposed, not set.
     */
    public function test_tutor_proposed(): void {
        global $DB;
        $this->resetAfterTest();
        [$library, $year, , $readers, $tutor] = $this->library();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $next = $gen->create_year(['entityid' => $library->entityid, 'name' => '2027-2028',
            'timestart' => $year->timeend, 'timeend' => make_timestamp(2028, 9, 1)]);
        $gen->create_member(['readerid' => $readers['ana']->id, 'yearid' => $next->id, 'groupname' => '3º A']);
        $gen->create_member(['readerid' => $readers['leo']->id, 'yearid' => $next->id, 'groupname' => '4º A']);
        $groups = tutors::groups((int) $library->entityid, (int) $next->id);
        $this->assertSame([
            ['group' => '3º A', 'userid' => (int) $tutor->id, 'proposed' => true],
            ['group' => '4º A', 'userid' => 0, 'proposed' => false],
        ], $groups);
        $this->assertSame(0, $DB->count_records(self::T . 'tutor', ['yearid' => $next->id]));
        tutors::set((int) $next->id, '3º A', (int) $tutor->id);
        $this->assertFalse(tutors::groups((int) $library->entityid, (int) $next->id)[0]['proposed']);
        tutors::set((int) $next->id, '3º A', 0);
        $this->assertSame(0, $DB->count_records(self::T . 'tutor', ['yearid' => $next->id]));
    }

    /**
     * The staff list: late loans by group, with names and titles; all of them on request.
     */
    public function test_late_list(): void {
        $this->resetAfterTest();
        [$library, , $now] = $this->library();
        $groups = late::by_group($library, true, $now);
        $this->assertCount(1, $groups);
        $this->assertSame('3º A', $groups[0]['group']);
        $this->assertSame(['Noa Sin Cuenta', 'Ana Inventada', 'Ana Inventada'], array_column($groups[0]['lines'], 'name'));
        $this->assertSame('Mapas del sur', $groups[0]['lines'][0]['title']);
        $this->assertSame(10, $groups[0]['lines'][0]['days']);
        $this->assertCount(4, late::by_group($library, false, $now)[0]['lines']);
    }

    /**
     * The copy holds every table of the entity in CSV and only the last seven are kept.
     */
    public function test_copies(): void {
        $this->resetAfterTest();
        [$library, , $now] = $this->library();
        $other = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $file = copies::make((int) $library->entityid, $now);
        $path = make_request_directory() . '/copy.zip';
        $file->copy_content_to($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('LEEME.txt'));
        $readers = $zip->getFromName('reader.csv');
        $this->assertStringContainsString('Sin Cuenta', $readers);
        $this->assertSame(4, substr_count($readers, "\n"));
        $this->assertSame(5, substr_count($zip->getFromName('loan.csv'), "\n"));
        // The catalogue in MARC21 too, as ISO 2709 and MARCXML, each record with its copies.
        $records = \local_oksigeniaayvurenda\core\marc\iso2709::read($zip->getFromName('catalogo.mrc'));
        $this->assertCount(4, $records);
        $this->assertCount(4, \local_oksigeniaayvurenda\core\marc\marcxml::read($zip->getFromName('catalogo.xml')));
        $titles = array_map(fn($r) => $r->first(['245'], 'a'), $records);
        $this->assertContains('Mapas del sur.', $titles);
        $this->assertCount(1, $records[0]->data_fields('852'));
        $zip->close();
        copies::make((int) $other->entityid, $now);
        for ($i = 1; $i <= 8; $i++) {
            copies::make((int) $library->entityid, $now + $i * DAYSECS);
        }
        $list = copies::list((int) $library->entityid);
        $this->assertCount(copies::KEEP, $list);
        $this->assertSame($now + 8 * DAYSECS, (int) $list[0]->get_timecreated());
        $this->assertCount(1, copies::list((int) $other->entityid));
    }
}
