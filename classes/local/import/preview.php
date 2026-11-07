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

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\core\abies\mapper;
use local_oksigeniaayvurenda\core\import\catalogue_csv;

/**
 * What a file would bring, before anything enters: its figures, the first books as they will stand on the shelf and,
 * for a spreadsheet, what each column was taken to be.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview {
    /** @var int The books shown. */
    public const BOOKS = 6;

    /** @var int The drawn covers there are. */
    private const COVERS = 6;

    /** @var int The rows looked at for what a column says. */
    private const SAMPLE_ROWS = 25;

    /**
     * The figures and the first books of some records, as the importers receive them.
     *
     * @param iterable $records Each with description and items.
     * @return array records, items, places, nocode, books, badisbn (the ISBNs that are not ones).
     */
    public static function of(iterable $records): array {
        $look = ['records' => 0, 'items' => 0, 'places' => 0, 'nocode' => 0, 'books' => [], 'badisbn' => []];
        $places = [];
        foreach ($records as $record) {
            $look['records']++;
            if (($record['badisbn'] ?? '') !== '') {
                $look['badisbn'][] = (string) $record['badisbn'];
            }
            foreach ($record['items'] ?? [] as $item) {
                $look['items']++;
                $look['nocode'] += trim((string) ($item['barcode'] ?? '')) === '' ? 1 : 0;
                $place = trim((string) ($item['location'] ?? ''));
                if ($place !== '') {
                    $places[$place] = true;
                }
            }
            if (count($look['books']) < self::BOOKS) {
                $look['books'][] = self::book($record, count($look['books']));
            }
        }
        $look['places'] = count($places);
        return $look;
    }

    /**
     * The first books of an Abies Web export, with the call number of their first copy.
     *
     * @param export_reader $export
     * @return array[] Books.
     */
    public static function abies_books(export_reader $export): array {
        $records = [];
        foreach ($export->documents() as $doc) {
            $records[$doc['id']] = ['description' => mapper::record($doc), 'items' => []];
            if (count($records) >= self::BOOKS) {
                break;
            }
        }
        foreach ($export->items() as $item) {
            $id = $item['IdRegistro'] ?? '';
            if (isset($records[$id]) && !$records[$id]['items']) {
                $records[$id]['items'][] = ['callnumber' => [$item['Signatura1'] ?? '', $item['Signatura2'] ?? '',
                    $item['Signatura3'] ?? '']];
            }
        }
        $books = [];
        foreach (array_values($records) as $index => $record) {
            $books[] = self::book($record, $index);
        }
        return $books;
    }

    /**
     * Each column of a spreadsheet: its heading, what it says, what it was taken to be and how to change it.
     *
     * @param array $csv columns and rows.
     * @param array $map Column index => field.
     * @return array columns, known, all, hastitle.
     */
    public static function columns(array $csv, array $map): array {
        $columns = [];
        $known = 0;
        foreach ($csv['columns'] as $index => $heading) {
            $field = $map[$index] ?? '';
            $label = $field !== '' ? get_string('importfield' . $field, 'local_oksigeniaayvurenda') : '';
            $options = [['value' => '', 'label' => get_string('readerslistignore', 'local_oksigeniaayvurenda'),
                'selected' => $field === '']];
            foreach (catalogue_csv::FIELDS as $option) {
                $options[] = ['value' => $option, 'label' => get_string('importfield' . $option, 'local_oksigeniaayvurenda'),
                    'selected' => $field === $option];
            }
            $known += $field !== '' ? 1 : 0;
            $columns[] = [
                'index' => $index,
                'heading' => $heading,
                'sample' => self::sample($csv['rows'], $index),
                'isknown' => $field !== '',
                // What it became, when the column's own name does not already say so.
                'becomes' => $field !== '' && !catalogue_csv::named($heading, $field) ? $label : '',
                'options' => $options,
            ];
        }
        return ['columns' => $columns, 'known' => $known, 'all' => count($columns),
            'hastitle' => in_array('title', $map, true)];
    }

    /**
     * The first thing a column says.
     *
     * @param array $rows
     * @param int $index
     * @return string
     */
    private static function sample(array $rows, int $index): string {
        foreach (array_slice($rows, 0, self::SAMPLE_ROWS) as $row) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * A book to draw.
     *
     * @param array $record description and items.
     * @param int $index Its place among those shown.
     * @return array title, authors, colour, lines, haslines, callnumber, place, copies.
     */
    private static function book(array $record, int $index): array {
        $description = $record['description'] ?? [];
        $authors = $description['authorities']['author'] ?? [];
        $byline = $authors ? implode(' · ', array_slice($authors, 0, 2))
            : (string) ($description['record']['responsibility'] ?? '');
        $items = $record['items'] ?? [];
        $first = $items[0] ?? [];
        $lines = array_values(array_filter(array_map(
            fn($line) => trim((string) $line),
            (array) ($first['callnumber'] ?? [])
        ), 'strlen'));
        return [
            'title' => (string) ($description['record']['title'] ?? ''),
            'authors' => $byline,
            'colour' => $index % self::COVERS,
            'lines' => array_map(fn($line) => ['line' => $line], $lines),
            'haslines' => (bool) $lines,
            'callnumber' => implode(' ', $lines),
            'place' => trim((string) ($first['location'] ?? '')),
            'copies' => count($items),
        ];
    }
}
