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
 * Why a copy is a candidate for weeding.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * Weeding keeps a collection alive: what nobody takes, what is too old to be right, what is worn out, what was lost
 * long ago. The library decides with these hints, never the program: the candidates are shown, the librarian chooses.
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class weeding_rules {
    /** @var string[] The states of a copy that ask for weeding. */
    public const WORN = ['worn', 'missingpart', 'broken'];

    /**
     * The year of publication as a number, from what catalogues write: «2005», «c2005», «[2005?]», «2005-2007».
     *
     * @param string|null $pubyear
     * @return int|null
     */
    public static function year(?string $pubyear): ?int {
        return preg_match('/(1[5-9]|20)\d\d/', (string) $pubyear, $matches) ? (int) $matches[0] : null;
    }

    /**
     * Why a copy is a candidate, the strongest reason first.
     *
     * @param array $copy status, condition, year (of publication), lastloan and acquired (times or null).
     * @param array $filters unused (years without loans, 0 for none), before (year, 0 for none), worn (bool), lost (bool).
     * @param int $now
     * @return string|null lost, worn, old or unused; null if it is not a candidate.
     */
    public static function why(array $copy, array $filters, int $now): ?string {
        if ($copy['status'] === 'onloan' || $copy['status'] === 'held' || $copy['status'] === 'withdrawn') {
            return null;
        }
        if (!empty($filters['lost']) && $copy['status'] === 'lost') {
            return 'lost';
        }
        if ($copy['status'] === 'lost') {
            return null;
        }
        if (!empty($filters['worn']) && in_array($copy['condition'], self::WORN, true)) {
            return 'worn';
        }
        if (!empty($filters['before']) && $copy['year'] !== null && $copy['year'] < (int) $filters['before']) {
            return 'old';
        }
        if (!empty($filters['unused'])) {
            $limit = $now - (int) $filters['unused'] * 365 * 86400;
            $since = $copy['lastloan'] ?? $copy['acquired'];
            if ($since === null || $since < $limit) {
                return 'unused';
            }
        }
        return null;
    }
}
