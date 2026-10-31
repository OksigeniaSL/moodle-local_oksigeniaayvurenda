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

/**
 * The groups of this school year with their readers, and each reader with the code of the card and what they have
 * out. For the staff: to find a card, see a group before the class comes, or print the cards later.
 */
final class readers {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

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
            $groups[] = ['name' => $row->groupname, 'stage' => (string) $row->stage, 'count' => (int) $row->readers];
        }
        return $groups;
    }

    /**
     * The readers of a group this school year, or those without one ('' as group), by name.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int|null $now
     * @return array[] id, name, photo, initial, url (their record), cardcode, type and loans out.
     */
    public static function in_group(\stdClass $library, string $group, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $params = ['entityid' => $library->entityid, 'a' => $now, 'b' => $now];
        $member = "SELECT m.readerid
                     FROM {" . self::T . "member} m
                     JOIN {" . self::T . "year} y ON y.id = m.yearid
                    WHERE y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b";
        if ($group === '') {
            $where = "r.id NOT IN ($member)";
        } else {
            $where = "r.id IN ($member AND m.groupname = :groupname)";
            $params['groupname'] = $group;
        }
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.cardcode, r.readertype, r.firstname, r.lastname, r.userid,
                    u.firstname AS ufirstname, u.lastname AS ulastname,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l WHERE l.readerid = r.id AND l.timereturned IS NULL) AS loans
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid2 AND r.status = 'active' AND $where",
            $params + ['entityid2' => $library->entityid]
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
                'name' => $name,
                'photo' => isset($users[$row->userid]) ? profile::photo((object) ['userid' => $row->userid,
                    'entityid' => $library->entityid], $users[$row->userid]) : '',
                'initial' => profile::initial($name),
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $library->slug,
                    'id' => $row->id]))->out(false),
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
