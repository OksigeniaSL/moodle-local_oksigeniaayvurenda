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
 * Who keeps one library only: the teachers of a department, the class teacher with the library of her room, a pupil
 * librarian. They work at that library's desk and nowhere else.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The keepers of each library, chosen by the centre's staff among its readers with an account.
 */
final class keepers {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Whether someone keeps a library.
     *
     * @param int $libraryid
     * @param int $userid
     * @return bool
     */
    public static function is(int $libraryid, int $userid): bool {
        global $DB;
        if (!$userid || !$libraryid) {
            return false;
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $kept = $cache->get('keeper_' . $libraryid . '_' . $userid);
        if ($kept === false) {
            $kept = $DB->record_exists(self::T . 'keeper', ['libraryid' => $libraryid, 'userid' => $userid]) ? 1 : 0;
            $cache->set('keeper_' . $libraryid . '_' . $userid, $kept);
        }
        return (bool) $kept;
    }

    /**
     * Whoever keeps a library.
     *
     * @param int $libraryid
     * @return array[] userid and name, by name.
     */
    public static function of(int $libraryid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT k.userid, u.firstname, u.lastname
               FROM {" . self::T . "keeper} k
               JOIN {user} u ON u.id = k.userid
              WHERE k.libraryid = :libraryid AND u.deleted = 0
           ORDER BY u.firstname, u.lastname",
            ['libraryid' => $libraryid]
        );
        return array_values(array_map(fn($row) => ['userid' => (int) $row->userid,
            'name' => trim($row->firstname . ' ' . $row->lastname)], $rows));
    }

    /**
     * The libraries someone keeps.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function libraries(int $userid): array {
        global $DB;
        if (!$userid) {
            return [];
        }
        return array_values($DB->get_records_sql(
            "SELECT b.*
               FROM {" . self::T . "keeper} k
               JOIN {" . self::T . "library} b ON b.id = k.libraryid
              WHERE k.userid = :userid
           ORDER BY b.name",
            ['userid' => $userid]
        ));
    }

    /**
     * Who may keep a library: the readers of its centre with an account whose name has every word typed.
     *
     * @param \stdClass $library
     * @param string $text
     * @param int $limit
     * @return array[] userid, name and type.
     */
    public static function candidates(\stdClass $library, string $text, int $limit = 12): array {
        global $DB;
        $words = \local_oksigeniaayvurenda\core\text::words($text);
        if (!$words) {
            return [];
        }
        $name = $DB->sql_concat_join("' '", ['u.firstname', 'u.lastname']);
        $conditions = [];
        $params = ['entityid' => $library->entityid, 'libraryid' => $library->id];
        foreach ($words as $i => $word) {
            $conditions[] = $DB->sql_like($name, ':w' . $i, false, false);
            $params['w' . $i] = '%' . $DB->sql_like_escape($word) . '%';
        }
        $rows = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, MIN(r.readertype) AS readertype
               FROM {" . self::T . "reader} r
               JOIN {user} u ON u.id = r.userid
          LEFT JOIN {" . self::T . "keeper} k ON k.libraryid = :libraryid AND k.userid = u.id
              WHERE r.entityid = :entityid AND r.status = 'active' AND u.deleted = 0 AND k.id IS NULL
                    AND " . implode(' AND ', $conditions) . "
           GROUP BY u.id, u.firstname, u.lastname
           ORDER BY u.firstname, u.lastname",
            $params,
            0,
            $limit
        );
        return array_values(array_map(fn($row) => ['userid' => (int) $row->id, 'name' => trim($row->firstname . ' ' .
            $row->lastname), 'type' => (string) $row->readertype], $rows));
    }

    /**
     * Gives someone a library to keep, if they are a reader of its centre with an account.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param int $staffid Who gives it.
     * @return bool Whether it was given.
     */
    public static function add(\stdClass $library, int $userid, int $staffid): bool {
        global $DB;
        $reader = $DB->record_exists(self::T . 'reader', ['entityid' => $library->entityid, 'userid' => $userid,
            'status' => 'active']);
        if (!$reader || self::is((int) $library->id, $userid)) {
            return false;
        }
        $DB->insert_record(self::T . 'keeper', (object) ['libraryid' => $library->id, 'userid' => $userid,
            'staffid' => $staffid, 'timecreated' => time()]);
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('keeper_' . $library->id . '_' . $userid);
        return true;
    }

    /**
     * Takes a library away from whoever kept it.
     *
     * @param \stdClass $library
     * @param int $userid
     */
    public static function remove(\stdClass $library, int $userid): void {
        global $DB;
        $DB->delete_records(self::T . 'keeper', ['libraryid' => $library->id, 'userid' => $userid]);
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('keeper_' . $library->id . '_' . $userid);
    }
}
