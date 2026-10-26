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
     * What each line of a book's label says, to find it on the shelf: the subject (the class and the corner it opens),
     * the author's letters, the title's letters. A line that is not what the house's rule would write is only named,
     * never explained wrongly: each library writes its labels its own way.
     *
     * @param string[] $lines The lines of the label, as written.
     * @param string|null $classlabel The name of the class of the first line, when the catalogue has it.
     * @param string|null $corner The name of the corner its first digit opens.
     * @param string $author The first author, as catalogued.
     * @param string $title
     * @return array[] One for each line: line, kind (class, section, author, title or other) and word (what it stands
     *     for: the class's name, the surname, the title's word), or '' when unknown.
     */
    public static function explain(array $lines, ?string $classlabel, ?string $corner, string $author, string $title): array {
        $explained = [];
        foreach (array_values($lines) as $i => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $kind = 'other';
            $word = '';
            if ($i === 0) {
                $kind = preg_match('/^\d/', $line) ? 'class' : 'section';
                $word = $kind === 'class' ? (string) ($classlabel ?? $corner ?? '') : '';
            } else if ($i === 1 && $author !== '' && self::author($author) === strtoupper(text::normalise($line))) {
                $kind = 'author';
                $word = str_contains($author, ',') ? trim(strstr($author, ',', true)) : (string) preg_replace(
                    '/^.*\s/u',
                    '',
                    trim($author)
                );
            } else if ($i === 2 && self::title($title) === text::normalise($line)) {
                $kind = 'title';
                $words = preg_split('/\s+/u', trim($title)) ?: [];
                while (count($words) > 1 && in_array(text::normalise($words[0]), self::ARTICLES, true)) {
                    array_shift($words);
                }
                $word = (string) preg_replace('/^[\p{P}\s]+|[\p{P}\s]+$/u', '', (string) ($words[0] ?? ''));
            }
            $explained[] = ['line' => $line, 'kind' => $kind, 'word' => $word,
                'corner' => $i === 0 && $kind === 'class' && $corner !== null && $corner !== $word ? $corner : ''];
        }
        return $explained;
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
