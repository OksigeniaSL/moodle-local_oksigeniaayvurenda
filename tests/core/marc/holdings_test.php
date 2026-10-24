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
            'itemtype' => 'BK', 'acquired' => '2024-03-01', 'notes' => ''], $items[0]);
        $this->assertSame('', $items[1]['barcode']);
        $this->assertSame([['barcode' => 'K1002', 'callnumber' => ['91', 'LAG', 'atl'], 'location' => 'Consulta',
            'itemtype' => '', 'acquired' => '', 'notes' => '']], holdings::items($records[1]));
        $none = new record();
        $none->data('035', ' ', ' ', [['a', '(OCoLC)123']]);
        $this->assertSame('(OCoLC)123', holdings::id($none));
        $this->assertSame('', holdings::id(new record()));
    }
}
