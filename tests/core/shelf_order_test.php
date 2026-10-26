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
 * Tests for the order of the shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\shelf_order
 */
#[\PHPUnit\Framework\Attributes\CoversClass(shelf_order::class)]
final class shelf_order_test extends \basic_testcase {
    /**
     * Classes in the order the UDC files them: a shorter number first, its auxiliaries (place, then hyphen) before the
     * point zero and the longer numbers; letters after numbers; copies without a class at the end.
     */
    public function test_udc_filing(): void {
        $expected = ['0', '8', '82', '82(460)', '82-3', '82-36', '82.0', '821', '821.134.2', '821.134.2-3', '9', 'N', ''];
        $shuffled = ['821.134.2-3', '', '82.0', 'N', '82-36', '8', '9', '82', '821', '82(460)', '0', '82-3', '821.134.2'];
        $keys = [];
        foreach ($shuffled as $i => $class) {
            $keys[shelf_order::key($class, 'AAA', 'aaa', 'Un libro', $i + 1)] = $class;
        }
        ksort($keys, SORT_STRING);
        $this->assertSame($expected, array_values($keys));
    }

    /**
     * With the same class, the author's letters; then the title's, then the title without its article.
     */
    public function test_author_and_title(): void {
        $books = [
            ['82-3', 'DAH', 'cha', 'Charlie y la fábrica de chocolate', 1],
            ['82-3', 'ALC', 'mar', 'Marianela', 2],
            ['82-3', 'DAH', 'bru', 'Las brujas', 3],
            ['82-3', 'MON', 'far', 'El faro dormido', 4],
            ['82-3', 'MON', 'far', 'Faro de invierno', 5],
            ['82-3', 'MON', 'far', 'El faro azul', 6],
        ];
        $keys = [];
        foreach ($books as $book) {
            $keys[shelf_order::key(...$book)] = $book[4];
        }
        ksort($keys, SORT_STRING);
        $this->assertSame([2, 3, 1, 6, 5, 4], array_values($keys));
    }

    /**
     * Keys are made only of digits and small letters, never longer than the field, whatever comes.
     */
    public function test_shape(): void {
        $long = str_repeat('821.134.2(460)"19"-3 ', 20);
        $books = [
            ['Ñandú', 'ÁÉÍ', 'çñü', '¿Qué? ¡Sí! — «Óscar»', 7],
            [$long, $long, $long, $long, 99999999],
            [null, null, null, '', 0],
        ];
        foreach ($books as $book) {
            $key = shelf_order::key(...$book);
            $this->assertMatchesRegularExpression('/^[0-9a-z]+$/', $key);
            $this->assertLessThanOrEqual(shelf_order::LENGTH, strlen($key));
        }
        $this->assertSame(shelf_order::key('82', 'NAN', 'que', 'Ñandú', 1), shelf_order::key('82', 'NAN', 'que', 'Nandu', 1));
    }
}
