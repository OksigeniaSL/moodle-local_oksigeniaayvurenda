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
 * Tests for the proposed call number.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\callnumber
 */
#[\PHPUnit\Framework\Attributes\CoversClass(callnumber::class)]
final class callnumber_test extends \basic_testcase {
    /**
     * The class, three capitals of the surname and three letters of the title after an article.
     */
    public function test_propose(): void {
        $this->assertSame(
            ['821.134.2-31', 'LAU', 'far'],
            callnumber::propose('821.134.2-31', 'Laurel Prado, Irene', 'El faro dormido')
        );
        $this->assertSame(['', 'OLM', 'nub'], callnumber::propose('', 'Bruno Olmo', 'La nube de tinta'));
        $this->assertSame(['', 'ACE', 'arb'], callnumber::propose('', 'Ácebo, Lía', 'Árbol'));
        $this->assertSame(['N', '', 'el'], callnumber::propose(' N ', '', 'El'));
    }
}
