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
 * Tests for the core class isbn.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\isbn
 */
#[\PHPUnit\Framework\Attributes\CoversClass(isbn::class)]
final class isbn_test extends \basic_testcase {
    /**
     * ISBN-10 and ISBN-13, with or without hyphens, become the ISBN-13 in digits.
     */
    public function test_normalise(): void {
        $this->assertSame('9780306406157', isbn::normalise('0-306-40615-2'));
        $this->assertSame('9780306406157', isbn::normalise('978-0-306-40615-7'));
        $this->assertSame('9780306406157', isbn::normalise(' 9780306406157 '));
        $this->assertSame('9780804429573', isbn::normalise('0-8044-2957-X'));
    }

    /**
     * A wrong check digit is not an ISBN.
     */
    public function test_invalid(): void {
        $this->assertNull(isbn::normalise('0-306-40615-3'));
        $this->assertNull(isbn::normalise('9780306406158'));
        $this->assertNull(isbn::normalise('12345'));
        $this->assertFalse(isbn::is_valid(''));
    }

    /**
     * A scanned EAN-13 is a book when it starts with 978 or 979 and its check digit is right.
     */
    public function test_book_ean(): void {
        $this->assertTrue(isbn::is_book_ean('9780306406157'));
        $this->assertFalse(isbn::is_book_ean('0306406152'));
        $this->assertFalse(isbn::is_book_ean('4006381333931'));
    }
}
