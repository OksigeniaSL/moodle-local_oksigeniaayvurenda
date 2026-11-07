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
 * Tests for the copies a MARC record carries.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\marc\holdings
 */
#[\PHPUnit\Framework\Attributes\CoversClass(holdings::class)]
final class holdings_test extends \basic_testcase {
    /**
     * Koha's 952 and the standard 852 give the same copies; the record is known by its source and number.
     */
    public function test_items_and_id(): void {
        $records = marcxml::read(file_get_contents(__DIR__ . '/../../fixtures/marc/programa.xml'));
        $this->assertSame('BibliotecaInventada:5001', holdings::id($records[0]));
        $items = holdings::items($records[0]);
        $this->assertCount(2, $items);
        $this->assertSame(['barcode' => 'K1001', 'callnumber' => ['N', 'DUN', 'jar'], 'location' => 'Narrativa',
            'itemtype' => 'BK', 'acquired' => '2024-03-01', 'notes' => '', 'regnumber' => ''], $items[0]);
        $this->assertSame('', $items[1]['barcode']);
        $this->assertSame([['barcode' => 'K1002', 'callnumber' => ['91', 'LAG', 'atl'], 'location' => 'Consulta',
            'itemtype' => '', 'acquired' => '', 'notes' => '', 'regnumber' => '']], holdings::items($records[1]));
        $none = new record();
        $none->data('035', ' ', ' ', [['a', '(OCoLC)123']]);
        $this->assertSame('(OCoLC)123', holdings::id($none));
        $this->assertSame('', holdings::id(new record()));
    }

    /**
     * The copies of other programs: 949, 999 and 995, only when 852 and 952 bring none.
     */
    public function test_other_programs(): void {
        $sierra = new record();
        $sierra->data('949', ' ', '1', [['a', '813 ROM fa'], ['i', '31000000123'], ['l', 'Adultos'], ['t', 'BOOK']]);
        $copy = holdings::items($sierra)[0];
        $this->assertSame('31000000123', $copy['barcode']);
        $this->assertSame(['813', 'ROM', 'fa'], $copy['callnumber']);
        $this->assertSame('Adultos', $copy['location']);
        $this->assertSame('BOOK', $copy['itemtype']);

        $symphony = new record();
        $symphony->data('999', ' ', ' ', [['a', '823 DUN'], ['i', '777'], ['l', 'STACKS'], ['t', 'BOOK']]);
        $this->assertSame('777', holdings::items($symphony)[0]['barcode']);

        $unimarc = new record();
        $unimarc->data('995', ' ', ' ', [['f', 'F100'], ['k', '843 LAU'], ['e', 'Salle'], ['r', 'LIVRE'], ['j', '42']]);
        $copy = holdings::items($unimarc)[0];
        $this->assertSame('F100', $copy['barcode']);
        $this->assertSame('Salle', $copy['location']);
        $this->assertSame('42', $copy['regnumber']);

        // An empty local field is no copy, and when 952 says, the others say nothing.
        $both = new record();
        $both->data('952', ' ', ' ', [['p', 'K1'], ['i', '9']]);
        $both->data('999', ' ', ' ', [['a', 'Local data']]);
        $this->assertCount(1, holdings::items($both));
        $this->assertSame('9', holdings::items($both)[0]['regnumber']);
        $empty = new record();
        $empty->data('949', ' ', ' ', [['c', '1']]);
        $this->assertSame([], holdings::items($empty));
    }

    /**
     * What goes to Koha comes back the same: the copies in 952, with the codes Koha asks for.
     */
    public function test_to_koha_and_back(): void {
        $marc = encoder::encode(['title' => 'El faro dormido', 'doctype' => 'book'], [], [['barcode' => 'A1', 'regnumber' => '77',
            'library' => 'Biblioteca Peña', 'location' => 'Sala', 'callnumber' => ['821', 'ROM', 'fa'], 'acquired' => '2024-05-02',
            'itemtype' => 'normal']], '', true);
        $this->assertSame([], $marc->data_fields('852'));
        $this->assertSame('BK', $marc->first(['942'], 'c'));
        $this->assertSame('BIBLIOTECA', $marc->first(['952'], 'a'));
        $item = holdings::items($marc)[0];
        $this->assertSame('A1', $item['barcode']);
        $this->assertSame(['821', 'ROM', 'fa'], $item['callnumber']);
        $this->assertSame('77', $item['regnumber']);
        $this->assertSame('2024-05-02', $item['acquired']);
    }

    /**
     * An 852 split as the standard has it, the class in $h and the part of the copy in $i: the whole call number.
     */
    public function test_852_with_its_parts(): void {
        $record = new record();
        $record->data('852', ' ', ' ', [['h', '821.134.2'], ['i', 'GAR cie'], ['p', 'B77']]);
        $this->assertSame(['821.134.2', 'GAR', 'cie'], holdings::items($record)[0]['callnumber']);
    }
}
