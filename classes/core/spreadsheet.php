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
 * Cells of the lists people open in a spreadsheet. A title brought from a catalogue that begins with «=», «+», «-» or
 * «@» would be read there as a formula (and a formula can call out or run things): it gets an apostrophe in front, which
 * the spreadsheet does not show. Numbers stay numbers. The daily copies, made to be read back, are left as they are.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class spreadsheet {
    /**
     * One cell, safe to open.
     *
     * @param mixed $value
     * @return string
     */
    public static function cell($value): string {
        $text = (string) ($value ?? '');
        if ($text === '' || is_numeric($text)) {
            return $text;
        }
        return in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }

    /**
     * A row of cells, safe to open.
     *
     * @param array $row
     * @return string[]
     */
    public static function row(array $row): array {
        return array_map([self::class, 'cell'], array_values($row));
    }
}
