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
 * The shelves of a library: its books as spines, in the order they stand in the room.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\callnumber;
use local_oksigeniaayvurenda\core\shelf_order;
use local_oksigeniaayvurenda\core\spine;

/**
 * Walking along the shelves, as in the library: one place (or one corner) at a time, book after book by call number,
 * with the gap of the ones that are out. Each copy keeps its place in the order (shelfkey), so a page is one question
 * to the database even with 20,000 copies. One spine a title: its copies in the same place stand together.
 */
final class stacks {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Spines on a page. */
    public const PAGE = 60;

    /** @var int Copies without their key that a page puts in order before showing itself. */
    private const FILL = 3000;

    /** @var string[] The colours of the corners, as the catalogue gives them (catalogue::CORNER_COLOURS): green, sun,
     * light green, night and the wood of the cream covers. */
    private const CORNERS = ['#17574c', '#f0b43c', '#2c7a6a', '#0f3a33', '#c9a46a'];

    /**
     * Puts every copy of an entity in its place in the order, after an import: only the ones whose place changed.
     *
     * @param int $entityid
     * @return int How many changed.
     */
    public static function refresh_keys(int $entityid): int {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT i.id, i.recordid, i.callnumber1, i.callnumber2, i.callnumber3, i.shelfkey, r.title
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE i.entityid = :entityid",
            ['entityid' => $entityid]
        );
        $changed = 0;
        foreach ($rows as $row) {
            $key = shelf_order::key(
                $row->callnumber1,
                $row->callnumber2,
                $row->callnumber3,
                (string) $row->title,
                (int) $row->recordid
            );
            if ($key !== $row->shelfkey) {
                $DB->set_field(self::T . 'item', 'shelfkey', $key, ['id' => $row->id]);
                $changed++;
            }
        }
        $rows->close();
        return $changed;
    }

    /**
     * Puts in order the copies of a library that came without their place (added at the desk, from the phone…).
     *
     * @param int $libraryid
     */
    public static function fill(int $libraryid): void {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT i.id, i.recordid, i.callnumber1, i.callnumber2, i.callnumber3, r.title
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE i.libraryid = :libraryid AND i.shelfkey IS NULL",
            ['libraryid' => $libraryid],
            0,
            self::FILL
        );
        foreach ($rows as $row) {
            $DB->set_field(self::T . 'item', 'shelfkey', shelf_order::key(
                $row->callnumber1,
                $row->callnumber2,
                $row->callnumber3,
                (string) $row->title,
                (int) $row->recordid
            ), ['id' => $row->id]);
        }
    }

    /**
     * The places of a library (its locations), with how many titles stand in each and their colour when their name is
     * one.
     *
     * @param \stdClass $library
     * @return array[] id, label, count and colour (#rrggbb or null), by name; id 0 for copies without a place.
     */
    public static function places(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT COALESCE(i.locationid, 0) AS place, COUNT(DISTINCT i.recordid) AS titles
               FROM {" . self::T . "item} i
              WHERE i.libraryid = :libraryid AND i.status <> 'lost' AND i.status <> 'withdrawn'
           GROUP BY COALESCE(i.locationid, 0)",
            ['libraryid' => $library->id]
        );
        $ids = array_filter(array_map('intval', array_keys($rows)));
        $labels = [];
        if ($ids) {
            [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $labels = $DB->get_records_select_menu(self::T . 'authority', 'id ' . $in, $params, '', 'id, label');
        }
        $places = [];
        foreach ($rows as $place => $row) {
            $label = (int) $place ? catalogue::tidy_label((string) ($labels[$place] ?? '')) : '';
            $places[] = ['id' => (int) $place, 'label' => $label, 'count' => (int) $row->titles,
                'colour' => $label !== '' ? spine::place_colour($label) : null];
        }
        // By name, as people read it («Estantería 2» before «Estantería 10»); copies without a place, at the end.
        $collator = new \Collator(current_language());
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        usort($places, fn($a, $b) => [$a['id'] ? 0 : 1, 0] <=> [$b['id'] ? 0 : 1, 0]
            ?: $collator->compare($a['label'], $b['label']));
        return $places;
    }

    /**
     * A stretch of shelf.
     *
     * @param \stdClass $library
     * @param array $filter place (a location id, 0 for copies without one) or corner (a digit); neither for the whole
     *     library.
     * @param string $after Start after this key (the last of the stretch before).
     * @param string $before End before this key (the first of the stretch after).
     * @param int $at A record to start a little before.
     * @return array spines, prev (the key to go back from, or '') and next (the key to go on from, or '').
     */
    public static function page(\stdClass $library, array $filter, string $after = '', string $before = '', int $at = 0): array {
        global $DB;
        self::fill((int) $library->id);
        [$where, $params] = self::where($library, $filter);
        $group = "SELECT i.recordid, MIN(i.shelfkey) AS k
                    FROM {" . self::T . "item} i
                   WHERE $where
                GROUP BY i.recordid";
        if ($at) {
            $here = $DB->get_field_sql(
                "SELECT MIN(i.shelfkey) FROM {" . self::T . "item} i WHERE $where AND i.recordid = :at",
                $params + ['at' => $at]
            );
            if ($here) {
                $earlier = $DB->get_records_sql("$group HAVING MIN(i.shelfkey) < :here ORDER BY k DESC", $params +
                    ['here' => $here], 0, 6);
                $after = $earlier ? (string) end($earlier)->k : '';
                $rows = $DB->get_records_sql("$group HAVING MIN(i.shelfkey) >= :start ORDER BY k", $params +
                    ['start' => $after !== '' ? $after : $here], 0, self::PAGE + 1);
                $more = count($rows) > self::PAGE;
                $rows = array_slice(array_values($rows), 0, self::PAGE);
                return self::stretch(
                    $library,
                    $rows,
                    $more,
                    $after !== '' ? self::earlier($group, $params, $rows) : false,
                    $filter
                );
            }
        }
        if ($before !== '') {
            $rows = array_reverse(array_values($DB->get_records_sql(
                "$group HAVING MIN(i.shelfkey) < :before ORDER BY k DESC",
                $params + ['before' => $before],
                0,
                self::PAGE + 1
            )));
            $earlier = count($rows) > self::PAGE;
            $rows = array_slice($rows, $earlier ? 1 : 0);
            return self::stretch($library, $rows, true, $earlier, $filter);
        }
        $rows = $after !== ''
            ? $DB->get_records_sql(
                "$group HAVING MIN(i.shelfkey) > :after ORDER BY k",
                $params + ['after' => $after],
                0,
                self::PAGE + 1
            )
            : $DB->get_records_sql("$group ORDER BY k", $params, 0, self::PAGE + 1);
        $more = count($rows) > self::PAGE;
        $rows = array_slice(array_values($rows), 0, self::PAGE);
        return self::stretch($library, $rows, $more, $after !== '', $filter);
    }

    /**
     * The books beside one on its shelf: the ones before and after it in the same place.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $each How many on each side.
     * @return array|null spines (with here on the record's own) and place (its location id), or null when the library
     *     has no copy of it.
     */
    public static function neighbours(\stdClass $library, int $recordid, int $each = 3): ?array {
        global $DB;
        self::fill((int) $library->id);
        $own = $DB->get_records_sql(
            "SELECT i.id, i.locationid, i.shelfkey, i.status
               FROM {" . self::T . "item} i
              WHERE i.libraryid = :libraryid AND i.recordid = :recordid AND i.status <> 'lost' AND i.status <> 'withdrawn'
           ORDER BY i.shelfkey",
            ['libraryid' => $library->id, 'recordid' => $recordid]
        );
        if (!$own) {
            return null;
        }
        $first = reset($own);
        $place = (int) $first->locationid;
        [$where, $params] = self::where($library, ['place' => $place]);
        $group = "SELECT i.recordid, MIN(i.shelfkey) AS k
                    FROM {" . self::T . "item} i
                   WHERE $where AND i.recordid <> :self
                GROUP BY i.recordid";
        $params['self'] = $recordid;
        $before = array_reverse(array_values($DB->get_records_sql(
            "$group HAVING MIN(i.shelfkey) < :here ORDER BY k DESC",
            $params + ['here' => $first->shelfkey],
            0,
            $each
        )));
        $after = array_values($DB->get_records_sql(
            "$group HAVING MIN(i.shelfkey) > :here ORDER BY k",
            $params + ['here' => $first->shelfkey],
            0,
            $each
        ));
        $rows = array_merge($before, [(object) ['recordid' => $recordid, 'k' => $first->shelfkey]], $after);
        $spines = self::spines($library, $rows, $place);
        foreach ($spines as &$spine) {
            $spine['here'] = $spine['id'] === $recordid;
        }
        return ['spines' => $spines, 'place' => $place];
    }

    /**
     * The main colour of a record's cover, kept by the cover's content: computed once.
     *
     * @param int $recordid
     * @param string $contenthash
     * @return int[]|null
     */
    public static function cover_colour(int $recordid, string $contenthash): ?array {
        $cache = \cache::make('local_oksigeniaayvurenda', 'spinecolour');
        $known = $cache->get($contenthash);
        if ($known !== false) {
            return $known === 'none' ? null : $known;
        }
        $colour = null;
        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'cover',
            $recordid,
            '/',
            'cover.jpg'
        );
        if ($file && function_exists('imagecreatefromstring')) {
            $colour = self::dominant($file->get_content());
        }
        $cache->set($contenthash, $colour ?? 'none');
        return $colour;
    }

    /**
     * The colour most of a picture is, leaving out greys: the hue that weighs most, averaged.
     *
     * @param string $data
     * @return int[]|null
     */
    public static function dominant(string $data): ?array {
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }
        $small = imagescale($image, 16, 24);
        if (!$small) {
            return null;
        }
        $buckets = [];
        $all = [0, 0, 0, 0];
        for ($x = 0; $x < 16; $x++) {
            for ($y = 0; $y < 24; $y++) {
                $pixel = imagecolorat($small, $x, $y);
                $rgb = [($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF];
                $all = [$all[0] + $rgb[0], $all[1] + $rgb[1], $all[2] + $rgb[2], $all[3] + 1];
                [$l, $c, $h] = spine::oklch($rgb);
                if ($c < 0.05 || $l < 0.15 || $l > 0.95) {
                    continue;
                }
                $bucket = (int) floor($h / 30) % 12;
                $buckets[$bucket] = $buckets[$bucket] ?? [0.0, 0, 0, 0];
                $buckets[$bucket] = [$buckets[$bucket][0] + $c, $buckets[$bucket][1] + $rgb[0] * $c,
                    $buckets[$bucket][2] + $rgb[1] * $c, $buckets[$bucket][3] + $rgb[2] * $c];
            }
        }
        if (!$buckets) {
            return [intdiv($all[0], $all[3]), intdiv($all[1], $all[3]), intdiv($all[2], $all[3])];
        }
        uasort($buckets, fn($a, $b) => $b[0] <=> $a[0]);
        $best = reset($buckets);
        return [(int) round($best[1] / $best[0]), (int) round($best[2] / $best[0]), (int) round($best[3] / $best[0])];
    }

    /**
     * The condition of the copies a stretch takes.
     *
     * @param \stdClass $library
     * @param array $filter
     * @return array SQL and its parameters.
     */
    private static function where(\stdClass $library, array $filter): array {
        global $DB;
        $where = "i.libraryid = :libraryid AND i.status <> 'lost' AND i.status <> 'withdrawn'";
        $params = ['libraryid' => $library->id];
        if (array_key_exists('place', $filter) && $filter['place'] !== null) {
            if ((int) $filter['place']) {
                $where .= ' AND i.locationid = :place';
                $params['place'] = (int) $filter['place'];
            } else {
                $where .= ' AND i.locationid IS NULL';
            }
        } else if (isset($filter['corner']) && preg_match('/^\d$/', (string) $filter['corner'])) {
            $where .= ' AND ' . $DB->sql_like('i.shelfkey', ':corner');
            $params['corner'] = '1' . $filter['corner'] . '%';
        }
        return [$where, $params];
    }

    /**
     * Whether there are books before a stretch.
     *
     * @param string $group
     * @param array $params
     * @param array $rows
     * @return bool
     */
    private static function earlier(string $group, array $params, array $rows): bool {
        global $DB;
        if (!$rows) {
            return false;
        }
        return (bool) $DB->get_records_sql("$group HAVING MIN(i.shelfkey) < :first", $params + ['first' => $rows[0]->k], 0, 1);
    }

    /**
     * A stretch with its ways on.
     *
     * @param \stdClass $library
     * @param array $rows recordid and k, in order.
     * @param bool $more Whether there are books after.
     * @param bool $earlier Whether there are books before.
     * @param array $filter The place, if the stretch is of one.
     * @return array
     */
    private static function stretch(\stdClass $library, array $rows, bool $more, bool $earlier, array $filter): array {
        return [
            'spines' => self::spines($library, $rows, isset($filter['place']) ? (int) $filter['place'] : null),
            'prev' => $earlier && $rows ? (string) $rows[0]->k : '',
            'next' => $more && $rows ? (string) end($rows)->k : '',
        ];
    }

    /**
     * The spines of some records, in the order given.
     *
     * @param \stdClass $library
     * @param array $rows recordid and k.
     * @param int|null $place On the shelf of a place, only its copies count (0 for copies without a place).
     * @return array[]
     */
    public static function spines(\stdClass $library, array $rows, ?int $place = null): array {
        global $DB;
        $ids = array_values(array_unique(array_map(fn($r) => (int) $r->recordid, $rows)));
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            self::T . 'record',
            'id ' . $in,
            $params,
            '',
            'id, title, subtitle, lang, extent, extra'
        );
        $items = $DB->get_records_select(
            self::T . 'item',
            'recordid ' . $in . " AND libraryid = :libraryid AND status <> 'lost' AND status <> 'withdrawn'",
            $params + ['libraryid' => $library->id],
            'shelfkey, id',
            'id, recordid, status, itemtype, locationid, callnumber1, callnumber2, callnumber3'
        );
        $copies = [];
        foreach ($items as $item) {
            if ($place === null || (int) $item->locationid === $place) {
                $copies[$item->recordid][] = $item;
            }
        }
        $authors = [];
        $firstauthor = [];
        $classlabel = [];
        $links = $DB->get_records_sql(
            "SELECT ra.id, ra.recordid, a.kind, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid $in AND (a.kind = 'author' OR a.kind = 'class')
           ORDER BY ra.sortorder, ra.id",
            $params
        );
        foreach ($links as $link) {
            if ($link->kind === 'class') {
                $classlabel[$link->recordid] = $classlabel[$link->recordid] ?? catalogue::tidy_label((string) $link->label);
                continue;
            }
            $firstauthor[$link->recordid] = $firstauthor[$link->recordid] ?? (string) $link->label;
            if (count($authors[$link->recordid] ?? []) < 2) {
                $authors[$link->recordid][] = catalogue::display_name($link->label);
            }
        }
        // The corners by their first digit, to say which one a label's class opens.
        $corners = [];
        foreach (catalogue::corners($library, true) as $corner) {
            $corners[$corner['code']] = $corner['label'];
        }
        $placeids = array_filter(array_map(fn($c) => (int) $c[0]->locationid, $copies));
        $places = [];
        if ($placeids) {
            [$pin, $pparams] = $DB->get_in_or_equal(array_values(array_unique($placeids)), SQL_PARAMS_NAMED);
            $places = $DB->get_records_select_menu(self::T . 'authority', 'id ' . $pin, $pparams, '', 'id, label');
        }
        $context = \context_system::instance();
        $files = $DB->get_records_select(
            'files',
            "contextid = :contextid AND component = 'local_oksigeniaayvurenda' AND filearea = 'cover'
            AND filename = 'cover.jpg' AND itemid $in",
            $params + ['contextid' => $context->id],
            '',
            'itemid, contenthash, timemodified'
        );
        $userlang = current_language();
        $spines = [];
        foreach ($rows as $row) {
            $id = (int) $row->recordid;
            $record = $records[$id] ?? null;
            if (!$record) {
                continue;
            }
            $extra = json_decode((string) $record->extra, true) ?: [];
            $size = spine::size((string) $record->extent, (string) ($extra['dimensions'] ?? ''), $id);
            $mine = $copies[$id] ?? [];
            $first = $mine[0] ?? null;
            $class = $first && preg_match('/^\s*(\d)/', (string) $first->callnumber1, $m) ? (int) $m[1] : $id;
            $file = $files[$id] ?? null;
            // Without a cover, the corner's colour; now and then the coral or the sun of the house, as on a real shelf.
            $fallback = $id % 5 === 0 ? '#e2654c' : ($id % 7 === 0 ? '#f0b43c' : self::CORNERS[$class % count(self::CORNERS)]);
            $colour = spine::colour($file ? self::cover_colour($id, $file->contenthash) : null, $fallback, $id);
            $state = self::state($mine);
            $lines = $first ? array_values(array_filter([(string) $first->callnumber1, (string) $first->callnumber2,
                (string) $first->callnumber3], fn($l) => trim($l) !== '')) : [];
            $place = $first && $first->locationid ? catalogue::tidy_label((string) ($places[$first->locationid] ?? '')) : '';
            $who = implode(' · ', $authors[$id] ?? []);
            $size2 = $size['width'] < 30 ? 11 : 13;
            // Room along the spine: its height less the label, the two headbands and a little air.
            $room = $size['cm'] * 8 - 64;
            $spines[] = [
                'id' => $id,
                'title' => $record->title,
                'shown' => spine::fit($record->title, $room, $size2),
                'authors' => $who,
                'state' => $state,
                'out' => $state === 'out' || $state === 'held',
                'held' => $state === 'held',
                'consult' => $state === 'consult',
                'lines' => array_map(fn($l) => ['line' => $l], $lines),
                'callnumber' => implode(' ', $lines),
                'joined' => implode('|', $lines),
                // What each line of its label stands for, to find it on the real shelf.
                'why' => callnumber::explain($lines, $classlabel[$id] ?? null, preg_match('/^\s*(\d)/', $lines[0] ?? '', $c)
                    ? ($corners[$c[1]] ?? null) : null, $firstauthor[$id] ?? '', (string) $record->title),
                'place' => $place,
                'placecolour' => $place !== '' ? spine::place_colour($place) : null,
                'cm' => $size['cm'],
                'width' => $size['width'],
                'pages' => $size['pages'],
                'background' => $colour['background'],
                'dark' => $colour['dark'],
                'ink' => $colour['ink'],
                'small' => $size2 === 11,
                'topdown' => spine::top_down((string) $record->lang, $userlang),
                // One in three, if it is thick enough, with a band of one of the house's motifs.
                'band' => $id % 3 === 0 && $size['pages'] >= 120 ? 'ayv-spine-band ayv-spine-band-' . ($id % 6) : '',
                'cover' => $file ? \moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_oksigeniaayvurenda',
                    'cover',
                    $id,
                    '/',
                    'cover.jpg'
                )->out(false) . '?t=' . $file->timemodified : null,
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $id]))
                    ->out(false),
                'key' => (string) $row->k,
                'copies' => count($mine),
                'here' => false,
            ];
        }
        return $spines;
    }

    /**
     * Whether a title is on the shelf: free, all out, set aside for someone who reserved it, or only to read there.
     *
     * @param \stdClass[] $items Its copies in this place.
     * @return string free, out, held or consult.
     */
    private static function state(array $items): string {
        $lendable = array_filter($items, fn($i) => $i->itemtype !== 'reference');
        if (!$items) {
            return 'out';
        }
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
}
