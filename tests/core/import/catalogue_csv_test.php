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

namespace local_oksigeniaayvurenda\core\import;

/**
 * Tests for a catalogue in CSV.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\import\catalogue_csv
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogue_csv::class)]
final class catalogue_csv_test extends \basic_testcase {
    /**
     * The headings are understood; rows of the same book are one record with its copies; a row can say how many.
     */
    public function test_records(): void {
        $columns = ['Título', 'Autor', 'Editorial', 'Año', 'ISBN', 'Código de barras', 'Signatura', 'Ubicación', 'Materias',
            'Idioma', 'Ejemplares'];
        $map = catalogue_csv::guess($columns);
        ksort($map);
        $this->assertSame(['title', 'author', 'publisher', 'year', 'isbn', 'barcode', 'callnumber', 'location', 'subject',
            'language', 'copies'], array_values($map));
        $rows = [
            ['El jardín de las palabras', 'Duna Faro, Lía', 'Editorial Brújula', 'c2025', '979-8-9999999-9-3', '100000L',
                'N DUN jar', 'Narrativa', 'Jardines; Palabras', 'Español', ''],
            ['El jardín de las palabras', 'Duna Faro, Lía', 'Editorial Brújula', '2025', '9798999999993', '100001C',
                'N DUN jar', 'Narrativa', '', 'Español', ''],
            ['Cuentos a oscuras', 'Olmo Ríos, Bruno', '', '', '', '', '', '', '', 'cat', '3'],
            ['', 'Sin título', '', '', '', '', '', '', '', '', ''],
        ];
        $records = catalogue_csv::records($rows, $map);
        $this->assertCount(2, $records);
        $first = $records[0];
        $this->assertSame('9798999999993', $first['key']);
        $this->assertSame('2025', $first['description']['record']['pubyear']);
        $this->assertSame('spa', $first['description']['record']['lang']);
        $this->assertSame(['Jardines', 'Palabras'], $first['description']['authorities']['subject']);
        $this->assertSame(['100000L', '100001C'], array_column($first['items'], 'barcode'));
        $this->assertSame(['N', 'DUN', 'jar'], $first['items'][0]['callnumber']);
        $this->assertCount(3, $records[1]['items']);
        $this->assertSame('cat', $records[1]['description']['record']['lang']);
        $this->assertSame('', $records[1]['items'][0]['barcode']);
    }
}
