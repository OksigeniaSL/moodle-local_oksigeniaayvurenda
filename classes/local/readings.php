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
 * The reading lists of the courses.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Each course can have its list of readings, which its teacher makes from the library's own search: by topic, required
 * or recommended, with a word on each; its students see, in the course, whether each one is free and where. The library
 * sees, from all the lists, how many students each required title has against its copies. No «read» and no marks: it is
 * a list of books, not a task.
 */
final class readings {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int The longest topic. */
    public const TOPIC = 100;

    /** @var int The longest note. */
    public const NOTE = 255;

    /**
     * The library a course takes its books from: that of the centre whose category holds the course (on a shared site);
     * else the site's main seat or first central library.
     *
     * @param \stdClass $course
     * @return \stdClass|null
     */
    public static function library_for(\stdClass $course): ?\stdClass {
        global $DB, $USER;
        $libraries = $DB->get_records_select(self::T . 'library', 'kind <> :bank', ['bank' => bank::KIND], 'id');
        if (!$libraries) {
            return null;
        }
        $path = (string) $DB->get_field('course_categories', 'path', ['id' => $course->category]);
        $categories = array_map('intval', array_filter(explode('/', $path)));
        if ($categories) {
            [$in, $params] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
            $entityids = array_map('intval', $DB->get_fieldset_select(self::T . 'entity', 'id', "categoryid $in", $params));
            $theirs = array_filter($libraries, fn($l) => in_array((int) $l->entityid, $entityids, true));
            if ($theirs) {
                return libraries::featured(array_values($theirs), (int) $USER->id);
            }
        }
        return libraries::featured(array_values($libraries), (int) $USER->id);
    }

    /**
     * The list of a course, if it has one.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function of_course(int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'readinglist', ['courseid' => $courseid]) ?: null;
    }

    /**
     * The list of a course, started if it had none.
     *
     * @param \stdClass $course
     * @param \stdClass $library
     * @param int $userid
     * @return \stdClass
     */
    public static function start(\stdClass $course, \stdClass $library, int $userid): \stdClass {
        global $DB;
        $list = self::of_course((int) $course->id);
        if ($list) {
            return $list;
        }
        $now = time();
        $list = (object) ['courseid' => $course->id, 'libraryid' => $library->id, 'createdby' => $userid ?: null,
            'timecreated' => $now, 'timemodified' => $now];
        $list->id = $DB->insert_record(self::T . 'readinglist', $list);
        return $list;
    }

    /**
     * Adds a book of the library's centre to a list, at its end; nothing if it is already there.
     *
     * @param \stdClass $list
     * @param int $recordid
     * @param string $topic
     * @param bool $required
     * @return bool Whether it was added.
     */
    public static function add(\stdClass $list, int $recordid, string $topic = '', bool $required = false): bool {
        global $DB;
        $library = $DB->get_record(self::T . 'library', ['id' => $list->libraryid], 'id, entityid', MUST_EXIST);
        if (
            !$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid])
            || $DB->record_exists(self::T . 'readingitem', ['listid' => $list->id, 'recordid' => $recordid])
        ) {
            return false;
        }
        $last = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::T . 'readingitem} WHERE listid = ?', [$list->id]);
        $DB->insert_record(self::T . 'readingitem', ['listid' => $list->id, 'recordid' => $recordid,
            'topic' => self::clean($topic, self::TOPIC), 'required' => (int) $required, 'note' => null,
            'sortorder' => $last + 1, 'timecreated' => time()]);
        self::touch($list);
        return true;
    }

    /**
     * Changes a book of a list: its topic, whether it is required, and the teacher's word on it.
     *
     * @param \stdClass $list
     * @param int $itemid
     * @param string $topic
     * @param bool $required
     * @param string $note
     */
    public static function change(\stdClass $list, int $itemid, string $topic, bool $required, string $note): void {
        global $DB;
        $item = $DB->get_record(self::T . 'readingitem', ['id' => $itemid, 'listid' => $list->id]);
        if (!$item) {
            return;
        }
        $DB->update_record(self::T . 'readingitem', (object) ['id' => $item->id, 'topic' => self::clean($topic, self::TOPIC),
            'required' => (int) $required, 'note' => self::clean($note, self::NOTE)]);
        self::touch($list);
    }

    /**
     * Takes a book off a list.
     *
     * @param \stdClass $list
     * @param int $itemid
     */
    public static function remove(\stdClass $list, int $itemid): void {
        global $DB;
        $DB->delete_records(self::T . 'readingitem', ['id' => $itemid, 'listid' => $list->id]);
        self::touch($list);
    }

    /**
     * Moves a book one place up (-1) or down (1) among the books of its topic, as the list shows them.
     *
     * @param \stdClass $list
     * @param int $itemid
     * @param int $by
     */
    public static function move(\stdClass $list, int $itemid, int $by): void {
        global $DB;
        $items = array_values($DB->get_records(self::T . 'readingitem', ['listid' => $list->id], 'sortorder, id', 'id, topic'));
        $ids = array_map(fn($i) => (int) $i->id, $items);
        $at = array_search($itemid, $ids, true);
        if ($at === false) {
            return;
        }
        $topic = (string) $items[$at]->topic;
        $to = $at;
        do {
            $to += $by < 0 ? -1 : 1;
        } while ($to >= 0 && $to < count($ids) && (string) $items[$to]->topic !== $topic);
        if ($to < 0 || $to >= count($ids)) {
            return;
        }
        [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
        foreach ($ids as $order => $id) {
            $DB->set_field(self::T . 'readingitem', 'sortorder', $order + 1, ['id' => $id]);
        }
        self::touch($list);
    }

    /**
     * The books of a list, in order, each with its card (title, authors, cover, whether it is free and where) and
     * whether it can be read online.
     *
     * @param \stdClass $list
     * @param \stdClass $library
     * @return array[] id, recordid, topic, required, note, card.
     */
    public static function items(\stdClass $list, \stdClass $library): array {
        global $DB;
        $items = $DB->get_records(self::T . 'readingitem', ['listid' => $list->id], 'sortorder, id');
        $cards = screens::cards(
            array_values(catalogue::cards_of(array_map(fn($i) => (int) $i->recordid, $items), $library)),
            $library
        );
        $byid = array_column($cards, null, 'id');
        // What its students may read online: a file or a link (not a voice) published for them or for anyone.
        $digital = [];
        if ($items) {
            [$in, $params] = $DB->get_in_or_equal(array_map(fn($i) => (int) $i->recordid, $items), SQL_PARAMS_NAMED);
            $digital = $DB->get_fieldset_sql(
                "SELECT DISTINCT recordid FROM {" . self::T . "digital}
                  WHERE status = 'published' AND kind <> 'voice' AND recordid $in
                        AND (" . $DB->sql_like('audience', ':students') . " OR " . $DB->sql_like('audience', ':public') . ")",
                $params + ['students' => '%students%', 'public' => '%public%']
            );
        }
        $out = [];
        foreach ($items as $item) {
            if (!isset($byid[$item->recordid])) {
                continue;
            }
            $out[] = ['id' => (int) $item->id, 'recordid' => (int) $item->recordid, 'topic' => (string) $item->topic,
                'required' => (bool) $item->required, 'note' => (string) ($item->note ?? ''), 'card' => $byid[$item->recordid],
                'digital' => in_array((int) $item->recordid, array_map('intval', $digital), true)];
        }
        return $out;
    }

    /**
     * What the courses ask of a library: each title some course requires, with the courses, their students, the
     * library's copies and the readers waiting for it; the most students per copy first.
     *
     * @param \stdClass $library
     * @return array[] recordid, title, courses, students, copies, waiting.
     */
    public static function demand(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT i.id, i.recordid, l.courseid, r.title
               FROM {" . self::T . "readingitem} i
               JOIN {" . self::T . "readinglist} l ON l.id = i.listid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND i.required = 1",
            ['libraryid' => $library->id]
        );
        $students = [];
        $titles = [];
        foreach ($rows as $row) {
            $courseid = (int) $row->courseid;
            if (!isset($students[$courseid])) {
                $context = \context_course::instance($courseid, IGNORE_MISSING);
                $students[$courseid] = $context ? count_enrolled_users($context, 'moodle/course:isincompletionreports') : 0;
            }
            $titles[$row->recordid]['title'] = $row->title;
            $titles[$row->recordid]['courses'][$courseid] = true;
        }
        $out = [];
        foreach ($titles as $recordid => $title) {
            $copies = $DB->count_records_select(self::T . 'item', "recordid = :recordid AND libraryid = :libraryid
                AND status <> 'lost' AND status <> 'withdrawn'", ['recordid' => $recordid, 'libraryid' => $library->id]);
            $waiting = $DB->count_records(self::T . 'hold', ['recordid' => $recordid, 'libraryid' => $library->id,
                'status' => 'waiting']);
            $out[] = ['recordid' => (int) $recordid, 'title' => $title['title'],
                'courses' => array_map('intval', array_keys($title['courses'])),
                'students' => array_sum(array_intersect_key($students, $title['courses'])), 'copies' => $copies,
                'waiting' => $waiting];
        }
        // No copy first, then the most students per copy.
        $need = fn(array $row) => [$row['copies'] ? 0 : 1, $row['students'] / max(1, $row['copies'])];
        usort($out, fn($a, $b) => $need($b) <=> $need($a));
        return $out;
    }

    /**
     * A course gone takes its reading list with it.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $list = self::of_course((int) $event->objectid);
        if ($list) {
            $DB->delete_records(self::T . 'readingitem', ['listid' => $list->id]);
            $DB->delete_records(self::T . 'readinglist', ['id' => $list->id]);
        }
    }

    /**
     * Text cut to its length, '' for nothing.
     *
     * @param string $text
     * @param int $length
     * @return string|null
     */
    private static function clean(string $text, int $length): ?string {
        $text = trim(\core_text::substr(trim($text), 0, $length));
        return $text !== '' ? $text : null;
    }

    /**
     * Marks a list as changed now.
     *
     * @param \stdClass $list
     */
    private static function touch(\stdClass $list): void {
        global $DB;
        $DB->set_field(self::T . 'readinglist', 'timemodified', time(), ['id' => $list->id]);
    }
}
