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
        $stats = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'marcxml'));
        $this->assertSame(['records' => 1, 'found' => 1, 'items' => 3, 'kept' => 0, 'newcodes' => 1], $stats);
        $this->assertSame(2, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $new = $DB->get_record(self::T . 'item', ['barcode' => control_letter::append('100042')], '*', MUST_EXIST);
        $this->assertSame('N', $new->callnumber1);
        $koha = $DB->get_record(self::T . 'item', ['barcode' => 'K1001'], '*', MUST_EXIST);
        $this->assertSame('BK', $koha->itemtype);
        $this->assertEquals(make_timestamp(2024, 3, 1), $koha->timeacquired);
        $this->assertSame('Narrativa', $DB->get_field(self::T . 'authority', 'label', ['id' => $koha->locationid]));

        $again = (new catalogue_import($library, 'marc'))->run(files::marc($path, 'marcxml'));
        $this->assertSame(['records' => 0, 'found' => 2, 'items' => 1, 'kept' => 2, 'newcodes' => 1], $again);
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
        $this->assertSame('Cuentos a oscuras', $DB->get_field(self::T . 'record', 'title', ['entityid' => $library->entityid]));
    }
}
