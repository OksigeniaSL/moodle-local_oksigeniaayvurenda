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

namespace local_oksigeniaayvurenda\local;

/**
 * The memory at work: she has read everything and remembers everything. To each reader, what they left half read, a
 * book like the one they loved, what they were looking for last time, or one more of the corner they read most; on a
 * book, what else its readers took (only when enough of them did, so that no one is singled out). One thing at a time,
 * the same all day, another the next.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class memory {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How far back she remembers a note of the desk. */
    private const BACK = 365 * DAYSECS;

    /** @var int How long she remembers what someone looked for. */
    private const SEARCHED = 30 * DAYSECS;

    /** @var int Readers of a book there must be before she tells what else they took. */
    public const AMONG = 3;

    /** @var string The preference that keeps someone's last search. */
    public const PREF = 'local_oksigeniaayvurenda_lastsearch';

    /**
     * What the memory has to tell a reader today, if anything.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param int|null $now
     * @return array|null kind (unfinished, loved, more, searched, corner), say (what the memory says, as
     *     characters::say gives it), recordid (the book, if there is one), query (the search, if it is that).
     */
    public static function remembers(\stdClass $library, \stdClass $reader, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        if (bank::is($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $read = self::read((int) $reader->id);
        $notes = $DB->get_records_sql(
            "SELECT l.id, l.returnnote, l.timereturned, i.recordid, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.timereturned IS NOT NULL AND l.returnnote IS NOT NULL
                    AND l.timereturned >= :since AND " . bank::reading('l.libraryid') . "
           ORDER BY l.timereturned DESC, l.id DESC",
            ['readerid' => $reader->id, 'since' => $now - self::BACK],
            0,
            10
        );
        foreach ($notes as $note) {
            // Left half read, and not taken again since: it is still on its shelf.
            if ($note->returnnote === 'unfinished' && $read[$note->recordid] <= (int) $note->timereturned) {
                if (self::free($library, [(int) $note->recordid])) {
                    return self::say(
                        $library,
                        'unfinished',
                        'memoryunfinished',
                        ['title' => $note->title],
                        (int) $note->recordid,
                        'lee'
                    );
                }
            }
            if ($note->returnnote === 'loved' || $note->returnnote === 'more') {
                $like = self::like($library, (int) $note->recordid, $read, (int) $reader->id, $now);
                if ($like) {
                    return self::say(
                        $library,
                        $note->returnnote,
                        'memory' . $note->returnnote,
                        ['from' => $note->title, 'title' => $like->title],
                        (int) $like->id,
                        'senala'
                    );
                }
            }
        }
        // What they were looking for, if they have not taken anything since.
        $searched = json_decode((string) get_user_preferences(self::PREF, '', (int) $reader->userid), true) ?: [];
        if (
            !empty($searched['q']) && (int) ($searched['entity'] ?? 0) === $entityid
            && (int) ($searched['time'] ?? 0) >= $now - self::SEARCHED && max($read ?: [0]) < (int) $searched['time']
        ) {
            $say = self::say($library, 'searched', 'memorysearched', ['query' => $searched['q']], 0, 'piensa');
            if ($say) {
                $say['query'] = (string) $searched['q'];
            }
            return $say;
        }
        // One more of the corner they read most.
        $corner = self::corner_read($entityid, (int) $reader->id, $now);
        if ($corner !== null) {
            $book = self::pick(self::in_corner($library, $corner, $read), (int) $reader->id, $now);
            if ($book) {
                $label = '';
                foreach (catalogue::corners($library) as $known) {
                    $label = $known['code'] === $corner ? $known['label'] : $label;
                }
                if ($label !== '') {
                    return self::say(
                        $library,
                        'corner',
                        'memorycorner',
                        ['corner' => $label, 'title' => $book->title],
                        (int) $book->id,
                        'senala'
                    );
                }
            }
        }
        return null;
    }

    /**
     * What else the readers of a book took, the most taken first: only when at least AMONG readers took it, and each
     * one taken by two of them at least.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $limit
     * @return int[] Record ids.
     */
    public static function also(\stdClass $library, int $recordid, int $limit = 4): array {
        global $DB;
        if (bank::is($library)) {
            return [];
        }
        $params = ['recordid' => $recordid, 'entityid' => $library->entityid];
        $readers = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.readerid)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE i.recordid = :recordid AND i.entityid = :entityid AND " . bank::reading('l.libraryid'),
            $params
        );
        if ($readers < self::AMONG) {
            return [];
        }
        $rows = $DB->get_records_sql(
            "SELECT i2.recordid, COUNT(DISTINCT l2.readerid) AS readers
               FROM {" . self::T . "loan} l1
               JOIN {" . self::T . "item} i1 ON i1.id = l1.itemid
               JOIN {" . self::T . "loan} l2 ON l2.readerid = l1.readerid
               JOIN {" . self::T . "item} i2 ON i2.id = l2.itemid
              WHERE i1.recordid = :recordid AND i1.entityid = :entityid AND i2.recordid <> :other
                    AND " . bank::reading('l1.libraryid') . " AND " . bank::reading('l2.libraryid') . "
           GROUP BY i2.recordid
             HAVING COUNT(DISTINCT l2.readerid) >= 2
           ORDER BY COUNT(DISTINCT l2.readerid) DESC, i2.recordid",
            $params + ['other' => $recordid],
            0,
            $limit
        );
        return array_map('intval', array_keys($rows));
    }

    /**
     * Keeps what a reader looked for, for the memory to remember it.
     *
     * @param \stdClass $library
     * @param string $query
     * @param int|null $now
     */
    public static function searched(\stdClass $library, string $query, ?int $now = null): void {
        global $USER;
        $query = trim(\core_text::substr($query, 0, 100));
        if ($query === '' || !isloggedin() || isguestuser()) {
            return;
        }
        set_user_preference(self::PREF, json_encode(['q' => $query, 'entity' => (int) $library->entityid,
            'time' => $now ?? time()]), (int) $USER->id);
    }

    /**
     * The memory's words, if she is on.
     *
     * @param \stdClass $library
     * @param string $kind
     * @param string $key
     * @param array $a
     * @param int $recordid
     * @param string $pose
     * @return array|null
     */
    private static function say(\stdClass $library, string $kind, string $key, array $a, int $recordid, string $pose): ?array {
        $said = characters::say((int) $library->entityid, 'memory', $key, $a, $pose);
        return $said ? ['kind' => $kind, 'say' => $said, 'recordid' => $recordid, 'query' => ''] : null;
    }

    /**
     * What the memory tells whoever is cataloguing a book: that a class asked for it, or what else there is by its author.
     *
     * @param int $entityid
     * @param string $title
     * @param string[] $authors As the catalogue keeps them.
     * @return string What she says, or nothing.
     */
    public static function cataloguing(int $entityid, string $title, array $authors): string {
        global $DB;
        $wanted = \core_text::strtolower(trim($title));
        if ($wanted !== '') {
            $wishes = $DB->get_records_sql(
                "SELECT w.id, w.title, c.groupname
                   FROM {" . self::T . "wish} w
                   JOIN {" . self::T . "challenge} c ON c.id = w.challengeid
                  WHERE w.entityid = :entityid AND w.status = :status",
                ['entityid' => $entityid, 'status' => 'asked']
            );
            foreach ($wishes as $wish) {
                if (\core_text::strtolower(trim((string) $wish->title)) === $wanted) {
                    $said = characters::say($entityid, 'memory', 'memorycatalogwish', ['group' => (string) $wish->groupname]);
                    return $said ? $said['text'] : '';
                }
            }
        }
        foreach ($authors as $author) {
            $count = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ra.recordid)
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE a.entityid = :entityid AND a.kind = 'author' AND a.label = :label",
                ['entityid' => $entityid, 'label' => (string) $author]
            );
            if (!$count) {
                continue;
            }
            $top = $DB->get_records_sql(
                "SELECT r.id, r.title, COUNT(l.id) AS taken
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                   JOIN {" . self::T . "record} r ON r.id = ra.recordid
                   JOIN {" . self::T . "item} i ON i.recordid = r.id
                   JOIN {" . self::T . "loan} l ON l.itemid = i.id
                  WHERE a.entityid = :entityid AND a.kind = 'author' AND a.label = :label
               GROUP BY r.id, r.title
               ORDER BY COUNT(l.id) DESC",
                ['entityid' => $entityid, 'label' => (string) $author],
                0,
                1
            );
            $top = reset($top);
            $a = ['author' => catalogue::display_name((string) $author), 'count' => $count, 'title' => $top ? $top->title : ''];
            $said = characters::say($entityid, 'memory', $top ? 'memorycatalogauthortop' : 'memorycatalogauthor', $a);
            return $said ? $said['text'] : '';
        }
        return '';
    }

    /**
     * When a reader last took each book they took.
     *
     * @param int $readerid
     * @return array Record id => the time of the last loan.
     */
    private static function read(int $readerid): array {
        global $DB;
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT i.recordid, MAX(l.timeloaned)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid
           GROUP BY i.recordid",
            ['readerid' => $readerid]
        ));
    }

    /**
     * Which of some books have a copy free in the library now.
     *
     * @param \stdClass $library
     * @param int[] $recordids
     * @return int[]
     */
    private static function free(\stdClass $library, array $recordids): array {
        global $DB;
        if (!$recordids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($recordids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_fieldset_select(
            self::T . 'item',
            "DISTINCT recordid",
            "libraryid = :libraryid AND status = 'available' AND recordid $in",
            $params + ['libraryid' => $library->id]
        ));
    }

    /**
     * A book like one the reader loved, free here and not read by them: by its author, else its topic, else its corner.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param array $read
     * @param int $readerid
     * @param int $now
     * @return \stdClass|null With id and title.
     */
    private static function like(\stdClass $library, int $recordid, array $read, int $readerid, int $now): ?\stdClass {
        global $DB;
        foreach (['author', 'subject'] as $kind) {
            $ids = $DB->get_fieldset_sql(
                "SELECT DISTINCT ra2.recordid
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = :kind
                   JOIN {" . self::T . "recauth} ra2 ON ra2.authorityid = ra.authorityid
                  WHERE ra.recordid = :recordid AND ra2.recordid <> :other",
                ['kind' => $kind, 'recordid' => $recordid, 'other' => $recordid]
            );
            $ids = array_values(array_diff(array_map('intval', $ids), array_keys($read)));
            $book = self::pick(self::titles(self::free($library, array_slice($ids, 0, 500))), $readerid, $now);
            if ($book) {
                return $book;
            }
        }
        $class = $DB->get_field_sql(
            "SELECT a.code
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE ra.recordid = :recordid",
            ['recordid' => $recordid],
            IGNORE_MULTIPLE
        );
        $top = $class === false ? null : catalogue::top_class((string) $class);
        return $top === null ? null : self::pick(self::in_corner($library, $top, $read), $readerid, $now);
    }

    /**
     * The corner a reader took most from this year, if they took at least two books from it.
     *
     * @param int $entityid
     * @param int $readerid
     * @param int $now
     * @return string|null Its top class.
     */
    private static function corner_read(int $entityid, int $readerid, int $now): ?string {
        global $DB;
        $codes = $DB->get_fieldset_sql(
            "SELECT a.code
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE l.readerid = :readerid AND a.entityid = :entityid AND l.timeloaned >= :since
                    AND " . bank::reading('l.libraryid'),
            ['readerid' => $readerid, 'entityid' => $entityid, 'since' => $now - self::BACK]
        );
        $tops = array_count_values(array_filter(
            array_map(fn($c) => catalogue::top_class((string) $c), $codes),
            fn($t) => $t !== null
        ));
        if (!$tops) {
            return null;
        }
        arsort($tops);
        return reset($tops) >= 2 ? (string) key($tops) : null;
    }

    /**
     * Books of a corner free here that the reader has not read, a few hundred at most.
     *
     * @param \stdClass $library
     * @param string $top
     * @param array $read
     * @return \stdClass[] With id and title.
     */
    private static function in_corner(\stdClass $library, string $top, array $read): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT r.id, r.title
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = r.id
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE i.libraryid = :libraryid AND i.status = 'available'
                    AND " . catalogue::class_condition('a.code', $top, 'top')[0] . "
           ORDER BY r.id",
            ['libraryid' => $library->id] + catalogue::class_condition('a.code', $top, 'top')[1],
            0,
            400
        );
        return array_values(array_filter($rows, fn($r) => !isset($read[(int) $r->id])));
    }

    /**
     * The titles of some books.
     *
     * @param int[] $ids
     * @return \stdClass[] With id and title.
     */
    private static function titles(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return array_values($DB->get_records_select(self::T . 'record', "id $in", $params, 'id', 'id, title'));
    }

    /**
     * One of some books: the same all day for a reader, another the next day.
     *
     * @param \stdClass[] $books
     * @param int $readerid
     * @param int $now
     * @return \stdClass|null
     */
    private static function pick(array $books, int $readerid, int $now): ?\stdClass {
        if (!$books) {
            return null;
        }
        $books = array_values($books);
        return $books[crc32($readerid . '-' . date('Ymd', $now)) % count($books)];
    }
}
