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

namespace local_oksigeniaayvurenda\local;

/**
 * The card on paper.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cards
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cards::class)]
final class cards_print_test extends \advanced_testcase {
    /**
     * On paper, everyone has the first day: the same drawing for all, by the skin and the reader's look; none in a sober skin.
     */
    public function test_on_paper(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'readertype' => 'student', 'cardcode' => '10000H']);
        $card = cards::printed($reader, 'Ana Montes', '3º A', 'Biblioteca', 'university');
        $this->assertSame('plain', $card['look']);
        $this->assertSame('', $card['art']);
        $this->assertSame('10000H', $card['code']);
        $this->assertStringStartsWith('<svg', $card['qr']);
        $this->assertStringContainsString('<svg', $card['barcode']);
        // A narrow bar of 0.45 mm, whatever the page: its width is the modules that make it up.
        $this->assertGreaterThan(40, (float) $card['barwidth']);
        $this->assertLessThanOrEqual(76.6, (float) $card['barwidth']);

        $this->assertSame('', cards::printed($reader, str_repeat('Ana ', 3), '', 'B', 'company')['namesize']);
        $this->assertSame('long', cards::printed($reader, str_repeat('Ana ', 6), '', 'B', 'company')['namesize']);
        $this->assertSame('verylong', cards::printed($reader, str_repeat('Ana ', 10), '', 'B', 'company')['namesize']);

        // A code too long for the bars keeps the QR and its letters.
        $long = clone $reader;
        $long->cardcode = str_repeat('A1', 16);
        $card = cards::printed($long, 'Ana', '', 'B', 'public');
        $this->assertSame('', $card['barcode']);
        $this->assertStringStartsWith('<svg', $card['qr']);
    }
}
