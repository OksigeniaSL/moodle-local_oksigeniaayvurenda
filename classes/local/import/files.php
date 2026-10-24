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
 * Files brought to import.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\import\catalogue_csv;
use local_oksigeniaayvurenda\core\marc\describer;
use local_oksigeniaayvurenda\core\marc\holdings;
use local_oksigeniaayvurenda\core\marc\iso2709;
use local_oksigeniaayvurenda\core\marc\marcxml;
use local_oksigeniaayvurenda\core\readers\csv_list;

/**
 * What kind of file was brought (an Abies Web export, MARC from any library program, or a spreadsheet as CSV), and its
 * records ready for the import.
 */
final class files {
    /**
     * The kind of a file, from its name and its first bytes.
     *
     * @param string $path
     * @param string $name The name it was brought with.
     * @return string abies, marc, marcxml, csv or '' when it is none of them.
     */
    public static function kind(string $path, string $name): string {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        if ($extension === 'zip') {
            return 'abies';
        }
        if (str_contains($head, 'MARC21/slim') || preg_match('/<(\w+:)?collection\b/', $head)) {
            return 'marcxml';
        }
        if (str_contains($head, '<Fichero')) {
            return 'abies';
        }
        if (in_array($extension, ['mrc', 'marc', 'iso', 'dat'], true) || preg_match('/^\d{5}[a-z ]{2}[a-z ]/', $head)) {
            return 'marc';
        }
        if (in_array($extension, ['csv', 'txt', 'tsv'], true)) {
            return 'csv';
        }
        return '';
    }

    /**
     * The XML of an Abies export, taken out of its zip when it comes in one.
     *
     * @param string $path
     * @return string|null
     */
    public static function abies_xml(string $path): ?string {
        if (strtolower(substr((string) file_get_contents($path, false, null, 0, 2), 0, 2)) !== 'pk') {
            return $path;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $xml = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (strtolower(pathinfo($zip->getNameIndex($i), PATHINFO_EXTENSION)) === 'xml') {
                $xml = make_request_directory() . '/export.xml';
                file_put_contents($xml, $zip->getFromIndex($i));
                break;
            }
        }
        $zip->close();
        return $xml;
    }

    /**
     * The records of a MARC file, as the import takes them.
     *
     * @param string $path
     * @param string $kind marc or marcxml.
     * @return \Generator Key, description and items of each record.
     */
    public static function marc(string $path, string $kind): \Generator {
        $data = (string) file_get_contents($path);
        $records = $kind === 'marcxml' ? marcxml::read($data) : iso2709::read($data);
        unset($data);
        foreach ($records as $record) {
            yield ['key' => holdings::id($record), 'description' => describer::describe($record),
                'items' => holdings::items($record)];
        }
    }

    /**
     * The columns and rows of a CSV file.
     *
     * @param string $path
     * @return array columns, rows.
     */
    public static function csv(string $path): array {
        return csv_list::read((string) file_get_contents($path));
    }

    /**
     * The records of a CSV file with its columns chosen.
     *
     * @param string $path
     * @param array $map Column index => field.
     * @return array[]
     */
    public static function csv_records(string $path, array $map): array {
        return catalogue_csv::records(self::csv($path)['rows'], $map);
    }
}
