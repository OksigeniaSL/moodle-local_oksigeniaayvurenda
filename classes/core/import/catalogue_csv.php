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
 * A catalogue in a CSV file.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\import;

use local_oksigeniaayvurenda\core\isbn;
use local_oksigeniaayvurenda\core\text;

/**
 * A catalogue from a spreadsheet saved as CSV, one row for each copy (or for each title, with how many copies): the
 * usual headings in Spanish and English are understood, and a column can be chosen by hand. Rows of the same title,
 * author and ISBN are one record with several copies. Plain PHP, without Moodle.
 */
final class catalogue_csv {
    /** @var string[] What a column can be. */
    public const FIELDS = ['title', 'subtitle', 'author', 'publisher', 'year', 'isbn', 'place', 'extent', 'language',
        'class', 'subject', 'collection', 'audience', 'summary', 'notes', 'barcode', 'callnumber', 'location', 'itemtype',
        'copies'];

    /** @var array<string, string[]> Words of a heading for each field, without accents or case. */
    private const WORDS = [
        'title' => ['titulo', 'title', 'titol'],
        'subtitle' => ['subtitulo', 'subtitle', 'resto del titulo'],
        'author' => ['autor', 'author', 'autores', 'authors', 'autora', 'creator'],
        'publisher' => ['editorial', 'publisher', 'editor', 'edita'],
        'year' => ['ano', 'year', 'fecha', 'date', 'ano de publicacion', 'fecha de publicacion', 'any'],
        'isbn' => ['isbn', 'isbn13', 'isbn 13', 'ean'],
        'place' => ['lugar', 'place', 'lugar de publicacion', 'ciudad'],
        'extent' => ['paginas', 'pages', 'extension', 'pags'],
        'language' => ['idioma', 'language', 'lengua', 'llengua'],
        'class' => ['cdu', 'clasificacion', 'dewey', 'class', 'classification', 'materia cdu'],
        'subject' => ['materia', 'materias', 'subject', 'subjects', 'tema', 'temas', 'descriptor', 'descriptores'],
        'collection' => ['coleccion', 'collection', 'serie', 'series'],
        'audience' => ['edad', 'age', 'nivel lector', 'publico', 'audience', 'aplicacion pedagogica'],
        'summary' => ['resumen', 'sinopsis', 'summary', 'abstract'],
        'notes' => ['notas', 'notes', 'observaciones', 'nota'],
        'barcode' => ['codigo', 'codigo de barras', 'barcode', 'ejemplar', 'n ejemplar', 'numero de ejemplar', 'registro'],
        'callnumber' => ['signatura', 'tejuelo', 'call number', 'callnumber', 'shelfmark'],
        'location' => ['ubicacion', 'location', 'estanteria', 'seccion', 'sala'],
        'itemtype' => ['tipo', 'type', 'tipo de ejemplar', 'item type'],
        'copies' => ['ejemplares', 'copies', 'cantidad', 'unidades', 'n de ejemplares'],
    ];

    /**
     * What each column is, guessed from its heading.
     *
     * @param string[] $columns
     * @return array Column index => field, for those it understands.
     */
    public static function guess(array $columns): array {
        $map = [];
        $taken = [];
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
     * The records of the rows: a description as the describers give it, its copies and a key to find it again.
     *
     * @param array[] $rows
     * @param array $map Column index => field.
     * @return array[] key, description, items (barcode, callnumber lines, location, itemtype, acquired, notes).
     */
    public static function records(array $rows, array $map): array {
        $records = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($map as $index => $field) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '' && in_array($field, self::FIELDS, true)) {
                    $values[$field] = $value;
                }
            }
            if (($values['title'] ?? '') === '') {
                continue;
            }
            $isbn = isset($values['isbn']) ? isbn::normalise($values['isbn']) : null;
            $key = $isbn ?? text::normalise($values['title'] . ' / ' . ($values['author'] ?? ''));
            if (!isset($records[$key])) {
                $year = isset($values['year']) && preg_match('/\d{4}/', $values['year'], $m) ? $m[0] : null;
                $list = fn(string $field) => array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\s*[;|]\s*/', $values[$field] ?? '')
                ), 'strlen'));
                $records[$key] = ['key' => $key, 'items' => [], 'description' => [
                    'record' => [
                        'doctype' => 'book',
                        'title' => $values['title'],
                        'subtitle' => $values['subtitle'] ?? null,
                        'isbn' => $isbn,
                        'pubplace' => $values['place'] ?? null,
                        'pubyear' => $year,
                        'lang' => self::language($values['language'] ?? ''),
                        'extent' => isset($values['extent']) && ctype_digit($values['extent']) ? $values['extent'] . ' p.'
                            : ($values['extent'] ?? null),
                        'summary' => $values['summary'] ?? null,
                        'notes' => null,
                    ],
                    'authorities' => [
                        'author' => $list('author'),
                        'publisher' => $list('publisher'),
                        'class' => $list('class'),
                        'subject' => $list('subject'),
                        'collection' => $list('collection'),
                        'audience' => $list('audience'),
                    ],
                    'extra' => [],
                ]];
            }
            $count = max(1, min(200, (int) ($values['copies'] ?? 1)));
            $lines = preg_split('/\s+/', trim($values['callnumber'] ?? ''), 3, PREG_SPLIT_NO_EMPTY) ?: [];
            for ($i = 0; $i < $count; $i++) {
                $records[$key]['items'][] = [
                    'barcode' => $i === 0 ? strtoupper($values['barcode'] ?? '') : '',
                    'callnumber' => array_pad($lines, 3, ''),
                    'location' => $values['location'] ?? '',
                    'itemtype' => $values['itemtype'] ?? '',
                    'acquired' => '',
                    'notes' => $values['notes'] ?? '',
                ];
            }
        }
        return array_values($records);
    }

    /**
     * A language as written in a spreadsheet: its three-letter code, or a name that is understood.
     *
     * @param string $value
     * @return string|null
     */
    private static function language(string $value): ?string {
        $key = text::normalise($value);
        $names = ['espanol' => 'spa', 'castellano' => 'spa', 'spanish' => 'spa', 'ingles' => 'eng', 'english' => 'eng',
            'catalan' => 'cat', 'catala' => 'cat', 'gallego' => 'glg', 'galego' => 'glg', 'euskera' => 'baq', 'vasco' => 'baq',
            'frances' => 'fre', 'french' => 'fre', 'aleman' => 'ger', 'german' => 'ger', 'italiano' => 'ita',
            'portugues' => 'por', 'guarani' => 'grn'];
        if (isset($names[$key])) {
            return $names[$key];
        }
        return preg_match('/^[a-z]{3}$/', $key) ? $key : null;
    }
}
