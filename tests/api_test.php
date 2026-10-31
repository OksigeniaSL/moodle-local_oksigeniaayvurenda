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

namespace local_oksigeniaayvurenda;

/**
 * Tests for what other plugins may ask the library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\api
 */
#[\PHPUnit\Framework\Attributes\CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /**
     * The new books: newest first, once each, with the library of the newest copy; never a bank, a withdrawn copy, a
     * device, something older or a closed centre.
     */
    public function test_new_books(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $now = time();
        $central = $gen->create_library(['name' => 'Biblioteca central']);
        $entityid = (int) $central->entityid;
        $room = $gen->create_library(['entityid' => $entityid, 'name' => 'Aula de 3.º', 'kind' => 'classroom',
            'parentid' => $central->id]);
        $bank = $gen->create_library(['entityid' => $entityid, 'name' => 'Banco de libros', 'kind' => 'bank']);
        $faro = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro', 'subtitle' => 'Una historia del mar']);
        $author = $DB->insert_record('local_oksigeniaayvurenda_authority', ['entityid' => $entityid, 'kind' => 'author',
            'label' => 'Montes, Ana', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('local_oksigeniaayvurenda_recauth', ['recordid' => $faro->id, 'authorityid' => $author,
            'sortorder' => 0]);
        $gen->create_item(['libraryid' => $central->id, 'recordid' => $faro->id, 'timecreated' => $now - 3 * DAYSECS]);
        $gen->create_item(['libraryid' => $room->id, 'recordid' => $faro->id, 'timecreated' => $now - DAYSECS]);
        $nube = $gen->create_record(['entityid' => $entityid, 'title' => 'La nube']);
        $gen->create_item(['libraryid' => $central->id, 'recordid' => $nube->id, 'timecreated' => $now - 2 * DAYSECS]);
        $old = $gen->create_record(['entityid' => $entityid, 'title' => 'Antiguo']);
        $gen->create_item(['libraryid' => $central->id, 'recordid' => $old->id, 'timecreated' => $now - 30 * DAYSECS]);
        $text = $gen->create_record(['entityid' => $entityid, 'title' => 'Matemáticas 3']);
        $gen->create_item(['libraryid' => $bank->id, 'recordid' => $text->id, 'timecreated' => $now]);
        $gone = $gen->create_record(['entityid' => $entityid, 'title' => 'Retirado']);
        $gen->create_item(['libraryid' => $central->id, 'recordid' => $gone->id, 'timecreated' => $now,
            'status' => 'withdrawn']);
        $tablet = $gen->create_record(['entityid' => $entityid, 'title' => 'Tableta', 'doctype' => 'device']);
        $gen->create_item(['libraryid' => $central->id, 'recordid' => $tablet->id, 'timecreated' => $now]);
        $closed = $gen->create_library(['name' => 'Otro centro']);
        $gen->create_item(['libraryid' => $closed->id, 'timecreated' => $now]);
        local\access::set_closed((int) $closed->entityid, true);
        $practice = local\practice::library();
        $gen->create_item(['libraryid' => $practice->id, 'timecreated' => $now]);

        $books = api::new_books($now - 7 * DAYSECS);
        $this->assertSame(['El faro', 'La nube'], array_column($books, 'title'));
        $this->assertSame('Aula de 3.º', $books[0]['library']);
        $this->assertSame(['Ana Montes'], $books[0]['authors']);
        $this->assertSame('Una historia del mar', $books[0]['subtitle']);
        $this->assertSame($now - DAYSECS, $books[0]['arrived']);
        $this->assertNull($books[0]['cover']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $books[0]['colour']['background']);
        $this->assertStringContainsString('record.php', $books[0]['url']);
        $this->assertCount(1, api::new_books($now - 7 * DAYSECS, 1));
        $this->assertSame([], api::new_books($now - 7 * DAYSECS, 12, (int) $closed->entityid));

        // With a picture, its stored file.
        local\covers::store((int) $nube->id, self::jpeg(), 'test');
        $books = api::new_books($now - 7 * DAYSECS, 12, $entityid);
        $this->assertInstanceOf(\stored_file::class, $books[1]['cover']);
    }

    /**
     * A small JPEG.
     *
     * @return string
     */
    private static function jpeg(): string {
        $image = imagecreatetruecolor(20, 30);
        ob_start();
        imagejpeg($image);
        return (string) ob_get_clean();
    }
}
