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
 * Tests for writing the catalogue in MARC21.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\marc\encoder
 * @covers     \local_oksigeniaayvurenda\core\marc\iso2709
 */
#[\PHPUnit\Framework\Attributes\CoversClass(encoder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(iso2709::class)]
final class export_test extends \basic_testcase {
    /**
     * A made-up record of the catalogue.
     *
     * @return array [record, authorities, items]
     */
    private function book(): array {
        return [
            ['id' => 42, 'doctype' => 'book', 'title' => 'El faro dormido', 'subtitle' => 'una historia de mar',
                'responsibility' => 'Irene Laurel', 'isbn' => '9788490000014', 'edition' => 'Primera edición',
                'pubplace' => 'Villanube', 'pubyear' => '2024', 'lang' => 'spa', 'extent' => '96 p.',
                'summary' => 'Un faro que se queda dormido.', 'notes' => "Inventado.\nPara las pruebas.",
                'extra' => json_encode(['classscheme' => 'udc', 'dimensions' => '22 cm', 'seriesnumber' => '12']),
                'timecreated' => 1767225600, 'timemodified' => 1767225600],
            ['author' => ['Laurel Prado, Irene', 'Olmo, Bruno'], 'publisher' => ['Editorial Brújula'],
                'class' => ['821.134.2-31'], 'subject' => ['Faros', 'Mar'], 'collection' => ['Colección Linterna'],
                'audience' => ['A partir de 8 años']],
            [['barcode' => '100042S', 'library' => 'Central', 'location' => 'Narrativa', 'callnumber' => ['N', 'LAU', 'far']]],
        ];
    }

    /**
     * What is written comes back the same through the describer: the round trip of the catalogue.
     */
    public function test_round_trip(): void {
        [$record, $authorities, $items] = $this->book();
        $marc = encoder::encode($record, $authorities, $items, 'Escuela inventada');
        $this->assertSame('am', substr($marc->leader, 6, 2));
        $this->assertSame('ayv-42', $marc->control_value('001'));
        $this->assertSame('3', $marc->data_fields('245')[0]['ind2']);
        $this->assertSame('El faro dormido :', $marc->first(['245'], 'a'));
        $this->assertSame(['N LAU far'], $marc->values(['852'], 'h'));
        $this->assertSame(['100042S'], $marc->values(['852'], 'p'));

        foreach ([iso2709::read(iso2709::write($marc))[0], marcxml::read(marcxml::write([$marc]))[0]] as $again) {
            $d = describer::describe($again);
            $this->assertSame('El faro dormido', $d['record']['title']);
            $this->assertSame('una historia de mar', $d['record']['subtitle']);
            $this->assertSame('Irene Laurel', $d['record']['responsibility']);
            $this->assertSame('9788490000014', $d['record']['isbn']);
            $this->assertSame('Primera edición', $d['record']['edition']);
            $this->assertSame('2024', $d['record']['pubyear']);
            $this->assertSame('spa', $d['record']['lang']);
            $this->assertSame('96 p.', $d['record']['extent']);
            $this->assertSame("Inventado.\nPara las pruebas.", $d['record']['notes']);
            $this->assertSame($authorities['author'], $d['authorities']['author']);
            $this->assertSame($authorities['publisher'], $d['authorities']['publisher']);
            $this->assertSame($authorities['class'], $d['authorities']['class']);
            $this->assertSame($authorities['subject'], $d['authorities']['subject']);
            $this->assertSame($authorities['collection'], $d['authorities']['collection']);
            $this->assertSame($authorities['audience'], $d['authorities']['audience']);
            $this->assertSame('12', $d['extra']['seriesnumber']);
        }
    }

    /**
     * ISO 2709 counts bytes, not letters, and reads records in ISO 8859-1 too.
     */
    public function test_iso2709(): void {
        [$record, $authorities] = $this->book();
        $data = iso2709::write(encoder::encode($record, $authorities)) . iso2709::write(encoder::encode(
            ['id' => 7, 'doctype' => 'device', 'title' => 'Calculadora científica', 'extra' => ['brand' => 'Marca']],
            []
        ));
        $this->assertSame((int) substr($data, 0, 5), strpos($data, "\x1D") + 1);
        $records = iso2709::read($data);
        $this->assertCount(2, $records);
        $this->assertSame('r', $records[1]->leader[6]);
        $this->assertSame('device', describer::describe($records[1])['record']['doctype']);
        $this->assertSame(['Marca'], $records[1]->values(['950'], 'a'));

        $latin = new record('00000nam  2200000 a 4500');
        $latin->data('245', '0', '0', [['a', 'Canción']]);
        $raw = iso2709::write($latin);
        // The same record as an old program would write it: ISO 8859-1, and position 9 empty.
        $raw = substr_replace($raw, ' ', 9, 1);
        $raw = str_replace('Canción', mb_convert_encoding('Canción', 'ISO-8859-1', 'UTF-8'), $raw);
        $this->assertSame('Canción', iso2709::read($raw)[0]->first(['245'], 'a'));
    }
}
