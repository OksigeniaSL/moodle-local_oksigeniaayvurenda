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
        }
        $text = mb_strtolower($text, 'UTF-8');
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
}
