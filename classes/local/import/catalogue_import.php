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
 * A catalogue from MARC or CSV, into a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\stacks;

/**
 * Records with their copies, from any library program's MARC file or from a spreadsheet, written into a library in
 * bulk. Each record is found again by where it came from, or by its ISBN (so a file on top of the Abies import does
 * not repeat the books); each copy by its code. A copy without a code gets a new one after the library's. The same
 * file can be loaded again: nothing is repeated.
 */
final class catalogue_import {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array<string, bool> Codes of the entity's copies. */
    private array $codes = [];

    /** @var int The highest number of a code, to follow. */
    private int $lastcode = 0;

    /** @var int Digits of the codes. */
    private int $digits = 6;

    /** @var int The highest register number. */
    private int $regnumber = 0;

    /** @var array<string, int> Places by their name. */
    private array $places = [];

    /** @var array<string, int> Records of the entity by ISBN. */
    private array $isbns = [];

    /** @var array<string, int> Records already brought from this system, by their key. */
    private array $sources = [];

    /** @var array[] By key: [source id, fingerprint of what the program sent, fingerprint of the record here then]. */
    private array $tracked = [];

    /** @var array<string, int> What was done. */
    private array $stats = ['records' => 0, 'found' => 0, 'items' => 0, 'kept' => 0, 'newcodes' => 0, 'untitled' => 0,
        'updated' => 0, 'moved' => 0];

    /** @var array[] Copies whose code was another record's: [the code printed, the new one]. */
    private array $relabelled = [];

    /** @var array<int, bool> Records brought before from this same system: a copy of one of them may move to another. */
    private array $ours = [];

    /** @var array<string, int> Codes this file has named so far, and for which record. */
    private array $infile = [];

    /** @var array<int, bool> Records made by this import: a copy of one of those does not change its date again. */
    private array $fresh = [];

    /** @var int[] Copies this batch brought, to note where they came from when it is written. */
    private array $made = [];

    /** @var int[] The last copy, record and source read: what others write meanwhile is read on top, batch by batch. */
    private array $last = ['item' => 0, 'record' => 0, 'source' => 0];

    /** @var callable|null Said after each batch, to say the import is alive (the background mark). */
    private $beat = null;

    /** @var int Records in a batch, at most: each batch is written, and the centre's codes let go a moment, on its own. */
    private const BATCH = 500;

    /** @var int Seconds of a batch, at most: whoever adds a book by hand meanwhile waits no longer than this. */
    private const BATCHTIME = 5;

    /**
     * An import into a library.
     *
     * @param \stdClass $library
     * @param string $system marc or csv: where the keys of the records come from.
     */
    public function __construct(
        /** @var \stdClass The library. */
        private \stdClass $library,
        /** @var string Where the records come from. */
        private string $system
    ) {
    }

    /**
     * Something to say after each batch that the import is still going (a background import renews its mark with it).
     *
     * @param callable $beat
     */
    public function on_batch(callable $beat): void {
        $this->beat = $beat;
    }

    /**
     * Writes the records and their copies.
     *
     * @param iterable $records Each with key, description (as the describers give it) and items.
     * @return array records (new), found (already there), items (new), kept (codes already there), newcodes, untitled (records
     *     with no title anywhere, left out and counted).
     * @throws \local_oksigeniaayvurenda\local\busy When the centre's codes could not be had in two minutes.
     */
    public function run(iterable $records): array {
        global $DB;
        $entityid = (int) $this->library->entityid;
        // The centre's new codes are held while a batch is written, as when a book is added by hand, and let go between
        // batches: someone adding books meanwhile, or another library of the centre importing, never takes the same code.
        // Inside a transaction already (a plan that refuses a whole import that does not fit), nothing is written until the
        // end, so they are held to the end.
        $whole = $DB->is_transaction_started();
        $lock = $this->hold();
        try {
            $this->load();
            cataloguer::know_authorities($entityid);
            $this->write($records, $whole, $lock);
        } finally {
            if ($lock) {
                $lock->release();
            }
            cataloguer::know_authorities(0);
        }
        catalogue::purge($entityid);
        stacks::refresh_keys($entityid);
        $told = ['how' => $this->system] + array_filter($this->stats, 'is_int');
        \local_oksigeniaayvurenda\event\catalogue_imported::fire($entityid, null, $told);
        return $this->stats;
    }

    /**
     * The centre's codes, waited for two minutes at most.
     *
     * @return \core\lock\lock
     * @throws \local_oksigeniaayvurenda\local\busy
     */
    private function hold(): \core\lock\lock {
        $lock = cataloguer::lock('copies', (int) $this->library->entityid, 120);
        if (!$lock) {
            throw new \local_oksigeniaayvurenda\local\busy('copiesbusy', 'local_oksigeniaayvurenda');
        }
        return $lock;
    }

    /**
     * Writes the records, batch by batch.
     *
     * @param iterable $records
     * @param bool $whole Whether it all goes in one transaction (the codes held to the end).
     * @param \core\lock\lock|null $lock The centre's codes, held; let go and had again between batches.
     */
    private function write(iterable $records, bool $whole, ?\core\lock\lock &$lock): void {
        global $DB;
        $entityid = (int) $this->library->entityid;
        $now = time();
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $letter = !empty($settings['controlletter']);
        $batch = 0;
        $since = time();
        $transaction = $DB->start_delegated_transaction();
        $oldused = [];
        foreach ($records as $record) {
            $key = (string) ($record['key'] ?? '');
            // A key that changed (a spreadsheet's book now with its year): the one of before still finds its record, once:
            // a second edition of it is another book.
            $old = (string) ($record['oldkey'] ?? '');
            if (
                $key !== '' && !isset($this->sources[$key]) && $old !== '' && isset($this->sources[$old])
                && !isset($oldused[$old])
            ) {
                $key = $old;
                $oldused[$old] = true;
            }
            $description = $record['description'];
            $isbn = (string) ($description['record']['isbn'] ?? '');
            // A record with nothing that names it (no 001, no 035, no ISBN: made by hand, or an old book) is known by what it
            // says, so importing it again does not make it twice.
            if ($key === '' && $isbn === '' && trim((string) ($description['record']['title'] ?? '')) !== '') {
                $key = 'fp:' . sha1(implode('|', array_map(fn($v) => \local_oksigeniaayvurenda\core\text::normalise((string) $v), [
                    $description['record']['title'] ?? '',
                    $description['authorities']['author'][0] ?? '',
                    $description['record']['pubyear'] ?? '',
                    $description['authorities']['publisher'][0] ?? '',
                ])));
            }
            // Two programs may number their records alike (001 = 1 in both): a record known by its key that is plainly
            // another book is not it, and comes in as its own, known by its key and its title.
            if ($key !== '' && isset($this->sources[$key]) && !$this->same_book((int) $this->sources[$key], $description)) {
                $key .= '|' . substr(sha1(\local_oksigeniaayvurenda\core\text::normalise((string) ($description['record']['title']
                    ?? ''))), 0, 12);
            }
            $recordid = ($key !== '' ? ($this->sources[$key] ?? 0) : 0) ?: ($isbn !== '' ? ($this->isbns[$isbn] ?? 0) : 0);
            // Copies with no code cannot be recognised by it: when this file's record came before, those it has count for them.
            $room = 0;
            if ($recordid && $key !== '' && isset($this->sources[$key])) {
                $room = $DB->count_records(self::T . 'item', ['recordid' => $recordid, 'libraryid' => $this->library->id]);
                foreach ($record['items'] ?? [] as $item) {
                    $code = strtoupper(trim((string) ($item['barcode'] ?? '')));
                    $room -= $code !== '' && ($this->codes[$code] ?? 0) === $recordid ? 1 : 0;
                }
            }
            // What the program says of the book now: changed there and not touched here since, the record takes it, as with
            // Abies; changed here too, what was done here stays.
            $hash = sha1((string) json_encode([$description['record'] ?? [], $description['authorities'] ?? []]));
            if ($recordid && $key !== '' && isset($this->tracked[$key])) {
                [$sourceid, $was, $own] = $this->tracked[$key];
                if ($was !== $hash) {
                    $mine = cataloguer::own_hash($recordid);
                    $renewed = false;
                    if ($was !== null && $own !== null && $mine === $own) {
                        cataloguer::renew_record($recordid, $entityid, $description, $now);
                        $this->stats['updated']++;
                        $renewed = true;
                        $mine = cataloguer::own_hash($recordid);
                    }
                    // Renewed, or first seen with a fingerprint (brought before there were any): from now on it is followed.
                    if ($renewed || $was === null) {
                        $DB->update_record(self::T . 'source', (object) ['id' => $sourceid, 'hash' => $hash, 'localhash' => $mine]);
                        $this->tracked[$key] = [$sourceid, $hash, $mine];
                    }
                }
            }
            if ($recordid) {
                $this->stats['found']++;
            } else {
                if (trim((string) ($description['record']['title'] ?? '')) === '') {
                    $this->stats['untitled']++;
                    continue;
                }
                $recordid = cataloguer::create_record($entityid, $description, $now);
                $this->fresh[$recordid] = true;
                $this->stats['records']++;
                if ($isbn !== '') {
                    $this->isbns[$isbn] = $recordid;
                }
            }
            if ($key !== '' && !isset($this->sources[$key])) {
                $own = cataloguer::own_hash($recordid);
                $sourceid = (int) $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'origin' => $this->system,
                    'tablename' => 'record', 'externalid' => \core_text::substr($key, 0, 255), 'localid' => $recordid,
                    'hash' => $hash, 'localhash' => $own, 'timecreated' => $now]);
                $this->sources[$key] = $recordid;
                $this->tracked[$key] = [$sourceid, $hash, $own];
                $this->ours[$recordid] = true;
            }
            foreach ($record['items'] ?? [] as $item) {
                // A copy with no code, or with another record's (relabelled the time before), is one this record already has.
                $code = strtoupper(trim((string) ($item['barcode'] ?? '')));
                $theirs = $code !== '' && isset($this->codes[$code]) && $this->codes[$code] !== $recordid;
                if (($code === '' || $theirs) && $room > 0) {
                    $room--;
                    $this->stats['kept']++;
                    continue;
                }
                $this->item($recordid, $item, $letter, $now);
            }
            if (++$batch % self::BATCH === 0 || time() - $since >= self::BATCHTIME) {
                $this->note_made($now);
                $transaction->allow_commit();
                if (!$whole) {
                    $lock->release();
                    $lock = null;
                    if ($this->beat) {
                        ($this->beat)();
                    }
                    $lock = $this->hold();
                    $this->load();
                    cataloguer::know_authorities($entityid);
                }
                $transaction = $DB->start_delegated_transaction();
                $since = time();
            }
        }
        $this->note_made($now);
        $transaction->allow_commit();
    }

    /**
     * Notes where the copies of the batch came from: a copy with no origin is one added by hand, and an import of a
     * university's catalogue counted as hundreds of thousands catalogued by hand today, and as news.
     *
     * @param int $now
     */
    private function note_made(int $now): void {
        global $DB;
        if (!$this->made) {
            return;
        }
        $DB->insert_records(self::T . 'source', array_map(fn($id) => ['entityid' => $this->library->entityid,
            'origin' => $this->system, 'tablename' => 'item', 'externalid' => 'item-' . $id, 'localid' => $id,
            'timecreated' => $now], $this->made));
        $this->made = [];
    }

    /**
     * Writes a copy, unless its code is already there.
     *
     * @param int $recordid
     * @param array $item
     * @param bool $letter Whether new codes end with a control letter.
     * @param int $now
     */
    private function item(int $recordid, array $item, bool $letter, int $now): void {
        global $DB;
        $code = strtoupper(trim((string) ($item['barcode'] ?? '')));
        // A code this record's copy already has is that copy; one of another record (labels printed twice, a slip of the
        // gun) gets a new code and is listed to be relabelled, not lost.
        $printed = '';
        if ($code !== '' && isset($this->codes[$code])) {
            $owner = $this->codes[$code];
            if ($owner === $recordid) {
                $this->infile[$code] = $recordid;
                $this->stats['kept']++;
                return;
            }
            // The program joined two records, or moved the copy to another: a copy of a record it brought before, that
            // this file has not named for another record, moves here with its label, instead of being there and here.
            if (!isset($this->infile[$code]) && isset($this->ours[$owner])) {
                $DB->set_field(
                    self::T . 'item',
                    'recordid',
                    $recordid,
                    ['entityid' => $this->library->entityid, 'barcode' => $code]
                );
                $this->codes[$code] = $recordid;
                $this->infile[$code] = $recordid;
                $this->stats['moved']++;
                return;
            }
            $printed = $code;
            $code = '';
        }
        if ($code === '') {
            $number = str_pad((string) ++$this->lastcode, $this->digits, '0', STR_PAD_LEFT);
            $code = $letter ? control_letter::append($number) : $number;
            $this->stats['newcodes']++;
        } else if (preg_match('/^(\d+)[A-Z]?$/', $code, $parts)) {
            $this->lastcode = max($this->lastcode, (int) $parts[1]);
        }
        $this->codes[$code] = $recordid;
        $this->infile[$code] = $recordid;
        if ($printed !== '') {
            $this->relabelled[] = [$printed, $code];
        }
        $lines = array_map(
            fn($l) => trim((string) $l) !== '' ? \core_text::substr(trim((string) $l), 0, 100) : null,
            array_pad($item['callnumber'] ?? [], 3, '')
        );
        // When the file does not say when the copy came, it is not known (as with Abies): not «today», which would make a
        // whole catalogue this year's acquisitions.
        $acquired = !empty($item['acquired']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $item['acquired'], $d)
            ? make_timestamp((int) $d[1], (int) $d[2], (int) $d[3]) : null;
        $this->made[] = (int) $DB->insert_record(self::T . 'item', [
            'entityid' => $this->library->entityid,
            'libraryid' => $this->library->id,
            'recordid' => $recordid,
            'barcode' => \core_text::substr($code, 0, 64),
            'regnumber' => trim((string) ($item['regnumber'] ?? '')) !== ''
                ? \core_text::substr(trim((string) $item['regnumber']), 0, 64) : (string) ++$this->regnumber,
            'callnumber1' => $lines[0],
            'callnumber2' => $lines[1],
            'callnumber3' => $lines[2],
            'locationid' => $this->place((string) ($item['location'] ?? ''), $now),
            'itemtype' => trim((string) ($item['itemtype'] ?? '')) !== '' ? \core_text::substr(trim($item['itemtype']), 0, 30)
                : 'normal',
            'status' => 'available',
            'notes' => trim((string) ($item['notes'] ?? '')) !== '' ? $item['notes'] : null,
            'timeacquired' => $acquired,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        // A record already here with a copy more changes: harvesters asking since a date see it.
        if (!isset($this->fresh[$recordid])) {
            $DB->set_field(self::T . 'record', 'timemodified', $now, ['id' => $recordid]);
        }
        $this->stats['items']++;
    }

    /**
     * A place of the entity, found by its name or made.
     *
     * @param string $label
     * @param int $now
     * @return int|null
     */
    private function place(string $label, int $now): ?int {
        global $DB;
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        $key = \core_text::strtolower($label);
        if (!isset($this->places[$key])) {
            $this->places[$key] = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $this->library->entityid,
                'kind' => 'location', 'label' => \core_text::substr($label, 0, 1333), 'timecreated' => $now,
                'timemodified' => $now]);
        }
        return $this->places[$key];
    }

    /**
     * What the entity already has: codes, register numbers, places, ISBNs and what came from this system before. Called
     * again, what was written since, by this import or by anyone else.
     */
    private function load(): void {
        global $DB;
        $entityid = (int) $this->library->entityid;
        $first = !$this->last['item'] && !$this->last['record'] && !$this->last['source'];
        $rows = $DB->get_recordset_select(
            self::T . 'item',
            'entityid = :entityid AND id > :last',
            ['entityid' => $entityid, 'last' => $this->last['item']],
            'id',
            'id, barcode, regnumber, recordid'
        );
        foreach ($rows as $row) {
            $this->last['item'] = (int) $row->id;
            $code = strtoupper((string) $row->barcode);
            $this->codes[$code] ??= (int) $row->recordid;
            if (preg_match('/^(\d+)[A-Z]?$/', $code, $parts)) {
                $this->lastcode = max($this->lastcode, (int) $parts[1]);
                $this->digits = max($this->digits, strlen($parts[1]));
            }
            if (ctype_digit((string) $row->regnumber)) {
                $this->regnumber = max($this->regnumber, (int) $row->regnumber);
            }
        }
        $rows->close();
        $rows = $DB->get_recordset_select(
            self::T . 'record',
            "entityid = :entityid AND id > :last",
            ['entityid' => $entityid, 'last' => $this->last['record']],
            'id',
            'id, isbn'
        );
        foreach ($rows as $row) {
            $this->last['record'] = (int) $row->id;
            if ((string) $row->isbn !== '') {
                $this->isbns[$row->isbn] = $this->isbns[$row->isbn] ?? (int) $row->id;
            }
        }
        $rows->close();
        $rows = $DB->get_recordset_select(
            self::T . 'source',
            "entityid = :entityid AND origin = :origin AND tablename = 'record' AND id > :last",
            ['entityid' => $entityid, 'origin' => $this->system, 'last' => $this->last['source']],
            'id',
            'id, externalid, localid, hash, localhash'
        );
        foreach ($rows as $source) {
            $this->last['source'] = (int) $source->id;
            $this->sources[$source->externalid] ??= (int) $source->localid;
            $this->ours[(int) $source->localid] = true;
            $this->tracked[$source->externalid] ??= [(int) $source->id, $source->hash, $source->localhash];
        }
        $rows->close();
        if (!$first) {
            return;
        }
        // Where the centre said its new codes start.
        foreach (\local_oksigeniaayvurenda\local\cataloguer::below_first_code($entityid) as $below) {
            $this->lastcode = max($this->lastcode, (int) $below);
            $this->digits = max($this->digits, strlen($below));
        }
        foreach (
            $DB->get_records_menu(
                self::T . 'authority',
                ['entityid' => $entityid, 'kind' => 'location'],
                '',
                'id, label'
            ) as $id => $label
        ) {
            $this->places[\core_text::strtolower($label)] = (int) $id;
        }
    }

    /**
     * The copies that got a new code because theirs was another record's, to be relabelled.
     *
     * @return array[] [printed code, new code].
     */
    public function relabelled(): array {
        return $this->relabelled;
    }

    /**
     * Whether a record of the catalogue is the book a description of the file says: the same ISBN when both have one (or
     * one of the file's, when it lists the hardback and the paperback in another order), or, with the ISBN corrected in
     * its program, the same title; else the same first author, or most of the words of their titles (a title corrected in
     * its program is still it).
     *
     * @param int $recordid
     * @param array $description
     * @return bool
     */
    private function same_book(int $recordid, array $description): bool {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $recordid], 'id, title, isbn');
        if (!$record) {
            return false;
        }
        $isbn = (string) ($description['record']['isbn'] ?? '');
        if ($isbn !== '' && (string) $record->isbn !== '') {
            $isbns = array_map('strval', (array) ($description['extra']['isbns'] ?? []));
            return $isbn === (string) $record->isbn || in_array((string) $record->isbn, $isbns, true)
                || \local_oksigeniaayvurenda\core\text::normalise((string) ($description['record']['title'] ?? ''))
                    === \local_oksigeniaayvurenda\core\text::normalise((string) $record->title);
        }
        $author = \local_oksigeniaayvurenda\core\text::normalise((string) ($description['authorities']['author'][0] ?? ''));
        if ($author !== '') {
            $first = $DB->get_records_sql(
                "SELECT a.id, a.label
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'author'
                  WHERE ra.recordid = :recordid
               ORDER BY ra.sortorder, ra.id",
                ['recordid' => $recordid],
                0,
                1
            );
            if ($first && \local_oksigeniaayvurenda\core\text::normalise((string) reset($first)->label) === $author) {
                return true;
            }
        }
        $a = \local_oksigeniaayvurenda\core\text::words((string) ($description['record']['title'] ?? ''));
        $b = \local_oksigeniaayvurenda\core\text::words((string) $record->title);
        if (!$a || !$b) {
            return true;
        }
        $shared = count(array_intersect($a, $b));
        return $shared === min(count($a), count($b)) || $shared / count(array_unique(array_merge($a, $b))) >= 0.5;
    }
}
