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
 * The copies a MARC record carries.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

/**
 * Library programs export each copy inside its record: the standard field 852 (Ayvurenda's own copies, and many
 * others) or Koha's 952. This reads either into the copies of the catalogue: code, spine, place, kind, date acquired
 * and notes; and the record's own identifier, to find it again when the file is loaded once more. Plain PHP, without
 * Moodle.
 */
final class holdings {
    /**
     * The copies of a record.
     *
     * @param record $record
     * @return array[] barcode, callnumber (up to three lines), location, itemtype, acquired (Y-m-d), notes.
     */
    public static function items(record $record): array {
        $items = [];
        foreach ($record->data_fields('852') as $field) {
            $sub = self::subfields($field);
            $items[] = self::item($sub['p'] ?? '', $sub['h'] ?? '', $sub['c'] ?? '', '', '', $sub['z'] ?? '');
        }
        foreach ($record->data_fields('952') as $field) {
            $sub = self::subfields($field);
            $items[] = self::item(
                $sub['p'] ?? '',
                $sub['o'] ?? '',
                $sub['c'] ?? '',
                $sub['y'] ?? '',
                $sub['d'] ?? '',
                $sub['z'] ?? ($sub['x'] ?? '')
            );
        }
        return $items;
    }

    /**
     * The identifier of a record in the program it comes from: its control number (with its source, 003, when there
     * is one), or else its first system number (035).
     *
     * @param record $record
     * @return string Empty when it has none.
     */
    public static function id(record $record): string {
        $number = trim((string) $record->control_value('001'));
        if ($number !== '') {
            $source = trim((string) $record->control_value('003'));
            return ($source !== '' ? $source . ':' : '') . $number;
        }
        return trim((string) $record->first(['035'], 'a'));
    }

    /**
     * The first value of each subfield code of a field.
     *
     * @param array $field
     * @return array<string, string>
     */
    private static function subfields(array $field): array {
        $values = [];
        foreach ($field['subfields'] as [$code, $value]) {
            if (!isset($values[$code])) {
                $values[$code] = trim((string) $value);
            }
        }
        return $values;
    }

    /**
     * A copy, with the spine in up to three lines.
     *
     * @param string $barcode
     * @param string $callnumber
     * @param string $location
     * @param string $itemtype
     * @param string $acquired
     * @param string $notes
     * @return array
     */
    private static function item(
        string $barcode,
        string $callnumber,
        string $location,
        string $itemtype,
        string $acquired,
        string $notes
    ): array {
        $lines = preg_split('/\s+/', trim($callnumber), 3, PREG_SPLIT_NO_EMPTY);
        return [
            'barcode' => strtoupper(trim($barcode)),
            'callnumber' => array_pad($lines ?: [], 3, ''),
            'location' => $location,
            'itemtype' => $itemtype,
            'acquired' => preg_match('/^\d{4}-\d{2}-\d{2}/', $acquired) ? substr($acquired, 0, 10) : '',
            'notes' => $notes,
        ];
    }
}
