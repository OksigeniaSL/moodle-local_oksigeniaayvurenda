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

/**
 * The people of a Cifra list, as readers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

/**
 * Cifra, a school management program, gives each person with their names, email, main academic group (its label, such
 * as «ESO 1º A»), what they are (student, teacher, employee, parent) and the day they left, if they did. This turns its
 * list into readers as the lists of the library take them. Plain PHP, without Moodle.
 */
final class cifra_people {
    /**
     * The readers of a list.
     *
     * @param array[] $people As Cifra gives them.
     * @param int $now
     * @return array{readers: array[], gone: string[]} The readers there now, and the keys of those who left.
     */
    public static function readers(array $people, int $now): array {
        $readers = [];
        $gone = [];
        $seen = [];
        foreach ($people as $person) {
            $id = (string) ($person['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $left = (string) ($person['fecha_baja'] ?? '');
            if ($left !== '' && strtotime($left) !== false && strtotime($left) <= $now) {
                $gone[] = 'id:cifra:' . $id;
                continue;
            }
            $traits = (array) ($person['caracteristicas'] ?? []);
            $type = !empty($traits['alumno']) ? 'student' : (!empty($traits['profesor']) ? 'teacher'
                : (!empty($traits['empleado']) ? 'staff' : (!empty($traits['padre']) ? 'family' : 'student')));
            $group = trim((string) ($person['grupo_academico_principal']['etiqueta'] ?? ''));
            $readers[] = array_filter([
                'externalid' => 'cifra:' . $id,
                'firstname' => trim((string) ($person['nombre'] ?? '')),
                'lastname' => trim(($person['apellido1'] ?? '') . ' ' . ($person['apellido2'] ?? '')),
                'email' => trim((string) ($person['email'] ?? '')),
                'group' => $type === 'student' ? $group : '',
                'stage' => $type === 'student' && $group !== '' ? stage_guesser::guess($group) : '',
                'type' => $type,
            ], fn($v) => $v !== '');
        }
        return ['readers' => $readers, 'gone' => $gone];
    }
}
