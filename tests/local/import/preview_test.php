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

use local_oksigeniaayvurenda\core\import\catalogue_csv;

/**
 * What a file would bring, before anything enters.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\import\preview
 */
#[\PHPUnit\Framework\Attributes\CoversClass(preview::class)]
final class preview_test extends \advanced_testcase {
    /**
     * The rows of a sheet, one for each copy.
     *
     * @return array columns and rows.
     */
    private function sheet(): array {
        return [
            'columns' => ['Título', 'Autor', 'Signatura', 'Sala', 'Código', 'Nº de caja'],
            'rows' => [
                ['El faro dormido', 'Roma Quintana', '821 RAM fa', 'Sala general', 'A1', ''],
                ['El faro dormido', 'Roma Quintana', '821 RAM fa', 'Sala general', '', '7'],
                ['Mapas del tesoro', 'Coral Duna; Teo Marín', '912 COR ma', 'Consulta', 'A3', ''],
                ['Sin ubicación', '', '', '', '', ''],
            ],
        ];
    }

    /**
     * The figures count records, copies, places and copies without a code, and the books come ready to draw.
     */
    public function test_figures_and_books(): void {
        $sheet = $this->sheet();
        $look = preview::of(catalogue_csv::records($sheet['rows'], catalogue_csv::guess($sheet['columns'])));
        $this->assertSame(3, $look['records']);
        $this->assertSame(4, $look['items']);
        $this->assertSame(2, $look['places']);
        $this->assertSame(2, $look['nocode']);
        $this->assertCount(3, $look['books']);

        [$lighthouse, $maps, $bare] = $look['books'];
        $this->assertSame('El faro dormido', $lighthouse['title']);
        $this->assertSame('Roma Quintana', $lighthouse['authors']);
        $this->assertSame([['line' => '821'], ['line' => 'RAM'], ['line' => 'fa']], $lighthouse['lines']);
        $this->assertSame('821 RAM fa', $lighthouse['callnumber']);
        $this->assertSame('Sala general', $lighthouse['place']);
        $this->assertSame(2, $lighthouse['copies']);
        $this->assertSame('Coral Duna · Teo Marín', $maps['authors']);
        $this->assertNotSame($lighthouse['colour'], $maps['colour']);

        // What is missing is there, empty: a template looks for what is not there in the page around it.
        $this->assertFalse($bare['haslines']);
        $this->assertSame('', $bare['authors']);
        $this->assertSame('', $bare['place']);
    }

    /**
     * Only the first books are drawn, and all of them are counted.
     */
    public function test_only_the_first_books_are_drawn(): void {
        $rows = [];
        for ($i = 0; $i < preview::BOOKS + 4; $i++) {
            $rows[] = ['Libro ' . $i];
        }
        $look = preview::of(catalogue_csv::records($rows, [0 => 'title']));
        $this->assertSame(preview::BOOKS + 4, $look['records']);
        $this->assertCount(preview::BOOKS, $look['books']);
    }

    /**
     * A generator is read once, as a MARC file arrives.
     */
    public function test_it_reads_a_generator(): void {
        $records = (function () {
            yield ['description' => ['record' => ['title' => 'Uno'], 'authorities' => ['author' => ['Ana']]],
                'items' => [['barcode' => 'X', 'callnumber' => ['82', '', ''], 'location' => '']]];
        })();
        $look = preview::of($records);
        $this->assertSame(1, $look['records']);
        $this->assertSame('Uno', $look['books'][0]['title']);
        $this->assertSame([['line' => '82']], $look['books'][0]['lines']);
        $this->assertSame(0, $look['places']);
        $this->assertSame(0, $look['nocode']);
    }

    /**
     * Each column says what it was taken to be, what it says, and whether its name already told.
     */
    public function test_columns(): void {
        $sheet = $this->sheet();
        $map = catalogue_csv::guess($sheet['columns']);
        $columns = preview::columns($sheet, $map);
        $this->assertSame(6, $columns['all']);
        $this->assertSame(5, $columns['known']);
        $this->assertTrue($columns['hastitle']);

        $byname = array_column($columns['columns'], null, 'heading');
        // Its name already said so.
        $this->assertSame('', $byname['Código']['becomes']);
        $this->assertSame('', $byname['Sala']['becomes']);
        // A column taken by hand for something its name does not say.
        $map[5] = 'location';
        unset($map[3]);
        $byhand = array_column(preview::columns($sheet, $map)['columns'], null, 'heading');
        $this->assertSame(get_string('importfieldlocation', 'local_oksigeniaayvurenda'), $byhand['Nº de caja']['becomes']);
        $this->assertFalse($byname['Nº de caja']['isknown']);
        // The first thing it says, though the first rows say nothing.
        $this->assertSame('7', $byname['Nº de caja']['sample']);
        $options = array_column($byname['Título']['options'], 'selected', 'value');
        $this->assertTrue($options['title']);
        $this->assertTrue($byname['Nº de caja']['options'][0]['selected']);
    }

    /**
     * Without a column for the title nothing could come in, and it is known.
     */
    public function test_a_sheet_without_a_title(): void {
        $columns = preview::columns(['columns' => ['Nombre', 'Autor'], 'rows' => [['Uno', 'Ana']]], [1 => 'author']);
        $this->assertFalse($columns['hastitle']);
        $this->assertSame(1, $columns['known']);
        $this->assertSame(0, preview::of(catalogue_csv::records([['Uno', 'Ana']], [1 => 'author']))['records']);
    }
}
