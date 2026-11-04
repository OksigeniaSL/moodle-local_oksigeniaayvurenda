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
 * The readers of a library, as the staff see them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\group_order;

/**
 * The groups of this school year with their readers, and each reader with the code of the card and what they have
 * out. For the staff: to find a card, see a group before the class comes, or print the cards later.
 */
final class readers {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The kinds of reader the staff can give someone. */
    public const KINDS = ['student', 'teacher', 'staff', 'family', 'other'];

    /**
     * The groups of this school year, with how many readers each has; readers without a group go apart.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array[] name, stage and count.
     */
    public static function groups(\stdClass $library, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $rows = $DB->get_records_sql(
            "SELECT m.groupname, MIN(m.stage) AS stage, COUNT(DISTINCT m.readerid) AS readers
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "year} y ON y.id = m.yearid
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b AND r.status = 'active'
           GROUP BY m.groupname
           ORDER BY MIN(m.stage), m.groupname",
            ['entityid' => $library->entityid, 'a' => $now, 'b' => $now]
        );
        $groups = [];
        foreach ($rows as $row) {
            if ((string) $row->groupname !== '') {
                $groups[] = ['name' => $row->groupname, 'stage' => (string) $row->stage, 'count' => (int) $row->readers];
            }
        }
        // As a school lists them: by stage, year and letter.
        usort($groups, fn($a, $b) => group_order::compare((string) $a['name'], (string) $b['name'], $a['stage'], $b['stage']));
        return $groups;
    }

    /**
     * The active readers of a library's centre.
     *
     * @param \stdClass $library
     * @return int
     */
    public static function total(\stdClass $library): int {
        global $DB;
        return $DB->count_records(self::T . 'reader', ['entityid' => $library->entityid, 'status' => 'active']);
    }

    /**
     * A column to search by a word typed without accents: PostgreSQL has no accent-insensitive search.
     *
     * @param string $sql
     * @return string
     */
    public static function without_accents(string $sql): string {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            return $sql;
        }
        [$from, $to] = \local_oksigeniaayvurenda\core\text::accents();
        return "translate($sql, '$from', '$to')";
    }

    /**
     * How many readers each place brought: by what the import keeps of each one, and by where its group came from
     * this year. Connected places bring the same reader in both; the larger figure is the true one.
     *
     * @param int $entityid
     * @param int|null $now
     * @return int[] Place (csv, oneroster, cifra, abies, lti, cohort…) => readers.
     */
    public static function from_sources(int $entityid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $kept = $DB->get_records_sql_menu(
            "SELECT origin, COUNT(1)
               FROM {" . self::T . "source}
              WHERE entityid = :entityid AND tablename = 'reader'
           GROUP BY origin",
            ['entityid' => $entityid]
        );
        $members = $DB->get_records_sql_menu(
            "SELECT m.source, COUNT(DISTINCT m.readerid)
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "year} y ON y.id = m.yearid
              WHERE y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b AND m.source IS NOT NULL
           GROUP BY m.source",
            ['entityid' => $entityid, 'a' => $now, 'b' => $now]
        );
        $from = [];
        foreach ([$kept, $members] as $counts) {
            foreach ($counts as $place => $count) {
                $from[$place] = max($from[$place] ?? 0, (int) $count);
            }
        }
        return $from;
    }

    /**
     * What selects the readers of a group this school year, or those without one ('' as group).
     *
     * @param \stdClass $library
     * @param string $group
     * @param int $now
     * @param array $filter type, a kind of reader; status, left for those who stopped coming.
     * @return array The condition and its parameters.
     */
    private static function in_group_where(\stdClass $library, string $group, int $now, array $filter = []): array {
        // Those who stopped coming, whatever group they had.
        if (($filter['status'] ?? 'active') !== 'active') {
            return ['r.status = :status', ['status' => $filter['status']]];
        }
        $type = '';
        $params = ['entityid' => $library->entityid, 'a' => $now, 'b' => $now];
        if (($filter['type'] ?? '') !== '') {
            [$type, $params] = self::type_where((string) $filter['type'], $params);
        }
        $member = "SELECT m.readerid
                     FROM {" . self::T . "member} m
                     JOIN {" . self::T . "year} y ON y.id = m.yearid
                    WHERE y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b";
        if ($group === '') {
            $grouped = "$member AND m.groupname IS NOT NULL AND m.groupname <> ''";
            return ["r.status = 'active' $type AND r.id NOT IN ($grouped)", $params];
        }
        $params['groupname'] = $group;
        return ["r.status = 'active' $type AND r.id IN ($member AND m.groupname = :groupname)", $params];
    }

    /**
     * What selects a kind of reader: «other» is everyone the staff has no kind of their own for.
     *
     * @param string $type
     * @param array $params
     * @return array The condition, starting with AND, and the parameters with its own.
     */
    private static function type_where(string $type, array $params): array {
        if ($type === 'other') {
            return ["AND r.readertype NOT IN ('student', 'teacher', 'staff', 'family')", $params];
        }
        return ['AND r.readertype = :readertype', $params + ['readertype' => $type]];
    }

    /**
     * Those without a group this school year, by kind: teachers and staff have none, and students may have lost theirs.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return int[] Kind (student, teacher, staff, family, other) => readers.
     */
    public static function without_group(\stdClass $library, ?int $now = null): array {
        global $DB;
        [$where, $params] = self::in_group_where($library, '', $now ?? time());
        $rows = $DB->get_records_sql_menu(
            "SELECT r.readertype, COUNT(1)
               FROM {" . self::T . "reader} r
              WHERE r.entityid = :entityid2 AND $where
           GROUP BY r.readertype",
            $params + ['entityid2' => $library->entityid]
        );
        $kinds = [];
        foreach ($rows as $type => $count) {
            $kind = in_array($type, self::KINDS, true) ? $type : 'other';
            $kinds[$kind] = ($kinds[$kind] ?? 0) + (int) $count;
        }
        return $kinds;
    }

    /**
     * How many readers a group has this school year, or how many have none ('' as group).
     *
     * @param \stdClass $library
     * @param string $group
     * @param int|null $now
     * @param array $filter type, a kind of reader; status, left for those who stopped coming.
     * @return int
     */
    public static function in_group_count(\stdClass $library, string $group, ?int $now = null, array $filter = []): int {
        global $DB;
        [$where, $params] = self::in_group_where($library, $group, $now ?? time(), $filter);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . self::T . "reader} r WHERE r.entityid = :entityid2 AND $where",
            $params + ['entityid2' => $library->entityid]
        );
    }

    /**
     * The readers of a group this school year, or those without one ('' as group), by name.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int|null $now
     * @param int $limit How many, for a library without classes that may have thousands; 0 for all.
     * @param int $from From which.
     * @param array $filter type, a kind of reader; status, left for those who stopped coming.
     * @return array[] id, name, photo, initial, url (their record), cardcode, type and loans out.
     */
    public static function in_group(
        \stdClass $library,
        string $group,
        ?int $now = null,
        int $limit = 0,
        int $from = 0,
        array $filter = []
    ): array {
        global $DB;
        $now = $now ?? time();
        [$where, $params] = self::in_group_where($library, $group, $now, $filter);
        // Their record comes back to this list.
        $back = array_filter(['group' => $group, 'type' => $filter['type'] ?? '',
            'list' => ($filter['status'] ?? '') === 'left' ? 'left' : '']);
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.cardcode, r.readertype, r.firstname, r.lastname, r.userid,
                    u.firstname AS ufirstname, u.lastname AS ulastname,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l WHERE l.readerid = r.id AND l.timereturned IS NULL) AS loans
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid2 AND $where
           ORDER BY COALESCE(u.lastname, r.lastname), COALESCE(u.firstname, r.firstname), r.id",
            $params + ['entityid2' => $library->entityid],
            $from,
            $limit
        );
        $readers = [];
        // Their photos, from their accounts, where the centre shows them.
        $users = profile::photos_on((int) $library->entityid) ? $DB->get_records_list('user', 'id', array_values(array_filter(
            array_map(fn($r) => (int) $r->userid, $rows)
        ))) : [];
        foreach ($rows as $row) {
            $first = $row->userid ? $row->ufirstname : $row->firstname;
            $last = $row->userid ? $row->ulastname : $row->lastname;
            $name = trim($first . ' ' . $last) ?: $row->cardcode;
            $readers[] = [
                'id' => (int) $row->id,
                'userid' => $row->userid ? (int) $row->userid : null,
                'name' => $name,
                'photo' => isset($users[$row->userid]) ? profile::photo((object) ['userid' => $row->userid,
                    'entityid' => $library->entityid], $users[$row->userid]) : '',
                'initial' => profile::initial($name),
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $library->slug,
                    'id' => $row->id] + $back))->out(false),
                'sortname' => \local_oksigeniaayvurenda\core\text::normalise($last . ' ' . $first),
                'cardcode' => $row->cardcode,
                'type' => $row->readertype,
                'loans' => (int) $row->loans,
            ];
        }
        usort($readers, fn($a, $b) => $a['sortname'] <=> $b['sortname']);
        return $readers;
    }
}
