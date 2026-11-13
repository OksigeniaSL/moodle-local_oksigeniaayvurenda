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
 * «Premieres» (P2 of the review of 12 November): the first to take home a new copy premieres it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A copy that came into the library in the last months and nobody had taken home yet: the first loan premieres it. The
 * desk says so, and the album keeps it. Read from the loans themselves: nothing new is kept.
 */
final class premieres {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How long a copy counts as new, from the day it came in. */
    public const DAYS = 120;

    /**
     * Whether a loan just made premieres its copy: the copy's only loan, and the copy came in within DAYS. Only a copy
     * with the day it came in counts (an imported catalogue without it is not all new).
     *
     * @param int $itemid
     * @param int|null $acquired
     * @param int $now
     * @return bool
     */
    public static function premiered(int $itemid, ?int $acquired, int $now): bool {
        global $DB;
        return $acquired !== null && $acquired > 0 && $acquired >= $now - self::DAYS * DAYSECS
            && $DB->count_records(self::T . 'loan', ['itemid' => $itemid]) === 1;
    }

    /**
     * The books a reader premiered in a school year.
     *
     * @param int $readerid
     * @param int $yearid
     * @return int[] Their records.
     */
    public static function of(int $readerid, int $yearid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT i.recordid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND l.yearid = :yearid AND i.timeacquired IS NOT NULL
                AND i.timeacquired >= l.timeloaned - :fresh
                AND NOT EXISTS (SELECT 1
                                  FROM {" . self::T . "loan} o
                                 WHERE o.itemid = l.itemid
                                   AND (o.timeloaned < l.timeloaned OR (o.timeloaned = l.timeloaned AND o.id < l.id)))",
            ['readerid' => $readerid, 'yearid' => $yearid, 'fresh' => self::DAYS * DAYSECS]
        ));
    }
}
