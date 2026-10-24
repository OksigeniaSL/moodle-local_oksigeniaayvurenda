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
 * Open Library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\sources;

use local_oksigeniaayvurenda\core\marc\describer;

/**
 * Open Library (Internet Archive): records by ISBN without a key, reusable, with covers. One question per book when it
 * is added and the answer kept, as its terms ask: never in bulk. Builds the addresses and reads the answer; the
 * request itself is the caller's. Plain PHP, without Moodle.
 */
final class openlibrary {
    /**
     * The address that asks for an ISBN.
     *
     * @param string $isbn Digits.
     * @return string
     */
    public static function url(string $isbn): string {
        return 'https://openlibrary.org/api/books?bibkeys=ISBN:' . rawurlencode($isbn) . '&jscmd=data&format=json';
    }

    /**
     * The address of the cover of an ISBN, or a 404 when there is none.
     *
     * @param string $isbn
     * @return string
     */
    public static function cover_url(string $isbn): string {
        return 'https://covers.openlibrary.org/b/isbn/' . rawurlencode($isbn) . '-M.jpg?default=false';
    }

    /**
     * The description in an answer.
     *
     * @param string $json
     * @param string $isbn The ISBN that was asked for.
     * @return array|null A description as describer gives them, with the cover address in extra; null if not found.
     */
    public static function description(string $json, string $isbn): ?array {
        $data = json_decode($json, true);
        $book = is_array($data) ? ($data['ISBN:' . $isbn] ?? reset($data)) : null;
        if (!is_array($book) || empty($book['title'])) {
            return null;
        }
        $names = fn(string $key): array => array_values(array_unique(array_filter(array_map(
            fn($entry) => describer::clean(is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry),
            $book[$key] ?? []
        ))));
        $year = isset($book['publish_date']) && preg_match('/\d{4}/', $book['publish_date'], $m) ? $m[0] : null;
        $extra = [];
        if (!empty($book['identifiers']['lccn'][0])) {
            $extra['lccn'] = (string) $book['identifiers']['lccn'][0];
        }
        if (!empty($book['cover']['medium'])) {
            $extra['cover'] = (string) $book['cover']['medium'];
        }
        if (!empty($book['url'])) {
            $extra['openlibrary'] = (string) $book['url'];
        }
        $places = $names('publish_places');
        return [
            'record' => [
                'doctype' => 'book',
                'title' => describer::clean((string) $book['title']) ?? '',
                'subtitle' => describer::clean($book['subtitle'] ?? null),
                'responsibility' => describer::clean($book['by_statement'] ?? null),
                'isbn' => $isbn,
                'edition' => null,
                'pubplace' => $places[0] ?? null,
                'pubyear' => $year,
                'lang' => null,
                'extent' => !empty($book['number_of_pages']) ? (int) $book['number_of_pages'] . ' p.' : null,
                'summary' => null,
                'notes' => null,
            ],
            'authorities' => [
                'author' => $names('authors'),
                'publisher' => $names('publishers'),
                'class' => array_values(array_filter(array_map('strval', $book['classifications']['dewey_decimal_class'] ?? []))),
                // Open Library's subjects are many and loose: the first few real ones are enough («series:…» is not one).
                'subject' => array_slice(array_values(array_filter(
                    $names('subjects'),
                    fn($s) => !preg_match('/^\w+:/', $s)
                )), 0, 5),
                'collection' => [],
                'audience' => [],
            ],
            'extra' => $extra,
        ];
    }
}
