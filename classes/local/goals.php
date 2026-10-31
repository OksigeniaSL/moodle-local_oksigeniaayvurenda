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
 * A reader's own reading goal for the school year.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A reader's own goal for the school year: how many books they mean to read. Only they see it; it counts the books
 * they took home from the library that year (not the textbooks), and nothing is won or lost by it.
 */
final class goals {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The user preference: the goal of each school year, by its id. */
    public const PREF = 'local_oksigeniaayvurenda_goal';

    /** @var int The most books a goal can be. */
    public const MOST = 365;

    /**
     * A user's goal for a school year; 0 without one.
     *
     * @param int $userid
     * @param int $yearid
     * @return int
     */
    public static function of(int $userid, int $yearid): int {
        $all = json_decode((string) get_user_preferences(self::PREF, '', $userid), true) ?: [];
        return (int) ($all[$yearid] ?? 0);
    }

    /**
     * Sets a user's goal for a school year (0 takes it away); only the last few years are kept.
     *
     * @param int $userid
     * @param int $yearid
     * @param int $books
     */
    public static function set(int $userid, int $yearid, int $books): void {
        $all = json_decode((string) get_user_preferences(self::PREF, '', $userid), true) ?: [];
        $all[$yearid] = max(0, min(self::MOST, $books));
        $all = array_filter($all);
        krsort($all);
        $all = array_slice($all, 0, 3, true);
        set_user_preference(self::PREF, $all ? json_encode($all) : null, $userid);
    }

    /**
     * How many books a reader took home in a school year, as the goal counts them.
     *
     * @param int $readerid
     * @param int $yearid
     * @return int
     */
    public static function done(int $readerid, int $yearid): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT i.recordid)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND l.yearid = :yearid AND " . bank::reading('l.libraryid'),
            ['readerid' => $readerid, 'yearid' => $yearid]
        );
    }
}
