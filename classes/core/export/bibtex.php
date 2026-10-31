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
 * A record of the catalogue in BibTeX.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\export;

/**
 * BibTeX, for LaTeX and the reference managers that read it (JabRef, Zotero, Mendeley): «@book{key, field = {value}}».
 * Material that is not a work is not cited. Plain PHP, without Moodle.
 */
final class bibtex {
    /** @var array<string, string> The BibTeX type of each kind of document. */
    private const TYPES = ['book' => 'book', 'periodical' => 'article', 'audio' => 'misc', 'video' => 'misc',
        'electronic' => 'misc', 'other' => 'misc'];

    /**
     * The entry of a record, or an empty text for material that is not cited.
     *
     * @param array $record The fields of the record (doctype, title, subtitle, isbn, edition, pubplace, pubyear).
     * @param array $authorities Kind => labels, in order (author, publisher, subject, collection).
     * @param string $callnumber Where it is on the shelf.
     * @param string $note A note of whoever made the list.
     * @param string $key Its key; made from the author, the year and the title when empty.
     * @return string
     */
    public static function entry(
        array $record,
        array $authorities,
        string $callnumber = '',
        string $note = '',
        string $key = ''
    ): string {
        $type = self::TYPES[(string) ($record['doctype'] ?? 'book')] ?? null;
        $title = trim((string) ($record['title'] ?? ''));
        if ($type === null || $title === '') {
            return '';
        }
        $subtitle = trim((string) ($record['subtitle'] ?? ''));
        $authors = $authorities['author'] ?? [];
        $year = trim((string) ($record['pubyear'] ?? ''));
        $key = $key !== '' ? $key : self::key($authors[0] ?? '', $year, $title);
        $notes = array_values(array_filter([$callnumber !== '' ? 'Signatura: ' . $callnumber : '', $note]));
        $fields = [
            'title' => $subtitle !== '' ? $title . ': ' . $subtitle : $title,
            'author' => implode(' and ', $authors),
            'year' => $year,
            'edition' => (string) ($record['edition'] ?? ''),
            'publisher' => (string) (($authorities['publisher'] ?? [])[0] ?? ''),
            'address' => (string) ($record['pubplace'] ?? ''),
            'series' => (string) (($authorities['collection'] ?? [])[0] ?? ''),
            'isbn' => (string) ($record['isbn'] ?? ''),
            'keywords' => implode(', ', $authorities['subject'] ?? []),
            'note' => implode('. ', $notes),
        ];
        $text = '@' . $type . '{' . $key;
        foreach ($fields as $name => $value) {
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            if ($value !== '') {
                $text .= ",\n  " . $name . ' = {' . self::escape($value) . '}';
            }
        }
        return $text . "\n}\n";
    }

    /**
     * A key: the first author's surname, the year and the first word of the title, in plain letters.
     *
     * @param string $author «Surname, Name».
     * @param string $year
     * @param string $title
     * @return string
     */
    public static function key(string $author, string $year, string $title): string {
        $plain = function (string $text): string {
            $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'ç' => 'c', 'Ç' => 'c']);
            return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $text));
        };
        $surname = $plain(trim(explode(',', $author)[0]));
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $word = '';
        foreach ($words as $candidate) {
            if (mb_strlen($candidate) > 3) {
                $word = $candidate;
                break;
            }
        }
        $key = $surname . preg_replace('/\D/', '', $year) . $plain($word !== '' ? $word : ($words[0] ?? ''));
        return $key !== '' ? $key : 'ref';
    }

    /**
     * A value as BibTeX takes it: the characters that mean something to LaTeX, escaped.
     *
     * @param string $value
     * @return string
     */
    private static function escape(string $value): string {
        return strtr($value, ['\\' => '\\textbackslash{}', '{' => '\\{', '}' => '\\}', '&' => '\\&', '%' => '\\%',
            '$' => '\\$', '#' => '\\#', '_' => '\\_', '~' => '\\textasciitilde{}', '^' => '\\textasciicircum{}']);
    }
}
