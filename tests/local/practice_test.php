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

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\core\import\catalogue_csv;
use local_oksigeniaayvurenda\local\import\preview;

/**
 * Tests for the practice library of the guided help.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\practice
 */
#[\PHPUnit\Framework\Attributes\CoversClass(practice::class)]
final class practice_test extends \advanced_testcase {
    /**
     * It is made with its readers, books and two loans; it is not one of the centre's libraries; the real desk works on
     * it, and making it again leaves it as new.
     */
    public function test_practice(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $library = practice::library();
        $this->assertSame(practice::SLUG, $library->slug);
        $this->assertTrue(practice::is((int) $library->entityid));
        $this->assertFalse(practice::is((int) $mine->entityid));
        $this->assertSame([(int) $mine->id], array_map(fn($l) => (int) $l->id, catalogue::libraries()));
        $this->assertSame(3, $DB->count_records('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid]));
        $this->assertSame(2, $DB->count_records('local_oksigeniaayvurenda_loan', ['libraryid' => $library->id]));

        $desk = new desk($library, (int) get_admin()->id);
        $ana = $desk->scan('10000H');
        $this->assertSame('Ana Montes Prado', $ana['reader']['name']);
        $this->assertSame('lent', $desk->scan('100000L', $ana['reader']['id'])['kind']);
        $this->assertSame(3, $DB->count_records('local_oksigeniaayvurenda_loan', ['libraryid' => $library->id]));

        $again = practice::reset();
        $this->assertNotEquals($library->entityid, $again->entityid);
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_entity', ['id' => $library->entityid]));
        $this->assertSame(2, $DB->count_records('local_oksigeniaayvurenda_loan', ['libraryid' => $again->id]));
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_library', ['slug' => practice::SLUG]));

        $found = lookup::isbn((int) $again->entityid, practice::ISBNS[0][0]);
        $this->assertTrue($found['found']);
        $this->assertSame('El jardín de las palabras', $found['description']['record']['title']);
        $this->assertFalse(lookup::isbn((int) $again->entityid, '9780743273565')['found']);
    }

    /**
     * A reader's flower and album: the books of the year with their notes, and the flowers of other years.
     */
    public function test_garden(): void {
        global $DB;
        $this->resetAfterTest();
        $library = practice::library();
        $noa = $DB->get_record(
            'local_oksigeniaayvurenda_reader',
            ['entityid' => $library->entityid, 'cardcode' => '10002C'],
            '*',
            MUST_EXIST
        );
        $desk = new desk($library, (int) get_admin()->id);
        $back = $desk->scan('100003E');
        $desk->note($back['loanid'], 'loved');
        $album = garden::album($noa);
        $this->assertSame(['La ladrona de la tormenta'], array_column($album['books'], 'title'));
        $this->assertSame('loved', $album['books'][0]['note']);
        $this->assertSame(['back' => 1, 'explore' => 0, 'tell' => 1, 'stay' => 0], $album['flower']['levels']);
        $this->assertSame(0, $album['flower']['stage']);
        $this->assertSame([], $album['past']);
        $card = cards::card($noa, 'Noa Ríos Lago', '5º B', 'Biblioteca de práctica', $album['flower']['stage']);
        $this->assertStringContainsString('>10002C</text>', $card['barcode']);
        $this->assertStringEndsWith('flor/seed.svg', $card['flower']);
    }

    /**
     * A book from the tables, passed without a card, can be noted as read in the library: it counts in the school
     * year, with no reader, and can be undone.
     */
    public function test_read_in_the_room(): void {
        global $DB;
        $this->resetAfterTest();
        $library = practice::library();
        $desk = new desk($library, (int) get_admin()->id);
        $answer = $desk->scan('100000L');
        $this->assertSame('needreader', $answer['kind']);
        $this->assertTrue($answer['room']);
        $read = $desk->room($answer['itemid']);
        $this->assertSame('roomread', $read['kind']);
        $year = years::around((int) $library->entityid, time());
        $this->assertSame(1, reports::year($library, $year)['room']);
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_loan', ['itemid' => $answer['itemid'],
            'timereturned' => null]));
        $desk->room_undo($read['roomid']);
        $this->assertSame(0, reports::year($library, $year)['room']);
    }

    /**
     * Each help finds what it shows, and making the library again takes it all away.
     */
    public function test_what_each_help_needs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $t = 'local_oksigeniaayvurenda_';
        $admin = (int) get_admin()->id;

        $library = practice::reset();
        practice::prepare($library, 'weed', $admin);
        $found = weeding::candidates($library, ['unused' => 5, 'before' => 0, 'worn' => 1, 'lost' => 1, 'place' => 0], null, 0);
        $why = array_count_values(array_column($found['rows'], 'why'));
        $this->assertSame(1, $why['lost']);
        $this->assertSame(1, $why['worn']);
        $this->assertSame(3, $why['unused']);

        $library = practice::reset();
        $this->assertSame(5, $DB->count_records($t . 'item', ['libraryid' => $library->id]));
        practice::prepare($library, 'late', $admin);
        $this->assertSame(4, $DB->count_records($t . 'loan', ['libraryid' => $library->id]));
        $this->assertSame(3, $DB->count_records_select($t . 'loan', 'libraryid = ? AND timedue < ?', [$library->id, time()]));
        $this->assertSame(1, $DB->count_records($t . 'tutor', ['userid' => $admin, 'groupname' => '3º A']));

        $library = practice::reset();
        $this->assertSame(0, $DB->count_records($t . 'tutor', ['userid' => $admin]));
        practice::prepare($library, 'holds', $admin);
        $lists = holds::lists($library, time());
        $this->assertCount(1, $lists['ready']);
        $this->assertCount(2, $lists['waiting']);

        $library = practice::reset();
        $this->assertSame(0, $DB->count_records($t . 'hold', ['libraryid' => $library->id]));
        practice::prepare($library, 'voices', $admin);
        $this->assertCount(1, voices::pending($library, $admin));

        $library = practice::reset();
        $this->assertSame(0, $DB->count_records($t . 'digital', ['entityid' => $library->entityid]));
    }

    /**
     * The example file of the help of importing: its columns are recognised but one, in both languages, and every
     * code passes its letter.
     */
    public function test_the_example_file(): void {
        $manager = get_string_manager();
        foreach (['en', 'es'] as $lang) {
            $lines = explode('|', $manager->get_string('practicesheet', 'local_oksigeniaayvurenda', null, $lang));
            $rows = array_map(fn($line) => explode(';', $line), $lines);
            $columns = array_shift($rows);
            $map = catalogue_csv::guess($columns);
            $this->assertCount(count($columns) - 1, $map, $lang);
            $look = preview::of(catalogue_csv::records($rows, $map));
            $this->assertSame(8, $look['records'], $lang);
            $this->assertSame(8, $look['items'], $lang);
            $this->assertSame(0, $look['nocode'], $lang);
            foreach ($rows as $row) {
                $this->assertTrue(control_letter::is_valid($row[6]), $row[6]);
            }
        }
    }
}
