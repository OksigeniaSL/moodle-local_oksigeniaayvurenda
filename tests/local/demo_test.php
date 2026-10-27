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

/**
 * Tests for the made-up library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\demo
 */
#[\PHPUnit\Framework\Attributes\CoversClass(demo::class)]
final class demo_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * It makes the entity, its library, a school year, loan rules, titles with copies and readers with groups.
     */
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = demo::create('Demo', 60, 30);

        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', MUST_EXIST);
        $this->assertSame('central', $library->kind);
        $this->assertSame(1, $DB->count_records(self::T . 'year', ['entityid' => $entityid]));
        $this->assertSame(4, $DB->count_records(self::T . 'rule', ['libraryid' => $library->id]));
        $this->assertSame(60, $DB->count_records(self::T . 'record', ['entityid' => $entityid]));
        $this->assertSame(30, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));

        $items = $DB->get_records(self::T . 'item', ['entityid' => $entityid]);
        $this->assertGreaterThanOrEqual(60, count($items));
        foreach ($items as $item) {
            $this->assertTrue(control_letter::is_valid($item->barcode), $item->barcode);
            $this->assertSame((int) $library->id, (int) $item->libraryid);
        }
        foreach ($DB->get_records(self::T . 'reader', ['entityid' => $entityid]) as $reader) {
            $this->assertTrue(control_letter::is_valid($reader->cardcode), $reader->cardcode);
            $members = $DB->count_records(self::T . 'member', ['readerid' => $reader->id]);
            $this->assertSame($reader->readertype === 'student' ? 1 : 0, $members);
        }
        $record = $DB->get_record_select(self::T . 'record', 'entityid = ?', [$entityid], '*', IGNORE_MULTIPLE);
        $this->assertNotEmpty($record->searchtext);
        $this->assertSame(4, $DB->count_records(self::T . 'recauth', ['recordid' => $record->id]));
    }

    /**
     * A made-up library made before some classes were in the list gets them, under their parent, with a share of the
     * books filed only under the parent and their copies in the new place on the shelf; nothing more the second time,
     * and nothing at all in a centre with records brought from a catalogue.
     */
    public function test_refine(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = demo::create('Demo', 120, 0);
        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', MUST_EXIST);
        $class = fn(string $code) => (int) $DB->get_field(self::T . 'authority', 'id', ['entityid' => $entityid,
            'kind' => 'class', 'code' => $code]);
        // As if it were made before 91 and 929 were in the list: their books back under 9.
        foreach (['91', '929'] as $code) {
            $DB->set_field(self::T . 'recauth', 'authorityid', $class('9'), ['authorityid' => $class($code)]);
            $DB->set_field(self::T . 'item', 'callnumber1', '9', ['entityid' => $entityid, 'callnumber1' => $code]);
            $DB->delete_records(self::T . 'authority', ['id' => $class($code)]);
        }
        stacks::refresh_keys($entityid);
        $nine = $DB->count_records(self::T . 'recauth', ['authorityid' => $class('9')]);
        $this->assertGreaterThan(2, $nine);

        $moved = demo::refine((int) $library->id);
        $this->assertSame($nine - (int) ceil($nine / 3), $moved, 'one in three stays under 9');
        $this->assertSame($class('9'), (int) $DB->get_field(self::T . 'authority', 'parentid', ['id' => $class('91')]));
        $this->assertSame(
            $DB->count_records(self::T . 'recauth', ['authorityid' => $class('91')]),
            (int) $DB->count_records_select(
                self::T . 'item',
                'entityid = ? AND callnumber1 = ?',
                [$entityid, '91'],
                'COUNT(DISTINCT recordid)'
            ),
            'the copies of a book move with it'
        );
        $item = $DB->get_record(self::T . 'item', ['entityid' => $entityid, 'callnumber1' => '929'], '*', IGNORE_MULTIPLE);
        $this->assertStringStartsWith(\local_oksigeniaayvurenda\core\shelf_order::prefix('929'), $item->shelfkey);
        $this->assertSame(0, demo::refine((int) $library->id), 'nothing more the second time');

        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $real = $gen->create_library();
        $DB->insert_record(self::T . 'source', ['entityid' => $real->entityid, 'system' => 'abies', 'tablename' => 'record',
            'externalid' => '1', 'localid' => 1, 'hash' => 'x', 'timecreated' => time()]);
        $this->assertSame(-1, demo::refine((int) $real->id));
    }

    /**
     * Made-up loans leave a fifth of the copies out at most, however many are asked for; the made-up library of an
     * earlier version, with most of them out, gets back its oldest, some days after they went out, and nothing more the
     * second time. A centre with records brought from a catalogue keeps its loans.
     */
    public function test_settle(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = demo::create('Demo', 80, 40);
        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', MUST_EXIST);
        $copies = $DB->count_records(self::T . 'item', ['libraryid' => $library->id, 'itemtype' => 'normal']);
        $now = time();
        demo::activity((int) $library->id, 500, 1, $now);
        demo::activity((int) $library->id, 500, 2, $now);
        $out = fn() => $DB->count_records(self::T . 'item', ['libraryid' => $library->id, 'status' => 'onloan']);
        $this->assertGreaterThan(0, $out());
        $this->assertLessThanOrEqual(intdiv($copies, 5), $out(), 'a fifth out at most, after two passes too');
        $this->assertSame(0, demo::settle((int) $library->id, $now));

        // As an earlier version left it: the last loan of every copy lent still out.
        $last = [];
        foreach ($DB->get_records(self::T . 'loan', ['libraryid' => $library->id], 'timeloaned, id') as $loan) {
            $last[$loan->itemid] = $loan->id;
        }
        foreach ($last as $itemid => $loanid) {
            $DB->set_field(self::T . 'loan', 'timereturned', null, ['id' => $loanid]);
            $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $itemid]);
        }
        $before = $out();
        $newest = (int) $DB->get_field_sql(
            "SELECT id FROM {" . self::T . "loan} WHERE libraryid = ? ORDER BY timeloaned DESC, id DESC",
            [$library->id],
            IGNORE_MULTIPLE
        );
        $this->assertSame($before - intdiv($copies, 5), demo::settle((int) $library->id, $now));
        $this->assertSame(intdiv($copies, 5), $out());
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $newest]), 'the newest stay out');
        $back = $DB->get_records_select(self::T . 'loan', 'libraryid = ? AND timereturned IS NOT NULL', [$library->id]);
        foreach ($back as $loan) {
            $this->assertGreaterThan((int) $loan->timeloaned, (int) $loan->timereturned);
            $this->assertLessThan($now, (int) $loan->timereturned);
        }
        $this->assertSame(0, demo::settle((int) $library->id, $now), 'nothing more the second time');

        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $real = $gen->create_library();
        $DB->insert_record(self::T . 'source', ['entityid' => $real->entityid, 'system' => 'abies', 'tablename' => 'record',
            'externalid' => '1', 'localid' => 1, 'hash' => 'x', 'timecreated' => time()]);
        $this->assertSame(-1, demo::settle((int) $real->id));
    }

    /**
     * It fills the public window with made-up EPUBs, each a valid package, and opens it; a second pass adds new titles.
     */
    public function test_window(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = demo::create('Demo', 20, 5);
        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', MUST_EXIST);

        $this->assertSame(['works' => 3, 'window' => 'open'], demo::window((int) $library->id, 3));
        $library = $DB->get_record(self::T . 'library', ['id' => $library->id]);
        $this->assertTrue(window::settings($library)['open']);
        $found = window::records($library);
        $this->assertSame(3, $found['total']);
        $item = $found['records'][0]['items'][0];
        $path = make_request_directory() . '/libro.epub';
        digital::file($item)->copy_content_to($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertSame('mimetype', $zip->getNameIndex(0));
        $this->assertSame('application/epub+zip', $zip->getFromName('mimetype'));
        $this->assertNotFalse((new \DOMDocument())->loadXML($zip->getFromName('OEBPS/content.opf')));
        $this->assertStringContainsString('inventado', $zip->getFromName('OEBPS/c1.xhtml'));
        $zip->close();

        demo::window((int) $library->id, 2);
        $this->assertSame(5, window::records($library)['total']);
    }

    /**
     * The same seed gives the same library.
     */
    public function test_seed(): void {
        global $DB;
        $this->resetAfterTest();
        $first = demo::create('One', 10, 5, 7);
        $second = demo::create('Two', 10, 5, 7);
        $titles = fn($entityid) => $DB->get_fieldset_select(self::T . 'record', 'title', 'entityid = ? ORDER BY id', [$entityid]);
        $this->assertSame($titles($first), $titles($second));
    }

    /**
     * Made-up covers go only to made-up books: a centre with records brought from a catalogue gets none. With the art
     * package, one book in seven keeps its drawn cover; without it, nothing changes.
     */
    public function test_covers(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $real = $gen->create_library();
        $record = $gen->create_record(['entityid' => $real->entityid, 'title' => 'El faro dormido']);
        $DB->insert_record('local_oksigeniaayvurenda_source', ['entityid' => $real->entityid, 'system' => 'abies',
            'tablename' => 'record', 'externalid' => '1', 'localid' => $record->id, 'hash' => 'x', 'timecreated' => time()]);
        $this->assertSame(-1, demo::covers((int) $real->id));
        $this->assertNull(covers::url((int) $record->id));

        $made = $gen->create_library();
        $ids = [];
        for ($i = 0; $i < 14; $i++) {
            $ids[] = (int) $gen->create_record(['entityid' => $made->entityid, 'title' => 'El faro perdido'])->id;
        }
        $given = demo::covers((int) $made->id);
        if (
            \core_component::get_component_directory('local_oksigeniaayvurendaarte') === null
                || !is_readable(\core_component::get_component_directory('local_oksigeniaayvurendaarte') .
                    '/pix/portadas/libro-faro.webp')
        ) {
            $this->assertSame(0, $given);
            return;
        }
        $kept = count(array_filter($ids, fn($id) => $id % 7 === 0));
        $this->assertSame(14 - $kept, $given);
        $this->assertSame(0, demo::covers((int) $made->id), 'a second pass gives none');
    }
}
