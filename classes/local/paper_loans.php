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

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\isbn;
use local_oksigeniaayvurenda\core\readers\csv_list;
use local_oksigeniaayvurenda\core\text;

/**
 * Loans written on paper (or in a document) while the desk could not be used, brought in as the desk would have done
 * them on their day: a list with who, what and when, each line read, its reader and its book found, and the loans,
 * returns and renewals made in date order. Who is found by card code, account or name, also with a slip of a letter;
 * what by copy code, ISBN, record or title, also abbreviated. What is not sure is listed with its candidates and left
 * for the list to say it better (a card code, a record number). A copy nobody wrote down is the first free one of its
 * record, marked so that the desk swaps it for the one that comes back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class paper_loans {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array<string, string[]> Headings of each column, without accents or case. */
    private const COLUMNS = [
        'reader' => ['lector', 'lectora', 'reader', 'alumno', 'alumna', 'nombre', 'name', 'usuario', 'user', 'carne', 'card',
            'persona', 'quien', 'who', 'socio', 'patron', 'borrower'],
        'group' => ['grupo', 'group', 'clase', 'class', 'curso', 'aula'],
        'copy' => ['ejemplar', 'copy', 'codigo', 'code', 'barcode', 'codigo del ejemplar'],
        'title' => ['titulo', 'title', 'libro', 'book', 'obra'],
        'isbn' => ['isbn'],
        'record' => ['ficha', 'record', 'registro'],
        'action' => ['accion', 'action', 'que', 'what', 'movimiento', 'operacion'],
        'date' => ['fecha', 'date', 'dia', 'day', 'cuando', 'when'],
    ];

    /** @var array<string, string[]> Words for each action, without accents or case. */
    private const ACTIONS = [
        'loan' => ['saca', 'sacar', 'presta', 'prestar', 'prestamo', 'se lleva', 'lleva', 'loan', 'lend', 'out', 'borrow',
            'checkout', 'check out', 'issue'],
        'return' => ['devuelve', 'devolver', 'devolucion', 'trae', 'return', 'returned', 'in', 'checkin', 'check in', 'back'],
        'renew' => ['renueva', 'renovar', 'renovacion', 'amplia', 'ampliar', 'prorroga', 'renew', 'renewal', 'extend'],
    ];

    /** @var \stdClass */
    private \stdClass $library;

    /** @var int */
    private int $entityid;

    /** @var int */
    private int $staffid;

    /** @var array|null Readers of the centre: id => [words, card, name, group]. */
    private ?array $readers = null;

    /** @var array|null Records with copies in the library: id => [words, title]. */
    private ?array $records = null;

    /**
     * The loans on paper of a library.
     *
     * @param \stdClass $library
     * @param int $staffid Who brings them in.
     */
    public function __construct(\stdClass $library, int $staffid = 0) {
        $this->library = $library;
        $this->entityid = (int) $library->entityid;
        $this->staffid = $staffid;
    }

    /**
     * The lines of a list saved as CSV: a first row saying what each column is, and one line per movement.
     *
     * @param string $data
     * @return array[] line, reader, group, copy, title, isbn, record, action (loan, return, renew or ''), date (a time or
     *     null), rawdate.
     */
    public function lines(string $data): array {
        $csv = csv_list::read($data);
        $map = [];
        foreach ($csv['columns'] as $index => $heading) {
            $key = text::normalise($heading);
            foreach (self::COLUMNS as $field => $words) {
                if (!in_array($field, $map, true) && in_array($key, array_map([text::class, 'normalise'], $words), true)) {
                    $map[$index] = $field;
                    break;
                }
            }
        }
        $lines = [];
        foreach ($csv['rows'] as $number => $row) {
            $line = ['line' => $number + 2];
            foreach (array_keys(self::COLUMNS) as $field) {
                $index = array_search($field, $map, true);
                $line[$field] = $index === false ? '' : trim((string) ($row[$index] ?? ''));
            }
            $line['rawdate'] = $line['date'];
            $line['date'] = $this->date($line['date']);
            $line['action'] = $this->action($line['action']);
            if ($line['reader'] === '' && $line['title'] === '' && $line['copy'] === '') {
                continue;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * What each line would do: its reader, its record and its copy, each sure, close (one only, with a slip of a letter)
     * or doubtful (with its candidates), and what is missing.
     *
     * @param array[] $lines As lines() gives them.
     * @return array[] Each line with reader, record and copy found: readerid, readerhow, readers (candidates), recordid,
     *     recordhow, records (candidates), itemid, code, problem.
     */
    public function plan(array $lines): array {
        $plan = [];
        foreach ($lines as $line) {
            [$readerid, $readerhow, $readers] = $this->find_reader($line['reader'], $line['group']);
            [$recordid, $recordhow, $records, $itemid] = $this->find_record($line);
            $problem = '';
            if ($line['action'] === '') {
                $problem = 'action';
            } else if ($line['date'] === null) {
                $problem = 'date';
            } else if ($readerid === null || !in_array($readerhow, ['sure', 'close'], true)) {
                $problem = 'reader';
            } else if ($recordid === null || !in_array($recordhow, ['sure', 'close'], true)) {
                $problem = 'record';
            }
            $plan[] = $line + compact('readerid', 'readerhow', 'readers', 'recordid', 'recordhow', 'records', 'itemid', 'problem');
        }
        usort($plan, fn($a, $b) => [$a['date'] ?? PHP_INT_MAX, $a['line']] <=> [$b['date'] ?? PHP_INT_MAX, $b['line']]);
        return $plan;
    }

    /**
     * Brings in the lines that are sure, in date order, as the desk would have done them on their day: a loan of the copy
     * written down or of the first free one of its record, the return of what that reader has of that record, and a
     * renewal of it (or, when the loan came before and is not here, a loan from that day). A line already done is not
     * done again.
     *
     * @param array[] $plan As plan() gives it.
     * @param int|null $due A date all the loans still out are due, instead of the rules': after a long time without the
     *     desk, to give everyone some days.
     * @return array[] Each line with done: lent, returned, renewed, lentonrenewal, already, notout, nocopy, stopped or
     *     skipped, and why when it stopped.
     */
    public function apply(array $plan, ?int $due = null): array {
        global $DB;
        $results = [];
        $touched = [];
        foreach ($plan as $line) {
            if ($line['problem'] !== '') {
                $results[] = $line + ['done' => 'skipped', 'why' => $line['problem']];
                continue;
            }
            $desk = new desk($this->library, $this->staffid, (int) $line['date']);
            $open = $this->open_loan((int) $line['readerid'], (int) $line['recordid'], (int) $line['date']);
            $done = 'stopped';
            $why = '';
            // What a line did keeps its day: a second run sees it.
            $did = fn(string $field): bool => $this->on_day(
                (int) $line['readerid'],
                (int) $line['recordid'],
                $field,
                (int) $line['date']
            );
            $renewed = fn(): bool => $this->renewed((int) $line['readerid'], (int) $line['recordid'], (int) $line['date']);
            if (
                ($line['action'] === 'loan' && $did('timeloaned')) || ($line['action'] === 'return' && $did('timereturned')) ||
                ($line['action'] === 'renew' && $renewed())
            ) {
                if ($open && $line['action'] !== 'return') {
                    $touched[] = (int) $open->id;
                }
                $results[] = $line + ['done' => 'already', 'why' => ''];
                continue;
            }
            if ($line['action'] === 'return') {
                if (!$open) {
                    $done = 'notout';
                } else {
                    $code = $DB->get_field(self::T . 'item', 'barcode', ['id' => $open->itemid]);
                    $answer = $desk->scan((string) $code);
                    $done = $answer['kind'] === 'returned' ? 'returned' : 'stopped';
                    $why = $answer['message'];
                }
            } else if ($line['action'] === 'renew' && $open) {
                $answer = $desk->renew((int) $open->id);
                $done = $answer['kind'] === 'renewed' ? 'renewed' : 'stopped';
                $why = $answer['message'];
                $touched[] = (int) $open->id;
            } else if ($open) {
                $done = 'already';
            } else {
                $items = $line['itemid'] ? [$DB->get_record(self::T . 'item', ['id' => $line['itemid']])] : $this->free_copies(
                    (int) $line['recordid']
                );
                $done = $items ? 'stopped' : 'nocopy';
                // The first that can be lent: one only for the room says why and the next is tried.
                foreach ($items as $item) {
                    $answer = $desk->scan((string) $item->barcode, (int) $line['readerid'], true);
                    $why = $answer['message'];
                    if ($answer['kind'] === 'lent') {
                        $done = $line['action'] === 'renew' ? 'lentonrenewal' : 'lent';
                        if (!$line['itemid']) {
                            $DB->set_field(self::T . 'loan', 'copyguessed', 1, ['id' => $answer['loanid']]);
                        }
                        $touched[] = (int) $answer['loanid'];
                        break;
                    }
                }
            }
            $results[] = $line + ['done' => $done, 'why' => $done === 'stopped' ? $why : ''];
        }
        if ($due !== null && $touched) {
            [$in, $params] = $DB->get_in_or_equal($touched);
            $DB->execute('UPDATE {' . self::T . "loan} SET timedue = ? WHERE timereturned IS NULL AND id $in", array_merge(
                [$due],
                $params
            ));
        }
        return $results;
    }

    /**
     * The loan out of a reader for a copy of a record in this library, lent by that day.
     *
     * @param int $readerid
     * @param int $recordid
     * @param int $time
     * @return \stdClass|null
     */
    private function open_loan(int $readerid, int $recordid, int $time): ?\stdClass {
        global $DB;
        return $DB->get_record_sql(
            'SELECT l.*
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND i.recordid = :recordid AND l.libraryid = :libraryid
                AND l.timereturned IS NULL AND l.timeloaned <= :time
           ORDER BY l.timeloaned',
            ['readerid' => $readerid, 'recordid' => $recordid, 'libraryid' => $this->library->id, 'time' => $time],
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Whether a reader has a loan of a record in this library that was lent or returned at that moment.
     *
     * @param int $readerid
     * @param int $recordid
     * @param string $field timeloaned or timereturned.
     * @param int $time
     * @return bool
     */
    private function on_day(int $readerid, int $recordid, string $field, int $time): bool {
        global $DB;
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND i.recordid = :recordid AND l.libraryid = :libraryid AND l.' . $field . ' = :time',
            ['readerid' => $readerid, 'recordid' => $recordid, 'libraryid' => $this->library->id, 'time' => $time]
        );
    }

    /**
     * Whether a renewal of that day is already here: a loan of the reader and the record out that day, lent that day or
     * renewed since it was lent.
     *
     * @param int $readerid
     * @param int $recordid
     * @param int $time
     * @return bool
     */
    private function renewed(int $readerid, int $recordid, int $time): bool {
        global $DB;
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND i.recordid = :recordid AND l.libraryid = :libraryid
                AND l.timeloaned <= :time AND (l.timereturned IS NULL OR l.timereturned > :time2)
                AND (l.timeloaned = :time3 OR l.renewals > 0)',
            ['readerid' => $readerid, 'recordid' => $recordid, 'libraryid' => $this->library->id, 'time' => $time,
                'time2' => $time, 'time3' => $time]
        );
    }

    /**
     * The copies of a record on the shelf of this library, by their code.
     *
     * @param int $recordid
     * @return \stdClass[]
     */
    private function free_copies(int $recordid): array {
        global $DB;
        return array_values($DB->get_records_sql(
            'SELECT i.*
               FROM {' . self::T . 'item} i
              WHERE i.recordid = :recordid AND i.libraryid = :libraryid AND i.status = :available
                AND NOT EXISTS (SELECT 1 FROM {' . self::T . 'loan} l WHERE l.itemid = i.id AND l.timereturned IS NULL)
           ORDER BY i.barcode',
            ['recordid' => $recordid, 'libraryid' => $this->library->id, 'available' => 'available']
        ));
    }

    /**
     * The reader a line means: by card code, by account, or by name, with its words in any order and some missing
     * (a second surname); a slip of a letter makes it close, never sure; several, or only some words, a doubt.
     *
     * @param string $who
     * @param string $group The group the list says, to choose between namesakes.
     * @return array [reader id or null, sure|close|doubt|none, candidates: id, card, name, group]
     */
    private function find_reader(string $who, string $group): array {
        global $DB;
        $who = trim($who);
        if ($who === '') {
            return [null, 'none', []];
        }
        $card = desk::by_code('reader', 'cardcode', $this->entityid, $who, " AND status <> 'deleted'");
        if ($card) {
            return [(int) $card->id, 'sure', []];
        }
        $user = $DB->get_record('user', ['username' => \core_text::strtolower($who), 'deleted' => 0], 'id');
        if ($user) {
            $reader = $DB->get_record_select(
                self::T . 'reader',
                "entityid = :entityid AND userid = :userid AND status <> 'deleted'",
                ['entityid' => $this->entityid, 'userid' => $user->id],
                'id',
                IGNORE_MULTIPLE
            );
            if ($reader) {
                return [(int) $reader->id, 'sure', []];
            }
        }
        $words = text::words($who);
        $found = ['exact' => [], 'close' => [], 'partial' => []];
        foreach ($this->readers() as $id => $reader) {
            $how = self::words_match($words, $reader['words']);
            if ($how !== null) {
                $found[$how][] = $id;
            }
        }
        $group = text::normalise($group);
        foreach ($found as $how => $ids) {
            if ($group !== '' && count($ids) > 1) {
                $same = array_values(array_filter($ids, fn($id) => text::normalise($this->readers[$id]['group']) === $group));
                $found[$how] = $same ?: $ids;
            }
        }
        if (count($found['exact']) === 1) {
            return [$found['exact'][0], 'sure', []];
        }
        if (!$found['exact'] && count($found['close']) === 1) {
            return [$found['close'][0], 'close', $this->candidates($found['close'])];
        }
        $all = array_merge($found['exact'], $found['close'], $found['partial']);
        return [null, $all ? 'doubt' : 'none', $this->candidates(array_slice($all, 0, 5))];
    }

    /**
     * How the words of a name match those of a reader: all of them as they are (exact), all with a slip of a letter
     * at most (close), or at least two and half of them (partial).
     *
     * @param string[] $words
     * @param string[] $theirs
     * @return string|null exact, close, partial or null.
     */
    private static function words_match(array $words, array $theirs): ?string {
        if (count($words) < 2 || !$theirs) {
            return null;
        }
        $exact = 0;
        $close = 0;
        foreach ($words as $word) {
            if (in_array($word, $theirs, true)) {
                $exact++;
                continue;
            }
            foreach ($theirs as $their) {
                if (strlen($word) >= 4 && levenshtein($word, $their) <= (strlen($word) >= 8 ? 2 : 1)) {
                    $close++;
                    break;
                }
            }
        }
        if ($exact === count($words)) {
            return 'exact';
        }
        if ($exact + $close === count($words)) {
            return 'close';
        }
        return $exact + $close >= 2 && ($exact + $close) * 2 >= count($words) ? 'partial' : null;
    }

    /**
     * The readers of the centre, once.
     *
     * @return array id => words, card, name, group.
     */
    private function readers(): array {
        global $DB;
        if ($this->readers === null) {
            $year = years::around($this->entityid, time());
            $this->readers = [];
            $rows = $DB->get_recordset_sql(
                'SELECT r.id, r.firstname, r.lastname, r.cardcode, m.groupname
                   FROM {' . self::T . 'reader} r
              LEFT JOIN {' . self::T . 'member} m ON m.readerid = r.id AND m.yearid = :yearid
                  WHERE r.entityid = :entityid AND r.status = :active',
                ['yearid' => $year->id, 'entityid' => $this->entityid, 'active' => 'active']
            );
            foreach ($rows as $row) {
                $name = trim($row->firstname . ' ' . $row->lastname);
                $this->readers[(int) $row->id] = ['words' => text::words($name), 'card' => (string) $row->cardcode,
                    'name' => $name, 'group' => (string) $row->groupname];
            }
            $rows->close();
        }
        return $this->readers;
    }

    /**
     * Readers to choose from, as the console shows them.
     *
     * @param int[] $ids
     * @return array[] id, card, name, group.
     */
    private function candidates(array $ids): array {
        return array_map(fn($id) => ['id' => $id] + array_diff_key($this->readers[$id], ['words' => 1]), $ids);
    }

    /**
     * The record a line means, with copies in this library: by the code of a copy, its number, its ISBN, or its title,
     * with words abbreviated or with a slip of a letter, and without what goes in brackets when that finds nothing; a
     * number (a volume) has to be there.
     *
     * @param array $line
     * @return array [record id or null, sure|close|doubt|none, candidates: id, title; the copy written down or null]
     */
    private function find_record(array $line): array {
        global $DB;
        if ($line['copy'] !== '') {
            $item = desk::by_code('item', 'barcode', $this->entityid, $line['copy']);
            return $item && (int) $item->libraryid === (int) $this->library->id
                ? [(int) $item->recordid, 'sure', [], (int) $item->id] : [null, 'none', [], null];
        }
        $records = $this->records();
        if (ctype_digit($line['record'])) {
            return isset($records[(int) $line['record']]) ? [(int) $line['record'], 'sure', [], null] : [null, 'none', [], null];
        }
        $isbn = isbn::normalise($line['isbn']);
        if ($isbn !== null) {
            $ids = array_values(array_filter(
                $DB->get_fieldset_select(self::T . 'record', 'id', 'entityid = ? AND isbn = ?', [$this->entityid, $isbn]),
                fn($id) => isset($records[(int) $id])
            ));
            if (count($ids) === 1) {
                return [(int) $ids[0], 'sure', [], null];
            }
            if ($ids) {
                return [null, 'doubt', $this->titles($ids), null];
            }
        }
        $title = trim($line['title']);
        $ids = $this->by_title(text::words($title));
        if (!$ids && str_contains($title, '(')) {
            $ids = $this->by_title(text::words(preg_replace('/\([^)]*\)/u', ' ', $title)));
        }
        if (!$ids) {
            return [null, 'none', [], null];
        }
        $exact = array_values(array_filter($ids, fn($id) => text::normalise($records[$id]['title']) === text::normalise($title)));
        if (count($exact) === 1) {
            return [$exact[0], 'sure', [], null];
        }
        if (count($ids) === 1) {
            return [$ids[0], 'close', $this->titles($ids), null];
        }
        return [null, 'doubt', $this->titles(array_slice($ids, 0, 5)), null];
    }

    /**
     * Records with copies here whose title has every word given, shortest title first.
     *
     * @param string[] $words
     * @return int[]
     */
    private function by_title(array $words): array {
        if (!$words) {
            return [];
        }
        $found = [];
        foreach ($this->records() as $id => $record) {
            foreach ($words as $word) {
                $matched = false;
                foreach ($record['words'] as $their) {
                    if (
                        $their === $word || (!ctype_digit($word) && (
                            (strlen($word) >= 3 && str_starts_with($their, $word)) ||
                            (strlen($word) >= 5 && levenshtein($word, $their) <= 1)
                        ))
                    ) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue 2;
                }
            }
            $found[$id] = count($record['words']);
        }
        asort($found);
        return array_keys($found);
    }

    /**
     * The records with copies in this library, once: the words of their title, subtitle and number in a series.
     *
     * @return array id => words, title.
     */
    private function records(): array {
        global $DB;
        if ($this->records === null) {
            $this->records = [];
            $rows = $DB->get_recordset_sql(
                'SELECT r.id, r.title, r.subtitle, r.extra
                   FROM {' . self::T . 'record} r
                  WHERE r.entityid = :entityid
                    AND EXISTS (SELECT 1 FROM {' . self::T . 'item} i WHERE i.recordid = r.id AND i.libraryid = :libraryid)',
                ['entityid' => $this->entityid, 'libraryid' => $this->library->id]
            );
            foreach ($rows as $row) {
                $extra = json_decode((string) $row->extra, true) ?: [];
                $this->records[(int) $row->id] = ['title' => (string) $row->title, 'words' => text::words(
                    $row->title . ' ' . $row->subtitle . ' ' . ($extra['seriesnumber'] ?? '')
                )];
            }
            $rows->close();
        }
        return $this->records;
    }

    /**
     * Records to choose from, as the console shows them.
     *
     * @param int[] $ids
     * @return array[] id, title.
     */
    private function titles(array $ids): array {
        global $DB;
        return array_map(fn($id) => ['id' => (int) $id, 'title' => (string) $DB->get_field(
            self::T . 'record',
            'title',
            ['id' => $id]
        )], $ids);
    }

    /**
     * A date as people write it (21/10/2026, 21-10-26, 2026-10-21), at midday in the centre's time zone.
     *
     * @param string $text
     * @return int|null
     */
    private function date(string $text): ?int {
        $text = trim($text);
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $text, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})/', $text, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $year += $year < 100 ? 2000 : 0;
        } else {
            return null;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return (new \DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00', $year, $month, $day), zones::of($this->library)))
            ->getTimestamp();
    }

    /**
     * The end of a day written as people write it, in the centre's time zone: when something is due.
     *
     * @param string $text
     * @return int|null
     */
    public function day_end(string $text): ?int {
        $noon = $this->date($text);
        return $noon === null ? null : (new \DateTimeImmutable('@' . $noon))->setTimezone(zones::of($this->library))
            ->setTime(23, 59, 59)->getTimestamp();
    }

    /**
     * What a line does, from its word: loan, return or renew; a line with no word is a loan.
     *
     * @param string $text
     * @return string loan, return, renew or '' when the word is not known.
     */
    private function action(string $text): string {
        $text = text::normalise($text);
        if ($text === '') {
            return 'loan';
        }
        foreach (self::ACTIONS as $action => $words) {
            if (in_array($text, array_map([text::class, 'normalise'], $words), true)) {
                return $action;
            }
        }
        return '';
    }
}
