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
 * Stocktakes: going along the shelves reading the copies, and what is missing or out of its place.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\stocktake_rules;

/**
 * A stocktake covers a library or one place in it (a corner, a shelf, a room). Several people can read at once, each
 * with a phone or a scanner, saying where they are; the phone keeps what it reads when there is no network and sends it
 * later. At the end: what should be on the shelves and was not read, what was read out of its place, what was thought
 * to be lent and was on the shelf, what was thought to be lost and turned up. The missing can be marked lost in one go.
 */
final class stocktake {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Starts a stocktake.
     *
     * @param \stdClass $library
     * @param string $name
     * @param int|null $locationid The place it covers, or the whole library.
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function start(\stdClass $library, string $name, ?int $locationid, int $userid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        return (int) $DB->insert_record(self::T . 'stocktake', ['libraryid' => $library->id,
            'name' => \core_text::substr(trim($name), 0, 100), 'locationid' => $locationid ?: null, 'status' => 'open',
            'createdby' => $userid ?: null, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * A stocktake of a library.
     *
     * @param int $id
     * @param \stdClass $library
     * @return \stdClass
     */
    public static function get(int $id, \stdClass $library): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'stocktake', ['id' => $id, 'libraryid' => $library->id], '*', MUST_EXIST);
    }

    /**
     * The stocktakes of a library, the open ones first.
     *
     * @param \stdClass $library
     * @return \stdClass[]
     */
    public static function all(\stdClass $library): array {
        global $DB;
        return array_values($DB->get_records(
            self::T . 'stocktake',
            ['libraryid' => $library->id],
            'status DESC, timecreated DESC'
        ));
    }

    /**
     * The places of a library's copies, with how many each has.
     *
     * @param \stdClass $library
     * @return array[] id, label, count.
     */
    public static function places(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT a.id, a.label, COUNT(i.id) AS copies
               FROM {' . self::T . 'item} i
               JOIN {' . self::T . 'authority} a ON a.id = i.locationid
              WHERE i.libraryid = :libraryid
           GROUP BY a.id, a.label
           ORDER BY a.label',
            ['libraryid' => $library->id]
        );
        return array_values(array_map(
            fn($r) => ['id' => (int) $r->id, 'label' => $r->label, 'count' => (int) $r->copies],
            $rows
        ));
    }

    /**
     * A code read on the shelves.
     *
     * @param \stdClass $stocktake
     * @param string $code
     * @param int|null $here Where the reader says they are; the place of the stocktake if it covers one.
     * @param int $userid
     * @param int|null $now
     * @return array code, kind (seen, again, misplaced, lent, found, withdrawn, otherlibrary, unknown), title, where.
     */
    public static function read(\stdClass $stocktake, string $code, ?int $here, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $code = trim($code);
        $here = $here ?: ($stocktake->locationid ? (int) $stocktake->locationid : null);
        $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $stocktake->libraryid]);
        $item = $code === '' ? null : $DB->get_record_select(self::T . 'item', 'entityid = :entityid AND ' .
            $DB->sql_equal('barcode', ':code', false), ['entityid' => $entityid, 'code' => $code], '*', IGNORE_MULTIPLE);
        $answer = ['code' => $code, 'kind' => 'unknown', 'title' => '', 'where' => ''];
        if (!$item) {
            if (
                $code !== '' && !$DB->record_exists(self::T . 'stockread', ['stocktakeid' => $stocktake->id, 'itemid' => null,
                    'code' => $code])
            ) {
                self::note($stocktake, null, $code, $here, $userid, $now);
            }
            return $answer;
        }
        $answer['title'] = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]);
        $answer['where'] = $item->locationid ? (string) $DB->get_field(
            self::T . 'authority',
            'label',
            ['id' => $item->locationid]
        ) : '';
        if ((int) $item->libraryid !== (int) $stocktake->libraryid) {
            $answer['kind'] = 'otherlibrary';
            $answer['where'] = format_string((string) $DB->get_field(self::T . 'library', 'name', ['id' => $item->libraryid]));
            return $answer;
        }
        $already = $DB->record_exists(self::T . 'stockread', ['stocktakeid' => $stocktake->id, 'itemid' => $item->id]);
        $answer['kind'] = stocktake_rules::verdict(
            $item->status,
            $item->locationid ? (int) $item->locationid : null,
            $here,
            $already
        );
        if (!$already) {
            self::note($stocktake, (int) $item->id, $item->barcode, $here, $userid, $now);
        }
        return $answer;
    }

    /**
     * How it is going: copies counted, read, missing so far, and the reads that need a look. Counted by the database,
     * as it is asked after every few codes read and a library may have tens of thousands of copies.
     *
     * @param \stdClass $stocktake
     * @return array expected, seen, missing, misplaced, lent, found, unknown.
     */
    public static function counts(\stdClass $stocktake): array {
        global $DB;
        [$expected, $eparams] = self::scope($stocktake, stocktake_rules::EXPECTED);
        [$shelf, $sparams] = self::scope($stocktake, stocktake_rules::ON_SHELF);
        $read = 'SELECT COUNT(DISTINCT r.itemid) FROM {' . self::T . 'stockread} r JOIN {' . self::T . 'item} i ON i.id = r.itemid
                  WHERE r.stocktakeid = :stocktakeid AND ';
        $mine = ['stocktakeid' => $stocktake->id];
        $onshelf = $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'item} i WHERE ' . $shelf, $sparams);
        $seenonshelf = $DB->count_records_sql($read . $shelf, $sparams + $mine);
        return [
            'expected' => $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'item} i WHERE ' . $expected, $eparams),
            'seen' => $DB->count_records_sql($read . $expected, $eparams + $mine),
            'missing' => max(0, $onshelf - $seenonshelf),
            'misplaced' => $DB->count_records_sql($read . "r.locationid IS NOT NULL AND i.locationid IS NOT NULL
                AND r.locationid <> i.locationid AND i.status <> 'onloan' AND i.status <> 'lost'", $mine),
            'lent' => $DB->count_records_sql($read . "i.status = 'onloan'", $mine),
            'found' => $DB->count_records_sql($read . "i.status = 'lost'", $mine),
            'unknown' => $DB->count_records_sql('SELECT COUNT(DISTINCT code) FROM {' . self::T . 'stockread}
                WHERE stocktakeid = :stocktakeid AND itemid IS NULL', $mine),
        ];
    }

    /**
     * What the stocktake found.
     *
     * @param \stdClass $stocktake
     * @param int $limit How many of each list at most; 0 for all.
     * @return array missing, misplaced, lent and found (copies with id, title, code, call number, place and where they
     *     were read); unknown (codes); missingbyplace (place and how many are missing from it).
     */
    public static function results(\stdClass $stocktake, int $limit = 0): array {
        global $DB;
        [$shelf, $sparams] = self::scope($stocktake, stocktake_rules::ON_SHELF);
        $notread = 'NOT EXISTS (SELECT 1 FROM {' . self::T . 'stockread} r WHERE r.stocktakeid = :stocktakeid AND r.itemid = i.id)';
        $mine = ['stocktakeid' => $stocktake->id];
        $missing = $DB->get_records_sql(
            'SELECT i.id, i.id AS itemid, i.barcode, i.locationid, NULL AS readat, i.callnumber1, i.callnumber2,
                    i.callnumber3, rec.title, a.label
               FROM {' . self::T . 'item} i
               JOIN {' . self::T . 'record} rec ON rec.id = i.recordid
          LEFT JOIN {' . self::T . 'authority} a ON a.id = i.locationid
              WHERE ' . $shelf . ' AND ' . $notread . '
           ORDER BY a.label, i.callnumber1, i.callnumber2, i.callnumber3, rec.title, i.id',
            $sparams + $mine,
            0,
            $limit
        );
        $byplace = $DB->get_records_sql(
            'SELECT COALESCE(i.locationid, 0) AS place, MAX(a.label) AS label, COUNT(1) AS copies
               FROM {' . self::T . 'item} i
          LEFT JOIN {' . self::T . 'authority} a ON a.id = i.locationid
              WHERE ' . $shelf . ' AND ' . $notread . '
           GROUP BY COALESCE(i.locationid, 0)
           ORDER BY MAX(a.label)',
            $sparams + $mine
        );
        $read = function (string $where) use ($DB, $mine, $limit) {
            return $DB->get_records_sql(
                'SELECT r.id, r.itemid, i.barcode, i.locationid, r.locationid AS readat, i.callnumber1, i.callnumber2,
                        i.callnumber3, rec.title
                   FROM {' . self::T . 'stockread} r
                   JOIN {' . self::T . 'item} i ON i.id = r.itemid
                   JOIN {' . self::T . 'record} rec ON rec.id = i.recordid
                  WHERE r.stocktakeid = :stocktakeid AND ' . $where . '
               ORDER BY r.timeread, r.id',
                $mine,
                0,
                $limit
            );
        };
        $lists = [
            'misplaced' => $read("r.locationid IS NOT NULL AND i.locationid IS NOT NULL AND r.locationid <> i.locationid
                AND i.status <> 'onloan' AND i.status <> 'lost'"),
            'lent' => $read("i.status = 'onloan'"),
            'found' => $read("i.status = 'lost'"),
        ];
        $places = self::labels(array_merge(
            array_column($missing, 'locationid'),
            array_column($lists['misplaced'], 'locationid'),
            array_column($lists['misplaced'], 'readat'),
            array_column($lists['lent'], 'locationid'),
            array_column($lists['found'], 'locationid')
        ));
        $copy = fn($row) => [
            'id' => (int) $row->itemid,
            'title' => (string) $row->title,
            'barcode' => (string) $row->barcode,
            'callnumber' => trim(implode(' ', array_filter([$row->callnumber1, $row->callnumber2, $row->callnumber3]))),
            'where' => $places[$row->locationid] ?? '',
            'readat' => $row->readat ? ($places[$row->readat] ?? '') : '',
        ];
        return [
            'missing' => array_values(array_map($copy, $missing)),
            'misplaced' => array_values(array_map($copy, $lists['misplaced'])),
            'lent' => array_values(array_map($copy, $lists['lent'])),
            'found' => array_values(array_map($copy, $lists['found'])),
            'unknown' => array_values(array_unique($DB->get_fieldset_select(
                self::T . 'stockread',
                'code',
                'stocktakeid = :stocktakeid AND itemid IS NULL',
                $mine
            ))),
            'missingbyplace' => array_values(array_map(
                fn($p) => ['label' => (string) $p->label, 'count' => (int) $p->copies],
                $byplace
            )),
        ];
    }

    /**
     * Marks as lost the copies still missing.
     *
     * @param \stdClass $stocktake
     * @param int|null $now
     * @return int How many.
     */
    public static function lose(\stdClass $stocktake, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        [$shelf, $sparams] = self::scope($stocktake, stocktake_rules::ON_SHELF);
        $ids = $DB->get_fieldset_sql(
            'SELECT i.id FROM {' . self::T . 'item} i WHERE ' . $shelf . ' AND NOT EXISTS (
            SELECT 1 FROM {' . self::T . 'stockread} r WHERE r.stocktakeid = :stocktakeid AND r.itemid = i.id)',
            $sparams + ['stocktakeid' => $stocktake->id]
        );
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            // A copy set aside for a reservation and not found: the reservation waits for another copy.
            foreach ($DB->get_records_select(self::T . 'hold', "status = 'ready' AND itemid $in", $params) as $hold) {
                $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'waiting', 'itemid' => null,
                    'timeready' => null, 'timeexpires' => null, 'timemodified' => $now]);
            }
            $DB->set_field_select(self::T . 'item', 'status', 'lost', "id $in", $params);
            $DB->set_field_select(self::T . 'item', 'timemodified', $now, "id $in", $params);
        }
        return count($ids);
    }

    /**
     * The copies thought lost that turned up go back to the shelf.
     *
     * @param \stdClass $stocktake
     * @param int|null $now
     * @return int How many.
     */
    public static function recover(\stdClass $stocktake, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $count = 0;
        foreach (self::results($stocktake)['found'] as $copy) {
            $item = $DB->get_record(self::T . 'item', ['id' => $copy['id']], '*', MUST_EXIST);
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $now]);
            $item->status = 'available';
            holds::place($item, $now);
            $count++;
        }
        return $count;
    }

    /**
     * Closes a stocktake: nothing more is read in it.
     *
     * @param \stdClass $stocktake
     * @param int|null $now
     */
    public static function close(\stdClass $stocktake, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $DB->update_record(self::T . 'stocktake', (object) ['id' => $stocktake->id, 'status' => 'closed',
            'timeclosed' => $now, 'timemodified' => $now]);
    }

    /**
     * SQL for the copies a stocktake covers (alias i), with the given statuses.
     *
     * @param \stdClass $stocktake
     * @param string[] $statuses
     * @return array The condition and its parameters.
     */
    private static function scope(\stdClass $stocktake, array $statuses): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
        $where = "i.libraryid = :scopelibrary AND i.status $in";
        $params['scopelibrary'] = $stocktake->libraryid;
        if ($stocktake->locationid) {
            $where .= ' AND i.locationid = :scopelocation';
            $params['scopelocation'] = $stocktake->locationid;
        }
        return [$where, $params];
    }

    /**
     * Keeps a read.
     *
     * @param \stdClass $stocktake
     * @param int|null $itemid
     * @param string $code
     * @param int|null $here
     * @param int $userid
     * @param int $now
     */
    private static function note(\stdClass $stocktake, ?int $itemid, string $code, ?int $here, int $userid, int $now): void {
        global $DB;
        $DB->insert_record(self::T . 'stockread', ['stocktakeid' => $stocktake->id, 'itemid' => $itemid,
            'code' => \core_text::substr($code, 0, 200), 'locationid' => $here, 'userid' => $userid ?: null,
            'timeread' => $now]);
        $DB->set_field(self::T . 'stocktake', 'timemodified', $now, ['id' => $stocktake->id]);
    }

    /**
     * Labels of places by id.
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
}
