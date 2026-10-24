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
 * Weeding: the candidates, taking copies out of the collection, and the record of it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\weeding_rules;

/**
 * The library chooses what leaves its collection; this shows the candidates (lost long ago, worn out, too old, not lent
 * for years), takes the chosen out together and keeps a record of it that can be printed and signed. A copy taken out
 * by mistake goes back with a tap. What is lent or set aside for a reservation is never a candidate.
 */
final class weeding {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The candidates of a library.
     *
     * @param \stdClass $library
     * @param array $filters unused (years), before (year), worn, lost, place (location id).
     * @param int|null $now
     * @param int $limit
     * @return array total and rows (id, title, barcode, callnumber, year, lastloan, why).
     */
    public static function candidates(\stdClass $library, array $filters, ?int $now = null, int $limit = 500): array {
        global $DB;
        $now = $now ?? time();
        $where = "i.libraryid = :libraryid AND i.status <> 'onloan' AND i.status <> 'held' AND i.status <> 'withdrawn'";
        $params = ['libraryid' => $library->id];
        if (!empty($filters['place'])) {
            $where .= ' AND i.locationid = :place';
            $params['place'] = (int) $filters['place'];
        }
        // The last loan of each copy, apart: joined to every copy it made large libraries slow.
        $lastloans = $DB->get_records_sql_menu('SELECT itemid, MAX(timeloaned) FROM {' . self::T . 'loan}
            WHERE libraryid = :libraryid GROUP BY itemid', ['libraryid' => $library->id]);
        $rows = $DB->get_recordset_sql(
            'SELECT i.id, i.barcode, i.status, i.itemcondition, i.timeacquired, i.callnumber1, i.callnumber2, i.callnumber3,
                    r.title, r.pubyear
               FROM {' . self::T . 'item} i
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE ' . $where,
            $params
        );
        $found = [];
        $order = ['lost' => 0, 'worn' => 1, 'old' => 2, 'unused' => 3];
        foreach ($rows as $row) {
            $row->lastloan = $lastloans[$row->id] ?? null;
            $year = weeding_rules::year($row->pubyear);
            $why = weeding_rules::why(['status' => $row->status, 'condition' => (string) $row->itemcondition, 'year' => $year,
                'lastloan' => $row->lastloan ? (int) $row->lastloan : null,
                'acquired' => $row->timeacquired ? (int) $row->timeacquired : null], $filters, $now);
            if ($why === null) {
                continue;
            }
            $callnumber = trim(implode(' ', array_filter([$row->callnumber1, $row->callnumber2, $row->callnumber3])));
            $found[] = ['id' => (int) $row->id, 'title' => (string) $row->title, 'barcode' => (string) $row->barcode,
                'callnumber' => $callnumber, 'year' => $year, 'lastloan' => $row->lastloan ? (int) $row->lastloan : null,
                'why' => $why, 'order' => [$order[$why], $callnumber, (string) $row->title]];
        }
        $rows->close();
        usort($found, fn($a, $b) => $a['order'] <=> $b['order']);
        $found = array_map(function ($row) {
            unset($row['order']);
            return $row;
        }, $found);
        return ['total' => count($found), 'rows' => $limit ? array_slice($found, 0, $limit) : $found];
    }

    /**
     * Takes copies out of the collection together, with a record of it.
     *
     * @param \stdClass $library
     * @param int[] $itemids
     * @param string $reason What the library says of it.
     * @param string[] $why Why each copy, by its id (lost, worn, old, unused); chosen by hand when not given.
     * @param int $userid
     * @param int|null $now
     * @return int The record of weeding, or 0 if nothing could be taken out.
     */
    public static function withdraw(
        \stdClass $library,
        array $itemids,
        string $reason,
        array $why,
        int $userid,
        ?int $now = null
    ): int {
        global $DB;
        $now = $now ?? time();
        $itemids = array_values(array_unique(array_filter(array_map('intval', $itemids))));
        if (!$itemids) {
            return 0;
        }
        [$in, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $items = $DB->get_records_sql(
            "SELECT i.*, r.title
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE i.id $in AND i.libraryid = :libraryid AND i.status <> 'onloan' AND i.status <> 'held'
                    AND i.status <> 'withdrawn'",
            $params + ['libraryid' => $library->id]
        );
        if (!$items) {
            return 0;
        }
        $transaction = $DB->start_delegated_transaction();
        $id = (int) $DB->insert_record(self::T . 'withdrawal', ['libraryid' => $library->id, 'reason' => trim($reason),
            'createdby' => $userid ?: null, 'timecreated' => $now]);
        foreach ($items as $item) {
            $DB->insert_record(self::T . 'withdrawn', ['withdrawalid' => $id, 'itemid' => $item->id,
                'barcode' => $item->barcode, 'title' => $item->title,
                'callnumber' => \core_text::substr(trim(implode(' ', array_filter([$item->callnumber1, $item->callnumber2,
                    $item->callnumber3]))), 0, 100),
                'why' => $why[$item->id] ?? 'chosen', 'statusbefore' => $item->status]);
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'withdrawn', 'timemodified' => $now]);
        }
        $transaction->allow_commit();
        return $id;
    }

    /**
     * A copy taken out by mistake goes back to the shelf.
     *
     * @param int $withdrawnid
     * @param \stdClass $library
     * @param int|null $now
     */
    public static function restore(int $withdrawnid, \stdClass $library, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record_sql(
            'SELECT w.*
               FROM {' . self::T . 'withdrawn} w
               JOIN {' . self::T . 'withdrawal} a ON a.id = w.withdrawalid
              WHERE w.id = :id AND a.libraryid = :libraryid AND w.timerestored IS NULL',
            ['id' => $withdrawnid, 'libraryid' => $library->id]
        );
        if (!$row) {
            return;
        }
        $item = $DB->get_record(self::T . 'item', ['id' => $row->itemid]);
        if ($item && $item->status === 'withdrawn') {
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $now]);
            $item->status = 'available';
            holds::place($item, $now);
        }
        $DB->set_field(self::T . 'withdrawn', 'timerestored', $now, ['id' => $row->id]);
    }

    /**
     * The records of weeding of a library, the newest first.
     *
     * @param \stdClass $library
     * @return array[] id, time, reason, copies.
     */
    public static function records(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT a.id, a.timecreated, a.reason, COUNT(w.id) AS copies
               FROM {' . self::T . 'withdrawal} a
          LEFT JOIN {' . self::T . 'withdrawn} w ON w.withdrawalid = a.id AND w.timerestored IS NULL
              WHERE a.libraryid = :libraryid
           GROUP BY a.id, a.timecreated, a.reason
           ORDER BY a.timecreated DESC, a.id DESC',
            ['libraryid' => $library->id]
        );
        return array_values(array_map(fn($r) => ['id' => (int) $r->id, 'time' => (int) $r->timecreated,
            'reason' => (string) $r->reason, 'copies' => (int) $r->copies], $rows));
    }

    /**
     * A record of weeding with its copies.
     *
     * @param int $id
     * @param \stdClass $library
     * @return array record (the row) and copies (id, title, barcode, callnumber, why, restored).
     */
    public static function record(int $id, \stdClass $library): array {
        global $DB;
        $record = $DB->get_record(self::T . 'withdrawal', ['id' => $id, 'libraryid' => $library->id], '*', MUST_EXIST);
        $copies = array_values(array_map(
            fn($w) => ['id' => (int) $w->id, 'title' => $w->title, 'barcode' => $w->barcode,
            'callnumber' => (string) $w->callnumber, 'why' => (string) $w->why, 'restored' => (bool) $w->timerestored],
            $DB->get_records(self::T . 'withdrawn', ['withdrawalid' => $id], 'callnumber, title, id')
        ));
        return ['record' => $record, 'copies' => $copies];
    }
}
