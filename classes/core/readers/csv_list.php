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
 * A list of readers in a CSV file.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

use local_oksigeniaayvurenda\core\text;

/**
 * Readers from a spreadsheet saved as CSV, as any school management program exports them: the separator is found
 * (comma, semicolon or tab), the first row says what each column is, and the usual names in Spanish and English are
 * understood (nombre, apellidos, grupo, curso, etapa, carné, correo, usuario…). A column can also be chosen by hand.
 * Plain PHP, without Moodle.
 */
final class csv_list {
    /** @var string[] What a column can be. */
    public const FIELDS = ['firstname', 'lastname', 'fullname', 'group', 'stage', 'cardcode', 'email', 'username', 'idnumber',
        'type'];

    /** @var array<string, string[]> Words of a heading for each field, without accents or case. */
    private const WORDS = [
        'firstname' => ['nombre', 'name', 'first name', 'firstname', 'given name', 'nom'],
        'lastname' => ['apellidos', 'apellido', 'surname', 'last name', 'lastname', 'family name', 'cognoms', 'primer apellido'],
        'fullname' => ['nombre completo', 'alumno', 'alumna', 'lector', 'full name', 'nombre y apellidos', 'apellidos y nombre'],
        'group' => ['grupo', 'group', 'clase', 'class', 'unidad', 'aula', 'curso y grupo', 'grup'],
        'stage' => ['etapa', 'stage', 'nivel', 'level', 'ensenanza', 'enseñanza', 'estudios'],
        'cardcode' => ['carne', 'carnet', 'codigo', 'card', 'card code', 'barcode', 'codigo de lector', 'numero de lector'],
        'email' => ['correo', 'email', 'e-mail', 'correo electronico', 'mail'],
        'username' => ['usuario', 'username', 'user', 'login', 'nombre de usuario'],
        'idnumber' => ['expediente', 'nia', 'id', 'idnumber', 'numero de expediente', 'matricula', 'student id'],
        'type' => ['tipo', 'type', 'perfil', 'role', 'rol'],
    ];

    /**
     * The rows of a CSV text.
     *
     * @param string $data
     * @return array{columns: string[], rows: array[]} The first row and the rest.
     */
    public static function read(string $data): array {
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);
        if (!mb_check_encoding($data, 'UTF-8')) {
            // Spreadsheets on Windows still save in Latin 1.
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\R/', trim($data));
        $first = $lines[0] ?? '';
        $separator = ',';
        $best = 0;
        foreach ([';', ',', "\t", '|'] as $candidate) {
            $count = substr_count($first, $candidate);
            if ($count > $best) {
                $best = $count;
                $separator = $candidate;
            }
        }
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map('trim', str_getcsv($line, $separator, '"', '\\'));
        }
        $columns = array_shift($rows) ?? [];
        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * What each column is, guessed from its heading.
     *
     * @param string[] $columns
     * @return array<int, string> Column index => field, for those it understands.
     */
    public static function guess(array $columns): array {
        $map = [];
        $taken = [];
        // Exact names first, then a name inside a longer heading.
        foreach ([true, false] as $exact) {
            foreach ($columns as $index => $heading) {
                if (isset($map[$index])) {
                    continue;
                }
                $key = text::normalise($heading);
                foreach (self::WORDS as $field => $words) {
                    if (isset($taken[$field])) {
                        continue;
                    }
                    foreach ($words as $word) {
                        $word = text::normalise($word);
                        if ($exact ? $key === $word : ($word !== '' && str_contains(' ' . $key . ' ', ' ' . $word . ' '))) {
                            $map[$index] = $field;
                            $taken[$field] = true;
                            continue 3;
                        }
                    }
                }
            }
        }
        return $map;
    }

    /**
     * The readers of the rows, with the columns as given.
     *
     * @param array[] $rows
     * @param array $map Column index => field.
     * @return array[] Each with the fields found (firstname, lastname, group…); rows without a name are left out.
     */
    public static function readers(array $rows, array $map): array {
        $readers = [];
        foreach ($rows as $row) {
            $reader = [];
            foreach ($map as $index => $field) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '' && in_array($field, self::FIELDS, true)) {
                    $reader[$field] = $value;
                }
            }
            if (isset($reader['fullname']) && !isset($reader['firstname'])) {
                // A full name comes as «Apellidos, Nombre» or as «Nombre Apellidos».
                if (str_contains($reader['fullname'], ',')) {
                    [$last, $first] = array_map('trim', explode(',', $reader['fullname'], 2));
                } else {
                    $words = preg_split('/\s+/', $reader['fullname']);
                    $first = array_shift($words);
                    $last = implode(' ', $words);
                }
                $reader['firstname'] = $first;
                $reader['lastname'] = $last;
            }
            unset($reader['fullname']);
            if (($reader['firstname'] ?? '') === '' && ($reader['lastname'] ?? '') === '') {
                continue;
            }
            $readers[] = $reader;
        }
        return $readers;
    }
}
