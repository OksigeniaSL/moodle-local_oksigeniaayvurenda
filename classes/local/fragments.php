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
 * Digital items only for the students of a course.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A chapter or some pages scanned for a course, as the law or a licence allows teaching to use them: only the students
 * enrolled in that course open it, and only until a date; the library always. The library keeps the record of what it
 * gave to which course, for whoever the rights are paid to: work, ISBN, pages, course, students, dates and how many
 * opened it.
 */
final class fragments {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int The longest description of the pages. */
    public const PAGES = 100;

    /**
     * The course a digital item is only for, if it is.
     *
     * @param int $digitalid
     * @return \stdClass|null
     */
    public static function of_item(int $digitalid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'fragment', ['digitalid' => $digitalid]) ?: null;
    }

    /**
     * Makes a digital item only for a course until a date, or for everyone it is for again (no course).
     *
     * @param int $digitalid
     * @param int $courseid 0 for none.
     * @param string $pages
     * @param int $timeend
     * @param int $now
     */
    public static function set(int $digitalid, int $courseid, string $pages, int $timeend, int $now): void {
        global $DB;
        $DB->delete_records(self::T . 'fragment', ['digitalid' => $digitalid]);
        if ($courseid && $DB->record_exists('course', ['id' => $courseid])) {
            $pages = trim(\core_text::substr(trim($pages), 0, self::PAGES));
            $DB->insert_record(self::T . 'fragment', ['digitalid' => $digitalid, 'courseid' => $courseid,
                'pages' => $pages !== '' ? $pages : null, 'timeend' => $timeend, 'timecreated' => $now]);
        }
    }

    /**
     * Whether someone may open a digital item only for a course: enrolled in it, before the date.
     *
     * @param \stdClass $fragment
     * @param int $userid
     * @param int $now
     * @return bool
     */
    public static function open(\stdClass $fragment, int $userid, int $now): bool {
        if (!$userid || (int) $fragment->timeend < $now) {
            return false;
        }
        $context = \context_course::instance((int) $fragment->courseid, IGNORE_MISSING);
        return $context && is_enrolled($context, $userid, '', true);
    }

    /**
     * The courses whose readings have a book of a library: those a part of it can be given to.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return array id => full name.
     */
    public static function courses(\stdClass $library, int $recordid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.fullname
               FROM {" . self::T . "readingitem} i
               JOIN {" . self::T . "readinglist} l ON l.id = i.listid
               JOIN {course} c ON c.id = l.courseid
              WHERE l.libraryid = :libraryid AND i.recordid = :recordid
           ORDER BY c.fullname",
            ['libraryid' => $library->id, 'recordid' => $recordid]
        );
        return array_map(fn($c) => format_string($c->fullname), $rows);
    }

    /**
     * The record of what a library's centre gave to its courses: one row per item, with its work, ISBN, pages, course,
     * students enrolled, dates and how many different people opened it.
     *
     * @param \stdClass $library
     * @return array[] title, isbn, pages, course, students, from, until, opened.
     */
    public static function log(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT f.id, f.courseid, f.pages, f.timeend, d.id AS digitalid, d.timecreated, r.title, r.isbn
               FROM {" . self::T . "fragment} f
               JOIN {" . self::T . "digital} d ON d.id = f.digitalid
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE d.entityid = :entityid
           ORDER BY d.timecreated, f.id",
            ['entityid' => $library->entityid]
        );
        $out = [];
        foreach ($rows as $row) {
            $context = \context_course::instance((int) $row->courseid, IGNORE_MISSING);
            $out[] = [
                'title' => $row->title,
                'isbn' => (string) $row->isbn,
                'pages' => (string) $row->pages,
                'course' => format_string((string) $DB->get_field('course', 'fullname', ['id' => $row->courseid])),
                'students' => $context ? count_enrolled_users($context, 'moodle/course:isincompletionreports') : 0,
                'from' => userdate((int) $row->timecreated, '%Y-%m-%d'),
                'until' => userdate((int) $row->timeend, '%Y-%m-%d'),
                'opened' => $DB->count_records_select(self::T . 'digitalread', 'digitalid = ?', [$row->digitalid]),
            ];
        }
        return $out;
    }

    /**
     * A course gone: its items stay, for the library only.
     *
     * @param int $courseid
     */
    public static function course_gone(int $courseid): void {
        global $DB;
        $DB->delete_records(self::T . 'fragment', ['courseid' => $courseid]);
    }
}
