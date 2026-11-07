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

namespace local_oksigeniaayvurenda\core\marc;

/**
 * Tests for MARCXML and the descriptions made from MARC21.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\marc\marcxml
 * @covers     \local_oksigeniaayvurenda\core\marc\record
 * @covers     \local_oksigeniaayvurenda\core\marc\describer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(marcxml::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(record::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(describer::class)]
final class marc_test extends \basic_testcase {
    /**
     * The made-up record of the fixtures.
     *
     * @return record
     */
    private function record(): record {
        $records = marcxml::read(file_get_contents(__DIR__ . '/../../fixtures/marc/record.xml'));
        $this->assertCount(1, $records);
        return $records[0];
    }

    /**
     * A record is read with its fields in order, and written and read back the same.
     */
    public function test_read_write(): void {
        $record = $this->record();
        $this->assertSame('01234cam a2200301 i 4500', $record->leader);
        $this->assertSame('ayv-0001', $record->control_value('001'));
        $this->assertSame('El faro dormido /', $record->first(['245'], 'a'));
        $this->assertSame(['Faros', 'Mar.', 'Faros'], $record->values(['650'], 'a'));
        $again = marcxml::read(marcxml::write([$record, $record]));
        $this->assertCount(2, $again);
        $this->assertEquals($record, $again[1]);
        $this->expectException(\InvalidArgumentException::class);
        marcxml::read('not xml');
    }

    /**
     * The description: the punctuation that closes a value goes, the ISBN loses its qualifier, the year comes from the
     * imprint, the language from the fixed field, authorities by kind without repeats.
     */
    public function test_describe(): void {
        $d = describer::describe($this->record());
        $this->assertSame('book', $d['record']['doctype']);
        $this->assertSame('El faro dormido', $d['record']['title']);
        $this->assertSame('una historia de mar', $d['record']['subtitle']);
        $this->assertSame('Irene Laurel ; ilustraciones de Bruno Olmo', $d['record']['responsibility']);
        $this->assertSame('9788490000014', $d['record']['isbn']);
        $this->assertSame('Primera edición', $d['record']['edition']);
        $this->assertSame('Villanube', $d['record']['pubplace']);
        $this->assertSame('2024', $d['record']['pubyear']);
        $this->assertSame('spa', $d['record']['lang']);
        $this->assertSame('96 p.', $d['record']['extent']);
        $this->assertSame(['Laurel Prado, Irene', 'Olmo, Bruno'], $d['authorities']['author']);
        $this->assertSame(['Editorial Brújula'], $d['authorities']['publisher']);
        $this->assertSame(['821.134.2-31'], $d['authorities']['class']);
        $this->assertSame(['Faros', 'Mar'], $d['authorities']['subject']);
        $this->assertSame(['Colección Linterna'], $d['authorities']['collection']);
        $this->assertSame(['A partir de 8 años'], $d['authorities']['audience']);
        $this->assertSame('udc', $d['extra']['classscheme']);
        $this->assertSame('12', $d['extra']['seriesnumber']);
        $this->assertSame(['9788490000014', '9788490000021'], $d['extra']['isbns']);
    }

    /**
     * A UNIMARC record (France, Portugal, Italy): its own fields, the same description; a MARC21 one stays as it was.
     */
    public function test_unimarc(): void {
        $record = (new record('     cam  22        450 '))
            ->data('010', ' ', ' ', [['a', '978-2-07-061275-8'], ['b', 'br.']])
            ->data('101', '0', ' ', [['a', 'fre']])
            ->data('200', '1', ' ', [['a', 'Le petit prince'], ['b', 'Texte imprimé'], ['e', 'roman'],
                ['f', 'Antoine de Saint-Exupéry']])
            ->data('205', ' ', ' ', [['a', '2e éd.']])
            ->data('210', ' ', ' ', [['a', '[Paris]'], ['c', 'Gallimard'], ['d', 'DL 2007']])
            ->data('215', ' ', ' ', [['a', '97 p.'], ['c', 'ill. en coul.'], ['d', '18 cm']])
            ->data('225', '2', ' ', [['a', 'Folio junior'], ['v', '100']])
            ->data('330', ' ', ' ', [['a', 'Un aviateur rencontre un petit prince.']])
            ->data('606', ' ', ' ', [['a', 'Contes']])
            ->data('676', ' ', ' ', [['a', '843.912']])
            ->data('700', ' ', '1', [['a', 'Saint-Exupéry,'], ['b', 'Antoine de'], ['f', '1900-1944']])
            ->data('702', ' ', '1', [['a', 'Dupont'], ['b', 'Marie']]);
        $this->assertTrue(describer::is_unimarc($record));
        $d = describer::describe($record);
        $this->assertSame('book', $d['record']['doctype']);
        $this->assertSame('Le petit prince', $d['record']['title']);
        $this->assertSame('roman', $d['record']['subtitle']);
        $this->assertSame('9782070612758', $d['record']['isbn']);
        $this->assertSame('2007', $d['record']['pubyear']);
        $this->assertSame('fre', $d['record']['lang']);
        $this->assertSame('97 p.', $d['record']['extent']);
        $this->assertSame(['Saint-Exupéry, Antoine de', 'Dupont, Marie'], $d['authorities']['author']);
        $this->assertSame(['Gallimard'], $d['authorities']['publisher']);
        $this->assertSame(['843.912'], $d['authorities']['class']);
        $this->assertSame(['Contes'], $d['authorities']['subject']);
        $this->assertSame(['Folio junior'], $d['authorities']['collection']);
        $this->assertSame('ddc', $d['extra']['classscheme']);
        $this->assertSame('unimarc', $d['extra']['format']);
        $twentyone = (new record())->data('245', '1', '0', [['a', 'El faro dormido']])->data('200', ' ', ' ', [['a', 'x']]);
        $this->assertFalse(describer::is_unimarc($twentyone));
        $this->assertSame('El faro dormido', describer::describe($twentyone)['record']['title']);
    }

    /**
     * Kinds of document from the leader.
     */
    public function test_doctype(): void {
        $this->assertSame('book', describer::doctype('00000nam a2200000 a 4500'));
        $this->assertSame('periodical', describer::doctype('00000nas a2200000 a 4500'));
        $this->assertSame('audio', describer::doctype('00000njm a2200000 a 4500'));
        $this->assertSame('video', describer::doctype('00000ngm a2200000 a 4500'));
        $this->assertSame('device', describer::doctype('00000nrm a2200000 a 4500'));
    }

    /**
     * Cleaning keeps the full stop of initials and abbreviations.
     */
    public function test_clean(): void {
        $this->assertSame('Tolkien, J. R. R.', describer::clean('Tolkien, J. R. R.'));
        $this->assertSame('120 p.', describer::clean('120 p. ;'));
        $this->assertSame('Madrid', describer::clean('Madrid :'));
        $this->assertSame('2015?', describer::clean('[2015?]'));
        $this->assertNull(describer::clean(' / '));
    }

    /**
     * The volumes of a work are told apart by the number and name of their part; without a title in $a, another one.
     */
    public function test_title_with_its_part(): void {
        $record = new record();
        $record->data('245', '1', '0', [['a', 'Harry Potter.'], ['n', 'Vol. 3,'], ['p', 'El prisionero de Azkaban.']]);
        $this->assertSame('Harry Potter. Vol. 3, El prisionero de Azkaban', describer::describe($record)['record']['title']);
        $qualified = new record();
        $qualified->data('020', ' ', ' ', [['a', '9788420482859(tapa flexible)']]);
        $qualified->data('245', '0', '0', [['a', 'Con calificador']]);
        $this->assertSame('9788420482859', describer::describe($qualified)['record']['isbn']);
        $other = new record();
        $other->data('245', '0', '0', [['b', 'cuentos del valle']]);
        $this->assertSame('cuentos del valle', describer::describe($other)['record']['title']);
    }

    /**
     * A file read a record at a time gives what reading it whole gives, across the pieces it is read in.
     */
    public function test_stream(): void {
        $records = [];
        for ($i = 0; $i < 3000; $i++) {
            $record = new record();
            $record->control('001', 'z' . $i);
            $record->data('245', '0', '0', [['a', 'Libro número ' . $i . ' ' . str_repeat('ñandú ', 60)]]);
            $records[] = iso2709::write($record);
        }
        $path = make_request_directory() . '/grande.mrc';
        file_put_contents($path, implode('', $records));
        $this->assertGreaterThan(1024 * 1024, filesize($path));
        $whole = iso2709::read((string) file_get_contents($path), 'utf8');
        $streamed = iterator_to_array(iso2709::stream($path, 'utf8'), false);
        $this->assertCount(3000, $streamed);
        $this->assertSame(
            array_map(fn($r) => $r->control_value('001'), $whole),
            array_map(fn($r) => $r->control_value('001'), $streamed)
        );
        $this->assertSame($whole[2999]->first(['245'], 'a'), $streamed[2999]->first(['245'], 'a'));
        // And MARCXML, record by record.
        $xmlpath = make_request_directory() . '/grande.xml';
        file_put_contents($xmlpath, marcxml::write(array_slice($whole, 0, 50)));
        $fromxml = iterator_to_array(marcxml::stream($xmlpath), false);
        $this->assertCount(50, $fromxml);
        $this->assertSame('z49', $fromxml[49]->control_value('001'));
    }
}
