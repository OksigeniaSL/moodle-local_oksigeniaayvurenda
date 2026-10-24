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

use local_oksigeniaayvurenda\core\marc\describer;
use local_oksigeniaayvurenda\core\marc\marcxml;

/**
 * Tests for adding books: the catalogues asked by ISBN, the record and its copies, the covers.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cataloguer
 * @covers     \local_oksigeniaayvurenda\local\lookup
 * @covers     \local_oksigeniaayvurenda\local\covers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cataloguer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lookup::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(covers::class)]
final class cataloguer_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Forgets the fake fetcher.
     */
    protected function tearDown(): void {
        lookup::$fetcher = null;
        parent::tearDown();
    }

    /**
     * The made-up record of the fixtures, described.
     *
     * @return array
     */
    private function description(): array {
        return describer::describe(marcxml::read(file_get_contents(__DIR__ . '/../fixtures/marc/record.xml'))[0]);
    }

    /**
     * A picture as a camera or a catalogue would give it.
     *
     * @param int $width
     * @param int $height
     * @return string JPEG.
     */
    private function picture(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 23, 87, 76));
        ob_start();
        imagejpeg($image, null, 90);
        return ob_get_clean();
    }

    /**
     * A new book: its record, authorities found or made, words for the search, copies with codes that follow the
     * entity's; then one more copy of it, with a label read from the book; then a code already taken.
     */
    public function test_add(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $DB->set_field(self::T . 'entity', 'settings', json_encode(['controlletter' => true]), ['id' => $library->entityid]);
        $gen->create_item(['libraryid' => $library->id, 'barcode' => '100041Z', 'regnumber' => '700']);
        $gen->create_item(['libraryid' => $library->id, 'barcode' => '100007-9', 'regnumber' => 'A-3']);
        $publisher = $DB->insert_record(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'publisher',
            'label' => 'EDITORIAL BRÚJULA', 'timecreated' => 1, 'timemodified' => 1]);
        $place = $DB->insert_record(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'location',
            'label' => 'Narrativa', 'timecreated' => 1, 'timemodified' => 1]);

        $added = cataloguer::add($library, $this->description(), ['count' => 2, 'callnumber1' => 'N', 'callnumber2' => 'LAU',
            'callnumber3' => 'far', 'locationid' => $place, 'itemtype' => 'normal']);
        $this->assertTrue($added['newrecord']);
        $this->assertSame(['100042S', '100043Q'], $added['barcodes']);
        $record = $DB->get_record(self::T . 'record', ['id' => $added['recordid']], '*', MUST_EXIST);
        $this->assertSame('El faro dormido', $record->title);
        $this->assertSame('9788490000014', $record->isbn);
        $this->assertStringContainsString('laurel prado irene', $record->searchtext);
        $this->assertStringContainsString('faros', $record->searchtext);
        $this->assertArrayNotHasKey('isbns', json_decode($record->extra, true));
        $links = $DB->get_records_sql('SELECT a.id, a.kind, a.label FROM {' . self::T . 'recauth} ra
            JOIN {' . self::T . 'authority} a ON a.id = ra.authorityid WHERE ra.recordid = ?', [$added['recordid']]);
        $this->assertArrayHasKey($publisher, $links);
        $this->assertCount(8, $links);
        $item = $DB->get_record(self::T . 'item', ['barcode' => '100042S'], '*', MUST_EXIST);
        $this->assertSame('701', $item->regnumber);
        $this->assertSame('N', $item->callnumber1);
        $this->assertEquals($place, $item->locationid);

        $existing = cataloguer::existing((int) $library->entityid, '9788490000014');
        $this->assertSame([['library' => $library->name, 'count' => 2]], $existing[0]['copies']);
        $this->assertSame(['N', 'LAU', 'far'], cataloguer::spine($library, $this->description(), $existing[0]['id']));
        $more = cataloguer::add($library, [], ['count' => 1, 'barcodes' => ['200000S']], $existing[0]['id']);
        $this->assertFalse($more['newrecord']);
        $this->assertSame(['200000S'], $more['barcodes']);
        $this->assertSame(3, $DB->count_records(self::T . 'item', ['recordid' => $added['recordid']]));

        $this->expectException(\invalid_parameter_exception::class);
        cataloguer::add($library, [], ['count' => 1, 'barcodes' => ['100042S']], $existing[0]['id']);
    }

    /**
     * Undoing takes the copies and a new record with its cover; not once a copy has been lent.
     */
    public function test_undo(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $added = cataloguer::add($library, $this->description(), ['count' => 1]);
        $this->assertTrue(covers::store($added['recordid'], $this->picture(400, 600), 'test'));
        $this->assertNotNull(covers::url($added['recordid']));
        $this->assertTrue(cataloguer::undo($entityid, $added['recordid'], $added['itemids'], true));
        $this->assertFalse($DB->record_exists(self::T . 'record', ['id' => $added['recordid']]));
        $this->assertNull(covers::url($added['recordid']));

        $again = cataloguer::add($library, $this->description(), ['count' => 1]);
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $year = $gen->create_year(['entityid' => $entityid]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $again['itemids'][0], 'readerid' => $reader->id,
            'yearid' => $year->id]);
        $this->assertFalse(cataloguer::undo($entityid, $again['recordid'], $again['itemids'], true));
        $this->assertTrue($DB->record_exists(self::T . 'record', ['id' => $again['recordid']]));
    }

    /**
     * The catalogues in order: the Library of Congress answers with other editions only, so Open Library's is taken;
     * the answer is kept, and a code that is not an ISBN asks nothing.
     */
    public function test_lookup(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $asked = [];
        lookup::$fetcher = function (string $url) use (&$asked) {
            $asked[] = $url;
            if (str_contains($url, 'lx2.loc.gov')) {
                return file_get_contents(__DIR__ . '/../fixtures/marc/sru.xml');
            }
            if (str_contains($url, 'openlibrary.org/api')) {
                return file_get_contents(__DIR__ . '/../fixtures/openlibrary/book.json');
            }
            return null;
        };
        $found = lookup::isbn((int) $library->entityid, '979-10-4000-007-5');
        $this->assertTrue($found['found']);
        $this->assertSame('openlibrary', $found['source']);
        $this->assertSame('La nube de tinta', $found['description']['record']['title']);
        $this->assertSame(['Library of Congress', 'Open Library'], $found['tried']);
        $this->assertSame('https://covers.openlibrary.org/b/id/0-M.jpg', $found['cover']);
        $this->assertCount(2, $asked);
        lookup::isbn((int) $library->entityid, '9791040000075');
        $this->assertCount(2, $asked);

        // An ISBN-10 is the same book as its ISBN-13: the second edition of the answer is the one.
        $ten = lookup::isbn((int) $library->entityid, '84-90000-01-8');
        $this->assertSame('loc', $ten['source']);
        $this->assertSame('El faro dormido', $ten['description']['record']['title']);

        $this->assertFalse(lookup::isbn((int) $library->entityid, '1234')['valid']);
    }

    /**
     * Without a choice, the order depends on the language of the site: Spain's National Library first in Spanish; a
     * choice of the entity wins.
     */
    public function test_sources(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $CFG->lang = 'en';
        $this->assertSame(['loc', 'openlibrary', 'bne'], lookup::sources($entityid));
        $CFG->lang = 'es';
        $this->assertSame(['bne', 'loc', 'openlibrary'], lookup::sources($entityid));
        $DB->set_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            json_encode(['sources' => ['openlibrary', 'nowhere']]),
            ['id' => $entityid]
        );
        $this->assertSame(['openlibrary'], lookup::sources($entityid));
    }

    /**
     * Covers are made small, found in one question for a page of cards, and taken away.
     */
    public function test_covers(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $one = $gen->create_record(['entityid' => $library->entityid]);
        $two = $gen->create_record(['entityid' => $library->entityid]);
        $this->assertTrue(covers::store($one->id, $this->picture(500, 900), 'test'));
        $this->assertFalse(covers::store($two->id, 'not a picture', 'test'));
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'cover',
            $one->id,
            '/',
            'cover.jpg'
        );
        $this->assertSame(covers::HEIGHT, getimagesizefromstring($file->get_content())[1]);
        $this->assertSame([(int) $one->id], array_keys(covers::urls([$one->id, $two->id])));
        covers::remove($one->id);
        $this->assertSame([], covers::urls([$one->id]));
    }

    /**
     * Material: its units with their serial numbers, fine to begin with; the first one in a library with rules brings
     * the rule that lets it be lent; the rules screen saves them all at once.
     */
    public function test_material_and_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertSame(1, rules::save((int) $library->id, [
            ['readertype' => 'student', 'itemtype' => 'normal', 'loandays' => 14, 'maxloans' => 2],
            ['readertype' => 'student', 'itemtype' => 'normal', 'loandays' => 21, 'maxloans' => -3],
        ]));
        $this->assertSame([21, 0], [(int) rules::of((int) $library->id)[0]['loandays'],
            (int) rules::of((int) $library->id)[0]['maxloans']]);
        $description = ['record' => ['doctype' => 'device', 'title' => 'Tableta'], 'authorities' => [],
            'extra' => ['brand' => 'Inventada']];
        $added = cataloguer::add($library, $description, ['count' => 2, 'serials' => ['SN-1', 'SN-2'],
            'itemtype' => 'device']);
        $this->assertTrue($added['ruled']);
        $units = array_values($DB->get_records('local_oksigeniaayvurenda_item', ['recordid' => $added['recordid']], 'id'));
        $this->assertSame(['SN-1', 'SN-2'], array_column($units, 'serial'));
        $this->assertSame('good', $units[0]->itemcondition);
        $more = cataloguer::add($library, [], ['count' => 1, 'itemtype' => 'device'], $added['recordid']);
        $this->assertFalse($more['ruled']);
        $this->assertSame(2, count(rules::of((int) $library->id)));
        $this->assertSame(['*', 'device'], [rules::of((int) $library->id)[0]['readertype'],
            rules::of((int) $library->id)[0]['itemtype']]);

        // A library without rules lends everything with the plain default: nothing is added.
        $other = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertFalse(cataloguer::add($other, $description, ['count' => 1, 'itemtype' => 'device'])['ruled']);
        $this->assertSame([], rules::of((int) $other->id));
    }
}
