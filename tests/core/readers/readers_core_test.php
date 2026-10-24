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

namespace local_oksigeniaayvurenda\core\readers;

/**
 * Tests for the guess of a stage and the codes of new cards.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\stage_guesser
 * @covers     \local_oksigeniaayvurenda\core\readers\card_codes
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stage_guesser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(card_codes::class)]
final class readers_core_test extends \basic_testcase {
    /**
     * The stage is the first real word of the group's name, whatever the order.
     */
    public function test_stage(): void {
        $this->assertSame('ESO', stage_guesser::guess('ESO 3º C'));
        $this->assertSame('INF', stage_guesser::guess('INF 2ºA'));
        $this->assertSame('Primaria', stage_guesser::guess('3º de Primaria A'));
        $this->assertSame('Year', stage_guesser::guess('Year 5 Blue'));
        // Groups named by the children's age: no stage to guess.
        $this->assertSame('', stage_guesser::guess('2 años La Casita'));
        $this->assertSame('', stage_guesser::guess('18 months Robins'));
        $this->assertSame('Claustro', stage_guesser::guess('Claustro de Profesores'));
        $this->assertSame('', stage_guesser::guess('3º A'));
    }

    /**
     * New codes follow the highest one, with as many digits and the control letter when there is one.
     */
    public function test_card_codes(): void {
        $this->assertSame(['10001L', '10002C'], card_codes::next(['10000H', 'ABC', '00042'], 2, true));
        $this->assertSame(['00043'], card_codes::next(['00042'], 1, false));
        $this->assertSame(['00001'], card_codes::next([], 1, false));
        $this->assertSame([], card_codes::next(['1'], 0, true));
    }
}
