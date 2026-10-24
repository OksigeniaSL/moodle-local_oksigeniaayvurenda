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
 * MARC in ISO 2709, the exchange format of library programs.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

/**
 * ISO 2709 («.mrc»): a leader of 24 bytes, a directory of 12-byte entries and the fields, with their separators; the
 * lengths count bytes. Written in UTF-8 (position 9 of the leader, «a»); read in UTF-8 or ISO 8859-1. Plain PHP,
 * without Moodle.
 */
final class iso2709 {
    /** @var string End of a field. */
    private const FIELD = "\x1E";

    /** @var string Start of a subfield. */
    private const SUB = "\x1F";

    /** @var string End of a record. */
    private const END = "\x1D";

    /**
     * One record.
     *
     * @param record $record
     * @return string
     */
    public static function write(record $record): string {
        $directory = '';
        $data = '';
        foreach ($record->fields as $field) {
            if (isset($field['value'])) {
                $body = $field['value'] . self::FIELD;
            } else {
                $body = substr($field['ind1'] . ' ', 0, 1) . substr($field['ind2'] . ' ', 0, 1);
                foreach ($field['subfields'] as [$code, $value]) {
                    $body .= self::SUB . $code . str_replace([self::FIELD, self::SUB, self::END], '', (string) $value);
                }
                $body .= self::FIELD;
            }
            $directory .= str_pad(substr($field['tag'], 0, 3), 3, '0', STR_PAD_LEFT) .
                sprintf('%04d%05d', strlen($body), strlen($data));
            $data .= $body;
        }
        $base = 24 + strlen($directory) + 1;
        $length = $base + strlen($data) + 1;
        $leader = sprintf('%05d', $length) . substr($record->leader, 5, 4) . 'a' . '22' . sprintf('%05d', $base) .
            substr($record->leader, 17, 3) . '4500';
        return $leader . $directory . self::FIELD . $data . self::END;
    }

    /**
     * The records of a file.
     *
     * @param string $data
     * @return record[]
     */
    public static function read(string $data): array {
        $records = [];
        foreach (explode(self::END, $data) as $raw) {
            $raw = ltrim($raw, "\r\n");
            if (strlen($raw) < 25) {
                continue;
            }
            $leader = substr($raw, 0, 24);
            $base = (int) substr($leader, 12, 5);
            $utf8 = $leader[9] === 'a';
            $record = new record($leader);
            $directory = substr($raw, 24, max(0, $base - 25));
            for ($i = 0; $i + 12 <= strlen($directory); $i += 12) {
                $tag = substr($directory, $i, 3);
                $body = substr($raw, $base + (int) substr($directory, $i + 7, 5), (int) substr($directory, $i + 3, 4));
                $body = rtrim($body, self::FIELD);
                if (!$utf8) {
                    $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
                }
                if ($tag < '010') {
                    $record->control($tag, $body);
                    continue;
                }
                $parts = explode(self::SUB, $body);
                $indicators = array_shift($parts) . '  ';
                $subfields = [];
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $subfields[] = [substr($part, 0, 1), substr($part, 1)];
                    }
                }
                $record->data($tag, $indicators[0], $indicators[1], $subfields);
            }
            $records[] = $record;
        }
        return $records;
    }
}
