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
 * The flower of a reader's card.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The card of each reader is a flower that grows during the school year by four paths at once: coming back to the
 * library, exploring corners it had not visited, telling about what it read, and staying with books (finishing them,
 * reading again, following a series). Each path has three levels; the flower goes up a stage for every three levels,
 * so no path alone is enough to make it flower. It only grows: nothing withers, there are no numbers to see and no
 * rankings. Each September a new seed; the flowers of other years stay in the album. Plain PHP, without Moodle.
 */
final class flower {
    /** @var string[] The four paths. */
    public const PATHS = ['back', 'explore', 'tell', 'stay'];

    /** @var array<string, int[]> What each level of each path asks for. */
    public const THRESHOLDS = [
        'back' => [2, 5, 10],
        'explore' => [2, 4, 7],
        'tell' => [1, 3, 6],
        'stay' => [2, 5, 10],
    ];

    /** @var string[] The stages of the flower. */
    public const STAGES = ['seed', 'sprout', 'bud', 'flower'];

    /**
     * The level of each path.
     *
     * @param array $counts What the reader did by each path this year.
     * @return array<string, int> Path => 0 to 3.
     */
    public static function levels(array $counts): array {
        $levels = [];
        foreach (self::THRESHOLDS as $path => $steps) {
            $count = (int) ($counts[$path] ?? 0);
            $levels[$path] = count(array_filter($steps, fn($step) => $count >= $step));
        }
        return $levels;
    }

    /**
     * The stage of the flower: one more for every three levels, up to the flower.
     *
     * @param array $levels
     * @return int 0 (seed) to 3 (flower).
     */
    public static function stage(array $levels): int {
        return min(count(self::STAGES) - 1, intdiv(array_sum($levels), 3));
    }

    /**
     * What a reader did by each path, from their loans of the year.
     *
     * @param array[] $loans Each with day (Y-m-d of lending), returnday (or null), corner (or ''), recordid, note
     *     (loved, unfinished, more or null) and returned (bool).
     * @return array<string, int>
     */
    public static function counts(array $loans): array {
        $days = [];
        $corners = [];
        $records = [];
        $tell = 0;
        $stay = 0;
        foreach ($loans as $loan) {
            $days[$loan['day']] = true;
            if (!empty($loan['returnday'])) {
                $days[$loan['returnday']] = true;
            }
            if (($loan['corner'] ?? '') !== '') {
                $corners[$loan['corner']] = true;
            }
            if (!empty($loan['note'])) {
                $tell++;
            }
            if (!empty($loan['returned']) && ($loan['note'] ?? '') !== 'unfinished') {
                $stay++;
                // Reading a book again counts once more.
                if (isset($records[$loan['recordid']])) {
                    $stay++;
                }
                $records[$loan['recordid']] = true;
            }
        }
        return ['back' => count($days), 'explore' => count($corners), 'tell' => $tell, 'stay' => $stay];
    }
}
