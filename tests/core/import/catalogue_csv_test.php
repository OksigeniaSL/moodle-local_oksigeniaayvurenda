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

    /**
     * The exports of other programs: Goodreads (its ISBN-13 first, «="…"», the «to-read» shelf left out), Calibre (« & »,
     * its description in HTML), Libib (authors by commas) and LibraryThing («[…]», several ISBNs in a cell).
     */
    public function test_other_programs(): void {
        // Goodreads.
        $columns = ['Book Id', 'Title', 'Author', 'Author l-f', 'Additional Authors', 'ISBN', 'ISBN13', 'Publisher',
            'Number of Pages', 'Year Published', 'Original Publication Year', 'Exclusive Shelf', 'Owned Copies'];
        $map = catalogue_csv::guess($columns);
        $this->assertSame('isbn', $map[6]);
        $this->assertArrayNotHasKey(5, $map);
        ksort($map);
        $this->assertSame(
            ['title', 'author', 'moreauthors', 'isbn', 'publisher', 'extent', 'year', 'shelf', 'copies'],
            array_values(array_intersect_key($map, array_flip([1, 2, 4, 6, 7, 8, 9, 11, 12])))
        );
        $records = catalogue_csv::records([
            ['1', 'El faro dormido', 'Irene Laurel', 'Laurel, Irene', 'Bruno Olmo, Marta Ríos', '="8490000018"',
                '="9788490000014"', 'Brújula', '96', '2024', '2020', 'read', '2'],
            ['2', 'La nube de tinta', 'Marco Quintana', 'Quintana, Marco', '', '=""', '=""', 'Brújula', '80', '2023', '',
                'to-read', '0'],
        ], $map);
        $this->assertCount(1, $records);
        $this->assertSame('9788490000014', $records[0]['description']['record']['isbn']);
        $this->assertSame(['Irene Laurel', 'Bruno Olmo', 'Marta Ríos'], $records[0]['description']['authorities']['author']);
        $this->assertSame('96 p.', $records[0]['description']['record']['extent']);
        $this->assertSame('2024', $records[0]['description']['record']['pubyear']);
        $this->assertCount(2, $records[0]['items']);

        // Calibre.
        $map = catalogue_csv::guess(['title', 'authors', 'author_sort', 'publisher', 'pubdate', 'isbn', 'tags', 'series',
            'languages', 'comments']);
        $records = catalogue_csv::records([['Mapas del sur', 'Olivia Estrada & Bruno Olmo', 'Estrada, Olivia', 'Brújula',
            '2022-05-01T00:00:00+00:00', '9788490000021', 'Viajes, Mapas', 'Linterna', 'spa',
            '<div><p>Un libro de <b>mapas</b>.</p><p>Para viajar.</p></div>']], $map);
        $d = $records[0]['description'];
        $this->assertSame(['Olivia Estrada', 'Bruno Olmo'], $d['authorities']['author']);
        $this->assertSame('2022', $d['record']['pubyear']);
        $this->assertSame('Un libro de mapas. Para viajar.', $d['record']['summary']);
        $this->assertSame(['Linterna'], $d['authorities']['collection']);

        // Libib and LibraryThing.
        $map = catalogue_csv::guess(['item_type', 'title', 'creators', 'ean_isbn13', 'upc_isbn10', 'description', 'copies']);
        $this->assertSame('isbn', $map[3]);
        $records = catalogue_csv::records([['book', 'El faro dormido', 'Irene Laurel, Bruno Olmo', '9788490000014', '',
            'Un faro.', '1']], $map);
        $this->assertSame(['Irene Laurel', 'Bruno Olmo'], $records[0]['description']['authorities']['author']);
        $map = catalogue_csv::guess(['Book Id', 'Title', 'Primary Author', 'Secondary Author', 'Date', 'Page Count',
            'Barcode', 'Tags', 'ISBN', 'ISBNs', 'Dewey Decimal', 'Other Call Number', 'Copies']);
        $records = catalogue_csv::records([['7', 'El faro dormido', 'Laurel, Irene', 'Olmo, Bruno', '2024', '96', 'LT1',
            'faros', '[8490000018]', '8490000018, 9788490000014', '863', 'N LAU', '1']], $map);
        $d = $records[0]['description'];
        $this->assertSame('9788490000014', $d['record']['isbn']);
        $this->assertSame(['Laurel, Irene', 'Olmo, Bruno'], $d['authorities']['author']);
        $this->assertSame(['863'], $d['authorities']['class']);
        $this->assertSame('LT1', $records[0]['items'][0]['barcode']);
        $this->assertSame(['N', 'LAU', ''], $records[0]['items'][0]['callnumber']);
    }

    /**
     * A date that is when the copy came is not the year, the kind of document is said, two editions without an ISBN are two
     * books, and Excel's «Unicode text» (UTF-16) is read.
     */
    public function test_dates_kinds_and_editions(): void {
        $columns = ['Título', 'Autor', 'Fecha de alta', 'Año de edición', 'Editor', 'Tipo de documento'];
        $map = catalogue_csv::guess($columns);
        ksort($map);
        $this->assertSame(['title', 'author', 'acquired', 'year', 'publisher', 'doctype'], array_values($map));
        // A heading of just Fecha is still the year; inside a longer heading it is not.
        $this->assertSame([0 => 'title', 1 => 'year'], catalogue_csv::guess(['Title', 'Date']));
        $this->assertNotContains('year', catalogue_csv::guess(['Título', 'Fecha de baja']));
        $rows = [
            ['Bloque H', 'Ruiz Mar, Ana', '01/03/2024', '1998', 'Brújula', 'Libro'],
            ['Bloque H', 'Ruiz Mar, Ana', '2024-03-02', '2015', 'Brújula', 'Libro'],
            ['Bloque H', 'Ruiz Mar, Ana', '31/02/2024', '2015', 'Brújula', 'Libro'],
            ['Mares del sur', 'Lago, Iker', '', '', '', 'DVD'],
            ['Revista Zeta', '', '', '', '', 'Revista'],
        ];
        $records = catalogue_csv::records($rows, $map);
        $this->assertCount(4, $records);
        $this->assertSame(['1998', '2015'], [$records[0]['description']['record']['pubyear'],
            $records[1]['description']['record']['pubyear']]);
        $this->assertSame('bloque h ruiz mar ana', $records[0]['oldkey']);
        $this->assertSame(['2024-03-02', ''], array_column($records[1]['items'], 'acquired'));
        $this->assertSame('2024-03-01', $records[0]['items'][0]['acquired']);
        $this->assertSame(['book', 'book', 'video', 'periodical'], array_map(
            fn($r) => $r['description']['record']['doctype'],
            $records
        ));
        // An ISBN typed wrong goes to the notes and is said.
        $bad = catalogue_csv::records([['Zeta mal tecleado', '9788400000016']], catalogue_csv::guess(['Título', 'ISBN']));
        $this->assertNull($bad[0]['description']['record']['isbn']);
        $this->assertSame('9788400000016', $bad[0]['badisbn']);
        $this->assertSame('ISBN 9788400000016', $bad[0]['description']['record']['notes']);
        $this->assertSame(['9788400000016'], \local_oksigeniaayvurenda\local\import\preview::of($bad)['badisbn']);
        $utf16 = "\xFF\xFE" . mb_convert_encoding("Título;Autor\nÑandú;Pérez", 'UTF-16LE', 'UTF-8');
        $list = \local_oksigeniaayvurenda\core\readers\csv_list::read($utf16);
        $this->assertSame(['Título', 'Autor'], $list['columns']);
        $this->assertSame([['Ñandú', 'Pérez']], $list['rows']);
    }
}
