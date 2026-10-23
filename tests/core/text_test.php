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
 * Tests for the core class text.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\text
 */
#[\PHPUnit\Framework\Attributes\CoversClass(text::class)]
final class text_test extends \basic_testcase {
    /**
     * Without accents, in lower case, with single spaces and no punctuation.
     */
    public function test_normalise(): void {
        $this->assertSame('arbol de la nandu', text::normalise('  Árbol, de la ÑANDÚ! '));
        $this->assertSame('el ultimo dragon 2', text::normalise('El último dragón (2)'));
    }

    /**
     * Every word of the query has to be there, in any order, as the start of a word.
     */
    public function test_matches(): void {
        $searchtext = text::normalise('El faro olvidado Montes, Ana Aventuras');
        $this->assertTrue(text::matches($searchtext, 'faro'));
        $this->assertTrue(text::matches($searchtext, 'Olvid FARO'));
        $this->assertTrue(text::matches($searchtext, 'aventuras ana'));
        $this->assertFalse(text::matches($searchtext, 'faro dragon'));
        $this->assertFalse(text::matches($searchtext, 'aro'));
        $this->assertTrue(text::matches($searchtext, '   '));
    }
}
