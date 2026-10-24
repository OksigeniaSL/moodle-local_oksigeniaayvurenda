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
 * Tests for which card a reader sees.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\card_look
 */
#[\PHPUnit\Framework\Attributes\CoversClass(card_look::class)]
final class card_look_test extends \basic_testcase {
    /**
     * The flower for the youngest, the sky from twelve (lively, then sober), none for teachers and families; the
     * centre's choice first; the reader's own choice from twelve on, never against the flower.
     */
    public function test_guess_and_resolve(): void {
        $this->assertSame('flower', card_look::guess('student', 'Infantil', '2º Infantil A'));
        $this->assertSame('flower', card_look::guess('student', '', 'PRI 3º B'));
        $this->assertSame('sky', card_look::guess('student', 'Secundaria', 'ESO 1º A'));
        $this->assertSame('sky', card_look::guess('student', '', '2º ESO C'));
        $this->assertSame('skysober', card_look::guess('student', 'ESO', '3º ESO B'));
        $this->assertSame('skysober', card_look::guess('student', 'Bachillerato', '1º Bachillerato A'));
        $this->assertSame('skysober', card_look::guess('student', 'Grado en Historia', ''));
        $this->assertSame('sky', card_look::guess('student', 'Year', 'Year 5 Blue'));
        $this->assertSame('none', card_look::guess('teacher', '', ''));
        $this->assertSame('none', card_look::guess('family', 'Primaria', ''));

        $this->assertSame('sky', card_look::resolve('flower', 'sky', null), 'the centre decides by stage');
        $this->assertSame('flower', card_look::resolve('flower', null, 'none'), 'the youngest do not choose');
        $this->assertSame('classic', card_look::resolve('skysober', null, 'classic'));
        $this->assertSame('sky', card_look::resolve('sky', null, 'sky'));
        $this->assertSame('skysober', card_look::resolve('none', null, 'sky'), 'a teacher who turns it on');
        $this->assertSame('none', card_look::resolve('sky', 'nonsense', 'none'));
        $this->assertSame('sky', card_look::resolve('sky', null, 'nonsense'));
    }
}
