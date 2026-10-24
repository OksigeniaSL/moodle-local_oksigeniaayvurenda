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
 * Boxes of copies one library lends to another of the same centre for a time.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The central library fills a box (a theme, a class reading, a corner for a term), sends it to a classroom or a
 * department until a date, and the copies sit on that shelf meanwhile: the teacher lends them to the children. When the
 * box comes back, what is on the shelf goes home at once; what a child still has goes home when it is returned. The
 * library it belongs to sees what is still missing.
 */
final class lots {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Starts filling a box.
     *
     * @param \stdClass $library The library the copies belong to.
     * @param string $name
     * @param int $tolibraryid Another library of the same centre.
     * @param int|null $due When it should come back.
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function create(
        \stdClass $library,
        string $name,
        int $tolibraryid,
        ?int $due,
        int $userid,
        ?int $now = null
    ): int {
        global $DB;
        $now = $now ?? time();
        if (
            $tolibraryid === (int) $library->id || !$DB->record_exists(self::T . 'library', ['id' => $tolibraryid,
                'entityid' => $library->entityid])
        ) {
            throw new \moodle_exception('lotnowhere', 'local_oksigeniaayvurenda');
        }
        return (int) $DB->insert_record(self::T . 'lot', ['libraryid' => $library->id, 'tolibraryid' => $tolibraryid,
            'name' => \core_text::substr(trim($name), 0, 100), 'status' => 'open', 'timedue' => $due ?: null,
            'createdby' => $userid ?: null, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * A box that a library sends or receives.
     *
     * @param int $id
     * @param \stdClass $library
     * @return \stdClass
     */
    public static function get(int $id, \stdClass $library): \stdClass {
        global $DB;
        return $DB->get_record_select(
            self::T . 'lot',
            'id = :id AND (libraryid = :a OR tolibraryid = :b)',
            ['id' => $id, 'a' => $library->id, 'b' => $library->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Puts a copy in a box being filled.
     *
     * @param \stdClass $lot
     * @param string $code
     * @return array kind (added, already, notfound, notfree, otherlibrary, inanother) and title.
     */
    public static function add(\stdClass $lot, string $code): array {
        global $DB;
        $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $lot->libraryid]);
        $item = trim($code) === '' ? null : $DB->get_record_select(self::T . 'item', 'entityid = :entityid AND ' .
            $DB->sql_equal('barcode', ':code', false), ['entityid' => $entityid, 'code' => trim($code)], '*', IGNORE_MULTIPLE);
        if (!$item) {
            return ['kind' => 'notfound', 'title' => trim($code)];
        }
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]);
        if ($DB->record_exists(self::T . 'lotitem', ['lotid' => $lot->id, 'itemid' => $item->id])) {
            return ['kind' => 'already', 'title' => $title];
        }
        if ((int) $item->libraryid !== (int) $lot->libraryid) {
            return ['kind' => 'otherlibrary', 'title' => $title];
        }
        if ($item->status !== 'available') {
            return ['kind' => 'notfree', 'title' => $title];
        }
        if (
            $DB->record_exists_sql("SELECT 1 FROM {" . self::T . "lotitem} li JOIN {" . self::T . "lot} l ON l.id = li.lotid
                WHERE li.itemid = :itemid AND li.timeback IS NULL AND l.status <> 'back'", ['itemid' => $item->id])
        ) {
            return ['kind' => 'inanother', 'title' => $title];
        }
        $DB->insert_record(self::T . 'lotitem', ['lotid' => $lot->id, 'itemid' => $item->id]);
        return ['kind' => 'added', 'title' => $title];
    }

    /**
     * Takes a copy out of a box still being filled.
     *
     * @param \stdClass $lot
     * @param int $itemid
     */
    public static function remove(\stdClass $lot, int $itemid): void {
        global $DB;
        if ($lot->status === 'open') {
            $DB->delete_records(self::T . 'lotitem', ['lotid' => $lot->id, 'itemid' => $itemid]);
        }
    }

    /**
     * Sends a box: its copies go to the other library's shelf until they come back.
     *
     * @param \stdClass $lot
     * @param int|null $now
     * @return int How many copies went.
     */
    public static function send(\stdClass $lot, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $sent = 0;
        foreach ($DB->get_records(self::T . 'lotitem', ['lotid' => $lot->id]) as $row) {
            $item = $DB->get_record(self::T . 'item', ['id' => $row->itemid]);
            if (!$item || $item->status !== 'available' || (int) $item->libraryid !== (int) $lot->libraryid) {
                // Lent or gone since it was put in the box: it stays out of it.
                $DB->delete_records(self::T . 'lotitem', ['id' => $row->id]);
                continue;
            }
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'libraryid' => $lot->tolibraryid,
                'timemodified' => $now]);
            $sent++;
        }
        $DB->update_record(self::T . 'lot', (object) ['id' => $lot->id, 'status' => 'out', 'timeout' => $now,
            'timemodified' => $now]);
        return $sent;
    }

    /**
     * The box comes back: what is on the shelf goes home now; what a child has, when it is returned.
     *
     * @param \stdClass $lot
     * @param int|null $now
     * @return array home (how many went home now) and pending (titles still out, with who has them).
     */
    public static function give_back(\stdClass $lot, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        if ($lot->status !== 'out') {
            return ['home' => 0, 'pending' => []];
        }
        $DB->update_record(self::T . 'lot', (object) ['id' => $lot->id, 'status' => 'back', 'timeback' => $now,
            'timemodified' => $now]);
        $lot->status = 'back';
        $home = 0;
        foreach ($DB->get_records(self::T . 'lotitem', ['lotid' => $lot->id, 'timeback' => null]) as $row) {
            $item = $DB->get_record(self::T . 'item', ['id' => $row->itemid]);
            if ($item && self::come_home($item, $now)) {
                $home++;
            }
        }
        return ['home' => $home, 'pending' => self::pending($lot)];
    }

    /**
     * A copy of a box that came back goes home, once it is not lent; called too when a child returns it.
     *
     * @param \stdClass $item Its library changes here too.
     * @param int $now
     * @return bool Whether it went home.
     */
    public static function come_home(\stdClass $item, int $now): bool {
        global $DB;
        if ($item->status === 'onloan') {
            return false;
        }
        $row = $DB->get_record_sql(
            "SELECT li.id, l.libraryid
              FROM {" . self::T . "lotitem} li
              JOIN {" . self::T . "lot} l ON l.id = li.lotid
             WHERE li.itemid = :itemid AND li.timeback IS NULL AND l.status = 'back'",
            ['itemid' => $item->id],
            IGNORE_MULTIPLE
        );
        if (!$row) {
            return false;
        }
        // A copy set aside in the classroom for a reservation goes home free; the reservation waits for another.
        $hold = holds::ready_for((int) $item->id);
        if ($hold) {
            $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'waiting', 'itemid' => null,
                'timeready' => null, 'timeexpires' => null, 'timemodified' => $now]);
            $item->status = 'available';
        }
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'libraryid' => $row->libraryid,
            'status' => $item->status, 'timemodified' => $now]);
        $DB->set_field(self::T . 'lotitem', 'timeback', $now, ['id' => $row->id]);
        $item->libraryid = $row->libraryid;
        return true;
    }

    /**
     * The boxes a library sent and those it received, the open ones first.
     *
     * @param \stdClass $library
     * @return array sent and received: lists of lot, copies, lent (to children) and missing (not home yet).
     */
    public static function of_library(\stdClass $library): array {
        global $DB;
        $lists = ['sent' => [], 'received' => []];
        $lots = $DB->get_records_select(
            self::T . 'lot',
            'libraryid = :a OR (tolibraryid = :b AND status <> :open)',
            ['a' => $library->id, 'b' => $library->id, 'open' => 'open'],
            'timecreated DESC, id DESC'
        );
        foreach ($lots as $lot) {
            $rows = $DB->get_records_sql(
                'SELECT li.id, li.timeback, i.status
                   FROM {' . self::T . 'lotitem} li
                   JOIN {' . self::T . 'item} i ON i.id = li.itemid
                  WHERE li.lotid = :lotid',
                ['lotid' => $lot->id]
            );
            $entry = [
                'lot' => $lot,
                'copies' => count($rows),
                'lent' => count(array_filter($rows, fn($r) => $r->timeback === null && $r->status === 'onloan')),
                'missing' => $lot->status === 'back' ? count(array_filter($rows, fn($r) => $r->timeback === null)) : 0,
            ];
            $lists[(int) $lot->libraryid === (int) $library->id ? 'sent' : 'received'][] = $entry;
        }
        $order = ['out' => 0, 'open' => 1, 'back' => 2];
        foreach ($lists as $key => $list) {
            usort($list, fn($a, $b) => $order[$a['lot']->status] <=> $order[$b['lot']->status]);
            $lists[$key] = $list;
        }
        return $lists;
    }

    /**
     * The copies of a box.
     *
     * @param \stdClass $lot
     * @return array[] itemid, title, barcode, status, home (it came back).
     */
    public static function copies(\stdClass $lot): array {
        global $DB;
        return array_values(array_map(fn($r) => ['itemid' => (int) $r->itemid, 'title' => $r->title, 'barcode' => $r->barcode,
            'status' => $r->status, 'home' => $r->timeback !== null], $DB->get_records_sql(
                'SELECT li.id, li.itemid, li.timeback, i.barcode, i.status, r.title
               FROM {' . self::T . 'lotitem} li
               JOIN {' . self::T . 'item} i ON i.id = li.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE li.lotid = :lotid
           ORDER BY r.title, li.id',
                ['lotid' => $lot->id]
            )));
    }

    /**
     * What of a returned box is not home yet, and who has it.
     *
     * @param \stdClass $lot
     * @return array[] title, who.
     */
    public static function pending(\stdClass $lot): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT li.id, r.title, l.readerid
               FROM {' . self::T . 'lotitem} li
               JOIN {' . self::T . 'item} i ON i.id = li.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
          LEFT JOIN {' . self::T . 'loan} l ON l.itemid = i.id AND l.timereturned IS NULL
              WHERE li.lotid = :lotid AND li.timeback IS NULL',
            ['lotid' => $lot->id]
        );
        $library = $DB->get_record(self::T . 'library', ['id' => $lot->tolibraryid]);
        $desk = $library ? new desk($library, 0) : null;
        return array_values(array_map(fn($r) => ['title' => $r->title,
            'who' => $r->readerid && $desk ? $desk->reader((int) $r->readerid)['name'] : ''], $rows));
    }
}
