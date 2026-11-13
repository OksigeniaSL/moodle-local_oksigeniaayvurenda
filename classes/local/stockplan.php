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
 * The plan of the shelves of a stocktake: each place of the library a piece of furniture with one shelf of spines.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A stocktake was a form (a name and a list), a list to say where one stood and a page of 17,000 px with every copy
 * missing. Now each place is a piece of furniture with its name on a plate and a shelf of twenty spines: touched, the
 * stocktake starts there; while reading, the spines light up as its books are read and the one where the reader stands
 * carries the light; at the end, each says how many are missing and opens its own list.
 */
final class stockplan {
    /** @var int Spines on a shelf. */
    public const SPINES = 20;

    /**
     * The spines of a shelf, lit as its books are read: the first as soon as one is, so a shelf begun shows it.
     *
     * @param int $count Copies it should find there.
     * @param int $seen Copies read.
     * @return array[] on, for each spine.
     */
    public static function spines(int $count, int $seen): array {
        $lit = $count > 0 ? (int) ceil(self::SPINES * min($seen, $count) / $count) : 0;
        return array_map(fn($i) => ['on' => $i < $lit], range(0, self::SPINES - 1));
    }

    /**
     * The places of the library, or the one the stocktake covers, as furniture: id, label, count, seen and its spines.
     *
     * @param \stdClass $library
     * @param \stdClass|null $stocktake With it, how it goes on each shelf.
     * @return array[]
     */
    public static function furniture(\stdClass $library, ?\stdClass $stocktake = null): array {
        $byplace = $stocktake ? stocktake::byplace($stocktake) : [];
        $list = [];
        foreach (stocktake::places($library) as $place) {
            if ($stocktake && $stocktake->locationid && (int) $stocktake->locationid !== $place['id']) {
                continue;
            }
            $count = $stocktake ? (int) ($byplace[$place['id']]['count'] ?? 0) : $place['count'];
            $seen = (int) ($byplace[$place['id']]['seen'] ?? 0);
            $list[] = ['id' => $place['id'], 'label' => $place['label'], 'count' => $count, 'seen' => $seen,
                'countlabel' => screens::number($count), 'seenlabel' => screens::number($seen),
                'whole' => $count > 0 && $seen >= $count, 'spines' => self::spines($count, $seen)];
        }
        return $list;
    }

    /**
     * The name a stocktake takes by itself: the day and what it covers.
     *
     * @param \stdClass $library
     * @param int $now
     * @param string $place The label of the place, or '' for the whole library.
     * @return string
     */
    public static function name(\stdClass $library, int $now, string $place): string {
        $at = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($library));
        $day = get_string('reportdaymonth', 'local_oksigeniaayvurenda', (object) ['day' => $at->format('j'),
            'month' => userdate($now, '%B', zones::name($library))]);
        return $place === '' ? get_string('stocktakedefault', 'local_oksigeniaayvurenda', $day)
            : get_string('stocktakedefaultplace', 'local_oksigeniaayvurenda', (object) ['date' => $day, 'place' => $place]);
    }
}
