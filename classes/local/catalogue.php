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
        $all = self::libraries();
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
     * @return array total, cards, suggestions, facets (corners and subjects of all the results).
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
            return ['total' => 0, 'cards' => [], 'suggestions' => [], 'facets' => ['corners' => [], 'subjects' => []]];
        }
        $conditions = [];
        $params = ['entityid' => $library->entityid];
        foreach ($words as $i => $word) {
            $escaped = $DB->sql_like_escape($word);
            $conditions[] = '(' . $DB->sql_like('r.searchtext', ':start' . $i) . ' OR ' .
                $DB->sql_like('r.searchtext', ':inner' . $i) . ')';
            $params['start' . $i] = $escaped . '%';
            $params['inner' . $i] = '% ' . $escaped . '%';
        }
        if ($onlyfree) {
            $conditions[] = self::free_condition();
            $params['freelibrary'] = $library->id;
        }
        if (preg_match('/^\d$/', $corner)) {
            $conditions[] = "r.id IN (SELECT fra.recordid FROM {" . self::T . "recauth} fra
                JOIN {" . self::T . "authority} fa ON fa.id = fra.authorityid
               WHERE fa.entityid = :fentityid AND fa.kind = 'class' AND " . $DB->sql_like('fa.code', ':fcorner') . ")";
            $params += ['fentityid' => $library->entityid, 'fcorner' => $corner . '%'];
        }
        if ($subject) {
            $conditions[] = 'r.id IN (SELECT sra.recordid FROM {' . self::T . 'recauth} sra WHERE sra.authorityid = :fsubject)';
            $params['fsubject'] = $subject;
        }
        $records = $DB->get_records_sql(
            'SELECT r.id, r.title, r.subtitle
               FROM {' . self::T . 'record} r
              WHERE r.entityid = :entityid AND ' . implode(' AND ', $conditions),
            $params,
            0,
            self::CANDIDATES
        );
        $normalquery = implode(' ', $words);
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
        $slice = array_column(array_slice($scored, $page * self::PERPAGE, self::PERPAGE), 2);
        $suggestions = $total === 0 ? (new suggester(self::vocabulary((int) $library->entityid)))->suggest($query) : [];
        return ['total' => $total, 'cards' => self::cards($slice, $library), 'suggestions' => $suggestions,
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
        $rows = $DB->get_recordset_sql(
            "SELECT ra.recordid, a.code
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE a.entityid = :entityid AND a.kind = 'class'",
            ['entityid' => $library->entityid]
        );
        $records = [];
        foreach ($rows as $row) {
            $top = self::top_class((string) $row->code);
            if ($top !== null) {
                $records[$top][$row->recordid] = true;
            }
        }
        $rows->close();
        ksort($records);
        $labels = $DB->get_records_select_menu(
            self::T . 'authority',
            "entityid = :entityid AND kind = 'class' AND " . $DB->sql_length('code') . ' = 1',
            ['entityid' => $library->entityid],
            '',
            'code, label'
        );
        // Each library may name its corners its own way, order them and hide some.
        $own = (array) ((json_decode((string) ($library->settings ?? ''), true) ?: [])['corners'] ?? []);
        $corners = [];
        foreach ($records as $code => $ids) {
            $label = $labels[$code] ?? null;
            if ($label === null && get_string_manager()->string_exists('udc' . $code, 'local_oksigeniaayvurenda')) {
                $label = get_string('udc' . $code, 'local_oksigeniaayvurenda');
            }
            $default = $label ?? (string) $code;
            $mine = (array) ($own[$code] ?? []);
            $corners[] = ['code' => (string) $code, 'label' => trim((string) ($mine['label'] ?? '')) !== '' ? trim($mine['label'])
                : $default, 'default' => $default, 'count' => count($ids), 'hidden' => !empty($mine['hidden']),
                'order' => (int) ($mine['order'] ?? ((int) $code + 1) * 10),
                'colour' => self::CORNER_COLOURS[((int) $code) % count(self::CORNER_COLOURS)]];
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
        $sql = "FROM {" . self::T . "record} r
               WHERE r.id IN (SELECT ra.recordid
                                FROM {" . self::T . "recauth} ra
                                JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                               WHERE a.entityid = :entityid AND a.kind = 'class' AND " . $DB->sql_like('a.code', ':code') . ")";
        $params = ['entityid' => $library->entityid, 'code' => $DB->sql_like_escape($code) . '%'];
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
        return ['total' => $total, 'cards' => self::cards(array_values($records), $library)];
    }

    /**
     * What came in last to the library.
     *
     * @param \stdClass $library
     * @param int $limit
     * @return array Cards.
     */
    public static function recent(\stdClass $library, int $limit = 12): array {
        global $DB;
        $latest = $DB->get_records_sql(
            'SELECT i.recordid, MAX(COALESCE(i.timeacquired, i.timecreated)) AS acquired
               FROM {' . self::T . 'item} i
              WHERE i.libraryid = :libraryid
           GROUP BY i.recordid
           ORDER BY acquired DESC, i.recordid DESC',
            ['libraryid' => $library->id],
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
     * The top class of a classification code: its first digit (UDC and Dewey alike).
     *
     * @param string $code
     * @return string|null
     */
    private static function top_class(string $code): ?string {
        return preg_match('/^\s*(\d)/', $code, $matches) ? $matches[1] : null;
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
