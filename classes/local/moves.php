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
 * Moving copies from place to place.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * Copies change place together, not one by one: a whole place to another; a part of it, from one book to another in the
 * order of the shelf, or one corner; or with the reader, the place chosen first and then the books passed one after
 * another. Each move can be undone for a while: what moved together goes back together, unless it moved again since.
 */
final class moves {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How long a move can be undone. */
    public const KEEP = 30 * DAYSECS;

    /**
     * The copies of a library some words choose: those of a place (0, those without one), from one copy to another in
     * the order of the shelf, or of one corner.
     *
     * @param \stdClass $library
     * @param array $what place (an id, or 0), and first and last (copy ids), letters (the first and the last letter of the
     *     spines' second line) or corner (a top class); place alone, all of it.
     * @return int[] Copy ids, in the order of the shelf.
     */
    public static function chosen(\stdClass $library, array $what): array {
        global $DB;
        $place = (int) ($what['place'] ?? 0);
        $where = "i.libraryid = :libraryid AND i.status <> 'withdrawn' AND "
            . ($place ? 'i.locationid = :place' : 'i.locationid IS NULL');
        $params = ['libraryid' => $library->id] + ($place ? ['place' => $place] : []);
        if (!empty($what['first']) && !empty($what['last'])) {
            $edges = $DB->get_records_select_menu(
                self::T . 'item',
                'libraryid = :libraryid AND id IN (:a, :b)',
                ['libraryid' => $library->id, 'a' => (int) $what['first'], 'b' => (int) $what['last']],
                '',
                'id, shelfkey'
            );
            if (count($edges) < ((int) $what['first'] === (int) $what['last'] ? 1 : 2)) {
                return [];
            }
            $keys = array_values(array_map('strval', $edges));
            sort($keys, SORT_STRING);
            $where .= ' AND i.shelfkey >= :from AND i.shelfkey <= :to';
            $params += ['from' => $keys[0], 'to' => end($keys)];
        }
        $rows = $DB->get_records_sql(
            "SELECT i.id, i.recordid FROM {" . self::T . "item} i WHERE $where ORDER BY i.shelfkey, i.id",
            $params
        );
        if (!empty($what['letters'])) {
            [$a, $b] = $what['letters'];
            [$a, $b] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
            $spines = $DB->get_records_select_menu(self::T . 'item', 'libraryid = :libraryid AND ' . ($place
                ? 'locationid = :place' : 'locationid IS NULL'), $params, '', 'id, callnumber2');
            $rows = array_filter($rows, function ($row) use ($spines, $a, $b) {
                $letter = self::letter((string) ($spines[$row->id] ?? ''));
                return $letter !== '#' && strcmp($letter, $a) >= 0 && strcmp($letter, $b) <= 0;
            });
        }
        if (isset($what['corner']) && (string) $what['corner'] !== '') {
            $classes = self::classes(array_values(array_unique(array_map(fn($r) => (int) $r->recordid, $rows))));
            $rows = array_filter($rows, fn($r) => ($classes[(int) $r->recordid] ?? null) === (string) $what['corner']);
        }
        return array_values(array_map('intval', array_keys($rows)));
    }

    /**
     * The corner each record is in: the top class of its first class.
     *
     * @param int[] $recordids
     * @return array Record id => corner.
     */
    private static function classes(array $recordids): array {
        global $DB;
        $corners = [];
        foreach (array_chunk($recordids, 1000) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $rows = $DB->get_recordset_sql(
                "SELECT ra.id, ra.recordid, a.code FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                  WHERE ra.recordid $in ORDER BY ra.id",
                $params
            );
            foreach ($rows as $row) {
                $top = catalogue::top_class((string) $row->code);
                if ($top !== null && !isset($corners[(int) $row->recordid])) {
                    $corners[(int) $row->recordid] = $top;
                }
            }
            $rows->close();
        }
        return $corners;
    }

    /**
     * The letters of the spines of a place (the author's, on their second line), in the order of the shelf: how many copies
     * each has, and the first and the last. The Ñ goes with the N, as on the shelf.
     *
     * @param \stdClass $library
     * @param int $place
     * @return array Letter => count, first and last (copy ids).
     */
    public static function letters(\stdClass $library, int $place): array {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT i.id, i.callnumber2 FROM {" . self::T . "item} i
              WHERE i.libraryid = :libraryid AND i.status <> 'withdrawn' AND "
                . ($place ? 'i.locationid = :place' : 'i.locationid IS NULL') . "
           ORDER BY i.shelfkey, i.id",
            ['libraryid' => $library->id] + ($place ? ['place' => $place] : [])
        );
        $letters = [];
        foreach ($rows as $row) {
            $letter = self::letter((string) $row->callnumber2);
            $letters[$letter] ??= ['count' => 0, 'first' => (int) $row->id, 'last' => (int) $row->id];
            $letters[$letter]['count']++;
            $letters[$letter]['last'] = (int) $row->id;
        }
        $rows->close();
        return $letters;
    }

    /**
     * The letter a spine's second line starts with, as the shelf files it: without accents, the Ñ with the N; «#» for
     * anything else.
     *
     * @param string $line
     * @return string
     */
    private static function letter(string $line): string {
        $plain = text::normalise(\core_text::substr(trim($line), 0, 1));
        $plain = $plain === 'ñ' ? 'n' : $plain;
        return preg_match('/^[a-z]$/', $plain) ? strtoupper($plain) : '#';
    }

    /**
     * The corners of the copies of a place, the fullest first.
     *
     * @param \stdClass $library
     * @param int $place
     * @return array[] code, label, count.
     */
    public static function corners(\stdClass $library, int $place): array {
        $rows = self::chosen($library, ['place' => $place]);
        global $DB;
        if (!$rows) {
            return [];
        }
        $records = [];
        foreach (array_chunk($rows, 1000) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $records += $DB->get_records_select_menu(self::T . 'item', "id $in", $params, '', 'id, recordid');
        }
        $classes = self::classes(array_values(array_unique(array_map('intval', $records))));
        $counts = [];
        foreach ($records as $recordid) {
            $code = $classes[(int) $recordid] ?? null;
            if ($code !== null) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }
        arsort($counts);
        $labels = array_column(catalogue::corners($library, true, false), 'label', 'code');
        $out = [];
        foreach ($counts as $code => $count) {
            $out[] = ['code' => (string) $code, 'label' => (string) ($labels[(string) $code] ?? $code), 'count' => $count];
        }
        return $out;
    }

    /**
     * What a move would take: how many copies, and the first and the last on the shelf.
     *
     * @param \stdClass $library
     * @param array $what As chosen().
     * @return array count, first and last (titles, or '').
     */
    public static function preview(\stdClass $library, array $what): array {
        global $DB;
        $ids = self::chosen($library, $what);
        $title = fn(int $id) => (string) $DB->get_field_sql(
            "SELECT r.title FROM {" . self::T . "item} i JOIN {" . self::T . "record} r ON r.id = i.recordid WHERE i.id = :id",
            ['id' => $id]
        );
        return ['count' => count($ids), 'first' => $ids ? $title($ids[0]) : '', 'last' => $ids ? $title(end($ids)) : ''];
    }

    /**
     * Moves the copies some words choose to a place.
     *
     * @param \stdClass $library
     * @param array $what As chosen().
     * @param int $to A place of the centre, or 0 for none.
     * @return array batch (to undo it) and count.
     */
    public static function move(\stdClass $library, array $what, int $to): array {
        $batch = random_string(32);
        return ['batch' => $batch, 'count' => self::put($library, self::chosen($library, $what), $to, $batch)];
    }

    /**
     * Moves one copy, read by its code, to a place: one more of a batch of the reader.
     *
     * @param \stdClass $library
     * @param string $code
     * @param int $to
     * @param string $batch The batch it joins; a new one if empty.
     * @return array found (bool), batch, title, from (the name of the place it was in), already (it was there), count (of
     *     the batch).
     */
    public static function one(\stdClass $library, string $code, int $to, string $batch): array {
        global $DB;
        $batch = preg_match('/^[A-Za-z0-9]{32}$/', $batch) ? $batch : random_string(32);
        $item = trim($code) === '' ? null : desk::by_code(
            'item',
            'barcode',
            (int) $library->entityid,
            trim($code),
            ' AND libraryid = ' . (int) $library->id . " AND status <> 'withdrawn'"
        );
        $count = fn() => $DB->count_records(self::T . 'move', ['libraryid' => $library->id, 'batch' => $batch]);
        if (!$item) {
            return ['found' => false, 'batch' => $batch, 'title' => '', 'from' => '', 'already' => false, 'count' => $count()];
        }
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]);
        $from = $item->locationid ? catalogue::tidy_label((string) $DB->get_field(
            self::T . 'authority',
            'label',
            ['id' => $item->locationid]
        )) : '';
        $already = (int) $item->locationid === $to;
        if (!$already) {
            self::put($library, [(int) $item->id], $to, $batch);
        }
        return ['found' => true, 'batch' => $batch, 'title' => $title, 'from' => $from, 'already' => $already,
            'count' => $count()];
    }

    /**
     * Puts some copies in a place, and keeps where each was.
     *
     * @param \stdClass $library
     * @param int[] $ids
     * @param int $to
     * @param string $batch
     * @return int How many moved.
     */
    private static function put(\stdClass $library, array $ids, int $to, string $batch): int {
        global $DB;
        $to = $to ? cataloguer::own_location((int) $library->entityid, $to) : null;
        $now = time();
        $moved = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach (array_chunk($ids, 500) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $items = $DB->get_records_select(self::T . 'item', "id $in AND libraryid = :libraryid", $params
                + ['libraryid' => $library->id], '', 'id, locationid');
            $rows = [];
            foreach ($items as $item) {
                if ((int) $item->locationid === (int) $to) {
                    continue;
                }
                $rows[] = ['libraryid' => $library->id, 'batch' => $batch, 'itemid' => $item->id,
                    'fromid' => $item->locationid ?: null, 'toid' => $to, 'timecreated' => $now];
            }
            if ($rows) {
                $DB->insert_records(self::T . 'move', $rows);
                [$in2, $params2] = $DB->get_in_or_equal(array_column($rows, 'itemid'), SQL_PARAMS_NAMED);
                $DB->set_field_select(self::T . 'item', 'locationid', $to, "id $in2", $params2);
                $DB->set_field_select(self::T . 'item', 'timemodified', $now, "id $in2", $params2);
                $moved += count($rows);
            }
        }
        $transaction->allow_commit();
        if ($moved) {
            catalogue::purge((int) $library->entityid);
        }
        return $moved;
    }

    /**
     * Undoes a move: each copy goes back to where it was, unless it moved again since.
     *
     * @param \stdClass $library
     * @param string $batch
     * @return int How many went back.
     */
    public static function undo(\stdClass $library, string $batch): int {
        global $DB;
        $rows = $DB->get_records(self::T . 'move', ['libraryid' => $library->id, 'batch' => $batch]);
        $back = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($rows as $row) {
            $now = $DB->get_field(self::T . 'item', 'locationid', ['id' => $row->itemid, 'libraryid' => $library->id]);
            if ($now !== false && (int) $now === (int) $row->toid) {
                $DB->update_record(self::T . 'item', (object) ['id' => $row->itemid, 'locationid' => $row->fromid,
                    'timemodified' => time()]);
                $back++;
            }
        }
        $DB->delete_records(self::T . 'move', ['libraryid' => $library->id, 'batch' => $batch]);
        $transaction->allow_commit();
        if ($back) {
            catalogue::purge((int) $library->entityid);
        }
        return $back;
    }

    /**
     * Undoes one copy of a batch: it goes back to where it was, unless it moved again since.
     *
     * @param \stdClass $library
     * @param string $batch
     * @param int $itemid
     * @return bool
     */
    public static function undo_one(\stdClass $library, string $batch, int $itemid): bool {
        global $DB;
        $row = $DB->get_record(self::T . 'move', ['libraryid' => $library->id, 'batch' => $batch, 'itemid' => $itemid]);
        if (!$row) {
            return false;
        }
        $now = $DB->get_field(self::T . 'item', 'locationid', ['id' => $itemid, 'libraryid' => $library->id]);
        $back = $now !== false && (int) $now === (int) $row->toid;
        if ($back) {
            $DB->update_record(self::T . 'item', (object) ['id' => $itemid, 'locationid' => $row->fromid,
                'timemodified' => time()]);
            catalogue::purge((int) $library->entityid);
        }
        $DB->delete_records(self::T . 'move', ['id' => $row->id]);
        return $back;
    }

    /**
     * Forgets the moves too old to be undone.
     *
     * @param int $now
     * @return int
     */
    public static function forget(int $now): int {
        global $DB;
        $old = $DB->count_records_select(self::T . 'move', 'timecreated < :before', ['before' => $now - self::KEEP]);
        $DB->delete_records_select(self::T . 'move', 'timecreated < :before', ['before' => $now - self::KEEP]);
        return $old;
    }
}
