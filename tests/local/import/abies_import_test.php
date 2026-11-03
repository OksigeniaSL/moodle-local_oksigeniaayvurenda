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

        $rules = $DB->get_records_menu(
            self::T . 'rule',
            ['libraryid' => $library->id, 'readertype' => 'student'],
            '',
            'itemtype, loandays'
        );
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

    /**
     * An old export: what it had out comes in on the shelf and returned on its day, never a year late; and a copy lent
     * here since keeps its loan when the same export comes again.
     */
    public function test_as_returned(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');

        // As it was, the copy Abies had out comes in out, with its loan open.
        $then = $generator->create_library();
        (new abies_import($then->id, true, true))->run($this->export());
        $item = $DB->get_record(self::T . 'item', ['entityid' => $then->entityid, 'barcode' => '100001C'], '*', MUST_EXIST);
        $this->assertSame('onloan', $item->status);
        $this->assertSame(1, $DB->count_records_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL', [$item->id]));

        $library = $generator->create_library();
        $importer = new abies_import($library->id, true, true, true);
        $importer->run($this->export());
        // Nothing here says the copy came back: it is listed to look on the shelf.
        $this->assertSame([['100001C', 'El faro dormido', '10000H']], array_map(
            fn($out) => array_slice($out, 0, 3),
            $importer->out_then()
        ));
        $item = $DB->get_record(self::T . 'item', ['entityid' => $library->entityid, 'barcode' => '100001C'], '*', MUST_EXIST);
        $this->assertSame('available', $item->status);
        $loan = $DB->get_record(self::T . 'loan', ['itemid' => $item->id], '*', MUST_EXIST);
        $this->assertNotNull($loan->timereturned);
        $this->assertGreaterThanOrEqual((int) $loan->timeloaned, (int) $loan->timereturned);
        $this->assertSame(0, $DB->count_records_select(
            self::T . 'loan',
            'libraryid = ? AND timereturned IS NULL',
            [$library->id]
        ));

        // Lent at the desk since: the same export again leaves it lent, and it is not listed.
        $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $item->id]);
        $again = new abies_import($library->id, true, true, true);
        $again->run($this->export());
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $item->id]));
        $this->assertSame([], $again->out_then());
    }

    /**
     * An export of the fixtures with some changes, as a newer export would bring them.
     *
     * @param array $changes Text to replace => new text.
     * @return export_reader
     */
    private function newer(array $changes): export_reader {
        $fixture = file_get_contents(__DIR__ . '/../../fixtures/abies/small.xml');
        foreach ($changes as $from => $to) {
            $this->assertSame(1, substr_count($fixture, $from), $from);
            $fixture = str_replace($from, $to, $fixture);
        }
        $file = make_request_directory() . '/newer.xml';
        file_put_contents($file, $fixture);
        return new export_reader($file);
    }

    /**
     * What an import leaves is its starting point: the fingerprint it keeps is the one the rows give back, also when
     * it is taken afterwards from what there is, as the upgrade does.
     */
    public function test_starting_point(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id, true, true))->run($this->export());
        $written = $DB->get_records_menu(self::T . 'source', ['entityid' => $library->entityid], 'id', 'id, localhash');
        $this->assertCount(0, array_filter($written, fn($hash) => $hash === null));
        $DB->set_field(self::T . 'source', 'localhash', null, ['entityid' => $library->entityid]);
        abies_import::settle((int) $library->entityid);
        $this->assertSame(
            $written,
            $DB->get_records_menu(self::T . 'source', ['entityid' => $library->entityid], 'id', 'id, localhash')
        );
    }

    /**
     * A newer export updates what was not changed here and leaves what was: a record, a copy and a loan.
     */
    public function test_changed_here(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id, true, true))->run($this->export());
        // From before this version: the upgrade takes what there is as the starting point.
        $DB->set_field(self::T . 'source', 'localhash', null, ['entityid' => $library->entityid]);
        abies_import::settle((int) $library->entityid);

        $record = fn(string $title) => $DB->get_record(self::T . 'record', ['entityid' => $library->entityid,
            'title' => $title], '*', MUST_EXIST);
        $item = fn(string $code) => $DB->get_record(self::T . 'item', ['entityid' => $library->entityid,
            'barcode' => $code], '*', MUST_EXIST);
        $faro = $record('El faro dormido');
        $DB->set_field(self::T . 'record', 'notes', 'Corregida aquí', ['id' => $faro->id]);
        $DB->set_field(self::T . 'item', 'callnumber3', 'FAR', ['id' => $item('100000L')->id]);
        $out = $DB->get_record_select(
            self::T . 'loan',
            'itemid = ? AND timereturned IS NULL',
            [$item('100001C')->id],
            '*',
            MUST_EXIST
        );
        $DB->set_field(self::T . 'loan', 'timereturned', time(), ['id' => $out->id]);

        $stats = (new abies_import($library->id, true, true))->run($this->newer([
            '<Titulo>El faro dormido</Titulo>' => '<Titulo>El faro despierto</Titulo>',
            '<Titulo>La isla de los animales</Titulo>' => '<Titulo>La isla de las aves</Titulo>',
            'Observaciones="Donado por la clase de 4.º"' => 'Observaciones="Donado por la clase de 5.º"',
            'NumRegistro="2001"' => 'NumRegistro="2002"',
            'FechaCalculada="2025-09-27"' => 'FechaCalculada="2025-10-04"',
        ]));

        $this->assertSame(1, $stats['record']['changedhere']);
        $this->assertSame(1, $stats['record']['updated']);
        $this->assertSame(1, $stats['item']['changedhere']);
        $this->assertSame(1, $stats['item']['updated']);
        $this->assertSame(1, $stats['loan']['changedhere']);
        $kept = $DB->get_record(self::T . 'record', ['id' => $faro->id], '*', MUST_EXIST);
        $this->assertSame('El faro dormido', $kept->title);
        $this->assertSame('Corregida aquí', $kept->notes);
        $this->assertSame('La isla de las aves', $record('La isla de las aves')->title);
        $this->assertSame('Donado por la clase de 4.º', $item('100000L')->notes);
        $this->assertSame('2002', $item('100001C')->regnumber);
        $this->assertNotNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $out->id]));
        $this->assertSame(4, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));

        // What was updated is the new starting point: the same export again changes nothing.
        $again = (new abies_import($library->id, true, true))->run($this->newer([
            '<Titulo>El faro dormido</Titulo>' => '<Titulo>El faro despierto</Titulo>',
            '<Titulo>La isla de los animales</Titulo>' => '<Titulo>La isla de las aves</Titulo>',
            'Observaciones="Donado por la clase de 4.º"' => 'Observaciones="Donado por la clase de 5.º"',
            'NumRegistro="2001"' => 'NumRegistro="2002"',
            'FechaCalculada="2025-09-27"' => 'FechaCalculada="2025-10-04"',
        ]));
        $this->assertArrayNotHasKey('updated', $again['record']);
        $this->assertArrayNotHasKey('updated', $again['item']);
    }

    /**
     * What was deleted here does not come back with a newer export: a record with its copies, and a loan.
     */
    public function test_deleted_here(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id, true, true))->run($this->export());
        $isla = $DB->get_record(
            self::T . 'record',
            ['entityid' => $library->entityid, 'title' => 'La isla de los animales'],
            '*',
            MUST_EXIST
        );
        $DB->delete_records(self::T . 'item', ['recordid' => $isla->id]);
        $DB->delete_records(self::T . 'recauth', ['recordid' => $isla->id]);
        $DB->delete_records(self::T . 'record', ['id' => $isla->id]);
        $returned = $DB->get_record_select(
            self::T . 'loan',
            'libraryid = ? AND timereturned IS NOT NULL',
            [$library->id],
            '*',
            IGNORE_MULTIPLE
        );
        $DB->delete_records(self::T . 'loan', ['id' => $returned->id]);
        $loans = $DB->count_records(self::T . 'loan', ['libraryid' => $library->id]);

        $stats = (new abies_import($library->id, true, true))->run($this->newer([
            '<Titulo>La isla de los animales</Titulo>' => '<Titulo>La isla de las aves</Titulo>',
        ]));

        $this->assertSame(1, $stats['record']['deletedhere']);
        $this->assertSame(2, $stats['item']['deletedhere']);
        $this->assertSame(1, $stats['loan']['deletedhere']);
        $this->assertFalse($DB->record_exists_select(
            self::T . 'record',
            'entityid = ? AND title LIKE ?',
            [$library->entityid, 'La isla%']
        ));
        $this->assertFalse($DB->record_exists(self::T . 'item', ['entityid' => $library->entityid, 'barcode' => '100005A']));
        $this->assertSame($loans, $DB->count_records(self::T . 'loan', ['libraryid' => $library->id]));
    }

    /**
     * Books added here before the export came: a copy with the code of its Abies label is that copy, with its record;
     * a record with the ISBN of one of the export is that record; and a copy that got the code Abies printed on
     * another book gets a new one, so that the printed label wins.
     */
    public function test_made_here(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $faro = $generator->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido: una aventura']);
        $label = $generator->create_item(['libraryid' => $library->id, 'recordid' => $faro->id, 'barcode' => '100000L']);
        $atlas = $generator->create_record(['entityid' => $library->entityid, 'title' => 'Atlas de los mares']);
        $clash = $generator->create_item(['libraryid' => $library->id, 'recordid' => $atlas->id, 'barcode' => '100005A']);

        $importer = new abies_import($library->id);
        $stats = $importer->run($this->export());

        $this->assertSame(1, $stats['record']['madehere']);
        $this->assertSame(1, $stats['item']['madehere']);
        $this->assertSame(1, $stats['item']['relabelled']);
        $this->assertSame(5, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertSame(1, $DB->count_records_select(
            self::T . 'record',
            'entityid = ? AND title LIKE ?',
            [$library->entityid, 'El faro%']
        ));
        // The copy with the label is the same one, and the other copy of the record joins it.
        $this->assertSame((int) $label->id, (int) $DB->get_field(self::T . 'item', 'id', ['entityid' => $library->entityid,
            'barcode' => '100000L']));
        $this->assertSame((int) $faro->id, (int) $DB->get_field(self::T . 'item', 'recordid', ['entityid' => $library->entityid,
            'barcode' => '100001C']));
        // The printed label wins; the copy made here is listed to be relabelled.
        $isla = $DB->get_field(self::T . 'record', 'id', ['entityid' => $library->entityid,
            'title' => 'La isla de los animales']);
        $this->assertEquals($isla, $DB->get_field(self::T . 'item', 'recordid', ['entityid' => $library->entityid,
            'barcode' => '100005A']));
        [[$old, $new, $title]] = $importer->relabelled();
        $this->assertSame(['100005A', 'Atlas de los mares'], [$old, $title]);
        $this->assertSame($new, $DB->get_field(self::T . 'item', 'barcode', ['id' => $clash->id]));
        $this->assertStringStartsWith('100006', $new);

        // Only the ISBN: the record made here is that one, and its copies come into it.
        $other = $generator->create_library();
        $made = $generator->create_record(['entityid' => $other->entityid, 'title' => 'Faro', 'isbn' => '9780306406157']);
        $stats = (new abies_import($other->id))->run($this->export());
        $this->assertSame(1, $stats['record']['madehere']);
        $this->assertSame(4, $DB->count_records(self::T . 'record', ['entityid' => $other->entityid]));
        $this->assertSame(2, $DB->count_records(self::T . 'item', ['recordid' => $made->id]));
        $this->assertSame('Faro', $DB->get_field(self::T . 'record', 'title', ['id' => $made->id]));
    }

    /**
     * A reader erased at their request does not come back with the export, and only a fingerprint of their Abies id
     * stays.
     */
    public function test_erased_reader(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        (new abies_import($library->id, true, true))->run($this->export());
        $reader = $DB->get_record(
            self::T . 'reader',
            ['entityid' => $library->entityid, 'cardcode' => '10002C'],
            '*',
            MUST_EXIST
        );
        \local_oksigeniaayvurenda\local\erasure::reader((int) $reader->id);
        $this->assertFalse($DB->record_exists(self::T . 'source', ['entityid' => $library->entityid, 'tablename' => 'reader',
            'externalid' => '70003']));

        $stats = (new abies_import($library->id, true, true))->run($this->export());
        $this->assertSame(1, $stats['reader']['erased']);
        $this->assertFalse($DB->record_exists(self::T . 'reader', ['entityid' => $library->entityid, 'firstname' => 'Antigua']));
    }
}
