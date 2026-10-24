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

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\abies\export_reader;

/**
 * Tests for importing an Abies Web export.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\import\abies_import
 */
#[\PHPUnit\Framework\Attributes\CoversClass(abies_import::class)]
final class abies_import_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The made-up export of the fixtures.
     *
     * @return export_reader
     */
    private function export(): export_reader {
        return new export_reader(__DIR__ . '/../../fixtures/abies/small.xml');
    }

    /**
     * The catalogue comes in faithfully: records, authors with roles, classification tree, copies with their codes.
     */
    public function test_import(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $stats = (new abies_import($library->id))->run($this->export());

        $this->assertSame(4, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertSame(4, $stats['record']['created']);
        $this->assertSame(1, $stats['item']['skipped']);
        $this->assertSame(1, $stats['item']['recoded']);
        $this->assertSame(1, $stats['authorlink']['skipped']);

        $faro = $DB->get_record(self::T . 'record', ['title' => 'El faro dormido'], '*', MUST_EXIST);
        $this->assertSame('9780306406157', $faro->isbn);
        $this->assertSame('spa', $faro->lang);
        $this->assertStringContainsString('montes ana', $faro->searchtext);
        $roles = $DB->get_records_sql_menu("SELECT a.label, ra.role
                                              FROM {" . self::T . "recauth} ra
                                              JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                                             WHERE ra.recordid = ? AND a.kind = 'author'", [$faro->id]);
        $this->assertSame(['Montes, Ana' => 'author', 'Prado, Leo' => 'illustrator'], $roles);

        $novel = $DB->get_record(self::T . 'authority', ['kind' => 'class', 'code' => '821.134.2-3'], '*', MUST_EXIST);
        $literature = $DB->get_record(self::T . 'authority', ['id' => $novel->parentid], '*', MUST_EXIST);
        $this->assertSame('82', $literature->code);

        $first = $DB->get_record(self::T . 'item', ['barcode' => '100000L'], '*', MUST_EXIST);
        $this->assertSame('100000', $first->regnumber);
        $this->assertSame('Donado por la clase de 4.º', $first->notes);
        $this->assertSame((int) $library->id, (int) $first->libraryid);
        $this->assertNotEmpty($first->locationid);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['barcode' => '100001C']));
        $this->assertSame('reference', $DB->get_field(self::T . 'item', 'itemtype', ['barcode' => '100002K']));
        $this->assertTrue($DB->record_exists(self::T . 'item', ['barcode' => '100002K-4']));

        $rules = $DB->get_records_menu(self::T . 'rule', ['libraryid' => $library->id, 'readertype' => 'student'], '',
            'itemtype, loandays');
        $this->assertEquals(['normal' => 7, 'reference' => 0], $rules);
        $this->assertSame(3, $DB->count_records(self::T . 'rule', ['libraryid' => $library->id]));
        $student = $DB->get_record(self::T . 'rule', ['libraryid' => $library->id, 'readertype' => 'student',
            'itemtype' => 'normal'], '*', MUST_EXIST);
        $this->assertSame(6, (int) $student->renewals);
        $settings = json_decode($DB->get_field(self::T . 'entity', 'settings', ['id' => $library->entityid]), true);
        $this->assertSame('No prestable', $settings['itemtypes']['reference']);
    }

    /**
     * A second run with the same export changes nothing; a changed title is updated, and nothing is duplicated.
     */
    public function test_reimport(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id))->run($this->export());
        $counts = fn() => [
            $DB->count_records(self::T . 'record'),
            $DB->count_records(self::T . 'item'),
            $DB->count_records(self::T . 'authority'),
            $DB->count_records(self::T . 'recauth'),
        ];
        $before = $counts();

        $again = (new abies_import($library->id))->run($this->export());
        $this->assertSame($before, $counts());
        $this->assertSame(4, $again['record']['unchanged']);
        $this->assertArrayNotHasKey('created', $again['record']);
        $this->assertSame(['kept' => 1], $again['rule']);

        $fixture = file_get_contents(__DIR__ . '/../../fixtures/abies/small.xml');
        $changed = str_replace('El faro dormido', 'El faro despierto', $fixture);
        $file = tempnam(sys_get_temp_dir(), 'ayv');
        file_put_contents($file, $changed);
        $third = (new abies_import($library->id))->run(new export_reader($file));
        unlink($file);
        $this->assertSame(1, $third['record']['updated']);
        $this->assertSame($before, $counts());
        $this->assertTrue($DB->record_exists(self::T . 'record', ['title' => 'El faro despierto']));
    }

    /**
     * Without notes, the free notes of the copies stay out.
     */
    public function test_without_notes(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id, false))->run($this->export());
        $this->assertNull($DB->get_field(self::T . 'item', 'notes', ['barcode' => '100000L']));
    }
}
