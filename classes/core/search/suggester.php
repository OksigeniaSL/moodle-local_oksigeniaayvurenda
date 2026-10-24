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
 * Suggestions for a search that found nothing.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\search;

use local_oksigeniaayvurenda\core\text;

/**
 * When a search finds nothing, up to three ways to write it that do find something: each word is swapped for a word of
 * the catalogue that sounds the same or is one or two letters away, the most common first. Children misspell in their
 * own way ("bolcan", "jirafa" for "girafa"), so sound counts as much as letters.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class suggester {
    /** @var int[] Words of the catalogue and how many records have each. */
    private array $vocabulary;

    /** @var array Words of the catalogue by their phonetic key. */
    private array $bysound = [];

    /**
     * Prepares the suggestions for a catalogue.
     *
     * @param int[] $vocabulary Word => number of records.
     */
    public function __construct(array $vocabulary) {
        $this->vocabulary = $vocabulary;
        foreach ($vocabulary as $word => $count) {
            $this->bysound[text::phonetic((string) $word)][] = (string) $word;
        }
    }

    /**
     * The vocabulary of a list of search texts.
     *
     * @param iterable $searchtexts Normalised texts.
     * @return int[]
     */
    public static function vocabulary(iterable $searchtexts): array {
        $vocabulary = [];
        foreach ($searchtexts as $searchtext) {
            foreach (array_unique(explode(' ', (string) $searchtext)) as $word) {
                if (strlen($word) >= 2) {
                    $vocabulary[$word] = ($vocabulary[$word] ?? 0) + 1;
                }
            }
        }
        return $vocabulary;
    }

    /**
     * Up to a number of queries, written better, that use words of the catalogue.
     *
     * @param string $query As typed.
     * @param int $max
     * @return string[]
     */
    public function suggest(string $query, int $max = 3): array {
        $words = text::words($query);
        if (!$words) {
            return [];
        }
        $options = [];
        foreach ($words as $word) {
            $options[] = $this->alternatives($word);
        }
        // The best of each word together, then the second best of the word that had most doubt.
        $suggestions = [];
        $best = array_map(fn($list) => $list[0] ?? null, $options);
        if (!in_array(null, $best, true) && $best !== $words) {
            $suggestions[] = implode(' ', $best);
        }
        foreach ($options as $i => $list) {
            foreach (array_slice($list, 1) as $other) {
                $variant = $best;
                $variant[$i] = $other;
                if (!in_array(null, $variant, true)) {
                    $suggestions[] = implode(' ', $variant);
                }
            }
        }
        return array_slice(array_values(array_unique($suggestions)), 0, $max);
    }

    /**
     * Words of the catalogue for one word of the query, the closest and most common first.
     *
     * @param string $word Normalised.
     * @return string[]
     */
    private function alternatives(string $word): array {
        if (isset($this->vocabulary[$word])) {
            return [$word];
        }
        $candidates = [];
        foreach ($this->bysound[text::phonetic($word)] ?? [] as $same) {
            $candidates[$same] = 0;
        }
        $limit = strlen($word) <= 5 ? 1 : 2;
        foreach ($this->vocabulary as $known => $count) {
            $known = (string) $known;
            if (isset($candidates[$known]) || abs(strlen($known) - strlen($word)) > $limit) {
                continue;
            }
            // A word typed as the start of a longer one is a good guess too.
            if (strlen($word) >= 3 && strpos($known, $word) === 0) {
                $candidates[$known] = 1;
                continue;
            }
            $distance = levenshtein($word, $known);
            if ($distance <= $limit) {
                $candidates[$known] = $distance;
            }
        }
        uksort($candidates, function ($a, $b) use ($candidates) {
            return [$candidates[$a], -($this->vocabulary[$a] ?? 0)] <=> [$candidates[$b], -($this->vocabulary[$b] ?? 0)];
        });
        return array_slice(array_map('strval', array_keys($candidates)), 0, 3);
    }
}
