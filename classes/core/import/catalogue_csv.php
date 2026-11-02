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
 * usual headings in Spanish and English are understood, and those of the exports of LibraryThing, Goodreads, Calibre and
 * Libib (their exact headings, por verificar for LibraryThing and Libib), and a column can be chosen by hand. Rows of
 * the same title, author and ISBN are one record with several copies. Plain PHP, without Moodle.
 */
final class catalogue_csv {
    /** @var string[] What a column can be. */
    public const FIELDS = ['title', 'subtitle', 'author', 'publisher', 'year', 'isbn', 'place', 'extent', 'language',
        'class', 'subject', 'collection', 'audience', 'summary', 'notes', 'barcode', 'callnumber', 'location', 'itemtype',
        'copies', 'moreauthors', 'shelf'];

    /** @var array<string, string[]> Words of a heading for each field, without accents or case. */
    private const WORDS = [
        'title' => ['titulo', 'title', 'titol'],
        'subtitle' => ['subtitulo', 'subtitle', 'resto del titulo'],
        'author' => ['autor', 'author', 'autores', 'authors', 'autora', 'creator', 'creators', 'primary author'],
        'moreauthors' => ['additional authors', 'secondary author', 'otros autores', 'mas autores'],
        'publisher' => ['editorial', 'publisher', 'editor', 'edita'],
        'year' => ['ano', 'year', 'fecha', 'date', 'ano de publicacion', 'fecha de publicacion', 'any', 'year published',
            'pubdate', 'publish date', 'publication year'],
        'isbn' => ['isbn', 'isbn13', 'isbn 13', 'ean', 'isbns', 'ean isbn13', 'upc isbn10'],
        'place' => ['lugar', 'place', 'lugar de publicacion', 'ciudad'],
        'extent' => ['paginas', 'pages', 'extension', 'pags', 'number of pages', 'page count', 'num pages'],
        'language' => ['idioma', 'language', 'lengua', 'llengua', 'languages', 'idiomas'],
        'class' => ['cdu', 'clasificacion', 'dewey', 'class', 'classification', 'materia cdu', 'dewey decimal'],
        'subject' => ['materia', 'materias', 'subject', 'subjects', 'tema', 'temas', 'descriptor', 'descriptores', 'tags',
            'etiquetas'],
        'collection' => ['coleccion', 'collection', 'serie', 'series'],
        'audience' => ['edad', 'age', 'nivel lector', 'publico', 'audience', 'aplicacion pedagogica'],
        'summary' => ['resumen', 'sinopsis', 'summary', 'abstract', 'description', 'descripcion', 'comments'],
        'notes' => ['notas', 'notes', 'observaciones', 'nota', 'private notes', 'private comment'],
        'barcode' => ['codigo', 'codigo de barras', 'barcode', 'ejemplar', 'n ejemplar', 'numero de ejemplar', 'registro'],
        'callnumber' => ['signatura', 'tejuelo', 'call number', 'callnumber', 'shelfmark', 'other call number'],
        'location' => ['ubicacion', 'location', 'estanteria', 'seccion', 'sala'],
        'itemtype' => ['tipo', 'type', 'tipo de ejemplar', 'item type'],
        'copies' => ['ejemplares', 'copies', 'cantidad', 'unidades', 'n de ejemplares', 'owned copies'],
        // Goodreads: a book on the «to-read» shelf is not in the library yet.
        'shelf' => ['exclusive shelf'],
    ];

    /** @var string[] Headings of a column with the ISBN-13, preferred to one with the ISBN-10 or both. */
    private const THIRTEEN = ['isbn13', 'isbn 13', 'ean isbn13'];

    /**
     * Whether a heading is one of the usual words for a field: then the column's name already says what it is.
     *
     * @param string $heading
     * @param string $field
     * @return bool
     */
    public static function named(string $heading, string $field): bool {
        $key = text::normalise($heading);
        foreach (self::WORDS[$field] ?? [] as $word) {
            if (text::normalise($word) === $key) {
                return true;
            }
        }
        return false;
    }

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
        // Goodreads and Libib give the ISBN-10 and the ISBN-13 apart: the ISBN-13, which every book has.
        foreach ($columns as $index => $heading) {
            if (in_array(text::normalise($heading), self::THIRTEEN, true)) {
                $map = array_filter($map, fn($field) => $field !== 'isbn');
                $map[$index] = 'isbn';
                break;
            }
        }
        return $map;
    }

    /**
     * The people of a cell: several separated by «;», «|» or « & » (Calibre), or by commas when each one is a name in
     * its natural order («Mary GrandPré, Jim Kay», Goodreads and Libib); «Rowling, J. K.» is one name.
     *
     * @param string $value
     * @return string[]
     */
    public static function people(string $value): array {
        $names = [];
        foreach (preg_split('/\s*(?:;|\||\s&\s)\s*/u', trim($value)) ?: [] as $part) {
            $commas = array_values(array_filter(array_map('trim', explode(',', $part)), 'strlen'));
            $natural = count($commas) > 1 && !array_filter($commas, fn($c) => !str_contains($c, ' '));
            foreach ($natural ? $commas : [trim($part)] as $name) {
                if ($name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }
        return $names;
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
            if (($values['title'] ?? '') === '' || text::normalise($values['shelf'] ?? '') === 'to read') {
                continue;
            }
            // An ISBN as «="9780439023481"» (Goodreads), «[0439023483]» (LibraryThing) or several in one cell: the first.
            $isbn = null;
            foreach (preg_split('/[\s,;]+/', $values['isbn'] ?? '') ?: [] as $candidate) {
                $isbn = isbn::normalise($candidate);
                if ($isbn !== null) {
                    break;
                }
            }
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
                        // Calibre keeps its description in HTML.
                        'summary' => isset($values['summary']) ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(
                            strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $values['summary'])),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        ))) : null,
                        'notes' => null,
                    ],
                    'authorities' => [
                        'author' => array_values(array_unique(array_merge(
                            self::people($values['author'] ?? ''),
                            self::people($values['moreauthors'] ?? '')
                        ))),
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
