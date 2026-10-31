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
 * Tests for the order of groups and stages.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\group_order
 */
#[\PHPUnit\Framework\Attributes\CoversClass(group_order::class)]
final class group_order_test extends \basic_testcase {
    /**
     * Groups go by stage, year and letter, and stages in their place; what has no stage goes last.
     */
    public function test_order(): void {
        $groups = ['2º Bachillerato A', '1º Infantil A', '1º Bachillerato A', '3º Primaria B', '1º Primaria A',
            '10º Primaria', '1º ESO A', '2º Primaria A', 'Aula de música'];
        usort($groups, fn($a, $b) => group_order::compare($a, $b));
        $this->assertSame(['1º Infantil A', '1º Primaria A', '2º Primaria A', '3º Primaria B', '10º Primaria', '1º ESO A',
            '1º Bachillerato A', '2º Bachillerato A', 'Aula de música'], $groups);
        $this->assertSame(
            ['Infantil', 'Primaria', 'Secundaria', 'Bachillerato', 'FP'],
            group_order::stages(['Secundaria', 'Bachillerato', 'Infantil', 'FP', 'Primaria'])
        );
        // The stage of the record wins over the name.
        $this->assertSame(-1, group_order::compare('Grupo B', 'Grupo A', 'Primaria', 'Secundaria'));
    }
}
