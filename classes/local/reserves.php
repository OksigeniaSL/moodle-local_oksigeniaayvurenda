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
 * Copies on reserve for a course.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The library puts some copies of a required reading on reserve for a course: lent for a day or a few, or in the room
 * (back the same day), until a date; then each goes back to its own rules. The desk lends them so, and they are not
 * renewed. The course's readings say a book is on reserve.
 */
final class reserves {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int[] How long a copy on reserve is lent: 0, in the room (back the same day), or days. */
    public const DAYS = [0, 1, 2, 3, 7];

    /**
     * The reserve a copy is under now, if any.
     *
     * @param int $itemid
     * @param int $now
     * @return \stdClass|null
     */
    public static function active(int $itemid, int $now): ?\stdClass {
        global $DB;
        return $DB->get_record_select(
            self::T . 'reserve',
            'itemid = :itemid AND timeend >= :now',
            ['itemid' => $itemid, 'now' => $now]
        ) ?: null;
    }

    /**
     * Puts copies of a book of a library on reserve for a course: as many as asked, those that are free first, among
     * those not on reserve now (an old reserve of a copy is replaced).
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $courseid
     * @param int $copies
     * @param int $days One of DAYS.
     * @param int $timeend
     * @param int $now
     * @return int How many were put on reserve.
     */
    public static function put(
        \stdClass $library,
        int $recordid,
        int $courseid,
        int $copies,
        int $days,
        int $timeend,
        int $now
    ): int {
        global $DB;
        if (!in_array($days, self::DAYS, true) || $timeend < $now || $copies < 1) {
            return 0;
        }
        $items = $DB->get_records_sql(
            "SELECT i.id
               FROM {" . self::T . "item} i
          LEFT JOIN {" . self::T . "reserve} r ON r.itemid = i.id AND r.timeend >= :now
              WHERE i.libraryid = :libraryid AND i.recordid = :recordid AND r.id IS NULL
                    AND i.status IN ('available', 'onloan', 'held') AND i.itemtype <> 'reference'
           ORDER BY CASE WHEN i.status = 'available' THEN 0 ELSE 1 END, i.id",
            ['now' => $now, 'libraryid' => $library->id, 'recordid' => $recordid],
            0,
            $copies
        );
        foreach ($items as $item) {
            $DB->delete_records(self::T . 'reserve', ['itemid' => $item->id]);
            $DB->insert_record(self::T . 'reserve', ['itemid' => $item->id, 'libraryid' => $library->id,
                'courseid' => $courseid, 'days' => $days, 'timeend' => $timeend, 'timecreated' => $now]);
        }
        return count($items);
    }

    /**
     * Takes a book of a library off reserve for a course.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $courseid
     */
    public static function end(\stdClass $library, int $recordid, int $courseid): void {
        global $DB;
        $DB->delete_records_select(
            self::T . 'reserve',
            "libraryid = :libraryid AND courseid = :courseid
             AND itemid IN (SELECT id FROM {" . self::T . "item} WHERE recordid = :recordid)",
            ['libraryid' => $library->id, 'courseid' => $courseid, 'recordid' => $recordid]
        );
    }

    /**
     * What a library has on reserve now, by book and course: how many copies, how long they are lent and until when.
     *
     * @param \stdClass $library
     * @param int $now
     * @param int|null $courseid Only those of a course.
     * @return array[] recordid => courseid => [copies, days, timeend].
     */
    public static function of_library(\stdClass $library, int $now, ?int $courseid = null): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, i.recordid, r.courseid, r.days, r.timeend
               FROM {" . self::T . "reserve} r
               JOIN {" . self::T . "item} i ON i.id = r.itemid
              WHERE r.libraryid = :libraryid AND r.timeend >= :now" . ($courseid ? ' AND r.courseid = :courseid' : ''),
            ['libraryid' => $library->id, 'now' => $now, 'courseid' => (int) $courseid]
        );
        $out = [];
        foreach ($rows as $row) {
            $here = $out[$row->recordid][$row->courseid] ?? ['copies' => 0, 'days' => (int) $row->days,
                'timeend' => (int) $row->timeend];
            $here['copies']++;
            $out[$row->recordid][$row->courseid] = $here;
        }
        return $out;
    }

    /**
     * How a reserve is lent, in words: in the room, or for so many days.
     *
     * @param int $days
     * @return string
     */
    public static function words(int $days): string {
        return $days === 0 ? get_string('reserveroom', 'local_oksigeniaayvurenda')
            : ($days === 1 ? get_string('reserveoneday', 'local_oksigeniaayvurenda')
            : get_string('reservedays', 'local_oksigeniaayvurenda', $days));
    }
}
