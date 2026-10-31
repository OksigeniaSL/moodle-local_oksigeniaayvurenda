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
 * The change of school year.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\school_year;

/**
 * What the change of school year asks of the library. Moving the cohorts is enough: the nightly sync notes this
 * year's groups. What is left is to choose the tutors that are missing, to see who left with books, and to read last
 * year in numbers before it is forgotten. Near the end of the year, the books still out before the summer.
 */
final class year_change {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Where the entity stands.
     *
     * @param int $entityid
     * @param int|null $now
     * @return array year, previous, moment, groups, members, tutors, notutor, left, out, last.
     */
    public static function status(int $entityid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        $previous = self::previous($entityid, $year);
        $groups = $DB->get_fieldset_sql(
            'SELECT DISTINCT groupname FROM {' . self::T . 'member} WHERE yearid = :yearid AND groupname IS NOT NULL',
            ['yearid' => $year->id]
        );
        $tutors = $DB->count_records(self::T . 'tutor', ['yearid' => $year->id]);
        return [
            'year' => $year,
            'previous' => $previous,
            'moment' => school_year::moment($now, (int) $year->timestart, (int) $year->timeend),
            'groups' => count($groups),
            'members' => $DB->count_records(self::T . 'member', ['yearid' => $year->id]),
            'tutors' => $tutors,
            'notutor' => max(0, count($groups) - $tutors),
            'left' => $previous ? self::left_with_books($entityid, $year, $previous) : [],
            'out' => $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . self::T . 'loan} l JOIN {' . self::T . 'library} b ON b.id = l.libraryid
                  WHERE b.entityid = :entityid AND l.timereturned IS NULL',
                ['entityid' => $entityid]
            ),
            'last' => $previous ? self::summary($previous) : null,
        ];
    }

    /**
     * Readers who were in a group last year and are not in one this year, or left, and still have books.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @param \stdClass $previous
     * @return array[] name, group (last year's), books.
     */
    public static function left_with_books(int $entityid, \stdClass $year, \stdClass $previous): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.firstname, r.lastname, r.cardcode, u.firstname AS ufirstname, u.lastname AS ulastname,
                    (SELECT MAX(m.groupname) FROM {" . self::T . "member} m
                      WHERE m.readerid = r.id AND m.yearid = :previous) AS groupname,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l
                      WHERE l.readerid = r.id AND l.timereturned IS NULL) AS books
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid
                AND EXISTS (SELECT 1 FROM {" . self::T . "loan} o WHERE o.readerid = r.id AND o.timereturned IS NULL)
                AND (r.status = 'left'
                     OR (EXISTS (SELECT 1 FROM {" . self::T . "member} p WHERE p.readerid = r.id AND p.yearid = :previous2)
                         AND NOT EXISTS (SELECT 1 FROM {" . self::T . "member} n
                                          WHERE n.readerid = r.id AND n.yearid = :current)))
           ORDER BY groupname, r.lastname, r.firstname",
            ['previous' => $previous->id, 'previous2' => $previous->id, 'current' => $year->id, 'entityid' => $entityid]
        );
        $list = [];
        foreach ($rows as $row) {
            $name = $row->ufirstname !== null ? trim($row->ufirstname . ' ' . $row->ulastname)
                : trim($row->firstname . ' ' . $row->lastname);
            $list[] = ['name' => $name !== '' ? $name : $row->cardcode, 'group' => (string) $row->groupname,
                'books' => (int) $row->books];
        }
        return $list;
    }

    /**
     * A school year in numbers: loans, readers who borrowed and readers in groups, in all and by group.
     *
     * @param \stdClass $year
     * @return array loans, borrowers, members, groups (group, members, borrowers, loans).
     */
    public static function summary(\stdClass $year): array {
        global $DB;
        $members = $DB->get_records_sql_menu(
            'SELECT groupname, COUNT(1) FROM {' . self::T . 'member} WHERE yearid = :yearid AND groupname IS NOT NULL
           GROUP BY groupname',
            ['yearid' => $year->id]
        );
        $loans = $DB->get_records_sql(
            'SELECT groupname, COUNT(1) AS loans, COUNT(DISTINCT readerid) AS borrowers
               FROM {' . self::T . 'loan} WHERE yearid = :yearid AND ' . bank::reading('libraryid') . ' GROUP BY groupname',
            ['yearid' => $year->id]
        );
        $groups = [];
        foreach ($members as $group => $count) {
            $groups[$group] = ['group' => $group, 'members' => (int) $count, 'borrowers' => 0, 'loans' => 0];
        }
        $total = ['loans' => 0, 'borrowers' => 0];
        foreach ($loans as $row) {
            $total['loans'] += (int) $row->loans;
            if ($row->groupname !== null && isset($groups[$row->groupname])) {
                $groups[$row->groupname]['borrowers'] = (int) $row->borrowers;
                $groups[$row->groupname]['loans'] = (int) $row->loans;
            }
        }
        $total['borrowers'] = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT readerid) FROM {' . self::T . 'loan} WHERE yearid = :yearid AND ' . bank::reading('libraryid'),
            ['yearid' => $year->id]
        );
        ksort($groups, SORT_NATURAL);
        return $total + ['name' => $year->name, 'members' => (int) array_sum($members), 'groups' => array_values($groups)];
    }

    /**
     * Changes the day the entity's school years start, from the next one on.
     *
     * @param int $entityid
     * @param int $month
     * @param int $day
     * @return bool Whether it was a valid day.
     */
    public static function set_start(int $entityid, int $month, int $day): bool {
        global $DB;
        if (!checkdate($month, $day, 2001)) {
            return false;
        }
        $DB->update_record(self::T . 'entity', (object) ['id' => $entityid,
            'yearstart' => sprintf('%02d-%02d', $month, $day), 'timemodified' => time()]);
        return true;
    }

    /**
     * The school year before.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @return \stdClass|null
     */
    private static function previous(int $entityid, \stdClass $year): ?\stdClass {
        global $DB;
        $previous = $DB->get_records_select(
            self::T . 'year',
            'entityid = :entityid AND timeend <= :start',
            ['entityid' => $entityid, 'start' => $year->timestart],
            'timeend DESC',
            '*',
            0,
            1
        );
        return $previous ? reset($previous) : null;
    }
}
