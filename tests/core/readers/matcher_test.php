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
 * Tests for matching readers with accounts by name.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\matcher
 */
#[\PHPUnit\Framework\Attributes\CoversClass(matcher::class)]
final class matcher_test extends \basic_testcase {
    /**
     * The same words in the same order match by themselves; another order, several, close or with another group are
     * in doubt; the rest, not there.
     */
    public function test_piles(): void {
        $readers = [
            ['id' => 1, 'name' => 'Ana María Montes Prado', 'group' => '3º A'],
            ['id' => 2, 'name' => 'Leo Prado Valle'],
            ['id' => 3, 'name' => 'Noa Ríos'],
            ['id' => 4, 'name' => 'Marta Lago Sauce'],
            ['id' => 5, 'name' => 'Hugo Olmo'],
            ['id' => 6, 'name' => 'Iker Duna Faro', 'group' => '4º B'],
            ['id' => 7, 'name' => 'Adrián Montes Lago'],
        ];
        $accounts = [
            ['id' => 10, 'name' => 'Ana María Montes Prado', 'group' => '3º A'],
            ['id' => 11, 'name' => 'Leo Prado'],
            ['id' => 12, 'name' => 'Marta Lago Sauce'],
            ['id' => 13, 'name' => 'Marta Lago Sauce'],
            ['id' => 14, 'name' => 'Hugo Olmos'],
            ['id' => 15, 'name' => 'Iker Duna Faro', 'group' => '1º A'],
            ['id' => 16, 'name' => 'Adrián Lago Montes'],
        ];
        $piles = matcher::piles($readers, $accounts);
        $this->assertSame([1 => 10], $piles['sure']);
        $this->assertSame([11], $piles['doubt'][2]);
        $this->assertSame([12, 13], $piles['doubt'][4]);
        $this->assertSame([14], $piles['doubt'][5]);
        $this->assertSame([15], $piles['doubt'][6]);
        $this->assertSame([16], $piles['doubt'][7]);
        $this->assertSame([3], $piles['none']);
    }

    /**
     * With given names and surnames apart, only who could be the same person is a candidate: never a shared first name and
     * one surname in another place, never a different first name; a surname less or a compound first name still are.
     */
    public function test_given_names_and_surnames(): void {
        $p = fn(int $id, string $first, string $last) => ['id' => $id, 'name' => $first . ' ' . $last, 'first' => $first,
            'last' => $last];
        $accounts = [
            $p(1, 'Hugo', 'Díaz Afonso'),
            $p(2, 'Carla', 'González Trujillo'),
            $p(3, 'Adrián', 'Rivero Díaz'),
            $p(4, 'Thiago', 'Herrera'),
            $p(5, 'Noa', 'Castro Medina'),
            $p(6, 'María', 'Olmos Lago'),
            $p(7, 'Lucía', 'Rodríguez Mesa'),
        ];
        $readers = [
            $p(10, 'Hugo', 'Afonso Bencomo'),
            $p(11, 'Carla', 'Alonso González'),
            $p(12, 'Adriana', 'Báez Díaz'),
            $p(13, 'Thiago', 'Brito Herrera'),
            $p(14, 'Mario', 'Castro Noda'),
            $p(15, 'María José', 'Olmo Lago'),
            $p(16, 'Manuel', 'Díaz Afonso'),
            $p(17, 'Lucía', 'Mesa Rodríguez'),
        ];
        $piles = matcher::piles($readers, $accounts);
        $this->assertSame([13 => [4], 15 => [6], 17 => [7]], $piles[matcher::DOUBT]);
        $this->assertSame([10, 11, 12, 14, 16], $piles[matcher::NONE]);
    }
}
