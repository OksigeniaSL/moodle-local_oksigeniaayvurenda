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

use local_oksigeniaayvurenda\core\control_letter;

/**
 * Tests for importing a catalogue from MARC or CSV.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\import\catalogue_import
 * @covers     \local_oksigeniaayvurenda\local\import\files
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogue_import::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(files::class)]
final class catalogue_import_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Another program's MARC: records and copies with their places; a copy without a code gets one after the
     * library's; a book the library already had by ISBN is not repeated; loading the file again repeats nothing.
     */
    public function test_marc(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $DB->set_field(self::T . 'entity', 'settings', json_encode(['controlletter' => true]), ['id' => $library->entityid]);
        $gen->create_item(['libraryid' => $library->id, 'barcode' => '100041Z',
            'recordid' => $gen->create_record(['entityid' => $library->entityid, 'title' => 'Ya estaba',
                'isbn' => '9798999999993'])->id]);
        $path = __DIR__ . '/../../fixtures/marc/programa.xml';
        $this->assertSame('marcxml', files::kind($path, 'programa.xml'));
        // One record on its own, without a namespace.
        $single = make_request_directory() . '/una.xml';
        file_put_contents($single, '<?xml version="1.0"?><record><leader>00000nam a2200000 a 4500</leader>'
            . '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Sola</subfield></datafield></record>');
        $this->assertSame('marcxml', files::kind($single, 'una.xml'));
        $stats = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'marcxml'));
        $this->assertSame(['records' => 1, 'found' => 1, 'items' => 3, 'kept' => 0, 'newcodes' => 1, 'untitled' => 0,
            'updated' => 0], $stats);
        $this->assertSame(2, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $new = $DB->get_record(self::T . 'item', ['barcode' => control_letter::append('100042')], '*', MUST_EXIST);
        $this->assertSame('N', $new->callnumber1);
        $koha = $DB->get_record(self::T . 'item', ['barcode' => 'K1001'], '*', MUST_EXIST);
        $this->assertSame('BK', $koha->itemtype);
        $this->assertEquals(make_timestamp(2024, 3, 1), $koha->timeacquired);
        $this->assertSame('Narrativa', $DB->get_field(self::T . 'authority', 'label', ['id' => $koha->locationid]));

        $again = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'marcxml'));
        $this->assertSame(['records' => 0, 'found' => 2, 'items' => 0, 'kept' => 3, 'newcodes' => 0, 'untitled' => 0,
            'updated' => 0], $again);
    }

    /**
     * A French program's UNIMARC, in MarcXchange: the record read from its own fields, and its copy from 995.
     */
    public function test_unimarc(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $path = __DIR__ . '/../../fixtures/marc/unimarc.xml';
        $stats = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'marcxml'));
        $this->assertSame(1, $stats['records']);
        $this->assertSame(1, $stats['items']);
        $record = $DB->get_record(self::T . 'record', ['entityid' => $library->entityid], '*', MUST_EXIST);
        $this->assertSame('Le phare endormi', $record->title);
        $this->assertSame('9791090000018', $record->isbn);
        $this->assertSame('fre', $record->lang);
        $item = $DB->get_record(self::T . 'item', ['barcode' => 'F100'], '*', MUST_EXIST);
        $this->assertEquals($record->id, $item->recordid);
        $this->assertSame('Laurier, Irène', $DB->get_field_sql(
            'SELECT a.label FROM {' . self::T . 'authority} a JOIN {' . self::T . 'recauth} ra ON ra.authorityid = a.id
              WHERE ra.recordid = ? AND a.kind = ?',
            [$record->id, 'author']
        ));
    }

    /**
     * A publisher's ONIX: the records, without copies (they are added at the desk); loading it again repeats nothing.
     */
    public function test_onix(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $path = __DIR__ . '/../../fixtures/onix/referencia.xml';
        $this->assertSame('onix', files::kind($path, 'catalogo.xml'));
        $stats = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'onix'));
        $this->assertSame(2, $stats['records']);
        $this->assertSame(0, $stats['items']);
        $this->assertSame('El faro dormido', $DB->get_field(self::T . 'record', 'title', ['entityid' => $library->entityid,
            'isbn' => '9788490000014']));
        $again = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'onix'));
        $this->assertSame(0, $again['records']);
    }

    /**
     * A spreadsheet: the records and copies of the rows.
     */
    public function test_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $dir = make_request_directory();
        file_put_contents($dir . '/libros.csv', "Título;Autor;Ejemplares\nCuentos a oscuras;Olmo Ríos, Bruno;2\n");
        $this->assertSame('csv', files::kind($dir . '/libros.csv', 'libros.csv'));
        $csv = files::csv($dir . '/libros.csv');
        $records = files::csv_records($dir . '/libros.csv', \local_oksigeniaayvurenda\core\import\catalogue_csv::guess(
            $csv['columns']
        ));
        $stats = (new catalogue_import($library, 'csv'))->run($records);
        $this->assertSame(1, $stats['records']);
        $this->assertSame(2, $stats['items']);
        // Another pass repeats nothing, though these copies have no code; one more copy in the file is one more.
        $again = (new catalogue_import($library, 'csv'))->run($records);
        $this->assertSame(['records' => 0, 'found' => 1, 'items' => 0, 'kept' => 2, 'newcodes' => 0, 'untitled' => 0,
            'updated' => 0], $again);
        file_put_contents($dir . '/libros.csv', "Título;Autor;Ejemplares\nCuentos a oscuras;Olmo Ríos, Bruno;3\n");
        $more = (new catalogue_import($library, 'csv'))->run(files::csv_records(
            $dir . '/libros.csv',
            \local_oksigeniaayvurenda\core\import\catalogue_csv::guess($csv['columns'])
        ));
        $this->assertSame([1, 2], [$more['items'], $more['kept']]);
        $this->assertSame('Cuentos a oscuras', $DB->get_field(self::T . 'record', 'title', ['entityid' => $library->entityid]));
    }

    /**
     * Records with nothing that names them come in once however many times they are imported; a copy whose code is
     * another record\'s gets a new one, to be relabelled; a record with no title at all is counted, not lost in silence.
     */
    public function test_unnamed_duplicated_and_untitled(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $record = fn(string $title, string $code) => ['key' => '', 'description' => ['record' => ['title' => $title],
            'authorities' => ['author' => ['Prado, Luis']]], 'items' => [['barcode' => $code, 'callnumber' => ['N', 'PRA', '']]]];
        $records = [$record('Sin número Zeta', 'ZETA-C1'), $record('Repetido dos Zeta', 'ZETA-C1'),
            ['key' => '', 'description' => ['record' => ['title' => '']], 'items' => []]];
        $first = new catalogue_import($library, 'marc');
        $stats = $first->run($records);
        $this->assertSame(2, $stats['records']);
        $this->assertSame(2, $stats['items']);
        $this->assertSame(1, $stats['untitled']);
        $this->assertCount(1, $first->relabelled());
        $this->assertSame('ZETA-C1', $first->relabelled()[0][0]);
        $again = (new catalogue_import($library, 'marc'))->run($records);
        $this->assertSame(0, $again['records']);
        $this->assertSame(0, $again['items']);
        $this->assertSame(2, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertSame(2, $DB->count_records(self::T . 'item', ['libraryid' => $library->id]));
    }

    /**
     * Two programs that number their records alike: the second one\'s book is not hung on the first one\'s record.
     */
    public function test_same_number_another_book(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $one = fn(string $title, string $code) => [['key' => '1', 'description' => ['record' => ['title' => $title],
            'authorities' => []], 'items' => [['barcode' => $code, 'callnumber' => ['', '', '']]]]];
        (new catalogue_import($library, 'marc'))->run($one('Primer programa Zeta', 'ZETA-B1'));
        $stats = (new catalogue_import($library, 'marc'))->run($one('Segundo programa Zeta, otro libro', 'ZETA-B2'));
        $this->assertSame(1, $stats['records']);
        $this->assertSame(2, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertNotEquals(
            $DB->get_field(self::T . 'item', 'recordid', ['barcode' => 'ZETA-B1']),
            $DB->get_field(self::T . 'item', 'recordid', ['barcode' => 'ZETA-B2'])
        );
        // And the same file again finds both.
        $again = (new catalogue_import($library, 'marc'))->run($one('Segundo programa Zeta, otro libro', 'ZETA-B2'));
        $this->assertSame(0, $again['records']);
    }

    /**
     * Brought again from its program: a correction made there comes in, unless the record was changed here meanwhile.
     */
    public function test_corrections_from_the_program(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $one = fn(string $title) => [['key' => '7', 'description' => ['record' => ['title' => $title],
            'authorities' => ['author' => ['Olmo, Bruno']]], 'items' => [['barcode' => 'ZETA-E1', 'callnumber' => ['', '', '']]]]];
        (new catalogue_import($library, 'marc'))->run($one('Titulo con errata Zeta'));
        $id = (int) $DB->get_field(self::T . 'item', 'recordid', ['barcode' => 'ZETA-E1']);
        $stats = (new catalogue_import($library, 'marc'))->run($one('Título corregido Zeta'));
        $this->assertSame(1, $stats['updated']);
        $this->assertSame(0, $stats['records']);
        $this->assertSame('Título corregido Zeta', $DB->get_field(self::T . 'record', 'title', ['id' => $id]));
        $this->assertStringContainsString('corregido', $DB->get_field(self::T . 'record', 'searchtext', ['id' => $id]));
        // The same file again changes nothing.
        $this->assertSame(0, (new catalogue_import($library, 'marc'))->run($one('Título corregido Zeta'))['updated']);
        // Changed here: what was done here stays.
        $DB->set_field(self::T . 'record', 'title', 'Título de aquí Zeta', ['id' => $id]);
        $this->assertSame(0, (new catalogue_import($library, 'marc'))->run($one('Título otra vez Zeta'))['updated']);
        $this->assertSame('Título de aquí Zeta', $DB->get_field(self::T . 'record', 'title', ['id' => $id]));
        $this->assertSame(1, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
    }
}
