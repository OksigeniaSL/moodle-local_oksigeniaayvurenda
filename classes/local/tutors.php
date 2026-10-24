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
 * The tutor of each group.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Who is the tutor of each group in a school year, to hear about late books. Where the platform of the centre does not
 * say it, the librarian chooses it once a year; the screen proposes last year's tutor of a group with the same name.
 */
final class tutors {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The groups of a school year with their tutor, or the proposal from the year before.
     *
     * @param int $entityid
     * @param int $yearid
     * @return array[] group, userid, proposed (whether it comes from last year).
     */
    public static function groups(int $entityid, int $yearid): array {
        global $DB;
        $names = $DB->get_fieldset_sql(
            'SELECT DISTINCT m.groupname FROM {' . self::T . 'member} m WHERE m.yearid = :yearid ORDER BY m.groupname',
            ['yearid' => $yearid]
        );
        $current = $DB->get_records_menu(self::T . 'tutor', ['yearid' => $yearid], '', 'groupname, userid');
        $year = $DB->get_record(self::T . 'year', ['id' => $yearid], '*', MUST_EXIST);
        $previous = $DB->get_records_select(
            self::T . 'year',
            'entityid = :entityid AND timeend <= :start',
            ['entityid' => $entityid, 'start' => $year->timestart],
            'timeend DESC',
            'id',
            0,
            1
        );
        $previous = $previous ? reset($previous) : null;
        $before = $previous ? $DB->get_records_menu(self::T . 'tutor', ['yearid' => $previous->id], '', 'groupname, userid') : [];
        $groups = [];
        foreach ($names as $name) {
            $userid = (int) ($current[$name] ?? 0);
            $proposed = !$userid && !empty($before[$name]);
            $groups[] = ['group' => $name, 'userid' => $userid ?: (int) ($before[$name] ?? 0), 'proposed' => $proposed];
        }
        return $groups;
    }

    /**
     * Who can be a tutor: the readers of the entity who are teachers or staff and have an account.
     *
     * @param int $entityid
     * @return array User id => name, by name.
     */
    public static function candidates(int $entityid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname
               FROM {" . self::T . "reader} r
               JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.status = 'active' AND r.readertype <> 'student' AND u.deleted = 0
           ORDER BY u.firstname, u.lastname",
            ['entityid' => $entityid]
        );
        $candidates = [];
        foreach ($rows as $row) {
            $candidates[(int) $row->id] = trim($row->firstname . ' ' . $row->lastname);
        }
        return $candidates;
    }

    /**
     * Sets, changes or takes away the tutor of a group.
     *
     * @param int $yearid
     * @param string $group
     * @param int $userid 0 to take it away.
     */
    public static function set(int $yearid, string $group, int $userid): void {
        global $DB;
        $now = time();
        $existing = $DB->get_record(self::T . 'tutor', ['yearid' => $yearid, 'groupname' => $group]);
        if (!$userid) {
            if ($existing) {
                $DB->delete_records(self::T . 'tutor', ['id' => $existing->id]);
            }
            return;
        }
        if ($existing) {
            if ((int) $existing->userid !== $userid) {
                $DB->update_record(self::T . 'tutor', (object) ['id' => $existing->id, 'userid' => $userid,
                    'timenotified' => null, 'timemodified' => $now]);
            }
            return;
        }
        $DB->insert_record(self::T . 'tutor', ['yearid' => $yearid, 'groupname' => $group, 'userid' => $userid,
            'timecreated' => $now, 'timemodified' => $now]);
    }
}
