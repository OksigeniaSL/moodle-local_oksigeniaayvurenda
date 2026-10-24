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

namespace local_oksigeniaayvurenda\core\barcode;

/**
 * Tests for Code 39 barcodes.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\barcode\code39
 */
#[\PHPUnit\Framework\Attributes\CoversClass(code39::class)]
final class code39_test extends \basic_testcase {
    /**
     * Each character is nine elements, three of them wide, between asterisks; small letters are drawn as capitals.
     */
    public function test_widths(): void {
        $widths = code39::widths('10000h');
        // Eight characters with the asterisks, nine elements each and a gap between them.
        $this->assertCount(8 * 9 + 7, $widths);
        $this->assertSame([1, 3, 1, 1, 3, 1, 3, 1, 1], array_slice($widths, 0, 9));
        $this->assertSame(8 * (3 * 3 + 6) + 7, array_sum($widths));
        $this->assertTrue(code39::can('100042-S'));
        $this->assertFalse(code39::can('ñandú'));
        $this->assertFalse(code39::can('A*B'));
        $svg = code39::svg('10000H');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('>10000H</text>', $svg);
        $this->assertSame(40, substr_count($svg, '<rect') - 1);
        $this->expectException(\InvalidArgumentException::class);
        code39::widths('');
    }
}
