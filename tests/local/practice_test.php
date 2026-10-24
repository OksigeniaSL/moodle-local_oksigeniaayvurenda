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
}
