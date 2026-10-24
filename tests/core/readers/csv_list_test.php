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
 * Tests for lists of readers in CSV.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\csv_list
 */
#[\PHPUnit\Framework\Attributes\CoversClass(csv_list::class)]
final class csv_list_test extends \basic_testcase {
    /**
     * The separator is found, the headings are understood in Spanish and English, Latin 1 is read, a full name is
     * split, and rows without a name are left out.
     */
    public function test_read_guess_readers(): void {
        $data = "\xEF\xBB\xBFNº expediente;Apellidos;Nombre;Curso y grupo;Etapa;Correo electrónico\n" .
            "1001;Montes Prado;Ana;3º A;Primaria;ana@example.invalid\n" .
            "1002;Faro;Leo;3º A;Primaria;\n;;;;;\n";
        $list = csv_list::read($data);
        $this->assertCount(6, $list['columns']);
        $this->assertCount(3, $list['rows']);
        $map = csv_list::guess($list['columns']);
        ksort($map);
        $this->assertSame(
            [0 => 'idnumber', 1 => 'lastname', 2 => 'firstname', 3 => 'group', 4 => 'stage', 5 => 'email'],
            $map,
            'every column understood'
        );
        $readers = csv_list::readers($list['rows'], $map);
        $this->assertCount(2, $readers);
        // The same fields and values, whatever their order.
        $this->assertEquals(['idnumber' => '1001', 'lastname' => 'Montes Prado', 'firstname' => 'Ana', 'group' => '3º A',
            'stage' => 'Primaria', 'email' => 'ana@example.invalid'], $readers[0]);

        $latin = mb_convert_encoding("Alumno,Grupo\n\"Ríos Lago, Noa\",5º B\nHugo Olmo,5º B\n", 'Windows-1252', 'UTF-8');
        $list = csv_list::read($latin);
        $readers = csv_list::readers($list['rows'], csv_list::guess($list['columns']));
        $this->assertEquals(['group' => '5º B', 'firstname' => 'Noa', 'lastname' => 'Ríos Lago'], $readers[0]);
        $this->assertEquals(['group' => '5º B', 'firstname' => 'Hugo', 'lastname' => 'Olmo'], $readers[1]);

        $tabs = csv_list::read("name\tsurname\tclass\nAna\tMontes\t3A\n");
        $this->assertSame(['firstname', 'lastname', 'group'], array_values(csv_list::guess($tabs['columns'])));
    }
}
