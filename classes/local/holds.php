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
 * Reservations: a reader waiting for a book, then a copy set aside.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\hold_rules;
use local_oksigeniaayvurenda\core\loan_rules;

/**
 * A reader asks for a book that is out, online if their stage may or at the desk. When a copy comes back, the desk says
 * to set it aside, and the reader hears it by a Moodle message (by any channel the centre has, Marandu too). The copy
 * waits a few school days; then it goes to the next in the queue, or back to the shelf. A copy set aside has the status
 * «held», so the catalogue does not show it as free.
 */
final class holds {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] Reservations still alive. */
    public const OPEN = ['waiting', 'ready'];

    /**
     * The reservation settings of a library's entity.
     *
     * @param int $entityid
     * @return array online, stages, days, max, renew.
     */
    public static function settings(int $entityid): array {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return hold_rules::settings($entity['holds'] ?? []);
    }

    /**
     * Saves the reservation settings.
     *
     * @param int $entityid
     * @param array $settings
     */
    public static function save_settings(int $entityid, array $settings): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['holds'] = hold_rules::settings($settings);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
    }

    /**
     * The stages of this year's readers, to choose those who reserve online.
     *
     * @param int $entityid
     * @param int $now
     * @return string[]
     */
    public static function stages(int $entityid, int $now): array {
        global $DB;
        $year = years::around($entityid, $now);
        $stages = $DB->get_fieldset_select(
            self::T . 'member',
            'DISTINCT stage',
            'yearid = :yearid AND stage IS NOT NULL',
            ['yearid' => $year->id]
        );
        $stages = array_values(array_filter(array_map('strval', $stages), fn($s) => trim($s) !== ''));
        sort($stages, SORT_NATURAL | SORT_FLAG_CASE);
        return $stages;
    }

    /**
     * Whether a reader may reserve online in this library.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @return bool
     */
    public static function online(\stdClass $library, int $readerid, int $now): bool {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'status' => 'active']);
        if (!$reader) {
            return false;
        }
        $year = years::around((int) $library->entityid, $now);
        $stage = $DB->get_field(self::T . 'member', 'stage', ['readerid' => $readerid, 'yearid' => $year->id], IGNORE_MULTIPLE);
        return hold_rules::online(self::settings((int) $library->entityid), $reader->readertype, $stage ?: null);
    }

    /**
     * The open reservation of a reader for a book in a library, if any.
     *
     * @param int $libraryid
     * @param int $recordid
     * @param int $readerid
     * @return \stdClass|null
     */
    public static function open(int $libraryid, int $recordid, int $readerid): ?\stdClass {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::OPEN, SQL_PARAMS_NAMED);
        $hold = $DB->get_record_select(self::T . 'hold', "libraryid = :libraryid AND recordid = :recordid
            AND readerid = :readerid AND status $in", $params + ['libraryid' => $libraryid, 'recordid' => $recordid,
            'readerid' => $readerid], '*', IGNORE_MULTIPLE);
        return $hold ?: null;
    }

    /**
     * How many wait before a reservation.
     *
     * @param \stdClass $hold
     * @return int
     */
    public static function ahead(\stdClass $hold): int {
        global $DB;
        return hold_rules::ahead(self::waiting((int) $hold->libraryid, (int) $hold->recordid), (int) $hold->id);
    }

    /**
     * Reserves a book for a reader. It is not reserved when a copy is free here (it is on the shelf), when the reader
     * has it already, has it reserved, or has as many reservations as the library allows, or when the library has no
     * copy of it to take home.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $readerid
     * @param int|null $createdby
     * @param int|null $now
     * @return array ok, reason (free, has, max, nocopies) and the hold.
     */
    public static function add(\stdClass $library, int $recordid, int $readerid, ?int $createdby, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $items = $DB->get_records_select(
            self::T . 'item',
            "libraryid = :libraryid AND recordid = :recordid
            AND itemtype <> 'reference' AND status IN ('available', 'onloan', 'held')",
            ['libraryid' => $library->id, 'recordid' => $recordid]
        );
        if (!$items) {
            return ['ok' => false, 'reason' => 'nocopies', 'hold' => null];
        }
        if (array_filter($items, fn($i) => $i->status === 'available')) {
            return ['ok' => false, 'reason' => 'free', 'hold' => null];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED);
        if (
            self::open((int) $library->id, $recordid, $readerid) || $DB->record_exists_select(
                self::T . 'loan',
                "readerid = :readerid AND timereturned IS NULL AND itemid $in",
                $params + ['readerid' => $readerid]
            )
        ) {
            return ['ok' => false, 'reason' => 'has', 'hold' => null];
        }
        [$open, $oparams] = $DB->get_in_or_equal(self::OPEN, SQL_PARAMS_NAMED);
        $count = $DB->count_records_select(
            self::T . 'hold',
            "readerid = :readerid AND status $open",
            $oparams + ['readerid' => $readerid]
        );
        if ($count >= self::settings((int) $library->entityid)['max']) {
            return ['ok' => false, 'reason' => 'max', 'hold' => null];
        }
        $id = $DB->insert_record(self::T . 'hold', ['libraryid' => $library->id, 'recordid' => $recordid,
            'readerid' => $readerid, 'status' => 'waiting', 'createdby' => $createdby ?: null, 'timecreated' => $now,
            'timemodified' => $now]);
        return ['ok' => true, 'reason' => '', 'hold' => $DB->get_record(self::T . 'hold', ['id' => $id])];
    }

    /**
     * Cancels a reservation; a copy set aside for it goes to the next, or back to the shelf.
     *
     * @param int $holdid
     * @param int|null $now
     */
    public static function cancel(int $holdid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $hold = $DB->get_record(self::T . 'hold', ['id' => $holdid]);
        if (!$hold || !in_array($hold->status, self::OPEN, true)) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'cancelled', 'timeclosed' => $now,
            'timemodified' => $now]);
        if ($hold->status === 'ready' && $hold->itemid) {
            self::release((int) $hold->itemid, $now);
        }
        $transaction->allow_commit();
    }

    /**
     * A copy is free again: it is set aside for the first who waits for its book in its library, who is told by a
     * message; if nobody waits, it goes back to the shelf.
     *
     * @param \stdClass $item
     * @param int $now
     * @return \stdClass|null The reservation it is set aside for.
     */
    public static function place(\stdClass $item, int $now): ?\stdClass {
        global $DB;
        if ($item->itemtype === 'reference') {
            return null;
        }
        $first = self::waiting((int) $item->libraryid, (int) $item->recordid)[0] ?? null;
        if (!$first) {
            return null;
        }
        $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $item->libraryid]);
        $days = self::settings($entityid)['days'];
        $DB->update_record(self::T . 'hold', (object) ['id' => $first->id, 'status' => 'ready', 'itemid' => $item->id,
            'timeready' => $now, 'timeexpires' => loan_rules::due($now, $days, \core_date::get_server_timezone_object()),
            'timenotified' => null, 'timemodified' => $now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'held', 'timemodified' => $now]);
        // The message waits a little: an «Undo» at the desk right after takes it back before it goes.
        $task = new \local_oksigeniaayvurenda\task\notify_hold();
        $task->set_custom_data(['holdid' => (int) $first->id]);
        $task->set_next_run_time($now + 2 * MINSECS);
        \core\task\manager::queue_adhoc_task($task);
        return $DB->get_record(self::T . 'hold', ['id' => $first->id]);
    }

    /**
     * The reservation a copy is set aside for, if it is.
     *
     * @param int $itemid
     * @return \stdClass|null
     */
    public static function ready_for(int $itemid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'hold', ['itemid' => $itemid, 'status' => 'ready'], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * After a loan: the reader's reservation of that book is fulfilled; a copy set aside for someone else and lent
     * anyway leaves that reservation waiting again, first in the queue.
     *
     * @param \stdClass $item
     * @param int $readerid
     * @param int $loanid
     * @param int $now
     * @return bool Whether a reservation of this reader was fulfilled.
     */
    public static function after_loan(\stdClass $item, int $readerid, int $loanid, int $now): bool {
        global $DB;
        $set = self::ready_for((int) $item->id);
        if ($set && (int) $set->readerid !== $readerid) {
            $DB->update_record(self::T . 'hold', (object) ['id' => $set->id, 'status' => 'waiting', 'itemid' => null,
                'timeready' => null, 'timeexpires' => null, 'timemodified' => $now]);
        }
        $mine = self::open((int) $item->libraryid, (int) $item->recordid, $readerid);
        if (!$mine) {
            return false;
        }
        if ($mine->status === 'ready' && (int) $mine->itemid !== (int) $item->id) {
            // They took another copy of the same book: the one set aside for them is free again.
            self::release((int) $mine->itemid, $now);
        }
        $DB->update_record(self::T . 'hold', (object) ['id' => $mine->id, 'status' => 'done', 'itemid' => $item->id,
            'loanid' => $loanid, 'timeclosed' => $now, 'timemodified' => $now]);
        return true;
    }

    /**
     * Undoing a loan: the reservation it fulfilled is set aside again with that copy.
     *
     * @param int $loanid
     * @param int $now
     * @return bool Whether the copy is set aside again.
     */
    public static function undo_loan(int $loanid, int $now): bool {
        global $DB;
        $hold = $DB->get_record(self::T . 'hold', ['loanid' => $loanid, 'status' => 'done']);
        if (!$hold) {
            return false;
        }
        $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'ready', 'loanid' => null,
            'timeclosed' => null, 'timemodified' => $now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $hold->itemid, 'status' => 'held', 'timemodified' => $now]);
        return true;
    }

    /**
     * Undoing a return: the reservation the copy was set aside for waits again.
     *
     * @param int $itemid
     * @param int $now
     */
    public static function undo_return(int $itemid, int $now): void {
        global $DB;
        $hold = self::ready_for($itemid);
        if ($hold) {
            $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'waiting', 'itemid' => null,
                'timeready' => null, 'timeexpires' => null, 'timemodified' => $now]);
        }
    }

    /**
     * Whether a loan cannot be renewed because others wait for the book (unless the library allows it), or because the
     * copy was just set aside for someone.
     *
     * @param \stdClass $item
     * @return bool
     */
    public static function blocks_renewal(\stdClass $item): bool {
        global $DB;
        if (self::ready_for((int) $item->id)) {
            return true;
        }
        $entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $item->libraryid]);
        return !self::settings($entityid)['renew'] && (bool) self::waiting((int) $item->libraryid, (int) $item->recordid);
    }

    /**
     * Copies set aside past their date: the reservation expires and the copy goes to the next, or back to the shelf.
     *
     * @param int|null $now
     * @return int How many expired.
     */
    public static function expire(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $late = $DB->get_records_select(self::T . 'hold', "status = 'ready' AND timeexpires < :now", ['now' => $now]);
        foreach ($late as $hold) {
            $DB->update_record(self::T . 'hold', (object) ['id' => $hold->id, 'status' => 'expired', 'timeclosed' => $now,
                'timemodified' => $now]);
            if ($hold->itemid) {
                self::release((int) $hold->itemid, $now);
            }
        }
        return count($late);
    }

    /**
     * Sends the message of a copy set aside, once, if the reader has an account and it is still set aside.
     *
     * @param int $holdid
     * @return bool Whether it went.
     */
    public static function notify(int $holdid): bool {
        global $DB;
        $hold = $DB->get_record(self::T . 'hold', ['id' => $holdid, 'status' => 'ready']);
        if (!$hold || $hold->timenotified) {
            return false;
        }
        $reader = $DB->get_record(self::T . 'reader', ['id' => $hold->readerid]);
        $user = $reader && $reader->userid ? \core_user::get_user($reader->userid) : null;
        $DB->set_field(self::T . 'hold', 'timenotified', time(), ['id' => $hold->id]);
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }
        $library = $DB->get_record(self::T . 'library', ['id' => $hold->libraryid], '*', MUST_EXIST);
        if (access::closed((int) $library->entityid)) {
            return false;
        }
        $a = (object) [
            'title' => (string) $DB->get_field(self::T . 'record', 'title', ['id' => $hold->recordid]),
            'library' => format_string($library->name),
            'until' => userdate((int) $hold->timeexpires, get_string('strftimedateshort', 'langconfig')),
        ];
        $body = get_string('holdreadybody', 'local_oksigeniaayvurenda', $a) . "\n\n" .
            characters::say((int) $library->entityid, 'herald', 'heraldsign')['text'];
        return notices::send('holdready', $user, get_string('holdreadysubject', 'local_oksigeniaayvurenda', $a), $body);
    }

    /**
     * A reader's open reservations, for the desk and their card.
     *
     * @param int $readerid
     * @param int $now
     * @return array[] id, title, ready, until (for those set aside), ahead (for those waiting).
     */
    public static function of_reader(int $readerid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT h.*, r.title
               FROM {" . self::T . "hold} h
               JOIN {" . self::T . "record} r ON r.id = h.recordid
              WHERE h.readerid = :readerid AND h.status IN ('waiting', 'ready')
           ORDER BY h.status DESC, h.timecreated, h.id",
            ['readerid' => $readerid]
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'recordid' => (int) $row->recordid,
                'title' => $row->title,
                'ready' => $row->status === 'ready',
                'until' => $row->status === 'ready' ? userdate(
                    (int) $row->timeexpires,
                    get_string('strftimedateshort', 'langconfig')
                ) : '',
                'ahead' => $row->status === 'waiting' ? self::ahead($row) : 0,
            ];
        }
        return $list;
    }

    /**
     * A library's reservations: copies set aside, with their date, and the queues.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array ready and waiting: lists with id, title, recordid, readerid, name, group, until or since, ahead.
     */
    public static function lists(\stdClass $library, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT h.*, r.title, i.barcode
               FROM {" . self::T . "hold} h
               JOIN {" . self::T . "record} r ON r.id = h.recordid
          LEFT JOIN {" . self::T . "item} i ON i.id = h.itemid
              WHERE h.libraryid = :libraryid AND h.status IN ('waiting', 'ready')
           ORDER BY h.timeexpires, h.timecreated, h.id",
            ['libraryid' => $library->id]
        );
        $desk = new desk($library, 0, $now);
        $lists = ['ready' => [], 'waiting' => []];
        $format = get_string('strftimedateshort', 'langconfig');
        foreach ($rows as $row) {
            $who = $desk->reader((int) $row->readerid);
            $lists[$row->status][] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'recordid' => (int) $row->recordid,
                'readerid' => (int) $row->readerid,
                'name' => $who['name'],
                'group' => $who['group'],
                'barcode' => (string) $row->barcode,
                'until' => $row->timeexpires ? userdate((int) $row->timeexpires, $format) : '',
                'since' => userdate((int) $row->timecreated, $format),
                'ahead' => $row->status === 'waiting' ? self::ahead($row) : 0,
            ];
        }
        return $lists;
    }

    /**
     * The reservations waiting for a book in a library, in order.
     *
     * @param int $libraryid
     * @param int $recordid
     * @return \stdClass[]
     */
    private static function waiting(int $libraryid, int $recordid): array {
        global $DB;
        return hold_rules::queue(array_values($DB->get_records(self::T . 'hold', ['libraryid' => $libraryid,
            'recordid' => $recordid, 'status' => 'waiting'])));
    }

    /**
     * A copy no longer set aside: to the next who waits, or back to the shelf.
     *
     * @param int $itemid
     * @param int $now
     */
    private static function release(int $itemid, int $now): void {
        global $DB;
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid]);
        if (!$item || $item->status !== 'held') {
            return;
        }
        if (!self::place($item, $now)) {
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $now]);
        }
    }
}
