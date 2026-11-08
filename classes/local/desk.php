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

    /**
     * The words of a state: a book is torn and missing pages, not «does not work».
     *
     * @param string $condition One of CONDITIONS.
     * @param bool $book Whether it is a book (a textbook) rather than material.
     * @return string
     */
    public static function condition_label(string $condition, bool $book): string {
        $condition = in_array($condition, self::CONDITIONS, true) ? $condition : 'good';
        $book = $book && in_array($condition, ['missingpart', 'broken'], true);
        return get_string('condition' . ($book ? 'book' : '') . $condition, 'local_oksigeniaayvurenda');
    }

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

    /** @var int How many more books the last search by title found than it shows. */
    private int $more = 0;

    /** @var bool Whether the last search of readers found more than it shows. */
    private bool $morereaders = false;

    /** @var array|null The centre's closed days, once read. */
    private ?array $closeddays = null;

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
     * Whether a copy was already lent or brought back at this very moment: a kept scan that is sent twice.
     *
     * @param string $code
     * @return bool
     */
    public function passed_at(string $code): bool {
        global $DB;
        $item = self::by_code('item', 'barcode', (int) $this->library->entityid, trim($code));
        return $item && $DB->record_exists_select(
            self::T . 'loan',
            'itemid = :itemid AND (timeloaned = :lent OR timereturned = :back)',
            ['itemid' => $item->id, 'lent' => $this->now, 'back' => $this->now]
        );
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
     * @param bool $forwhom A book is waiting for its reader: words are a name, not a title.
     * @return array The answer: kind, sound, message and what the screen needs.
     */
    public function scan(string $code, int $readerid = 0, bool $force = false, bool $forwhom = false): array {
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
            // A copy given up for lost turns up: back on the shelf, or straight to the reader who is at the desk.
            if (!$open && $item->status === 'lost') {
                $this->found($item);
                if (!$readerid) {
                    return $this->answer('returned', 'ok', 'deskfound', $this->title($item), ['loanid' => 0]);
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
                        'who' => $this->who((int) $hold->readerid)], ['title' => $this->title($item)]);
                }
                if ($guessed) {
                    return $this->answer('needreader', 'look', 'deskguessed', (object) ['title' => $this->title($item),
                        'who' => $this->who((int) $guessed->readerid)], ['itemid' => (int) $item->id, 'room' => true,
                        'title' => $this->title($item)]);
                }
                // Or it was read here, without going home: one touch counts it.
                return $this->answer('needreader', 'look', 'deskneedreader', $this->title($item), ['itemid' => (int) $item->id,
                    'room' => true, 'title' => $this->title($item)]);
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
        // The number on the back cover, for a book without a label of its own or whose label does not read.
        $isbn = self::cover_isbn($code);
        if ($isbn !== null) {
            return $this->by_isbn($isbn, $code, $readerid, $force);
        }
        // Not a code: with a reader open, the title of a book without a label (or whose label does not read); without
        // one, the name of a reader who came without the card, or the title of a book that comes back.
        $words = (bool) preg_match('/\p{L}{3,}/u', $code);
        if ($words) {
            if ($readerid) {
                $books = $this->find_books($code, true);
                if ($books) {
                    return $this->answer('choose', 'look', 'deskchoosebooks', (object) ['text' => $code,
                        'who' => $this->reader($readerid)['name']], ['books' => $books, 'readerid' => $readerid,
                        'more' => $this->more]);
                }
                // A title typed wrong: what it may have meant.
                $title = $this->near_title($code);
                $books = $title !== null ? $this->find_books($title, true) : [];
                if ($books) {
                    return $this->answer('choose', 'look', 'deskchoosebooksnear', (object) ['text' => $code,
                        'who' => $this->reader($readerid)['name']], ['books' => $books, 'readerid' => $readerid,
                        'more' => $this->more]);
                }
            }
            $found = $this->find_readers($code);
            if (!$readerid && count($found) === 1) {
                return $this->answer('reader', 'ok', null, null, ['reader' => $this->reader_panel($found[0]['id'])]);
            }
            // A book is waiting for its reader: only people, or a title would answer the question of who takes it.
            if ($forwhom) {
                if ($found) {
                    return $this->answer('choose', 'look', 'deskchoose', $code, ['readers' => $found,
                        'morereaders' => $this->morereaders]);
                }
                // Never lent straight to someone whose name was only guessed: they are chosen.
                $near = $this->near_readers($code);
                return $near ? $this->answer('choose', 'look', 'desknearreaders', $code, ['readers' => $near])
                    : $this->answer('unknown', 'no', 'deskunknownreader', $code);
            }
            // Without a reader: the loans out of it, to bring one back, and what is on the shelf, to lend it.
            $out = $readerid ? [] : $this->find_books($code, false);
            $shelf = $readerid ? [] : $this->find_books($code, true);
            $more = $this->more;
            $morereaders = $this->morereaders;
            // When one list has the words in its titles and the other only elsewhere, the other is noise.
            $titled = fn(array $books) => (bool) array_filter($books, fn($book) => $book['intitle']);
            if ($titled($shelf) && !$titled($out)) {
                $out = [];
            } else if ($titled($out) && !$titled($shelf)) {
                $shelf = [];
            }
            if ($found || $out || $shelf) {
                $message = $found ? 'deskchoose' : ($out ? 'deskchooseout' : 'deskshelfreader');
                return $this->answer('choose', 'look', $message, $code, ['readers' => $found, 'out' => $out,
                    'shelf' => $shelf, 'more' => $shelf ? $more : 0, 'morereaders' => $morereaders]);
            }
            // Nothing as it was typed: who or what it may have meant, to choose, never at once.
            if (!$readerid) {
                $near = $this->near_readers($code);
                $title = $this->near_title($code);
                $shelf = $title !== null ? $this->find_books($title, true) : [];
                if ($near || $shelf) {
                    return $this->answer('choose', 'look', 'desknear', $code, ['readers' => $near, 'shelf' => $shelf,
                        'more' => $shelf ? $this->more : 0]);
                }
            }
        }
        $unknown = !$words ? 'deskunknown' : ($readerid ? 'deskunknownbook' : 'deskunknownwords');
        return $this->answer('unknown', 'no', $unknown, $code);
    }

    /**
     * The ISBN-13 of what a scanner reads on a back cover or someone types: an EAN of a book (978 or 979, also with the
     * price add-on of 2 or 5 digits after it), or an ISBN-10 or 13 with or without hyphens. Null for anything else.
     *
     * @param string $code
     * @return string|null
     */
    public static function cover_isbn(string $code): ?string {
        if (!preg_match('/^[0-9][0-9 -]*[0-9Xx]$/', $code)) {
            return null;
        }
        $digits = strtoupper(preg_replace('/[ -]/', '', $code));
        if (in_array(strlen($digits), [15, 18], true) && ctype_digit($digits)) {
            $digits = substr($digits, 0, 13);
        }
        if (strlen($digits) === 13 && !in_array(substr($digits, 0, 3), ['978', '979'], true)) {
            return null;
        }
        if (strlen($digits) !== 13 && strlen($digits) !== 10) {
            return null;
        }
        return \local_oksigeniaayvurenda\core\isbn::normalise($digits);
    }

    /**
     * A book read by its ISBN, as if it were its copy: with a reader open, it comes back if they have it and goes out if
     * not; without one, it comes back when only one is out, stays in the hand when only the shelf has it, and the desk
     * offers both lists when it is not clear. Which copy went is not known when the record has more than one: the loan
     * says so, as when a book is lent by its title.
     *
     * @param string $isbn ISBN-13.
     * @param string $code As it was read.
     * @param int $readerid
     * @param bool $force
     * @return array As scan() answers.
     */
    private function by_isbn(string $isbn, string $code, int $readerid, bool $force): array {
        global $DB;
        $records = $DB->get_fieldset_select(self::T . 'record', 'id', 'entityid = :entityid AND isbn = :isbn', [
            'entityid' => $this->library->entityid, 'isbn' => $isbn]);
        if (!$records) {
            return $this->answer('unknown', 'no', 'deskunknownisbn', $code);
        }
        [$in, $params] = $DB->get_in_or_equal(array_map('intval', $records), SQL_PARAMS_NAMED);
        $params['libraryid'] = $this->library->id;
        $loans = $DB->get_records_sql(
            "SELECT l.id, l.readerid, l.timedue, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND i.recordid $in
           ORDER BY l.timeloaned, l.id",
            $params
        );
        $loans = array_filter($loans, fn($loan) => $this->allowed((int) $loan->readerid));
        $free = $DB->get_records_sql(
            "SELECT i.id, i.recordid
               FROM {" . self::T . "item} i
              WHERE i.recordid $in AND i.libraryid = :libraryid AND i.status = 'available'
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
           ORDER BY i.barcode, i.id",
            $params
        );
        $title = format_string(
            (string) $DB->get_field(self::T . 'record', 'title', ['id' => reset($records)]),
            true,
            ['escape' => false]
        );
        if ($readerid) {
            $theirs = array_filter($loans, fn($loan) => (int) $loan->readerid === $readerid);
            if ($theirs) {
                return $this->give_back_loan((int) reset($theirs)->id, $readerid);
            }
            if ($free) {
                return $this->lend_record((int) reset($free)->recordid, $readerid, $force);
            }
        } else {
            if (count($loans) === 1 && !$free) {
                return $this->give_back_loan((int) reset($loans)->id);
            }
            if ($loans) {
                $shelf = [];
                foreach (array_unique(array_map(fn($item) => (int) $item->recordid, $free)) as $recordid) {
                    $shelf[] = $this->shelf_book($recordid);
                }
                $out = array_values(array_map(fn($loan) => $this->out_choice($loan), $loans));
                return $this->answer('choose', 'look', $shelf ? 'deskisbnboth' : 'deskchooseout', $title, [
                    'out' => $out, 'shelf' => array_values(array_filter($shelf)), 'more' => 0, 'readers' => [],
                    'morereaders' => 0]);
            }
            if ($free) {
                $item = $DB->get_record(self::T . 'item', ['id' => reset($free)->id], '*', MUST_EXIST);
                return $this->answer('needreader', 'look', 'deskneedreader', $this->title($item), ['itemid' => (int) $item->id,
                    'room' => true, 'title' => $this->title($item)]);
            }
        }
        $here = $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::T . "item} i WHERE i.recordid $in
            AND i.libraryid = :libraryid", $params);
        return $here ? $this->answer('stop', 'no', 'desknocopy', $title)
            : $this->answer('unknown', 'no', 'deskisbnnothere', $title);
    }

    /**
     * A loan out, as the desk offers it to bring back.
     *
     * @param \stdClass $row id, readerid, timedue and title; intitle if it was found by words.
     * @return array loanid, title, who, due and intitle.
     */
    private function out_choice(\stdClass $row): array {
        $who = $this->reader((int) $row->readerid);
        return [
            'loanid' => (int) $row->id,
            'title' => format_string($row->title, true, ['escape' => false]),
            'who' => $who['name'] . ($who['group'] !== '' ? ' · ' . $who['group'] : ''),
            'due' => $this->date((int) $row->timedue),
            'intitle' => $row->intitle ?? true,
        ];
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
            $this->more = self::beyond($rows, $words, $limit);
            $rows = self::best_first($rows, $words, $limit);
            return array_values(array_map(fn($row) => [
                'recordid' => (int) $row->id,
                'title' => format_string($row->title, true, ['escape' => false]),
                'author' => $this->author((int) $row->id),
                'free' => (int) $row->free,
                'callnumber' => trim($row->line1 . ' ' . $row->line2),
                'intitle' => $row->intitle,
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
            $out[] = $this->out_choice($row);
        }
        return $out;
    }

    /**
     * A record as the search of the desk shows it, if a copy is on the shelf of this library.
     *
     * @param int $recordid
     * @return array|null recordid, title, author, free and callnumber.
     */
    public function shelf_book(int $recordid): ?array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT i.id, i.callnumber1, i.callnumber2, r.title
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE r.id = :recordid AND r.entityid = :entityid AND i.libraryid = :libraryid AND i.status = 'available'
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
           ORDER BY i.barcode",
            ['recordid' => $recordid, 'entityid' => $this->library->entityid, 'libraryid' => $this->library->id]
        );
        if (!$rows) {
            return null;
        }
        $first = reset($rows);
        return ['recordid' => $recordid, 'title' => format_string($first->title, true, ['escape' => false]),
            'author' => $this->author($recordid), 'free' => count($rows),
            'callnumber' => trim($first->callnumber1 . ' ' . $first->callnumber2)];
    }

    /**
     * The books found, those with the words in their title first: the words as they were typed, then all of them; the
     * rest (a word only in the author or a subject) only when no title has them all. Shorter titles first; each says
     * whether its title has them all (intitle).
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
        foreach ($rows as $row) {
            $row->intitle = $rank($row)[0] < 2;
        }
        if (array_filter($rows, fn($row) => $row->intitle)) {
            $rows = array_filter($rows, fn($row) => $row->intitle);
        }
        usort($rows, fn($a, $b) => $rank($a) <=> $rank($b));
        return array_slice($rows, 0, $limit);
    }

    /**
     * How many more books a list found than it shows.
     *
     * @param \stdClass[] $rows
     * @param string[] $words
     * @param int $limit
     * @return int
     */
    private static function beyond(array $rows, array $words, int $limit): int {
        $titled = array_filter($rows, fn($row) => !array_diff($words, \local_oksigeniaayvurenda\core\text::words(
            (string) $row->title
        )));
        return max(0, count($titled ?: $rows) - $limit);
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
     * A book a reader has lost: the loan ends as lost, the copy is lost (to replace it, in what the library lacks), and
     * what a late book costs at this centre is counted as if it came back today. «Undo» opens the loan again.
     *
     * @param int $loanid
     * @param int $readerid The reader open at the desk, 0 if none.
     * @return array As scan() answers.
     */
    public function lose(int $loanid, int $readerid = 0): array {
        global $DB;
        $loan = $this->loan($loanid);
        if ($loan->timereturned !== null) {
            return $this->answer('stop', 'no', 'deskalreadyback');
        }
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => $this->now,
            'returnstaffid' => $this->staffid ?: null, 'conditionin' => 'lost', 'timemodified' => $this->now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'lost', 'timemodified' => $this->now]);
        // The days the library was closed are not days the reader could have brought it back: they cost nothing.
        $days = max(0, loan_rules::days_late((int) $loan->timedue, $this->now, $this->tz)
            - closures::between((int) $this->library->entityid, (int) $loan->timedue, $this->now));
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
        $sanction = $reader ? sanctions::after_return($this->library, $reader, $loan, $days, $this->staffid, $this->now) : null;
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\book_lost::fire((int) $this->library->entityid, (int) $loan->id);
        $extra = ['loanid' => (int) $loan->id, 'readerid' => (int) $loan->readerid, 'sanction' => $sanction];
        if ($readerid) {
            $extra['reader'] = $this->reader_panel($readerid);
        }
        return $this->answer('lost', 'look', 'desklostdone', (object) ['title' => $this->title($item),
            'name' => $this->reader((int) $loan->readerid)['name']], $extra);
    }

    /**
     * A copy given up for lost is here again: on the shelf, its last loan no longer says it was lost, and whoever waits
     * for its book gets it set aside.
     *
     * @param \stdClass $item
     */
    private function found(\stdClass $item): void {
        global $DB;
        $DB->set_field_select(
            self::T . 'loan',
            'conditionin',
            null,
            "itemid = :itemid AND conditionin = 'lost'",
            ['itemid' => $item->id]
        );
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $this->now]);
        $item->status = 'available';
        holds::place($item, $this->now);
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
        $name = readers::without_accents($name);
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
            $limit + 1
        );
        $this->morereaders = count($rows) > $limit;
        return $this->shown(array_slice(array_keys($rows), 0, $limit));
    }

    /**
     * Readers whose name is close to what was typed, the closest first, for a name written by ear or with a slip: every
     * word typed is near a word of the name.
     *
     * @param string $text
     * @param int $limit
     * @return array[] id, name and group.
     */
    public function near_readers(string $text, int $limit = 8): array {
        global $DB;
        $words = \local_oksigeniaayvurenda\core\text::words($text);
        if (!$words) {
            return [];
        }
        $rows = $DB->get_recordset_sql(
            "SELECT r.id, COALESCE(r.firstname, u.firstname, '') AS firstname, COALESCE(r.lastname, u.lastname, '') AS lastname
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.status = 'active'",
            ['entityid' => $this->library->entityid]
        );
        $scores = [];
        foreach ($rows as $row) {
            $theirs = \local_oksigeniaayvurenda\core\text::words($row->firstname . ' ' . $row->lastname);
            $total = 0;
            foreach ($words as $word) {
                $distances = array_filter(array_map(
                    fn($their) => \local_oksigeniaayvurenda\core\text::near($word, $their),
                    $theirs
                ), fn($distance) => $distance !== null);
                if (!$distances) {
                    continue 2;
                }
                $total += min($distances);
            }
            $scores[(int) $row->id] = $total;
        }
        $rows->close();
        asort($scores);
        return $this->shown(array_slice(array_keys($scores), 0, $limit));
    }

    /**
     * What a search shows of each reader found, of those who may be seen here.
     *
     * @param int[] $ids
     * @return array[] id, name, group, photo and initial.
     */
    private function shown(array $ids): array {
        $found = [];
        foreach ($ids as $id) {
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
     * What a title typed wrong may have meant, by the words of the catalogue.
     *
     * @param string $text
     * @return string|null
     */
    private function near_title(string $text): ?string {
        $vocabulary = catalogue::vocabulary((int) $this->library->entityid);
        return (new \local_oksigeniaayvurenda\core\search\suggester($vocabulary))->suggest($text, 1)[0] ?? null;
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
                // More time without the book here (the reader at the desk, the family on the phone), where the rules let.
                'renewable' => $this->renewable((int) $row->id) !== null,
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
        $ofcentre = $DB->record_exists_select(
            self::T . 'reader',
            "id = ? AND entityid = ? AND status <> 'deleted'",
            [$readerid, $this->library->entityid]
        );
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
        if ($loan->timereturned !== null && !$this->free_again($loan)) {
            return $this->answer('stop', 'no', 'desktakenafter', $this->title($item), ['readerid' => (int) $reader->id]);
        }
        if ($loan->timereturned !== null) {
            // Renewing right after the copy came back: it goes out again with the same loan.
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        }
        $due = $this->capped(loan_rules::renewed_due($previous, $this->now, $rule, $this->tz, $this->closed()));
        // No more time past the date to have everything back.
        if ($due <= $previous && $loan->timereturned === null) {
            return $this->answer('stop', 'no', 'desknorenewreturnby', $this->date($due), ['readerid' => (int) $reader->id]);
        }
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
            || reserves::active((int) $item->id, $this->now) || ($loan->timereturned !== null && !$this->free_again($loan))
        ) {
            return null;
        }
        $due = $this->capped(loan_rules::renewed_due((int) $loan->timedue, $this->now, $rule, $this->tz, $this->closed()));
        return $due > (int) $loan->timedue ? $due : null;
    }

    /**
     * A date a reading library gives, no later than the centre's date to have everything back when it has set one and it
     * has not passed (a bank keeps its own round).
     *
     * @param int $due
     * @return int
     */
    private function capped(int $due): int {
        if (bank::is($this->library)) {
            return $due;
        }
        $last = closures::return_by((int) $this->library->entityid);
        return $last !== null && $this->now <= $last && $due > $last ? $last : $due;
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
        // What changed since cannot be undone: a book that came back, one another reader took after, a date that was not
        // this loan's.
        $stale = ($what === 'lent' && $loan->timereturned !== null)
            || ($what === 'returned' && ($loan->timereturned === null || !$this->free_again($loan)))
            || ($what === 'lost' && $loan->conditionin !== 'lost')
            || ($what === 'renewed' && ($loan->renewals < 1 || $previousdue <= (int) $loan->timeloaned
                || $previousdue >= (int) $loan->timedue));
        if ($stale) {
            return $this->answer('stop', 'no', $what === 'returned' && $loan->timereturned !== null ? 'desktakenafter'
                : 'deskundonotnow', $this->title($item), ['readerid' => (int) $loan->readerid]);
        }
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
        } else if ($what === 'lost' && $loan->conditionin === 'lost') {
            sanctions::undo_return((int) $loan->id);
            $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => null, 'returnstaffid' => null,
                'conditionin' => null, 'timemodified' => $this->now]);
            $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $this->now]);
        } else if ($what === 'renewed' && $previousdue > 0 && $loan->renewals > 0) {
            $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timedue' => $previousdue,
                'renewals' => $loan->renewals - 1, 'timemodified' => $this->now]);
        }
        $transaction->allow_commit();
        return $this->answer(
            'undone',
            'look',
            // Undone: now and then, that nothing happened here (jue-humor H24).
            (int) $loan->id % 2 ? 'deskundone2' : 'deskundone',
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
        // What counts against the limit (and what is late) is what is out of the libraries under the same rules: not the
        // textbooks of a bank, nor the books of a classroom with rules of its own.
        [$in, $params] = $DB->get_in_or_equal($this->same_rules(), SQL_PARAMS_NAMED, 'lib');
        $open = $DB->get_records_sql(
            'SELECT l.id, l.timedue, i.itemtype, i.recordid
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND l.timereturned IS NULL AND l.libraryid ' . $in . '
                    AND ' . bank::reading('l.libraryid'),
            ['readerid' => $readerid] + $params
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
            $sanction['reason'] ?? null,
            $reader->status !== 'active',
            // Two copies of one book to one reader is rarely meant (a bank's set, where a title comes once, is not here).
            (bool) array_filter($open, fn($loan) => (int) $loan->recordid === (int) $item->recordid)
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
        $due = bank::is($this->library) ? bankround::due($this->library, $this->now)
            : ($reserve ? loan_rules::due($this->now, (int) $reserve->days, $this->tz, (int) $reserve->days > 0, $this->closed())
            : $this->capped(loan_rules::due($this->now, (int) $rule['loandays'], $this->tz, true, $this->closed())));
        // Two desks (or a desk and a self-loan station) reading the same copy at once lend it once: the second waits for
        // the first and finds it out.
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniaayvurenda')->get_lock('lend_' . $item->id, 5);
        if (!$lock) {
            return $this->answer('stop', 'no', 'deskbusy', $this->title($item));
        }
        try {
            if ($DB->record_exists_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL', [$item->id])) {
                return $this->answer('stop', 'no', 'desktakenjust', $this->title($item));
            }
            return $this->write_loan($item, $readerid, $year, $group ?: null, $due, $reserve);
        } finally {
            $lock->release();
        }
    }

    /**
     * Writes a loan already decided, under the lock of its copy.
     *
     * @param \stdClass $item
     * @param int $readerid
     * @param \stdClass $year
     * @param string|null $group
     * @param int $due
     * @param \stdClass|null $reserve
     * @return array As scan() answers.
     */
    private function write_loan(\stdClass $item, int $readerid, \stdClass $year, ?string $group, int $due, $reserve): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $loanid = $DB->insert_record(self::T . 'loan', [
            // The loan is of the copy's library, wherever the desk is: its lists, figures and challenges see it there.
            'libraryid' => (int) ($item->libraryid ?? $this->library->id) ?: $this->library->id,
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
        // A book without a cover that moves asks for it, once, in the background.
        covers::seek((int) $item->recordid, $this->now);
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
        // The days the library was closed are not days the reader could have brought it back: they cost nothing.
        $days = max(0, loan_rules::days_late((int) $loan->timedue, $this->now, $this->tz)
            - closures::between((int) $this->library->entityid, (int) $loan->timedue, $this->now));
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
        $sanction = $reader ? sanctions::after_return($this->library, $reader, $loan, $days, $this->staffid, $this->now) : null;
        $transaction->allow_commit();
        covers::seek((int) $item->recordid, $this->now);
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
        $extra['herald'] = $sanction ? null : ($hold ? $this->herald_word('deskheraldheld') : $this->herald_back($loan));
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
     * The libraries of the centre that lend under the same rules as this one: those whose rules come from the same
     * library up their chain (or all that have none, under the default ones).
     *
     * @return int[]
     */
    private function same_rules(): array {
        global $DB;
        $libraries = $DB->get_records(self::T . 'library', ['entityid' => $this->library->entityid], '', 'id, parentid');
        $ruled = array_flip(array_map('intval', $DB->get_fieldset_sql(
            'SELECT DISTINCT r.libraryid FROM {' . self::T . 'rule} r JOIN {' . self::T . 'library} b ON b.id = r.libraryid
              WHERE b.entityid = ?',
            [$this->library->entityid]
        )));
        $source = function (int $id) use ($libraries, $ruled): int {
            $seen = [];
            while ($id && isset($libraries[$id]) && !isset($seen[$id])) {
                if (isset($ruled[$id])) {
                    return $id;
                }
                $seen[$id] = true;
                $id = (int) $libraries[$id]->parentid;
            }
            return 0;
        };
        $mine = $source((int) $this->library->id);
        $same = array_values(array_filter(array_keys($libraries), fn($id) => $source((int) $id) === $mine));
        return $same ?: [(int) $this->library->id];
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
     * Whether a copy that came back with this loan can go out with it again (undo the return, renew it): nobody took it
     * after, and it is on the shelf or set aside, as the self-loan station asks.
     *
     * @param \stdClass $loan
     * @return bool
     */
    private function free_again(\stdClass $loan): bool {
        global $DB;
        return in_array($DB->get_field(self::T . 'item', 'status', ['id' => $loan->itemid]), ['available', 'held'], true)
            && !$DB->record_exists_select(
                self::T . 'loan',
                'itemid = ? AND id <> ? AND (timereturned IS NULL OR timeloaned >= ?)',
                [$loan->itemid, $loan->id, $loan->timeloaned]
            );
    }

    /**
     * The days the centre is closed, from today on (nothing falls due on them).
     *
     * @return array 'Y-m-d' => true.
     */
    private function closed(): array {
        return $this->closeddays ??= closures::days((int) $this->library->entityid, $this->now);
    }

    /**
     * A loan of the centre (of this library, for whoever only keeps it).
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
            $conditions[] = ['value' => $condition, 'label' => self::condition_label($condition, bank::is($this->library))];
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
     * @param array $a What the line takes (the nights a book slept out).
     * @return array|null name, text, initial, portrait.
     */
    private function herald_word(string $key, array $a = []): ?array {
        $entityid = (int) $this->library->entityid;
        if (bank::is($this->library) || !skins::lively($entityid)) {
            return null;
        }
        $said = characters::say($entityid, 'herald', $key, $a);
        return $said ? ['name' => $said['name'], 'text' => $said['text'], 'initial' => $said['initial'],
            'portrait' => $said['portrait']] : null;
    }

    /**
     * The herald's word as a book comes home, for the age of whoever brings it: a bag of lines, with the nights it slept
     * out (jue-humor H17-H23).
     *
     * @param \stdClass $loan
     * @return array|null
     */
    private function herald_back(\stdClass $loan): ?array {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $loan->readerid]);
        $look = $reader ? cards::look_of($reader, $this->now) : 'none';
        $nights = max(0, intdiv($this->now - (int) $loan->timeloaned, DAYSECS));
        if ($look === 'flower') {
            $keys = ['deskheraldback1', 'deskheraldback2', 'deskheraldback3', 'deskheraldback4',
                $nights ? 'deskheraldback5' : 'deskheraldback6', 'deskheraldback7'];
        } else if ($look === 'sky') {
            $keys = $nights ? ['deskheraldbacksecondary1', 'deskheraldbacksecondary2'] : ['deskheraldbacksecondary1'];
        } else {
            $keys = $nights ? ['deskheraldbacklay1', 'deskheraldbacklay2'] : ['deskheraldbacklay2'];
        }
        $said = $this->herald_word($keys[crc32((string) $loan->id) % count($keys)], ['nights' => $nights]);
        if ($said && $look === 'flower') {
            // A child who brings back their last book: the hummingbird crosses their card once and leaves a feather by
            // their name (Gaudí, dis-efectos 17). Whether it is the last, the screen knows.
            $said['cross'] = ['figure' => (string) characters::figure('herald', 'celebra'),
                'feather' => (string) screens::art('visita/pluma-mainumby', 'svg')];
        }
        return $said;
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
