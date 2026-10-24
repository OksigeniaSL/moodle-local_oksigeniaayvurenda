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
 * The proposed call number of a book.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The three lines of the spine label, as school libraries write them: the class, three capitals of the first author's
 * surname and three letters of the title, leaving out an article. Only a proposal: each library corrects it, and what
 * it wrote for the book before goes first. Plain PHP, without Moodle.
 */
final class callnumber {
    /** @var string[] Articles that do not count at the start of a title. */
    private const ARTICLES = ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'lo', 'the', 'a', 'an', 'le', 'les',
        'l', 'il', 'lo', 'gli', 'der', 'die', 'das', 'o', 'os', 'as', 'els', 'en', 'et'];

    /**
     * The proposal.
     *
     * @param string $class The class (CDU, Dewey or the library's own), or ''.
     * @param string $author The first author as catalogued («Dahl, Roald») or as printed («Roald Dahl»), or ''.
     * @param string $title
     * @return string[] The three lines.
     */
    public static function propose(string $class, string $author, string $title): array {
        return [trim($class), self::author($author), self::title($title)];
    }

    /**
     * Three capitals of the surname.
     *
     * @param string $author
     * @return string
     */
    public static function author(string $author): string {
        $author = trim($author);
        if ($author === '') {
            return '';
        }
        // A catalogued name puts the surname first («Dahl, Roald»); a printed one, last («Roald Dahl»).
        $surname = str_contains($author, ',') ? strstr($author, ',', true) : (string) preg_replace('/^.*\s/u', '', $author);
        $letters = preg_replace('/[^a-z]/', '', text::normalise($surname));
        return strtoupper(substr($letters, 0, 3));
    }

    /**
     * Three small letters of the title, after an article.
     *
     * @param string $title
     * @return string
     */
    public static function title(string $title): string {
        $words = text::words($title);
        while (count($words) > 1 && in_array($words[0], self::ARTICLES, true)) {
            array_shift($words);
        }
        $letters = preg_replace('/[^a-z0-9]/', '', implode('', $words));
        return substr($letters, 0, 3);
    }
}
