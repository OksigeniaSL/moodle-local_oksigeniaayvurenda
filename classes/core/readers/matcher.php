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
 * Matching readers with accounts by name.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

use local_oksigeniaayvurenda\core\text;

/**
 * Puts readers who came without an account (from Abies, from a list) in three piles against the accounts of the site:
 * those that match by themselves, those in doubt (several accounts, or one that is close) and those not there. A name
 * alone never links anyone without the librarian seeing it: the first pile is accepted with one tap, the second one by
 * one, the third stays as readers without an account, with their history.
 *
 * Names are compared word by word without accents or case. Only the same words in the same order match by
 * themselves: «Ana Montes Lago» and «Ana Lago Montes» are often two people (brothers and cousins share surnames), so
 * they are only candidates, as is an account with only the first surname or with a letter changed.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class matcher {
    /** @var string Matches by itself: one account with exactly the same words. */
    public const SURE = 'sure';

    /** @var string Needs the librarian: several accounts, or close ones. */
    public const DOUBT = 'doubt';

    /** @var string No account comes close. */
    public const NONE = 'none';

    /**
     * The three piles.
     *
     * @param array[] $readers Each with id and name (and group, optional).
     * @param array[] $accounts Each with id and name (and group, optional).
     * @return array{sure: array, doubt: array, none: array} sure: reader id => account id; doubt: reader id => list of
     *     account ids, best first; none: list of reader ids.
     */
    public static function piles(array $readers, array $accounts): array {
        $exact = [];
        $sameset = [];
        $byword = [];
        $words = [];
        $groups = [];
        foreach ($accounts as $account) {
            $set = self::words((string) $account['name']);
            if (!$set) {
                continue;
            }
            $words[$account['id']] = $set;
            $groups[$account['id']] = text::normalise((string) ($account['group'] ?? ''));
            $exact[self::ordered((string) $account['name'])][] = $account['id'];
            $sameset[self::key($set)][] = $account['id'];
            foreach ($set as $word) {
                $byword[$word][$account['id']] = true;
            }
        }
        $piles = [self::SURE => [], self::DOUBT => [], self::NONE => []];
        foreach ($readers as $reader) {
            $set = self::words((string) $reader['name']);
            $group = text::normalise((string) ($reader['group'] ?? ''));
            $same = $set ? ($exact[self::ordered((string) $reader['name'])] ?? []) : [];
            if (count($same) === 1) {
                $account = $same[0];
                // A different group on both sides is worth a look.
                if ($group === '' || $groups[$account] === '' || $groups[$account] === $group) {
                    $piles[self::SURE][$reader['id']] = $account;
                    continue;
                }
            }
            $candidates = self::close($set, $words, $byword);
            foreach ($set ? ($sameset[self::key($set)] ?? []) : [] as $account) {
                $candidates[$account] = 98;
            }
            foreach ($same as $account) {
                $candidates[$account] = 99;
            }
            if ($candidates) {
                arsort($candidates);
                $piles[self::DOUBT][$reader['id']] = array_slice(array_keys($candidates), 0, 5);
            } else {
                $piles[self::NONE][] = $reader['id'];
            }
        }
        return $piles;
    }

    /**
     * Accounts whose words are all in the reader's name, or that share all but one, scored by shared words.
     *
     * @param string[] $set
     * @param array $words Account id => words.
     * @param array $byword Word => account ids.
     * @return array Account id => score.
     */
    private static function close(array $set, array $words, array $byword): array {
        if (count($set) < 2) {
            return [];
        }
        $shared = [];
        foreach ($set as $word) {
            foreach (array_keys($byword[$word] ?? []) as $account) {
                $shared[$account] = ($shared[$account] ?? 0) + 1;
            }
        }
        $close = [];
        foreach ($shared as $account => $count) {
            // Words one letter away count as the same (Olmo and Olmos), a little less than equal ones.
            $loose = 0;
            foreach ($set as $word) {
                foreach ($words[$account] as $theirword) {
                    if ($word === $theirword || (strlen($word) >= 4 && levenshtein($word, $theirword) <= 1)) {
                        $loose++;
                        break;
                    }
                }
            }
            $theirs = count($words[$account]);
            $missing = max(count($set), $theirs) - $loose;
            // The account has all its words in the reader (a surname less), or both differ in one word only.
            if ($loose >= 2 && ($loose >= $theirs || $missing <= 1)) {
                $close[$account] = $loose * 10 - $missing - ($loose - $count);
            }
        }
        return $close;
    }

    /**
     * The words of a name, without accents, case or order.
     *
     * @param string $name
     * @return string[]
     */
    private static function words(string $name): array {
        $words = array_filter(text::words($name), fn($w) => strlen($w) > 1 || ctype_digit($w));
        sort($words);
        return array_values($words);
    }

    /**
     * The words of a name in their order, without accents or case.
     *
     * @param string $name
     * @return string
     */
    private static function ordered(string $name): string {
        $words = \local_oksigeniaayvurenda\core\text::words($name);
        return implode(' ', array_filter($words, fn($w) => strlen($w) > 1 || ctype_digit($w)));
    }

    /**
     * A key for a set of words.
     *
     * @param string[] $set
     * @return string
     */
    private static function key(array $set): string {
        return implode(' ', $set);
    }
}
