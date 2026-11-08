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
 * The catalogue as readers see it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\search\suggester;
use local_oksigeniaayvurenda\core\text;

/**
 * Looking up the catalogue of a library: search by words, corners by classification, what came in last, and a record
 * with its copies. A record is shared by the libraries of its entity; whether it is free, out or only to read there is
 * told for the library the reader is in.
 */
final class catalogue {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Cards on a page of results. */
    public const PERPAGE = 24;

    /** @var int Matches looked at for ranking; a search that finds more shows the best of these. */
    private const CANDIDATES = 2000;

    /** @var int Colours of the drawn covers. */
    private const COVERS = 6;

    /** @var int[] Colours for the corners: their words are read, so not the coral one, which is for drawing. */
    private const CORNER_COLOURS = [0, 2, 3, 4, 5];

    /**
     * Every library, by name.
     *
     * @return \stdClass[]
     */
    public static function libraries(): array {
        global $DB;
        // The practice library of the guided help is not one of the centre's.
        return array_values(array_filter(
            $DB->get_records(self::T . 'library', null, 'name, id'),
            fn($library) => !practice::is((int) $library->entityid)
        ));
    }

    /**
     * The libraries someone may look up: of the centres where they have the capability or are a reader.
     *
     * @param int|null $userid The current user when not given.
     * @return \stdClass[]
     */
    public static function visible(?int $userid = null): array {
        $entities = array_flip(access::entities('local/oksigeniaayvurenda:view', $userid));
        return array_values(array_filter(self::libraries(), fn($library) => isset($entities[(int) $library->entityid])));
    }

    /**
     * A library by its short name or id; with nothing given, the only one there is.
     *
     * @param string $ref
     * @return \stdClass|null
     */
    public static function library(string $ref): ?\stdClass {
        global $DB;
        if ($ref !== '') {
            $library = $DB->get_record(self::T . 'library', ['slug' => $ref]);
            if (!$library && ctype_digit($ref)) {
                $library = $DB->get_record(self::T . 'library', ['id' => (int) $ref]);
            }
            return $library ?: null;
        }
        $all = self::visible();
        return count($all) === 1 ? $all[0] : null;
    }

    /**
     * Records that have every word, the best first: words in the title count most.
     *
     * @param \stdClass $library
     * @param string $query As typed.
     * @param int $page From 0.
     * @param bool $onlyfree Only records with a copy free in this library.
     * @param string $corner Only records in this corner (a top class), if given.
     * @param int $subject Only records about this topic (an authority), if given.
     * @return array total, reachable (how many can be paged), cards, suggestions, facets (corners and subjects of all the
     *     results).
     */
    public static function search(
        \stdClass $library,
        string $query,
        int $page = 0,
        bool $onlyfree = false,
        string $corner = '',
        int $subject = 0
    ): array {
        global $DB;
        $words = text::words($query);
        if (!$words) {
            return ['total' => 0, 'reachable' => 0, 'cards' => [], 'suggestions' => [],
                'facets' => ['corners' => [], 'subjects' => []]];
        }
        $conditions = [];
        $params = ['entityid' => $library->entityid];
        // An ISBN (from the back cover, with or without hyphens, in 10 or 13) finds its book, which no word of the title
        // would.
        $isbn = desk::cover_isbn(trim($query));
        if ($isbn !== null) {
            $conditions[] = 'r.isbn = :isbn';
            $params['isbn'] = $isbn;
        }
        // What a child writes: «un libro de dragones» is «dragones», and «dragones» finds «dragón».
        foreach ($isbn === null ? text::meaningful($words) : [] as $i => $word) {
            $starts = [$word];
            $stem = text::stem($word);
            if ($stem !== null) {
                $starts[] = $stem;
            }
            $either = [];
            foreach ($starts as $j => $start) {
                $escaped = $DB->sql_like_escape($start);
                $either[] = $DB->sql_like('r.searchtext', ':start' . $i . '_' . $j);
                $either[] = $DB->sql_like('r.searchtext', ':inner' . $i . '_' . $j);
                $params['start' . $i . '_' . $j] = $escaped . '%';
                $params['inner' . $i . '_' . $j] = '% ' . $escaped . '%';
            }
            $conditions[] = '(' . implode(' OR ', $either) . ')';
        }
        // The textbooks only a bank has stay out of the reading catalogue; in the bank itself they are found.
        $only = bank::is($library) ? [] : bank::only((int) $library->entityid);
        if ($only) {
            [$notin, $onlyparams] = $DB->get_in_or_equal($only, SQL_PARAMS_NAMED, 'only', false);
            $conditions[] = "r.id $notin";
            $params += $onlyparams;
        }
        if ($onlyfree) {
            $conditions[] = self::free_condition();
            $params['freelibrary'] = $library->id;
        }
        // A record whose copies were all withdrawn or lost, and with nothing to read online, is no longer in the catalogue
        // for its readers (the staff still find it, to correct it or to see its record of weeding).
        if (!access::can('local/oksigeniaayvurenda:desk', $library)) {
            $conditions[] = "(EXISTS (SELECT 1 FROM {" . self::T . "item} li
                                      WHERE li.recordid = r.id AND li.status NOT IN ('withdrawn', 'lost'))
                              OR EXISTS (SELECT 1 FROM {" . self::T . "digital} ld
                                          WHERE ld.recordid = r.id AND ld.kind <> 'voice'))";
        }
        if (self::is_corner($corner)) {
            [$incorner, $cornerparams] = self::class_condition('fa.code', $corner, 'fcorner');
            $conditions[] = "r.id IN (SELECT fra.recordid FROM {" . self::T . "recauth} fra
                JOIN {" . self::T . "authority} fa ON fa.id = fra.authorityid
               WHERE fa.entityid = :fentityid AND fa.kind = 'class' AND $incorner)";
            $params += ['fentityid' => $library->entityid] + $cornerparams;
        }
        if ($subject) {
            $conditions[] = 'r.id IN (SELECT sra.recordid FROM {' . self::T . 'recauth} sra WHERE sra.authorityid = :fsubject)';
            $params['fsubject'] = $subject;
        }
        $normalquery = implode(' ', $words);
        $where = 'r.entityid = :entityid AND ' . implode(' AND ', $conditions);
        $count = $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'record} r WHERE ' . $where, $params);
        $order = '';
        if ($count > self::CANDIDATES) {
            // More than are scored: those whose title starts with what was typed (with or without its article) come into
            // the candidates first; the search text begins with the title.
            $cases = [];
            foreach (['', 'el ', 'la ', 'los ', 'las ', 'un ', 'una ', 'the ', 'a ', 'an '] as $i => $article) {
                $cases[] = 'WHEN ' . $DB->sql_like('r.searchtext', ':lead' . $i) . ' THEN 0';
                $params['lead' . $i] = $article . $DB->sql_like_escape($normalquery) . '%';
            }
            $order = ' ORDER BY CASE ' . implode(' ', $cases) . ' ELSE 1 END, r.id';
        }
        $records = $DB->get_records_sql(
            'SELECT r.id, r.title, r.subtitle
               FROM {' . self::T . 'record} r
              WHERE ' . $where . $order,
            $params,
            0,
            self::CANDIDATES
        );
        $scored = [];
        foreach ($records as $record) {
            $title = ' ' . text::normalise($record->title . ' ' . $record->subtitle) . ' ';
            $score = 0;
            foreach ($words as $word) {
                // A whole word of the title counts most, then the start of one, then anywhere else (authors, topics).
                if (strpos($title, ' ' . $word . ' ') !== false) {
                    $score += 4;
                } else if (strpos($title, ' ' . $word) !== false) {
                    $score += 3;
                } else {
                    $score += 1;
                }
            }
            // A title that starts with what was typed, leaving out its article, goes first among equals.
            $bare = preg_replace('/^ (el|la|los|las|un|una|lo|the|a|an) /', ' ', $title);
            if (strpos($bare, ' ' . $normalquery) === 0) {
                $score += 2;
            }
            $scored[] = [$score, text::normalise($record->title), $record];
        }
        usort($scored, fn($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        $total = count($scored);
        // Only the candidates are paged (reachable); how many there are in all is the count.
        $all = max($total, $count);
        $slice = array_column(array_slice($scored, $page * self::PERPAGE, self::PERPAGE), 2);
        $suggestions = $total === 0 ? (new suggester(self::vocabulary((int) $library->entityid)))->suggest($query) : [];
        return ['total' => $all, 'reachable' => $total, 'cards' => self::cards($slice, $library), 'suggestions' => $suggestions,
            'facets' => self::facets(array_map(fn($row) => (int) $row[2]->id, $scored))];
    }

    /**
     * What the results of a search have: how many in each corner, and the topics most of them share.
     *
     * @param int[] $ids
     * @return array corners (code => count), subjects (id => label, count; the most shared first, eight at most).
     */
    private static function facets(array $ids): array {
        global $DB;
        $corners = [];
        $subjects = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $rows = $DB->get_recordset_sql(
                "SELECT ra.id, ra.recordid, a.id AS authorityid, a.kind, a.code, a.label
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid $in AND (a.kind = 'class' OR a.kind = 'subject')",
                $params
            );
            foreach ($rows as $row) {
                if ($row->kind === 'class') {
                    $top = self::top_class((string) $row->code);
                    if ($top !== null) {
                        $corners[$top][$row->recordid] = true;
                    }
                } else {
                    $subjects[$row->authorityid]['label'] = $row->label;
                    $subjects[$row->authorityid]['records'][$row->recordid] = true;
                }
            }
            $rows->close();
        }
        $corners = array_map('count', $corners);
        ksort($corners);
        $subjects = array_map(fn($s) => ['label' => $s['label'], 'count' => count($s['records'])], $subjects);
        uasort($subjects, fn($a, $b) => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);
        // A topic every result shares tells nothing: it is not offered.
        $subjects = array_filter($subjects, fn($s) => $s['count'] < count($ids));
        return ['corners' => $corners, 'subjects' => array_slice($subjects, 0, 8, true)];
    }

    /**
     * The corners of the library: the top classes of its classification, with how many records each has.
     *
     * @param \stdClass $library
     * @param bool $all Also the ones the library hides, for its page of corners.
     * @return array[] Each with code, label, default, count, hidden, order and colour, in the library's order.
     */
    public static function corners(\stdClass $library, bool $all = false): array {
        global $DB;
        // How many records each corner has: counted over every class of the catalogue (thousands of rows), so kept a
        // while for the centre, apart for a bank (where textbooks count) and for reading.
        $cache = \cache::make('local_oksigeniaayvurenda', 'corners');
        // A library under another (a classroom, a department) counts the books it has; the central one, the centre's.
        $mine = !empty($library->parentid) && !bank::is($library);
        $key = $library->entityid . '_' . (bank::is($library) ? 'bank' : 'reading') . ($mine ? '_' . $library->id : '');
        $counts = $cache->get($key);
        if ($counts === false) {
            $rows = $DB->get_recordset_sql(
                "SELECT ra.recordid, a.code
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE a.entityid = :entityid AND a.kind = 'class'"
                    . ($mine ? " AND EXISTS (SELECT 1 FROM {" . self::T . "item} i
                                            WHERE i.recordid = ra.recordid AND i.libraryid = :libraryid)" : ''),
                ['entityid' => $library->entityid, 'libraryid' => $library->id]
            );
            $records = [];
            $only = bank::is($library) ? [] : array_flip(bank::only((int) $library->entityid));
            foreach ($rows as $row) {
                $top = self::top_class((string) $row->code);
                if ($top !== null && !isset($only[$row->recordid])) {
                    $records[$top][$row->recordid] = true;
                }
            }
            $rows->close();
            $counts = array_map('count', $records);
            ksort($counts);
            $cache->set($key, $counts);
        }
        // A class may stand twice (two imports named it alike): one label for each code, the first.
        $labels = [];
        foreach (
            $DB->get_records_select(
                self::T . 'authority',
                "entityid = :entityid AND kind = 'class' AND " . $DB->sql_length('code') . ' <= 3',
                ['entityid' => $library->entityid],
                'id',
                'id, code, label'
            ) as $row
        ) {
            $labels[(string) $row->code] = $labels[(string) $row->code] ?? $row->label;
        }
        // Each library may name its corners its own way, order them and hide some.
        $own = (array) ((json_decode((string) ($library->settings ?? ''), true) ?: [])['corners'] ?? []);
        // With the UDC, the plugin's short names («Ciencias naturales»): a program's are long and in capitals
        // («CIENCIAS PURAS. CIENCIAS EXACTAS Y NATURALES. MATEMÁTICAS.»). Otherwise the catalogue's, tidied.
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $library->entityid]), true) ?: [];
        $udc = ($entity['classification'] ?? 'udc') === 'udc';
        $corners = [];
        $letters = 0;
        foreach ($counts as $code => $count) {
            // Figures in their order and colour; letters after them, coloured in turn.
            $digit = ctype_digit((string) $code);
            $place = $digit ? (int) $code : 10 + $letters++;
            $label = isset($labels[$code]) ? self::tidy_label((string) $labels[$code]) : null;
            if (($udc || $label === null) && get_string_manager()->string_exists('udc' . $code, 'local_oksigeniaayvurenda')) {
                $label = get_string('udc' . $code, 'local_oksigeniaayvurenda');
            }
            $default = $label ?? (string) $code;
            $mine = (array) ($own[$code] ?? []);
            $corners[] = ['code' => (string) $code, 'label' => trim((string) ($mine['label'] ?? '')) !== '' ? trim($mine['label'])
                : $default, 'default' => $default, 'count' => (int) $count, 'hidden' => !empty($mine['hidden']),
                'order' => (int) ($mine['order'] ?? ($place + 1) * 10),
                'colour' => self::CORNER_COLOURS[$place % count(self::CORNER_COLOURS)]];
        }
        usort($corners, fn($a, $b) => [$a['order'], $a['code']] <=> [$b['order'], $b['code']]);
        return $all ? $corners : array_values(array_filter($corners, fn($corner) => !$corner['hidden']));
    }

    /**
     * Keeps how a library names, orders and hides its corners.
     *
     * @param \stdClass $library
     * @param array $corners Code => label, order, hidden.
     */
    public static function save_corners(\stdClass $library, array $corners): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $settings['corners'] = [];
        foreach ($corners as $code => $corner) {
            if (!preg_match('/^\d$/', (string) $code)) {
                continue;
            }
            $settings['corners'][(string) $code] = ['label' => \core_text::substr(trim((string) ($corner['label'] ?? '')), 0, 80),
                'order' => (int) ($corner['order'] ?? 0), 'hidden' => !empty($corner['hidden'])];
        }
        $DB->update_record(self::T . 'library', (object) ['id' => $library->id, 'settings' => json_encode($settings),
            'timemodified' => time()]);
        $library->settings = json_encode($settings);
    }

    /**
     * The records of a corner, by title.
     *
     * @param \stdClass $library
     * @param string $code The top class.
     * @param int $page
     * @param bool $onlyfree Only records with a copy free in this library.
     * @return array{total: int, cards: array}
     */
    public static function corner(\stdClass $library, string $code, int $page = 0, bool $onlyfree = false): array {
        global $DB;
        [$incorner, $cornerparams] = self::class_condition('a.code', $code, 'code');
        $sql = "FROM {" . self::T . "record} r
               WHERE r.id IN (SELECT ra.recordid
                                FROM {" . self::T . "recauth} ra
                                JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                               WHERE a.entityid = :entityid AND a.kind = 'class' AND $incorner)";
        $params = ['entityid' => $library->entityid] + $cornerparams;
        if ($onlyfree) {
            $sql .= ' AND ' . self::free_condition();
            $params['freelibrary'] = $library->id;
        }
        $total = $DB->count_records_sql('SELECT COUNT(1) ' . $sql, $params);
        $records = $DB->get_records_sql(
            'SELECT r.id, r.title, r.subtitle ' . $sql . ' ORDER BY r.title, r.id',
            $params,
            $page * self::PERPAGE,
            self::PERPAGE
        );
        // As a search gives it, so that the page knows there is more than one page of it.
        return ['total' => $total, 'reachable' => $total, 'cards' => self::cards(array_values($records), $library),
            'suggestions' => [], 'facets' => ['corners' => [], 'subjects' => []]];
    }

    /**
     * What a class reads: books that several readers of a group lent lately (anonymity::FEW at least), never who.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int $yearid
     * @param int|null $now
     * @param int $limit
     * @return array Cards.
     */
    public static function class_reads(\stdClass $library, string $group, int $yearid, ?int $now = null, int $limit = 6): array {
        global $DB;
        $now = $now ?? time();
        $rows = $DB->get_records_sql(
            'SELECT i.recordid, COUNT(DISTINCT l.readerid) AS readers
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'member} m ON m.readerid = l.readerid AND m.yearid = :yearid
              WHERE i.entityid = :entityid AND m.groupname = :groupname AND l.timeloaned > :since
                AND ' . bank::reading('l.libraryid') . '
           GROUP BY i.recordid
             HAVING COUNT(DISTINCT l.readerid) >= :few
           ORDER BY readers DESC, i.recordid DESC',
            ['yearid' => $yearid, 'entityid' => $library->entityid, 'groupname' => $group, 'since' => $now - 120 * DAYSECS,
                'few' => anonymity::FEW],
            0,
            $limit
        );
        if (!$rows) {
            return [];
        }
        $records = $DB->get_records_list(self::T . 'record', 'id', array_keys($rows), '', 'id, title, subtitle');
        $ordered = [];
        foreach (array_keys($rows) as $id) {
            if (isset($records[$id])) {
                $ordered[] = $records[$id];
            }
        }
        return self::cards($ordered, $library);
    }

    /**
     * Ideas to start a search: the subjects lent most this school year and, for a reader, the one of their last book.
     *
     * @param \stdClass $library
     * @param int $since
     * @param int $readerid 0 for nobody in particular.
     * @return array[] id, label and last (the subject of the reader's last book).
     */
    public static function ideas(\stdClass $library, int $since, int $readerid = 0): array {
        global $DB;
        $ideas = [];
        if ($readerid) {
            $last = $DB->get_record_sql(
                "SELECT a.id, a.label
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                   JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'subject'
                  WHERE l.readerid = :readerid AND " . bank::reading('l.libraryid') . "
               ORDER BY l.timeloaned DESC, ra.sortorder",
                ['readerid' => $readerid],
                IGNORE_MULTIPLE
            );
            if ($last) {
                $ideas[(int) $last->id] = ['id' => (int) $last->id, 'label' => $last->label, 'last' => true];
            }
        }
        $top = $DB->get_records_sql(
            "SELECT a.id, a.label, COUNT(1) AS lent
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'subject'
              WHERE i.entityid = :entityid AND l.timeloaned > :since AND " . bank::reading('l.libraryid') . "
           GROUP BY a.id, a.label
           ORDER BY lent DESC, a.id",
            ['entityid' => $library->entityid, 'since' => $since],
            0,
            4
        );
        foreach ($top as $row) {
            if (count($ideas) < 3 && !isset($ideas[(int) $row->id])) {
                $ideas[(int) $row->id] = ['id' => (int) $row->id, 'label' => $row->label, 'last' => false];
            }
        }
        return array_values($ideas);
    }

    /**
     * What came in last to the library.
     *
     * @param \stdClass $library
     * @param int $limit
     * @param string $corner Only of this corner (the top class), when given.
     * @return array Cards.
     */
    public static function recent(\stdClass $library, int $limit = 12, string $corner = ''): array {
        global $DB;
        $params = ['libraryid' => $library->id];
        $where = '';
        if ($corner !== '') {
            $where = " AND i.recordid IN (SELECT ra.recordid
                                           FROM {" . self::T . "recauth} ra
                                           JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                                          WHERE a.entityid = :entityid AND a.kind = 'class' AND " .
                self::class_condition('a.code', $corner, 'code')[0] . ')';
            $params += ['entityid' => $library->entityid] + self::class_condition('a.code', $corner, 'code')[1];
        }
        $latest = $DB->get_records_sql(
            'SELECT i.recordid, MAX(COALESCE(i.timeacquired, i.timecreated)) AS acquired
               FROM {' . self::T . 'item} i
              WHERE i.libraryid = :libraryid' . $where . '
           GROUP BY i.recordid
           ORDER BY acquired DESC, i.recordid DESC',
            $params,
            0,
            $limit
        );
        if (!$latest) {
            return [];
        }
        $records = $DB->get_records_list(self::T . 'record', 'id', array_keys($latest), '', 'id, title, subtitle');
        $ordered = [];
        foreach (array_keys($latest) as $id) {
            if (isset($records[$id])) {
                $ordered[] = $records[$id];
            }
        }
        return self::cards($ordered, $library);
    }

    /**
     * A record with everything a reader wants to know: who wrote it, what it is about and where its copies are.
     *
     * @param int $id
     * @param \stdClass $library
     * @return array|null
     */
    public static function record(int $id, \stdClass $library): ?array {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $id, 'entityid' => $library->entityid]);
        if (!$record) {
            return null;
        }
        $authorities = $DB->get_records_sql(
            'SELECT ra.id, ra.role, ra.sortorder, a.kind, a.code, a.label
               FROM {' . self::T . 'recauth} ra
               JOIN {' . self::T . 'authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = :recordid
           ORDER BY ra.sortorder, ra.id',
            ['recordid' => $id]
        );
        $by = [];
        foreach ($authorities as $authority) {
            $label = $authority->kind === 'author' ? self::display_name($authority->label) : $authority->label;
            $by[$authority->kind][] = ['label' => $label, 'role' => $authority->role, 'code' => $authority->code];
        }
        $libraries = $DB->get_records_menu(self::T . 'library', ['entityid' => $library->entityid], '', 'id, name');
        $items = $DB->get_records_select(
            self::T . 'item',
            "recordid = :recordid AND status <> 'lost' AND status <> 'withdrawn'",
            ['recordid' => $id],
            'libraryid, barcode'
        );
        $locations = self::labels(array_column($items, 'locationid'));
        $copies = [];
        foreach ($items as $item) {
            $copies[] = [
                'id' => (int) $item->id,
                'libraryid' => (int) $item->libraryid,
                'here' => (int) $item->libraryid === (int) $library->id,
                'library' => $libraries[$item->libraryid] ?? '',
                'state' => self::state([$item]),
                'location' => $locations[$item->locationid] ?? null,
                'callnumber' => trim(implode(' ', array_filter([$item->callnumber1, $item->callnumber2, $item->callnumber3]))),
                'barcode' => $item->barcode,
            ];
        }
        usort($copies, fn($a, $b) => [$b['here'], $a['library']] <=> [$a['here'], $b['library']]);
        $card = self::cards([$record], $library)[0];
        return ['record' => $record, 'card' => $card, 'authorities' => $by, 'copies' => $copies];
    }

    /**
     * The words of an entity's catalogue, cached.
     *
     * @param int $entityid
     * @return int[]
     */
    public static function vocabulary(int $entityid): array {
        global $DB;
        $cache = \cache::make('local_oksigeniaayvurenda', 'vocabulary');
        $vocabulary = $cache->get($entityid);
        if ($vocabulary === false) {
            $rows = $DB->get_recordset(self::T . 'record', ['entityid' => $entityid], '', 'id, searchtext');
            $texts = function () use ($rows) {
                foreach ($rows as $row) {
                    yield (string) $row->searchtext;
                }
            };
            $vocabulary = suggester::vocabulary($texts());
            $rows->close();
            $cache->set($entityid, $vocabulary);
        }
        return $vocabulary;
    }

    /**
     * Forgets what is cached about an entity's catalogue, after an import or a change.
     *
     * @param int $entityid
     */
    public static function purge(int $entityid): void {
        \cache::make('local_oksigeniaayvurenda', 'vocabulary')->delete($entityid);
        global $DB;
        $keys = [$entityid . '_reading', $entityid . '_bank'];
        foreach ($DB->get_fieldset_select(self::T . 'library', 'id', 'entityid = ? AND parentid IS NOT NULL', [$entityid]) as $id) {
            $keys[] = $entityid . '_reading_' . $id;
        }
        \cache::make('local_oksigeniaayvurenda', 'corners')->delete_many($keys);
    }

    /**
     * Cards of some records, in the order given (for a list someone made, such as a course's readings).
     *
     * @param int[] $ids
     * @param \stdClass $library
     * @return array[] As the search gives them, by record id.
     */
    public static function cards_of(array $ids, \stdClass $library): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            self::T . 'record',
            "id $in AND entityid = :entityid",
            $params + ['entityid' => $library->entityid],
            '',
            'id, title, subtitle'
        );
        $cards = [];
        foreach (self::cards(array_values($records), $library) as $card) {
            $cards[$card['id']] = $card;
        }
        return $cards;
    }

    /**
     * Cards of records: title, authors, the colour of the drawn cover and whether it is free in this library.
     *
     * @param \stdClass[] $records With id, title and subtitle.
     * @param \stdClass $library
     * @return array[]
     */
    private static function cards(array $records, \stdClass $library): array {
        global $DB;
        if (!$records) {
            return [];
        }
        $ids = array_map(fn($r) => (int) $r->id, $records);
        $covers = covers::urls($ids);
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $items = $DB->get_records_select(
            self::T . 'item',
            'recordid ' . $in . " AND status <> 'lost' AND status <> 'withdrawn'",
            $params,
            '',
            'id, recordid, libraryid, status, itemtype, locationid'
        );
        $byrecord = [];
        foreach ($items as $item) {
            $byrecord[$item->recordid][] = $item;
        }
        $authors = [];
        $classes = [];
        $links = $DB->get_records_sql(
            "SELECT ra.id, ra.recordid, a.kind, a.code, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid $in AND (a.kind = 'author' OR a.kind = 'class')
           ORDER BY ra.sortorder, ra.id",
            $params
        );
        foreach ($links as $link) {
            if ($link->kind === 'author' && count($authors[$link->recordid] ?? []) < 2) {
                $authors[$link->recordid][] = self::display_name($link->label);
            } else if ($link->kind === 'class' && !isset($classes[$link->recordid])) {
                $classes[$link->recordid] = self::top_class((string) $link->code);
            }
        }
        $here = [];
        foreach ($byrecord as $recordid => $list) {
            $here[$recordid] = array_values(array_filter($list, fn($i) => (int) $i->libraryid === (int) $library->id));
        }
        $locations = self::labels(array_column(array_merge(...array_values($here ?: [[]])), 'locationid'));
        $cards = [];
        foreach ($records as $record) {
            $mine = $here[$record->id] ?? [];
            $state = $mine ? self::state($mine) : (isset($byrecord[$record->id]) ? 'elsewhere' : 'none');
            $location = null;
            foreach ($mine as $item) {
                if ($item->status === 'available' && $item->locationid) {
                    $location = $locations[$item->locationid] ?? null;
                    break;
                }
            }
            $class = $classes[$record->id] ?? null;
            $cards[] = [
                'cover' => $covers[(int) $record->id] ?? null,
                'id' => (int) $record->id,
                'title' => $record->title,
                'subtitle' => $record->subtitle,
                'authors' => implode(' · ', $authors[$record->id] ?? []),
                'colour' => ($class !== null ? (int) $class : (int) $record->id) % self::COVERS,
                'state' => $state,
                'location' => $location,
            ];
        }
        return $cards;
    }

    /**
     * The first authors of a record, as a card shows them.
     *
     * @param int $recordid
     * @return string
     */
    public static function authors_of(int $recordid): string {
        global $DB;
        $labels = $DB->get_fieldset_sql(
            "SELECT a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = :recordid AND a.kind = 'author'
           ORDER BY ra.sortorder, ra.id",
            ['recordid' => $recordid]
        );
        return implode(' · ', array_map(fn($label) => self::display_name($label), array_slice($labels, 0, 2)));
    }

    /**
     * SQL that keeps records with a copy free to take home from a library (parameter freelibrary).
     *
     * @return string
     */
    private static function free_condition(): string {
        return "EXISTS (SELECT 1
                          FROM {" . self::T . "item} fi
                         WHERE fi.recordid = r.id AND fi.libraryid = :freelibrary
                               AND fi.status = 'available' AND fi.itemtype <> 'reference')";
    }

    /**
     * Whether copies are free, all out, set aside for someone who reserved them, only to read in the library, or not
     * lent.
     *
     * @param \stdClass[] $items
     * @return string free, out, held or consult.
     */
    private static function state(array $items): string {
        $lendable = array_filter($items, fn($i) => $i->itemtype !== 'reference');
        if (!$lendable) {
            return 'consult';
        }
        foreach ($lendable as $item) {
            if ($item->status === 'available') {
                return 'free';
            }
        }
        return array_filter($lendable, fn($i) => $i->status !== 'held') ? 'out' : 'held';
    }

    /**
     * Labels of authorities by id.
     *
     * @param array $ids
     * @return string[]
     */
    private static function labels(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return $DB->get_records_select_menu(self::T . 'authority', 'id ' . $in, $params, '', 'id, label');
    }

    /**
     * A label as a person writes it: without the dots at the end, and in lower case with its first letter in capitals
     * when it came all in capitals.
     *
     * @param string $label
     * @return string
     */
    public static function tidy_label(string $label): string {
        $label = trim(preg_replace('/[.\s]+$/u', '', trim($label)));
        if ($label !== '' && \core_text::strtoupper($label) === $label && \core_text::strtolower($label) !== $label) {
            // Each sentence with its first letter in capitals: «Ciencias puras. Matemáticas».
            $label = implode('. ', array_map(
                fn($part) => \core_text::strtoupper(\core_text::substr($part, 0, 1)) . \core_text::substr($part, 1),
                explode('. ', \core_text::strtolower($label))
            ));
        }
        return $label;
    }

    /**
     * The top class of a classification code: its first digit (UDC and Dewey alike).
     *
     * @param string $code
     * @return string|null
     */
    public static function top_class(string $code): ?string {
        // A number, or a number after a letter or two («J 821», children's books in the UDC): its first figure.
        if (preg_match('/^\s*(?:[A-Za-z]{1,3}\s+)?(\d)/', $code, $matches)) {
            return $matches[1];
        }
        // Letters alone (N for novels, P for poetry, C for comics), as many school and public libraries sort.
        return preg_match('/^\s*([A-Za-z]{1,3})(?=$|[\s.\-\/])/', $code, $matches) ? strtoupper($matches[1]) : null;
    }

    /**
     * Whether a corner is one: a figure or up to three letters.
     *
     * @param string $corner
     * @return bool
     */
    public static function is_corner(string $corner): bool {
        return (bool) preg_match('/^(\d+|[A-Z]{1,3})$/', $corner);
    }

    /**
     * The SQL that says a class code is in a corner, as top_class() reads it: «8» takes 821 and «J 821»; «N» takes N and
     * «N DUN», not NA.
     *
     * @param string $field The class code, as alias.code.
     * @param string $corner
     * @param string $name Prefix of the parameters.
     * @return array SQL and its parameters.
     */
    public static function class_condition(string $field, string $corner, string $name): array {
        global $DB;
        $corner = strtoupper(trim($corner));
        $escaped = $DB->sql_like_escape($corner);
        $patterns = ctype_digit($corner)
            ? [$escaped . '%', '_ ' . $escaped . '%', '__ ' . $escaped . '%', '___ ' . $escaped . '%']
            : [$escaped, $escaped . ' %', $escaped . '-%', $escaped . '.%', $escaped . '/%'];
        $sql = [];
        $params = [];
        foreach ($patterns as $i => $pattern) {
            $sql[] = $DB->sql_like($field, ':' . $name . $i, false);
            $params[$name . $i] = $pattern;
        }
        return ['(' . implode(' OR ', $sql) . ')', $params];
    }

    /**
     * An author as people say it: «Ana Montes» for «Montes, Ana»; bodies and other forms stay as they are.
     *
     * @param string $name
     * @return string
     */
    public static function display_name(string $name): string {
        if (preg_match('/^([^,]+),\s*([^,]+)$/u', trim($name), $parts)) {
            return trim($parts[2]) . ' ' . trim($parts[1]);
        }
        return $name;
    }
}
