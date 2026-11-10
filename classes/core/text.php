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
 * Text for word search.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * Search by words, in any order, regardless of accents and case: "arbol" finds "Árbol", "nandu" finds "Ñandú".
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class text {
    /** @var string[] Letters with no accent to take away that a reader writes otherwise (Straße, œuvre, Łódź). */
    public const LETTERS = ['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'ł' => 'l', 'đ' => 'd', 'ð' => 'd', 'þ' => 'th',
        'ı' => 'i'];

    /** @var string[] Accented Latin letters and their plain form, for systems without intl. */
    private const ACCENTS = ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
        'ê' => 'e', 'ẽ' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ĩ' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ũ' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ỹ' => 'y',
        'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ã' => 'a', 'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e', 'Í' => 'i',
        'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i', 'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ú' => 'u', 'Ù' => 'u',
        'Ü' => 'u', 'Û' => 'u', 'Ñ' => 'n', 'Ç' => 'c'];

    /**
     * The accents, as the two strings the SQL translate() of a database without accent-insensitive search asks for.
     *
     * @return string[] From, to.
     */
    public static function accents(): array {
        return [implode('', array_keys(self::ACCENTS)), implode('', array_values(self::ACCENTS))];
    }

    /**
     * Lower case, without accents, with single spaces and no punctuation.
     *
     * @param string $text
     * @return string
     */
    public static function normalise(string $text): string {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = preg_replace('/\p{Mn}+/u', '', $text);
        } else {
            // Without the intl extension, at least the accents of the Latin alphabet.
            $text = strtr($text, self::ACCENTS);
        }
        $text = strtr(mb_strtolower($text, 'UTF-8'), self::LETTERS);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim($text);
    }

    /**
     * The words of a text, normalised and without repeats.
     *
     * @param string $text
     * @return string[]
     */
    public static function words(string $text): array {
        $normal = self::normalise($text);
        return $normal === '' ? [] : array_values(array_unique(explode(' ', $normal)));
    }

    /**
     * How far a word someone typed is from a word of a name, or null if it is not that word: what is written the way it
     * sounds, one or two letters wrong in a long word or two letters swapped. Foreign surnames are typed by ear.
     *
     * @param string $typed Normalised.
     * @param string $word Normalised.
     * @return int|null 0 for the word or its start; more, the further it is.
     */
    public static function near(string $typed, string $word): ?int {
        if ($typed === $word || ($typed !== '' && str_starts_with($word, $typed))) {
            return 0;
        }
        $length = strlen($typed);
        if ($length < 3) {
            return null;
        }
        if (self::phonetic($typed) === self::phonetic($word)) {
            return 1;
        }
        if ($length < 4) {
            return null;
        }
        $start = substr($word, 0, $length);
        $distance = min(levenshtein($typed, $word), levenshtein($typed, $start));
        if ($distance <= ($length >= 7 ? 2 : 1)) {
            return $distance;
        }
        for ($i = 0; $i < $length - 1; $i++) {
            $swapped = $typed;
            [$swapped[$i], $swapped[$i + 1]] = [$typed[$i + 1], $typed[$i]];
            if ($swapped === $word || $swapped === $start) {
                return 1;
            }
        }
        return null;
    }

    /**
     * A key that sounds the same for words a child may spell either way in Spanish: b and v, ll and y, a silent h, c, z
     * and s before e and i, qu and k, g and j before e and i. Other languages get the same simplification, which is
     * harmless: it only finds suggestions.
     *
     * @param string $word
     * @return string
     */
    public static function phonetic(string $word): string {
        $word = self::normalise($word);
        $word = preg_replace(
            ['/ch/', '/qu/', '/c([eiy])/', '/g([ei])/', '/ll/', '/h/', '/v/', '/w/', '/z/', '/c/', '/x/'],
            ['@', 'k', 's$1', 'j$1', 'y', '', 'b', 'b', 's', 'k', 'ks'],
            $word
        );
        // A double letter sounds like one.
        $word = preg_replace('/(.)\1+/', '$1', $word);
        return str_replace('@', 'ch', $word);
    }

    /**
     * Whether every word of a query is in a text, as the start of one of its words.
     *
     * @param string $searchtext Already normalised.
     * @param string $query As typed.
     * @return bool
     */
    public static function matches(string $searchtext, string $query): bool {
        $haystack = ' ' . $searchtext;
        foreach (self::words($query) as $word) {
            if (strpos($haystack, ' ' . $word) === false) {
                return false;
            }
        }
        return true;
    }

    /** @var string[] Articles a title may start with, in the languages libraries catalogue in (Spanish, English, Portuguese,
     * French, Catalan, Galician): a catalogue may write them after the title or leave them out. */
    private const ARTICLES = ['el', 'la', 'los', 'las', 'lo', 'un', 'una', 'unos', 'unas', 'the', 'a', 'an', 'o', 'os', 'as',
        'um', 'uma', 'uns', 'umas', 'le', 'les', 'l', 'une', 'des', 'els', 'unha', 'unhas'];

    /**
     * A title as a key to tell whether two records are the same book: normalised and without its first article, wherever
     * the catalogue put it. «El principito», «Principito, El», «Principito (El)» and «Principito» give the same key.
     *
     * @param string $title
     * @return string
     */
    public static function title_key(string $title): string {
        // An article written after the title, as library catalogues do: «Principito, El», «Étranger (L')».
        $moved = preg_match('/^(.+?)\s*[,(]\s*(\p{L}+)[\'’]?\s*\)?\s*$/u', trim($title), $match);
        if ($moved && in_array(self::normalise($match[2]), self::ARTICLES, true)) {
            $title = $match[1];
        }
        $words = explode(' ', self::normalise($title));
        if (count($words) > 1 && in_array($words[0], self::ARTICLES, true)) {
            array_shift($words);
        }
        return implode(' ', $words);
    }

    /** @var string[] Words that say nothing of the book sought («un libro de dragones»), in the languages of the plugin. */
    private const EMPTY = ['a', 'al', 'de', 'del', 'el', 'la', 'las', 'lo', 'los', 'un', 'una', 'unos', 'unas', 'y', 'o', 'en',
        'con', 'para', 'por', 'sobre', 'que', 'libro', 'libros', 'quiero', 'busco', 'me', 'mi', 'an', 'the', 'of', 'and', 'or',
        'in', 'on', 'about', 'for', 'with', 'book', 'books', 'i', 'want'];

    /**
     * The words of a query that say what is sought: without «un», «libro», «de»… unless nothing else is left.
     *
     * @param string[] $words Normalised.
     * @return string[]
     */
    public static function meaningful(array $words): array {
        $kept = array_values(array_filter($words, fn($w) => !in_array($w, self::EMPTY, true)));
        return $kept ?: $words;
    }

    /**
     * Another start of a normalised word that finds its singular too: «dragones» → «dragon», «piratas» → «pirata»,
     * «luces» → «luz». Null when there is none.
     *
     * @param string $word
     * @return string|null
     */
    public static function stem(string $word): ?string {
        $length = mb_strlen($word);
        if ($length < 4 || preg_match('/\d/', $word)) {
            return null;
        }
        if ($length >= 5 && str_ends_with($word, 'ces')) {
            return mb_substr($word, 0, -3) . 'z';
        }
        if ($length >= 5 && str_ends_with($word, 'es')) {
            return mb_substr($word, 0, -2);
        }
        return str_ends_with($word, 's') ? mb_substr($word, 0, -1) : null;
    }
}
