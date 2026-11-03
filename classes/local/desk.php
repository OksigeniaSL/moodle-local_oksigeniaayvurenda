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

    /** @var bool Whoever is at it keeps this library only: its own books and, in a classroom, the readers of its groups. */
    private bool $own;

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
     * @param bool $own Whoever is at it keeps only this library (keepers::is), not the centre's.
     */
    public function __construct(\stdClass $library, int $staffid, ?int $now = null, bool $own = false) {
        $this->library = $library;
        $this->staffid = $staffid;
        $this->own = $own;
        $this->now = $now ?? time();
        $this->tz = zones::of($library);
    }

    /**
     * A copy or a card by its code, as the scanner or the keyboard gives it: first as it is and in capitals, which the
     * index finds at once; only if neither is there, without minding capitals (a read of every code of the centre).
     *
     * @param string $table item or reader.
     * @param string $field barcode or cardcode.
     * @param int $entityid
     * @param string $code
     * @param string $extra More conditions, starting with « AND ».
     * @return \stdClass|null
     */
    public static function by_code(string $table, string $field, int $entityid, string $code, string $extra = ''): ?\stdClass {
        global $DB;
        foreach (array_unique([$code, \core_text::strtoupper($code)]) as $exact) {
            $found = $DB->get_records_select(
                self::T . $table,
                "entityid = :entityid AND $field = :code" . $extra,
                ['entityid' => $entityid, 'code' => $exact],
                'id',
                '*',
                0,
                1
            );
            if ($found) {
                return reset($found);
            }
        }
        $found = $DB->get_records_select(self::T . $table, 'entityid = :entityid AND ' . $DB->sql_equal($field, ':code', false)
            . $extra, ['entityid' => $entityid, 'code' => $code], 'id', '*', 0, 1);
        return $found ? reset($found) : null;
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
        $item = self::by_code('item', 'barcode', (int) $this->library->entityid, $code);
        if ($item && $this->own && (int) $item->libraryid !== (int) $this->library->id) {
            return $this->answer('unknown', 'no', 'deskotherlibrary', $this->title($item));
        }
        if ($item) {
            $open = $DB->get_record_select(
                self::T . 'loan',
                'itemid = :itemid AND timereturned IS NULL',
                ['itemid' => $item->id],
                '*',
                IGNORE_MULTIPLE
            );
            if ($open && $readerid && !empty($open->copyguessed) && (int) $open->readerid !== $readerid) {
                // A loan on paper took this copy without knowing which went: it is still here, and another goes with it.
                $other = $this->free_copy((int) $item->recordid, (int) $item->id);
                if ($other) {
                    $this->move_loan($open, $item, $other, false);
                    $open = null;
                    $item->status = 'available';
                }
            }
            if ($open || $item->status === 'onloan') {
                return $this->give_back($item, $open, $readerid);
            }
            // A loan on paper of this record took another copy: this is the one that went.
            $guessed = $this->guessed($item, $readerid);
            if ($guessed && $readerid) {
                $from = $DB->get_record(self::T . 'item', ['id' => $guessed->itemid], '*', MUST_EXIST);
                $this->move_loan($guessed, $from, $item, true);
                $item->status = 'onloan';
                return $this->give_back($item, $DB->get_record(self::T . 'loan', ['id' => $guessed->id]), $readerid);
            }
            if (!$readerid) {
                $hold = $item->status === 'held' ? holds::ready_for((int) $item->id) : null;
                if ($hold) {
                    return $this->answer('needreader', 'look', 'deskheldfor', (object) ['title' => $this->title($item),
                        'who' => $this->who((int) $hold->readerid)]);
                }
                if ($guessed) {
                    return $this->answer('needreader', 'look', 'deskguessed', (object) ['title' => $this->title($item),
                        'who' => $this->who((int) $guessed->readerid)], ['itemid' => (int) $item->id, 'room' => true]);
                }
                // Or it was read here, without going home: one touch counts it.
                return $this->answer('needreader', 'look', 'deskneedreader', $this->title($item), ['itemid' => (int) $item->id,
                    'room' => true]);
            }
            return $this->lend($item, $readerid, $force);
        }
        $reader = self::by_code('reader', 'cardcode', (int) $this->library->entityid, $code, " AND status <> 'deleted'");
        if ($reader && !$this->allowed((int) $reader->id)) {
            return $this->answer('unknown', 'no', 'deskotherreader');
        }
        if ($reader) {
            return $this->answer('reader', 'ok', null, null, ['reader' => $this->reader_panel((int) $reader->id)]);
        }
        // Not a code: with a reader open, the title of a book without a label (or whose label does not read); without
        // one, the name of a reader who came without the card, or the title of a book that comes back.
        $words = (bool) preg_match('/\p{L}{3,}/u', $code);
        if ($words) {
            if ($readerid) {
                $books = $this->find_books($code, true);
                if ($books) {
                    return $this->answer('choose', 'look', 'deskchoosebooks', $code, ['books' => $books,
                        'readerid' => $readerid]);
                }
            }
            $found = $this->find_readers($code);
            if (!$readerid && count($found) === 1) {
                return $this->answer('reader', 'ok', null, null, ['reader' => $this->reader_panel($found[0]['id'])]);
            }
            $out = $readerid ? [] : $this->find_books($code, false);
            if ($found || $out) {
                return $this->answer('choose', 'look', $found ? 'deskchoose' : 'deskchooseout', $code, ['readers' => $found,
                    'out' => $out]);
            }
        }
        return $this->answer('unknown', 'no', $readerid && $words ? 'deskunknownbook' : 'deskunknown', $code);
    }

    /**
     * The loan on paper of another copy of this one's record, of the reader open at the desk, or the only one when no
     * reader is open.
     *
     * @param \stdClass $item
     * @param int $readerid
     * @return \stdClass|null
     */
    private function guessed(\stdClass $item, int $readerid): ?\stdClass {
        global $DB;
        $loans = $DB->get_records_sql(
            'SELECT l.*
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND l.copyguessed = 1
                AND i.recordid = :recordid AND i.id <> :itemid',
            ['libraryid' => $this->library->id, 'recordid' => $item->recordid, 'itemid' => $item->id]
        );
        if ($readerid) {
            $loans = array_filter($loans, fn($loan) => (int) $loan->readerid === $readerid);
        }
        return count($loans) === 1 ? reset($loans) : null;
    }

    /**
     * Another copy of a record on the shelf of this library.
     *
     * @param int $recordid
     * @param int $notid
     * @return \stdClass|null
     */
    private function free_copy(int $recordid, int $notid): ?\stdClass {
        global $DB;
        return $DB->get_record_sql(
            'SELECT i.*
               FROM {' . self::T . 'item} i
              WHERE i.recordid = :recordid AND i.libraryid = :libraryid AND i.status = :available AND i.id <> :notid
                AND NOT EXISTS (SELECT 1 FROM {' . self::T . 'loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
           ORDER BY i.barcode',
            ['recordid' => $recordid, 'libraryid' => $this->library->id, 'available' => 'available', 'notid' => $notid],
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Moves a loan on paper from the copy it took to another: the one that really went, or one more still to guess. The
     * first goes back on the shelf.
     *
     * @param \stdClass $loan
     * @param \stdClass $from
     * @param \stdClass $to
     * @param bool $known Whether $to is the copy that went.
     */
    private function move_loan(\stdClass $loan, \stdClass $from, \stdClass $to, bool $known): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'itemid' => $to->id, 'copyguessed' => $known ? 0 : 1,
            'timemodified' => $this->now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $from->id, 'status' => 'available', 'timemodified' => $this->now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $to->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        $transaction->allow_commit();
    }

    /**
     * Books of this library whose title, author or subject has every word typed: those with a copy on the shelf, to
     * lend, or the loans out of them, to bring back.
     *
     * @param string $text
     * @param bool $shelf On the shelf (true) or out (false).
     * @param int $limit
     * @return array[] On the shelf: recordid, title, author, free (how many), callnumber. Out: loanid, title, who, due.
     */
    public function find_books(string $text, bool $shelf, int $limit = 8): array {
        global $DB;
        $words = \local_oksigeniaayvurenda\core\text::words($text);
        if (!$words) {
            return [];
        }
        $conditions = [];
        $params = ['libraryid' => $this->library->id, 'entityid' => $this->library->entityid];
        foreach ($words as $i => $word) {
            $conditions[] = $DB->sql_like('r.searchtext', ':w' . $i, false, false);
            $params['w' . $i] = '%' . $DB->sql_like_escape($word) . '%';
        }
        $match = implode(' AND ', $conditions);
        if ($shelf) {
            $rows = $DB->get_records_sql(
                "SELECT r.id, r.title, COUNT(i.id) AS free, MIN(i.callnumber1) AS line1, MIN(i.callnumber2) AS line2
                   FROM {" . self::T . "record} r
                   JOIN {" . self::T . "item} i ON i.recordid = r.id
                  WHERE r.entityid = :entityid AND i.libraryid = :libraryid AND i.status = 'available' AND $match
                    AND NOT EXISTS (SELECT 1 FROM {" . self::T . "loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
               GROUP BY r.id, r.title",
                $params,
                0,
                200
            );
            $rows = self::best_first($rows, $words, $limit);
            return array_values(array_map(fn($row) => [
                'recordid' => (int) $row->id,
                'title' => format_string($row->title, true, ['escape' => false]),
                'author' => $this->author((int) $row->id),
                'free' => (int) $row->free,
                'callnumber' => trim($row->line1 . ' ' . $row->line2),
            ], $rows));
        }
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.readerid, l.timedue, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND r.entityid = :entityid AND $match
           ORDER BY l.timeloaned",
            $params,
            0,
            200
        );
        $rows = self::best_first($rows, $words, $limit);
        $out = [];
        foreach ($rows as $row) {
            if (!$this->allowed((int) $row->readerid)) {
                continue;
            }
            $who = $this->reader((int) $row->readerid);
            $out[] = [
                'loanid' => (int) $row->id,
                'title' => format_string($row->title, true, ['escape' => false]),
                'who' => $who['name'] . ($who['group'] !== '' ? ' · ' . $who['group'] : ''),
                'due' => $this->date((int) $row->timedue),
            ];
        }
        return $out;
    }

    /**
     * The books found, those with the words in their title first: the words as they were typed, then all of them, then
     * the rest (a word in the author or a subject); shorter titles first.
     *
     * @param \stdClass[] $rows Each with a title.
     * @param string[] $words
     * @param int $limit
     * @return \stdClass[]
     */
    private static function best_first(array $rows, array $words, int $limit): array {
        $phrase = implode(' ', $words);
        $rank = function (\stdClass $row) use ($words, $phrase): array {
            $title = \local_oksigeniaayvurenda\core\text::normalise((string) $row->title);
            $all = !array_diff($words, \local_oksigeniaayvurenda\core\text::words((string) $row->title));
            return [str_contains(' ' . $title . ' ', ' ' . $phrase . ' ') ? 0 : ($all ? 1 : 2), strlen($title), $title];
        };
        usort($rows, fn($a, $b) => $rank($a) <=> $rank($b));
        return array_slice($rows, 0, $limit);
    }

    /**
     * Lends a book chosen by its title: the first copy on the shelf that can go. When the record has more than one, which
     * went is not known: the loan says so, and the copy that comes back is taken as the one.
     *
     * @param int $recordid
     * @param int $readerid
     * @param bool $force
     * @return array As scan() answers.
     */
    public function lend_record(int $recordid, int $readerid, bool $force = false): array {
        global $DB;
        $copies = $DB->get_records_sql(
            "SELECT i.*
               FROM {" . self::T . "item} i
              WHERE i.recordid = :recordid AND i.libraryid = :libraryid AND i.status = 'available'
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
           ORDER BY i.barcode",
            ['recordid' => $recordid, 'libraryid' => $this->library->id]
        );
        if (!$copies) {
            return $this->answer('stop', 'no', 'desknocopy', format_string(
                (string) $DB->get_field(self::T . 'record', 'title', ['id' => $recordid, 'entityid' => $this->library->entityid]),
                true,
                ['escape' => false]
            ));
        }
        $several = $DB->count_records(self::T . 'item', ['recordid' => $recordid, 'libraryid' => $this->library->id]) > 1;
        $answer = null;
        foreach ($copies as $item) {
            $answer = $this->lend($item, $readerid, $force);
            if ($answer['kind'] === 'lent') {
                if ($several) {
                    $DB->set_field(self::T . 'loan', 'copyguessed', 1, ['id' => $answer['loanid']]);
                }
                return $answer;
            }
            // One only for the room says so; the next may go.
            if (($answer['reason'] ?? '') !== policy::NOT_LENDABLE) {
                break;
            }
        }
        return $answer + ['recordid' => $recordid];
    }

    /**
     * Brings back a loan chosen from the list, for a book without a label.
     *
     * @param int $loanid
     * @param int $readerid The reader open at the desk, 0 if none.
     * @return array As scan() answers.
     */
    public function give_back_loan(int $loanid, int $readerid = 0): array {
        global $DB;
        $loan = $this->loan($loanid);
        if ($loan->timereturned !== null) {
            return $this->answer('stop', 'no', 'deskalreadyback');
        }
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], '*', MUST_EXIST);
        return $this->give_back($item, $loan, $readerid);
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
            if (!$this->allowed((int) $id)) {
                continue;
            }
            $reader = $this->reader((int) $id);
            $found[] = ['id' => (int) $id, 'name' => $reader['name'], 'group' => $reader['group'], 'photo' => $reader['photo'],
                'initial' => $reader['initial']];
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
        if (!$this->allowed($readerid)) {
            throw new \moodle_exception('deskotherreader', 'local_oksigeniaayvurenda');
        }
        $reader = $this->reader($readerid);
        $loans = [];
        $late = 0;
        $rows = $DB->get_records_sql(
            'SELECT l.id, l.timedue, l.libraryid, r.title
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL' . ($this->own ? ' AND l.libraryid = :libraryid' : '') . '
           ORDER BY l.timedue, l.id',
            ['readerid' => $readerid, 'libraryid' => $this->library->id]
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
        // Whoever keeps one library only sees what waits for the reader there.
        $holds = $this->own ? [] : holds::of_reader($readerid, $this->now);
        // At a textbook bank, the set of their grade: what is still to give, and the contract for the family.
        $set = bank::is($this->library) ? bank::of_reader($this->library, $readerid, $this->now) : null;
        if ($set) {
            $set['contract'] = (new \moodle_url('/local/oksigeniaayvurenda/contract.php', ['library' => $this->library->slug,
                'reader' => $readerid]))->out(false);
            $set['pending'] = array_values(array_column(array_filter($set['titles'], fn($t) => !$t['out']), 'title'));
        }
        return $reader + ['loans' => $loans, 'latecount' => $late, 'holds' => $holds, 'bank' => $set,
            'sanction' => sanctions::panel($this->library, $readerid, $this->now, $this->staffid)];
    }

    /**
     * Lifts the days without lending of a reader, or closes what they owe.
     *
     * @param int $readerid
     * @param string $what lift, pay or waive.
     * @param int $row What one book left, from its line; 0 for all of it, from the panel.
     * @return array
     */
    public function sanction(int $readerid, string $what, int $row = 0): array {
        global $DB;
        $ofcentre = $DB->record_exists(self::T . 'reader', ['id' => $readerid, 'entityid' => $this->library->entityid]);
        if (!$ofcentre || !$this->allowed($readerid) || !in_array($what, ['lift', 'pay', 'waive'], true)) {
            return $this->answer('unknown', 'no', 'deskotherreader');
        }
        if ($what === 'lift') {
            sanctions::lift($this->library, $readerid, $this->staffid, $this->now, $row);
        } else {
            $status = $what === 'pay' ? sanctions::PAID : sanctions::WAIVED;
            sanctions::settle($this->library, $readerid, $status, $this->staffid, $this->now, $row);
        }
        $words = ['lift' => 'lifted', 'pay' => 'paid', 'waive' => 'waived'][$what];
        $message = $row ? 'sanctionrow' . $words : 'sanction' . $words;
        return $this->answer('sanction', 'ok', $message, $row ? null : $this->who($readerid), ['readerid' => $readerid,
            'reader' => $this->reader_panel($readerid)]);
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
        if (reserves::active((int) $item->id, $this->now)) {
            return $this->answer('stop', 'no', 'desknorenewreserve', $this->title($item), ['readerid' => (int) $reader->id]);
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
     * Whether a loan out can be given more time now, as renew() would see it, and until when.
     *
     * @param int $loanid
     * @return int|null The new date it would be due, or null if it cannot.
     */
    public function renewable(int $loanid): ?int {
        global $DB;
        $loan = $this->loan($loanid);
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], '*', MUST_EXIST);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid], '*', MUST_EXIST);
        $rule = $this->rule($reader->readertype, $item->itemtype);
        if (
            !loan_rules::can_renew($rule, (int) $loan->renewals) || holds::blocks_renewal($item)
            || reserves::active((int) $item->id, $this->now)
        ) {
            return null;
        }
        return loan_rules::renewed_due((int) $loan->timedue, $this->now, $rule, $this->tz);
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
            sanctions::undo_return((int) $loan->id);
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
        if (!$this->allowed($readerid)) {
            return $this->answer('unknown', 'no', 'deskotherreader');
        }
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
        // A textbook bank lends what a student needs for class, whatever they owe the library.
        $sanction = $force || bank::is($this->library) ? null
            : sanctions::stop((int) $this->library->entityid, $readerid, $this->now);
        $decision = policy::lend(
            $rule,
            $item->status,
            $samerule,
            $late,
            $force,
            $set && (int) $set->readerid !== $readerid,
            $sanction['reason'] ?? null
        );
        if ($decision !== policy::OK) {
            $a = (object) ['title' => $this->title($item), 'max' => $rule['maxloans'] ?? 0,
                'until' => $sanction['until'] ?? '', 'owes' => $sanction['owes'] ?? ''];
            if ($decision === policy::HELD) {
                $a->who = $this->who((int) $set->readerid);
            }
            // Why is for the staff of the centre: whoever only keeps this library hears where to send them.
            $hidden = $sanction && !sanctions::may_know($this->library, $this->staffid);
            return $this->answer('stop', 'no', $hidden ? 'deskstopelsewhere' : 'deskstop' . $decision, $a, [
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
        // From a textbook bank, until the school year ends; a copy on reserve for a course, short (or back today).
        $reserve = reserves::active((int) $item->id, $this->now);
        $due = bank::is($this->library) ? (int) $year->timeend - 1
            : ($reserve ? loan_rules::due($this->now, (int) $reserve->days, $this->tz, (int) $reserve->days > 0)
            : loan_rules::due($this->now, (int) $rule['loandays'], $this->tz));
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
            'staffid' => $this->staffid ?: null,
            'timecreated' => $this->now,
            'timemodified' => $this->now,
        ]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        $fulfilled = holds::after_loan($item, $readerid, (int) $loanid, $this->now);
        $material = $this->material($item, (int) $loanid, 'out');
        $transaction->allow_commit();
        $a = (object) ['title' => $this->title($item), 'due' => $this->date($due)];
        $message = $fulfilled ? 'desklentheld' : ($reserve ? 'desklentreserve' : 'desklent');
        if ($reserve) {
            $a->course = format_string(
                (string) $DB->get_field('course', 'shortname', ['id' => $reserve->courseid]),
                true,
                ['escape' => false]
            );
        }
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
            'returnstaffid' => $this->staffid ?: null, 'timemodified' => $this->now]);
        $material = $this->material($item, (int) $loan->id, 'in');
        $item->status = 'available';
        // A copy of a box that already went back goes home now.
        lots::come_home($item, $this->now);
        $hold = holds::place($item, $this->now);
        $days = loan_rules::days_late((int) $loan->timedue, $this->now, $this->tz);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
        $sanction = $reader ? sanctions::after_return($this->library, $reader, $loan, $days, $this->staffid, $this->now) : null;
        $transaction->allow_commit();
        $owner = $this->reader((int) $loan->readerid);
        $rule = $reader ? $this->rule($reader->readertype, $item->itemtype) : null;
        $a = (object) ['title' => $this->title($item), 'name' => $owner['name'], 'days' => $days];
        $extra = [
            'loanid' => (int) $loan->id,
            'material' => $material,
            'canrenew' => !$hold && loan_rules::can_renew($rule, (int) $loan->renewals) && !holds::blocks_renewal($item),
            'late' => $days,
            'readerid' => (int) $loan->readerid,
            'sanction' => $sanction,
        ];
        if ($readerid) {
            $extra['reader'] = $this->reader_panel($readerid);
        }
        // The herald, in a corner, a word as each book comes home: never in the way, and never at a sober centre.
        // Nor when the book has just cost the reader something.
        $extra['herald'] = $sanction ? null
            : $this->herald_word($hold ? 'deskheraldheld' : 'deskheraldback' . (1 + ((int) $loan->id % 4)));
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
        // Another library is no reader of the rules: what it gets is what it agrees on, with no limit; only what is never
        // lent stays here.
        if ($readertype === ill::READERTYPE) {
            return ['readertype' => $readertype, 'itemtype' => '*', 'loandays' => $itemtype === 'reference' ? 0 : 30,
                'maxloans' => 0,
                'renewals' => 1, 'renewdays' => 14];
        }
        // A textbook bank lends a whole set, until the year ends, unless it has its own rules.
        if (bank::is($this->library) && !$DB->record_exists(self::T . 'rule', ['libraryid' => $this->library->id])) {
            // Lent (any days, the year's end sets the date), with no limit of copies and no renewal.
            return ['readertype' => '*', 'itemtype' => '*', 'loandays' => 365, 'maxloans' => 0, 'renewals' => 0,
                'renewdays' => 0];
        }
        $library = $this->library;
        $seen = [];
        // Up the libraries it hangs from, each once (a loop in the data cannot hang the desk).
        while ($library && !isset($seen[$library->id])) {
            $seen[$library->id] = true;
            $rules = $DB->get_records(self::T . 'rule', ['libraryid' => $library->id]);
            if ($rules) {
                return loan_rules::pick(array_map(fn($r) => (array) $r, $rules), $readertype, $itemtype);
            }
            $library = !empty($library->parentid) ? $DB->get_record(self::T . 'library', ['id' => $library->parentid]) : null;
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
        // Only a reader of this library's centre: an id of another centre is not found.
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $this->library->entityid], '*', MUST_EXIST);
        $name = trim(($reader->firstname ?? '') . ' ' . ($reader->lastname ?? ''));
        $user = $reader->userid ? \core_user::get_user($reader->userid) : null;
        if ($user) {
            $name = fullname($user);
        }
        $group = $DB->get_field_sql(
            'SELECT m.groupname
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'year} y ON y.id = m.yearid
              WHERE m.readerid = :readerid AND y.timestart <= :a AND y.timeend > :b',
            ['readerid' => $readerid, 'a' => $this->now, 'b' => $this->now],
            IGNORE_MULTIPLE
        );
        $name = $name !== '' ? $name : $reader->cardcode;
        return [
            'id' => $readerid,
            'name' => $name,
            'group' => $group ?: '',
            'cardcode' => $reader->cardcode,
            'type' => $reader->readertype,
            // So the child is known even without the card; and their record, a click away.
            'photo' => profile::photo($reader, $user ?: null),
            'initial' => profile::initial($name),
            'url' => (new \moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $this->library->slug,
                'id' => $readerid]))->out(false),
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
              WHERE l.id = :id AND b.entityid = :entityid' . ($this->own ? ' AND l.libraryid = :libraryid' : ''),
            ['id' => $loanid, 'entityid' => $this->library->entityid, 'libraryid' => $this->library->id],
            MUST_EXIST
        );
    }

    /**
     * Whether a reader may be served here: at any desk of the centre, anyone; by whoever keeps a classroom library, only
     * the readers of its groups this year.
     *
     * @param int $readerid
     * @return bool
     */
    public function allowed(int $readerid): bool {
        global $DB;
        if (!$this->own) {
            return true;
        }
        $settings = json_decode((string) ($this->library->settings ?? ''), true) ?: [];
        $groups = array_values(array_filter((array) ($settings['groups'] ?? []), 'is_string'));
        if (!$groups) {
            return true;
        }
        [$in, $params] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "year} y ON y.id = m.yearid
              WHERE m.readerid = :readerid AND y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b
                    AND m.groupname $in",
            ['readerid' => $readerid, 'entityid' => $this->library->entityid, 'a' => $this->now, 'b' => $this->now] + $params
        );
    }

    /**
     * The first author of a record, as the desk shows it next to the title.
     *
     * @param int $recordid
     * @return string
     */
    private function author(int $recordid): string {
        global $DB;
        $author = $DB->get_field_sql(
            "SELECT a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = :recordid AND a.kind = 'author'
           ORDER BY ra.sortorder",
            ['recordid' => $recordid],
            IGNORE_MULTIPLE
        );
        return $author ? format_string($author, true, ['escape' => false]) : '';
    }

    /**
     * The title of a copy's record, as the desk says it.
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
        // The state goes with material, and with every textbook of a bank.
        if (!bank::is($this->library) && $DB->get_field(self::T . 'record', 'doctype', ['id' => $item->recordid]) !== 'device') {
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
     * A book read in the library without being taken home: it counts in the numbers, with no reader.
     *
     * @param int $itemid
     * @return array The answer, with the id that undoes it.
     */
    public function room(int $itemid): array {
        global $DB;
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $this->library->id], '*', MUST_EXIST);
        $id = $DB->insert_record(self::T . 'roomread', ['libraryid' => $this->library->id, 'itemid' => $item->id,
            'recordid' => $item->recordid, 'staffid' => $this->staffid ?: null, 'timecreated' => $this->now]);
        return $this->answer('roomread', 'ok', 'deskroomread', $this->title($item), ['roomid' => (int) $id]);
    }

    /**
     * Takes back a book noted as read in the library.
     *
     * @param int $roomid
     * @return array
     */
    public function room_undo(int $roomid): array {
        global $DB;
        $DB->delete_records(self::T . 'roomread', ['id' => $roomid, 'libraryid' => $this->library->id]);
        return $this->answer('undone', 'ok', 'deskroomundone');
    }

    /**
     * What the herald says in the desk's corner, if she is on and the centre's skin is lively.
     *
     * @param string $key
     * @return array|null name, text, initial, portrait.
     */
    private function herald_word(string $key): ?array {
        $entityid = (int) $this->library->entityid;
        if (bank::is($this->library) || !skins::lively($entityid)) {
            return null;
        }
        $said = characters::say($entityid, 'herald', $key);
        return $said ? ['name' => $said['name'], 'text' => $said['text'], 'initial' => $said['initial'],
            'portrait' => $said['portrait']] : null;
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
