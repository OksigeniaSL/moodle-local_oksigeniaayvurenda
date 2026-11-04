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
 * The books that are late.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\loan_rules;
use local_oksigeniaayvurenda\core\group_order;

/**
 * The late loans of a library, by group, for the staff: who, which book and since when. Also the list for the end of
 * the year, of who has books out.
 */
final class late {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array What is out, by how soon it comes back: what should be back already, then the days ahead of today. */
    public const WINDOWS = ['overdue' => -1, 'today' => 0, 'three' => 3, 'week' => 7, 'fortnight' => 15];

    /**
     * Whether a line of by_group() falls in one of the windows.
     *
     * @param array $line
     * @param string $window A key of WINDOWS.
     * @return bool
     */
    public static function in_window(array $line, string $window): bool {
        return $window === 'overdue' ? $line['days'] > 0 : $line['days'] === 0 && $line['left'] <= self::WINDOWS[$window];
    }

    /**
     * Loans out of a library, by group of this school year: only the late ones, or all of them.
     *
     * @param \stdClass $library
     * @param bool $onlylate
     * @param int|null $now
     * @return array[] group and its lines (name, title, due, days).
     */
    public static function by_group(\stdClass $library, bool $onlylate = true, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $tz = zones::of($library);
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
        $year = years::around((int) $library->entityid, $now);
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timedue, l.readerid, i.recordid, r.firstname, r.lastname, r.userid, r.cardcode, rec.title, m.groupname,
                    u.firstname AS ufirstname, u.lastname AS ulastname
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
          LEFT JOIN {user} u ON u.id = r.userid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
          LEFT JOIN {" . self::T . "member} m ON m.readerid = r.id AND m.yearid = :yearid
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL" . ($onlylate ? ' AND l.timedue < :today' : '') . "
           ORDER BY m.groupname, l.timedue",
            ['yearid' => $year->id, 'libraryid' => $library->id, 'today' => $today]
        );
        $groups = [];
        foreach ($rows as $row) {
            $name = $row->userid ? trim($row->ufirstname . ' ' . $row->ulastname) : trim($row->firstname . ' ' . $row->lastname);
            $days = loan_rules::days_late((int) $row->timedue, $now, $tz);
            $group = (string) $row->groupname;
            $groups[$group][] = [
                'name' => $name !== '' ? $name : $row->cardcode,
                'readerid' => (int) $row->readerid,
                'recordid' => (int) $row->recordid,
                'title' => $row->title,
                'due' => zones::date((int) $row->timedue, 'strftimedateshort', $library),
                'days' => $days,
                'left' => $days > 0 ? 0 : loan_rules::days_late($now, (int) $row->timedue, $tz),
                'timedue' => (int) $row->timedue,
            ];
        }
        $list = [];
        foreach ($groups as $group => $lines) {
            $list[] = ['group' => (string) $group, 'lines' => $lines];
        }
        usort($list, fn($a, $b) => group_order::compare($a['group'], $b['group']));
        return $list;
    }
}
