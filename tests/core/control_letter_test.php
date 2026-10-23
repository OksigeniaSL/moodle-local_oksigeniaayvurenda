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

namespace local_oksigeniaayvurenda\core;

/**
 * Tests for the core class control_letter.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\control_letter
 */
#[\PHPUnit\Framework\Attributes\CoversClass(control_letter::class)]
final class control_letter_test extends \basic_testcase {
    /**
     * The letter is the remainder of dividing by 23, as in the Spanish identity number.
     */
    public function test_letter(): void {
        $this->assertSame('Z', control_letter::for_number('12345678'));
        $this->assertSame('T', control_letter::for_number('0'));
        $this->assertSame('100000' . control_letter::for_number('100000'), control_letter::append('100000'));
    }

    /**
     * Long numbers work too, digit by digit.
     */
    public function test_long_number(): void {
        $this->assertSame('W', control_letter::for_number('123456789012345678901'));
    }

    /**
     * A code is valid only with its own letter, in any case and with spaces around.
     */
    public function test_valid(): void {
        $this->assertTrue(control_letter::is_valid('12345678Z'));
        $this->assertTrue(control_letter::is_valid(' 12345678z '));
        $this->assertFalse(control_letter::is_valid('12345678A'));
        $this->assertFalse(control_letter::is_valid('12345678'));
        $this->assertFalse(control_letter::is_valid('ABC'));
    }

    /**
     * Only digits get a letter.
     */
    public function test_not_digits(): void {
        $this->expectException(\InvalidArgumentException::class);
        control_letter::for_number('12A');
    }
}
