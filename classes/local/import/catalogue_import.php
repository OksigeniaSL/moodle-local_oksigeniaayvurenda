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

    /** @var array<string, int> What was done. */
    private array $stats = ['records' => 0, 'found' => 0, 'items' => 0, 'kept' => 0, 'newcodes' => 0, 'untitled' => 0];

    /** @var array[] Copies whose code was another record's: [the code printed, the new one]. */
    private array $relabelled = [];

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
     * Writes the records and their copies.
     *
     * @param iterable $records Each with key, description (as the describers give it) and items.
     * @return array records (new), found (already there), items (new), kept (codes already there), newcodes, untitled (records
     *     with no title anywhere, left out and counted).
     */
    public function run(iterable $records): array {
        global $DB;
        $this->load();
        $entityid = (int) $this->library->entityid;
        $now = time();
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $letter = !empty($settings['controlletter']);
        $batch = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($records as $record) {
            $key = (string) ($record['key'] ?? '');
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
            if ($recordid) {
                $this->stats['found']++;
            } else {
                if (trim((string) ($description['record']['title'] ?? '')) === '') {
                    $this->stats['untitled']++;
                    continue;
                }
                $recordid = cataloguer::create_record($entityid, $description, $now);
                $this->stats['records']++;
                if ($isbn !== '') {
                    $this->isbns[$isbn] = $recordid;
                }
            }
            if ($key !== '' && !isset($this->sources[$key])) {
                $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'origin' => $this->system,
                    'tablename' => 'record', 'externalid' => \core_text::substr($key, 0, 255), 'localid' => $recordid,
                    'timecreated' => $now]);
                $this->sources[$key] = $recordid;
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
            if (++$batch % 500 === 0) {
                $transaction->allow_commit();
                $transaction = $DB->start_delegated_transaction();
            }
        }
        $transaction->allow_commit();
        catalogue::purge($entityid);
        stacks::refresh_keys($entityid);
        $told = ['how' => $this->system] + array_filter($this->stats, 'is_int');
        \local_oksigeniaayvurenda\event\catalogue_imported::fire($entityid, null, $told);
        return $this->stats;
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
            if ($this->codes[$code] === $recordid) {
                $this->stats['kept']++;
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
        if ($printed !== '') {
            $this->relabelled[] = [$printed, $code];
        }
        $lines = array_map(
            fn($l) => trim((string) $l) !== '' ? \core_text::substr(trim((string) $l), 0, 100) : null,
            array_pad($item['callnumber'] ?? [], 3, '')
        );
        $acquired = !empty($item['acquired']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $item['acquired'], $d)
            ? make_timestamp((int) $d[1], (int) $d[2], (int) $d[3]) : $now;
        $DB->insert_record(self::T . 'item', [
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
     * What the entity already has: codes, register numbers, places, ISBNs and what came from this system before.
     */
    private function load(): void {
        global $DB;
        $entityid = (int) $this->library->entityid;
        $rows = $DB->get_recordset_select(
            self::T . 'item',
            'entityid = :entityid',
            ['entityid' => $entityid],
            '',
            'id, barcode, regnumber, recordid'
        );
        foreach ($rows as $row) {
            $code = strtoupper((string) $row->barcode);
            $this->codes[$code] = (int) $row->recordid;
            if (preg_match('/^(\d+)[A-Z]?$/', $code, $parts)) {
                $this->lastcode = max($this->lastcode, (int) $parts[1]);
                $this->digits = max($this->digits, strlen($parts[1]));
            }
            if (ctype_digit((string) $row->regnumber)) {
                $this->regnumber = max($this->regnumber, (int) $row->regnumber);
            }
        }
        $rows->close();
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
        $rows = $DB->get_recordset_select(
            self::T . 'record',
            "entityid = :entityid AND isbn IS NOT NULL",
            ['entityid' => $entityid],
            '',
            'id, isbn'
        );
        foreach ($rows as $row) {
            $this->isbns[$row->isbn] = $this->isbns[$row->isbn] ?? (int) $row->id;
        }
        $rows->close();
        $this->sources = array_map('intval', $DB->get_records_menu(self::T . 'source', ['entityid' => $entityid,
            'origin' => $this->system, 'tablename' => 'record'], '', 'externalid, localid'));
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
     * Whether a record of the catalogue is the book a description of the file says: the same ISBN when both have one,
     * else most of the words of their titles.
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
            return $isbn === (string) $record->isbn;
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
