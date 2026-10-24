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

namespace local_oksigeniaayvurenda\core\search;

/**
 * Tests for the suggestions of a search that found nothing.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\search\suggester
 */
#[\PHPUnit\Framework\Attributes\CoversClass(suggester::class)]
final class suggester_test extends \basic_testcase {
    /**
     * A small catalogue.
     *
     * @return suggester
     */
    private function suggester(): suggester {
        return new suggester(suggester::vocabulary([
            'el volcan dormido', 'la jirafa azul', 'el dragon olvidado', 'la isla del tesoro', 'el tesoro escondido',
            'dragones y mazmorras', 'el dragon de la isla',
        ]));
    }

    /**
     * The vocabulary counts the records that have each word, once per record; words that say nothing about a book
     * («el», «de») are not in it, so they are never suggested.
     */
    public function test_vocabulary(): void {
        $vocabulary = suggester::vocabulary(['el faro el faro', 'el lobo de mar', 'a']);
        $this->assertArrayNotHasKey('el', $vocabulary);
        $this->assertArrayNotHasKey('de', $vocabulary);
        $this->assertSame(1, $vocabulary['lobo']);
        $this->assertSame(1, $vocabulary['faro']);
        $this->assertArrayNotHasKey('a', $vocabulary);
    }

    /**
     * Words that sound the same, a letter away or the start of a word give a suggestion.
     */
    public function test_suggest(): void {
        $suggester = $this->suggester();
        $this->assertSame(['volcan'], $suggester->suggest('bolcan'));
        $this->assertSame(['jirafa'], $suggester->suggest('girafa'));
        $this->assertSame(['dragon'], array_slice($suggester->suggest('drgon'), 0, 1));
        $this->assertSame(['tesoro isla'], $suggester->suggest('tesorro isla'));
    }

    /**
     * Nothing to suggest when the words are already there, or when nothing is close.
     */
    public function test_nothing(): void {
        $suggester = $this->suggester();
        $this->assertSame([], $suggester->suggest('dragon'));
        $this->assertSame([], $suggester->suggest('xyz'));
        $this->assertSame([], $suggester->suggest('  '));
    }
}
