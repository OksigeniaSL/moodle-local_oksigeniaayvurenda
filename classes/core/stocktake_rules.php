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
 * What a stocktake makes of each copy read on the shelves.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * Going along the shelves with a phone or a scanner, each copy read is seen where it should be, or out of its place, or
 * thought to be lent, or thought to be lost and turning up. What should be on the shelves and was not read is missing.
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class stocktake_rules {
    /** @var string[] Copies a stocktake counts: on the shelf, out, set aside for someone, being repaired. */
    public const EXPECTED = ['available', 'onloan', 'held', 'repair'];

    /** @var string[] Copies that should be on the shelves: if they are not read, they are missing. */
    public const ON_SHELF = ['available', 'held'];

    /**
     * What a read copy says.
     *
     * @param string $status The status of the copy.
     * @param int|null $belongs Where the copy belongs (its location), if it has one.
     * @param int|null $here Where the reader says they are, if they say.
     * @param bool $already It was read before in this stocktake.
     * @return string again, found (it was lost), withdrawn, lent, misplaced or seen.
     */
    public static function verdict(string $status, ?int $belongs, ?int $here, bool $already): string {
        if ($already) {
            return 'again';
        }
        if ($status === 'lost') {
            return 'found';
        }
        if ($status === 'withdrawn') {
            return 'withdrawn';
        }
        if ($status === 'onloan') {
            return 'lent';
        }
        if ($here !== null && $belongs !== null && $belongs !== $here) {
            return 'misplaced';
        }
        return 'seen';
    }
}
