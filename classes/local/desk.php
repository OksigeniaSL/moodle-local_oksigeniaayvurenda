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
 * The desk: lending and taking back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\desk\policy;
use local_oksigeniaayvurenda\core\loan_rules;

/**
 * What happens at the desk when a code is read: a reader's card opens the reader; a copy on loan comes back; a free
 * copy goes to the open reader, if the rules allow it or the librarian says so. Every answer comes with the words to
 * show and the sound to make, so that the screen only has to show it. Every action can be undone.
 */
final class desk {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The one-tap notes on return. */
    public const NOTES = ['loved', 'unfinished', 'more'];

    /** @var string[] The states of material, the usual one first. */
    public const CONDITIONS = ['good', 'worn', 'missingpart', 'broken'];

    /** @var array The rule when a library has none, nor has the library it hangs from. */
    private const DEFAULT_RULE = ['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 0,
        'renewals' => 1, 'renewdays' => 7];

    /** @var \stdClass */
    private \stdClass $library;

    /** @var int Who is at the desk. */
    private int $staffid;

    /** @var int */
    private int $now;

    /** @var \DateTimeZone */
    private \DateTimeZone $tz;

    /**
     * The desk of a library.
     *
     * @param \stdClass $library
     * @param int $staffid
     * @param int|null $now
     */
    public function __construct(\stdClass $library, int $staffid, ?int $now = null) {
        $this->library = $library;
        $this->staffid = $staffid;
        $this->now = $now ?? time();
        $this->tz = \core_date::get_server_timezone_object();
    }

    /**
     * A code was read, or a name typed.
     *
     * @param string $code
     * @param int $readerid The reader open at the desk, 0 if none.
     * @param bool $force The librarian said to lend despite a reason that can be overridden.
     * @return array The answer: kind, sound, message and what the screen needs.
     */
    public function scan(string $code, int $readerid = 0, bool $force = false): array {
        global $DB;
        $code = trim($code);
        if ($code === '') {
            return $this->answer('unknown', 'no', 'deskunknown', $code);
        }
        $item = $DB->get_record_select(self::T . 'item', 'entityid = :entityid AND ' .
            $DB->sql_equal('barcode', ':code', false), ['entityid' => $this->library->entityid, 'code' => $code]);
        if ($item) {
            $open = $DB->get_record_select(
                self::T . 'loan',
                'itemid = :itemid AND timereturned IS NULL',
                ['itemid' => $item->id],
                '*',
                IGNORE_MULTIPLE
            );
            if ($open || $item->status === 'onloan') {
                return $this->give_back($item, $open, $readerid);
            }
            if (!$readerid) {
                $hold = $item->status === 'held' ? holds::ready_for((int) $item->id) : null;
                if ($hold) {
                    return $this->answer('needreader', 'look', 'deskheldfor', (object) ['title' => $this->title($item),
                        'who' => $this->who((int) $hold->readerid)]);
                }
                return $this->answer('needreader', 'look', 'deskneedreader', $this->title($item));
            }
            return $this->lend($item, $readerid, $force);
        }
        $reader = $DB->get_record_select(
            self::T . 'reader',
            'entityid = :entityid AND ' .
            $DB->sql_equal('cardcode', ':code', false) . " AND status <> 'deleted'",
            ['entityid' => $this->library->entityid, 'code' => $code]
        );
        if ($reader) {
            return $this->answer('reader', 'ok', null, null, ['reader' => $this->reader_panel((int) $reader->id)]);
        }
        // Not a code: perhaps the name of a reader who came without the card.
        if (preg_match('/\p{L}{3,}/u', $code)) {
            $found = $this->find_readers($code);
            if (count($found) === 1) {
                return $this->answer('reader', 'ok', null, null, ['reader' => $this->reader_panel($found[0]['id'])]);
            }
            if ($found) {
                return $this->answer('choose', 'look', 'deskchoose', $code, ['readers' => $found]);
            }
        }
        return $this->answer('unknown', 'no', 'deskunknown', $code);
    }

    /**
     * Readers whose name has every word typed.
     *
     * @param string $text
     * @param int $limit
     * @return array[] id, name and group.
     */
    public function find_readers(string $text, int $limit = 8): array {
        global $DB;
        $words = \local_oksigeniaayvurenda\core\text::words($text);
        if (!$words) {
            return [];
        }
        $name = $DB->sql_concat_join("' '", ["COALESCE(r.firstname, u.firstname, '')", "COALESCE(r.lastname, u.lastname, '')"]);
        $conditions = [];
        $params = ['entityid' => $this->library->entityid];
        foreach ($words as $i => $word) {
            $conditions[] = $DB->sql_like($name, ':w' . $i, false, false);
            $params['w' . $i] = '%' . $DB->sql_like_escape($word) . '%';
        }
        $rows = $DB->get_records_sql(
            "SELECT r.id
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.status = 'active' AND " . implode(' AND ', $conditions) . "
           ORDER BY r.lastname, r.firstname, r.id",
            $params,
            0,
            $limit
        );
        $found = [];
        foreach (array_keys($rows) as $id) {
            $reader = $this->reader((int) $id);
            $found[] = ['id' => (int) $id, 'name' => $reader['name'], 'group' => $reader['group']];
        }
        return $found;
    }

    /**
     * What the desk shows of a reader: name, group, the loans out and which are late.
     *
     * @param int $readerid
     * @return array
     */
    public function reader_panel(int $readerid): array {
        global $DB;
        $reader = $this->reader($readerid);
        $loans = [];
        $late = 0;
        $rows = $DB->get_records_sql(
            'SELECT l.id, l.timedue, l.libraryid, r.title
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL
           ORDER BY l.timedue, l.id',
            ['readerid' => $readerid]
        );
        foreach ($rows as $row) {
            $days = loan_rules::days_late((int) $row->timedue, $this->now, $this->tz);
            $late += $days > 0 ? 1 : 0;
            $loans[] = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'due' => $this->date((int) $row->timedue),
                'late' => $days > 0 ? get_string(
                    $days === 1 ? 'desklateoneday' : 'desklatedays',
                    'local_oksigeniaayvurenda',
                    $days
                ) : '',
            ];
        }
        return $reader + ['loans' => $loans, 'latecount' => $late, 'holds' => holds::of_reader($readerid, $this->now)];
    }

    /**
     * Gives a loan more time.
     *
     * @param int $loanid
     * @return array
     */
    public function renew(int $loanid): array {
        global $DB;
        $loan = $this->loan($loanid);
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], '*', MUST_EXIST);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid], '*', MUST_EXIST);
        $rule = $this->rule($reader->readertype, $item->itemtype);
        if (!loan_rules::can_renew($rule, (int) $loan->renewals)) {
            return $this->answer('stop', 'no', 'desknorenew', $this->title($item), ['readerid' => (int) $reader->id]);
        }
        if (holds::blocks_renewal($item)) {
            return $this->answer('stop', 'no', 'desknorenewheld', $this->title($item), ['readerid' => (int) $reader->id]);
        }
        $previous = (int) $loan->timedue;
        if ($loan->timereturned !== null) {
            // Renewing right after the copy came back: it goes out again with the same loan.
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        }
        $due = loan_rules::renewed_due($previous, $this->now, $rule, $this->tz);
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timedue' => $due, 'timereturned' => null,
            'returnstaffid' => null, 'renewals' => $loan->renewals + 1, 'timemodified' => $this->now]);
        $a = (object) ['title' => $this->title($item), 'due' => $this->date($due)];
        return $this->answer('renewed', 'ok', 'deskrenewed', $a, ['loanid' => (int) $loan->id, 'previousdue' => $previous,
            'readerid' => (int) $reader->id, 'reader' => $this->reader_panel((int) $reader->id)]);
    }

    /**
     * Notes what the reader said of the book on returning it.
     *
     * @param int $loanid
     * @param string $note One of NOTES, or '' to take it away.
     * @return array
     */
    public function note(int $loanid, string $note): array {
        global $DB;
        $loan = $this->loan($loanid);
        $note = in_array($note, self::NOTES, true) ? $note : null;
        $DB->set_field(self::T . 'loan', 'returnnote', $note, ['id' => $loan->id]);
        return ['kind' => 'noted', 'loanid' => (int) $loan->id, 'note' => $note ?? ''];
    }

    /**
     * Notes the state of material when it went or came back, and keeps it as the unit's state.
     *
     * @param int $loanid
     * @param string $when out or in.
     * @param string $condition One of CONDITIONS.
     * @return array
     */
    public function condition(int $loanid, string $when, string $condition): array {
        global $DB;
        $loan = $this->loan($loanid);
        if (!in_array($condition, self::CONDITIONS, true) || !in_array($when, ['out', 'in'], true)) {
            throw new \invalid_parameter_exception('condition');
        }
        $DB->set_field(self::T . 'loan', 'condition' . $when, $condition, ['id' => $loan->id]);
        $DB->set_field(self::T . 'item', 'itemcondition', $condition, ['id' => $loan->itemid]);
        return ['kind' => 'condition', 'loanid' => (int) $loan->id, 'condition' => $condition];
    }

    /**
     * Undoes what the desk just did: a loan, a return or a renewal.
     *
     * @param int $loanid
     * @param string $what lent, returned or renewed.
     * @param int $previousdue For a renewal: the due date before it.
     * @return array
     */
    public function undo(int $loanid, string $what, int $previousdue = 0): array {
        global $DB;
        $loan = $this->loan($loanid);
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        if ($what === 'lent' && $loan->timereturned === null) {
            // A loan that fulfilled a reservation leaves the copy set aside again; any other, back to the shelf, or to
            // whoever waits for it.
            if (!holds::undo_loan((int) $loan->id, $this->now)) {
                $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available',
                    'timemodified' => $this->now]);
                $item->status = 'available';
                holds::place($item, $this->now);
            }
            $DB->delete_records(self::T . 'loan', ['id' => $loan->id]);
        } else if ($what === 'returned' && $loan->timereturned !== null) {
            holds::undo_return((int) $item->id, $this->now);
            $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => null, 'returnstaffid' => null,
                'returnnote' => null, 'timemodified' => $this->now]);
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        } else if ($what === 'renewed' && $previousdue > 0 && $loan->renewals > 0) {
            $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timedue' => $previousdue,
                'renewals' => $loan->renewals - 1, 'timemodified' => $this->now]);
        }
        $transaction->allow_commit();
        return $this->answer(
            'undone',
            'look',
            'deskundone',
            $this->title($item),
            ['loanid' => (int) $loan->id, 'reader' => $this->reader_panel((int) $loan->readerid)]
        );
    }

    /**
     * What the desk shows when no reader is open: what comes back today, what is late, what is out.
     *
     * @return array
     */
    public function today(): array {
        global $DB;
        $start = (new \DateTimeImmutable('@' . $this->now))->setTimezone($this->tz)->setTime(0, 0)->getTimestamp();
        $end = $start + DAYSECS;
        $base = 'libraryid = :libraryid AND timereturned IS NULL';
        $params = ['libraryid' => $this->library->id];
        $out = $DB->count_records_select(self::T . 'loan', $base, $params);
        $today = $DB->count_records_select(
            self::T . 'loan',
            $base . ' AND timedue >= :start AND timedue < :end',
            $params + ['start' => $start, 'end' => $end]
        );
        $late = $DB->count_records_select(self::T . 'loan', $base . ' AND timedue < :start', $params + ['start' => $start]);
        $held = $DB->count_records(self::T . 'hold', ['libraryid' => $this->library->id, 'status' => 'ready']);
        return ['out' => $out, 'today' => $today, 'late' => $late, 'held' => $held];
    }

    /**
     * Lends a free copy to the open reader.
     *
     * @param \stdClass $item
     * @param int $readerid
     * @param bool $force
     * @return array
     */
    private function lend(\stdClass $item, int $readerid, bool $force): array {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $this->library->entityid], '*', MUST_EXIST);
        $rule = $this->rule($reader->readertype, $item->itemtype);
        $open = $DB->get_records_sql(
            'SELECT l.id, l.timedue, i.itemtype
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL',
            ['readerid' => $readerid]
        );
        $samerule = 0;
        $late = 0;
        foreach ($open as $loan) {
            if ($rule !== null && ($rule['itemtype'] === '*' || $rule['itemtype'] === $loan->itemtype)) {
                $samerule++;
            }
            if ((int) $loan->timedue < $this->now) {
                $late++;
            }
        }
        $set = $item->status === 'held' ? holds::ready_for((int) $item->id) : null;
        $decision = policy::lend($rule, $item->status, $samerule, $late, $force, $set && (int) $set->readerid !== $readerid);
        if ($decision !== policy::OK) {
            $a = (object) ['title' => $this->title($item), 'max' => $rule['maxloans'] ?? 0];
            if ($decision === policy::HELD) {
                $a->who = $this->who((int) $set->readerid);
            }
            return $this->answer('stop', 'no', 'deskstop' . $decision, $a, [
                'reason' => $decision,
                'forceable' => in_array($decision, policy::FORCEABLE, true),
                'code' => $item->barcode,
                'readerid' => $readerid,
            ]);
        }
        $year = years::around((int) $this->library->entityid, $this->now);
        $group = $DB->get_field(
            self::T . 'member',
            'groupname',
            ['readerid' => $readerid, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
        $due = loan_rules::due($this->now, (int) $rule['loandays'], $this->tz);
        $transaction = $DB->start_delegated_transaction();
        $loanid = $DB->insert_record(self::T . 'loan', [
            'libraryid' => $this->library->id,
            'itemid' => $item->id,
            'readerid' => $readerid,
            'yearid' => $year->id,
            'groupname' => $group ?: null,
            'timeloaned' => $this->now,
            'timedue' => $due,
            'renewals' => 0,
            'staffid' => $this->staffid,
            'timecreated' => $this->now,
            'timemodified' => $this->now,
        ]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        $fulfilled = holds::after_loan($item, $readerid, (int) $loanid, $this->now);
        $material = $this->material($item, (int) $loanid, 'out');
        $transaction->allow_commit();
        $a = (object) ['title' => $this->title($item), 'due' => $this->date($due)];
        $message = $fulfilled ? 'desklentheld' : 'desklent';
        return $this->answer('lent', 'ok', $message, $a, ['loanid' => (int) $loanid, 'readerid' => $readerid,
            'reader' => $this->reader_panel($readerid), 'material' => $material]);
    }

    /**
     * Takes a copy back. A copy marked out without a loan (as an import may bring it) comes back too.
     *
     * @param \stdClass $item
     * @param \stdClass|false $loan
     * @param int $readerid The reader open at the desk.
     * @return array
     */
    private function give_back(\stdClass $item, $loan, int $readerid): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $this->now]);
        if (!$loan) {
            $transaction->allow_commit();
            return $this->answer('returned', 'ok', 'deskreturnednoloan', $this->title($item), ['loanid' => 0]);
        }
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => $this->now,
            'returnstaffid' => $this->staffid, 'timemodified' => $this->now]);
        $material = $this->material($item, (int) $loan->id, 'in');
        $item->status = 'available';
        // A copy of a box that already went back goes home now.
        lots::come_home($item, $this->now);
        $hold = holds::place($item, $this->now);
        $transaction->allow_commit();
        $owner = $this->reader((int) $loan->readerid);
        $days = loan_rules::days_late((int) $loan->timedue, $this->now, $this->tz);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
        $rule = $reader ? $this->rule($reader->readertype, $item->itemtype) : null;
        $a = (object) ['title' => $this->title($item), 'name' => $owner['name'], 'days' => $days];
        $extra = [
            'loanid' => (int) $loan->id,
            'material' => $material,
            'canrenew' => !$hold && loan_rules::can_renew($rule, (int) $loan->renewals) && !holds::blocks_renewal($item),
            'late' => $days,
            'readerid' => (int) $loan->readerid,
        ];
        if ($readerid) {
            $extra['reader'] = $this->reader_panel($readerid);
        }
        if ($hold) {
            // Set it aside: the other sound, and who it is for.
            $a->for = $this->who((int) $hold->readerid);
            $extra['held'] = true;
            return $this->answer('returned', 'look', $days > 0 ? 'deskreturnedlateheld' : 'deskreturnedheld', $a, $extra);
        }
        return $this->answer(
            'returned',
            $days > 0 ? 'look' : 'ok',
            $days > 0 ? 'deskreturnedlate' : 'deskreturned',
            $a,
            $extra
        );
    }

    /**
     * The loan rule for a reader type and copy type: the library's, else that of the library it hangs from, else a
     * plain default.
     *
     * @param string $readertype
     * @param string $itemtype
     * @return array|null
     */
    private function rule(string $readertype, string $itemtype): ?array {
        global $DB;
        $library = $this->library;
        while ($library) {
            $rules = $DB->get_records(self::T . 'rule', ['libraryid' => $library->id]);
            if ($rules) {
                return loan_rules::pick(array_map(fn($r) => (array) $r, $rules), $readertype, $itemtype);
            }
            $library = $library->parentid ? $DB->get_record(self::T . 'library', ['id' => $library->parentid]) : null;
        }
        return self::DEFAULT_RULE;
    }

    /**
     * A reader's name, group this year and type.
     *
     * @param int $readerid
     * @return array
     */
    public function reader(int $readerid): array {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid], '*', MUST_EXIST);
        $name = trim(($reader->firstname ?? '') . ' ' . ($reader->lastname ?? ''));
        if ($reader->userid) {
            $user = \core_user::get_user($reader->userid);
            if ($user) {
                $name = fullname($user);
            }
        }
        $group = $DB->get_field_sql(
            'SELECT m.groupname
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'year} y ON y.id = m.yearid
              WHERE m.readerid = :readerid AND y.timestart <= :a AND y.timeend > :b',
            ['readerid' => $readerid, 'a' => $this->now, 'b' => $this->now],
            IGNORE_MULTIPLE
        );
        return [
            'id' => $readerid,
            'name' => $name !== '' ? $name : $reader->cardcode,
            'group' => $group ?: '',
            'cardcode' => $reader->cardcode,
            'type' => $reader->readertype,
        ];
    }

    /**
     * Who a reader is, as the desk says it: «Hugo Lago (5.º A)».
     *
     * @param int $readerid
     * @return string
     */
    private function who(int $readerid): string {
        $reader = $this->reader($readerid);
        return $reader['name'] . ($reader['group'] !== '' ? ' (' . $reader['group'] . ')' : '');
    }

    /**
     * A loan of this library's entity.
     *
     * @param int $loanid
     * @return \stdClass
     */
    private function loan(int $loanid): \stdClass {
        global $DB;
        return $DB->get_record_sql(
            'SELECT l.*
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'library} b ON b.id = l.libraryid
              WHERE l.id = :id AND b.entityid = :entityid',
            ['id' => $loanid, 'entityid' => $this->library->entityid],
            MUST_EXIST
        );
    }

    /**
     * The title of a copy's record.
     *
     * @param \stdClass $item
     * @return string
     */
    private function title(\stdClass $item): string {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $item->recordid], 'title, doctype');
        if (!$record) {
            return '';
        }
        // Units of material look alike: the serial number, or the label, tells which one.
        if ($record->doctype === 'device') {
            return get_string('deskunit', 'local_oksigeniaayvurenda', (object) ['title' => $record->title,
                'unit' => (string) ($item->serial ?: $item->barcode)]);
        }
        return (string) $record->title;
    }

    /**
     * For material: notes its present state on the loan, and gives what the screen needs to change it with a tap.
     *
     * @param \stdClass $item
     * @param int $loanid
     * @param string $when out or in.
     * @return array|null conditions, current and when; null for what is not material.
     */
    private function material(\stdClass $item, int $loanid, string $when): ?array {
        global $DB;
        if ($DB->get_field(self::T . 'record', 'doctype', ['id' => $item->recordid]) !== 'device') {
            return null;
        }
        $current = in_array($item->itemcondition, self::CONDITIONS, true) ? $item->itemcondition : 'good';
        $DB->set_field(self::T . 'loan', 'condition' . $when, $current, ['id' => $loanid]);
        $conditions = [];
        foreach (self::CONDITIONS as $condition) {
            $conditions[] = ['value' => $condition, 'label' => get_string('condition' . $condition, 'local_oksigeniaayvurenda')];
        }
        return ['conditions' => $conditions, 'current' => $current, 'when' => $when];
    }

    /**
     * A date as the desk says it: «30 de octubre».
     *
     * @param int $time
     * @return string
     */
    private function date(int $time): string {
        return userdate($time, get_string('strftimedateshort', 'langconfig'), $this->tz->getName());
    }

    /**
     * An answer for the screen.
     *
     * @param string $kind
     * @param string $sound ok, look or no.
     * @param string|null $message A string key of the plugin.
     * @param mixed $a Its parameter.
     * @param array $extra
     * @return array
     */
    private function answer(string $kind, string $sound, ?string $message, $a = null, array $extra = []): array {
        return ['kind' => $kind, 'sound' => $sound,
            'message' => $message !== null ? get_string($message, 'local_oksigeniaayvurenda', $a) : ''] + $extra;
    }
}
