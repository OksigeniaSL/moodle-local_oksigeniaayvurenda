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
 * A centre back from its copy.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\recover
 */
#[\PHPUnit\Framework\Attributes\CoversClass(recover::class)]
final class recover_test extends \advanced_testcase {
    /**
     * Everything comes back as a new centre, with everything pointing at the new rows; the old one is not touched; the desk works.
     */
    public function test_the_drill(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $t = 'local_oksigeniaayvurenda_';
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library(['name' => 'Biblioteca Zeta']);
        $entityid = (int) $library->entityid;
        $account = $this->getDataGenerator()->create_user([
            'firstname' => 'Cuenta',
            'lastname' => 'Zeta',
            'email' => 'zeta@example.org',
        ]);
        $linked = $gen->create_reader(['entityid' => $entityid, 'userid' => $account->id, 'cardcode' => 'C1']);
        $plain = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Ana', 'lastname' => 'Sola', 'cardcode' => 'C2']);
        $records = [];
        foreach ([1, 2, 3] as $n) {
            $records[$n] = $gen->create_record(['entityid' => $entityid, 'title' => 'Libro ' . $n]);
            $items[$n] = $gen->create_item(['libraryid' => $library->id, 'recordid' => $records[$n]->id, 'barcode' => 'B' . $n]);
        }
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $items[1]->id, 'readerid' => $linked->id]);
        $DB->set_field($t . 'item', 'status', 'onloan', ['id' => $items[1]->id]);
        $hold = ['libraryid' => $library->id, 'recordid' => $records[1]->id, 'readerid' => $plain->id, 'status' => 'waiting',
            'timecreated' => time(), 'timemodified' => time()];
        $DB->insert_record($t . 'hold', $hold);
        $count = fn(string $table, array $where = []) => $DB->count_records($t . $table, $where);
        $itemsbefore = $count('item', ['entityid' => $entityid]);

        $copy = copies::make($entityid);
        $path = make_request_directory() . '/copia.zip';
        $copy->copy_content_to($path);
        $look = recover::look($path);
        $this->assertSame(3, $look['records']);
        $this->assertSame(1, $look['loans']);
        $this->assertSame(2, $look['readers']);

        $sink = $this->redirectEvents();
        $back = recover::restore($path, (int) get_admin()->id);
        $told = array_map(fn($event) => get_class($event), $sink->get_events());
        $sink->close();
        $this->assertContains('local_oksigeniaayvurenda\event\copy_recovered', $told);
        $this->assertNotSame((int) $back->entityid, $entityid);
        $this->assertNotSame($back->slug, $library->slug);
        $this->assertSame($itemsbefore, $count('item', ['entityid' => $entityid]));
        $new = (int) $back->entityid;
        $this->assertSame(3, $count('item', ['entityid' => $new]));
        $this->assertSame(3, $count('record', ['entityid' => $new]));
        $this->assertSame(2, $count('reader', ['entityid' => $new]));

        // Nothing points at the old rows.
        $loan = $DB->get_record($t . 'loan', ['libraryid' => $back->id], '*', MUST_EXIST);
        $this->assertSame($new, (int) $DB->get_field($t . 'item', 'entityid', ['id' => $loan->itemid]));
        $this->assertSame($new, (int) $DB->get_field($t . 'reader', 'entityid', ['id' => $loan->readerid]));
        $restoredhold = $DB->get_record($t . 'hold', ['libraryid' => $back->id], '*', MUST_EXIST);
        $this->assertSame($new, (int) $DB->get_field($t . 'record', 'entityid', ['id' => $restoredhold->recordid]));

        // The account does not cross over, its name does.
        $reader = $DB->get_record($t . 'reader', ['entityid' => $new, 'cardcode' => 'C1'], '*', MUST_EXIST);
        $this->assertNull($reader->userid);
        $this->assertSame('Cuenta', $reader->firstname);
        $this->assertSame('zeta@example.org', $reader->email);

        // The desk works with what came back: the book out comes home by its code.
        $desk = new desk($back, (int) get_admin()->id);
        $this->assertSame('returned', $desk->scan('B1')['kind']);
        $this->assertSame('available', $DB->get_field($t . 'item', 'status', ['entityid' => $new, 'barcode' => 'B2']));
    }

    /**
     * A text that ends in a backslash, or has one before a quote, comes back as it was, with the row after it.
     */
    public function test_backslash(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $texts = ['Ruta C:\\', 'Con \\"comillas\\" dentro', 'Normal'];
        foreach ($texts as $text) {
            $gen->create_record(['entityid' => $entityid, 'title' => $text]);
        }
        $path = make_request_directory() . '/copia.zip';
        copies::make($entityid)->copy_content_to($path);
        $look = recover::look($path);
        $this->assertSame(3, $look['records']);
        $this->assertSame(0, $look['missing']);
        $back = recover::restore($path, (int) get_admin()->id);
        $titles = $DB->get_fieldset_select('local_oksigeniaayvurenda_record', 'title', 'entityid = ?', [$back->entityid]);
        sort($titles);
        $wanted = $texts;
        sort($wanted);
        $this->assertSame($wanted, $titles);
    }

    /**
     * What is not a copy says so, and leaves nothing.
     */
    public function test_not_a_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $path = make_request_directory() . '/otra.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('hola.txt', 'hola');
        $zip->close();
        $this->assertNull(recover::look($path));
        $before = $DB->count_records('local_oksigeniaayvurenda_entity');
        $this->expectException(\moodle_exception::class);
        try {
            recover::restore($path, 2);
        } finally {
            $this->assertSame($before, $DB->count_records('local_oksigeniaayvurenda_entity'));
        }
    }
}
