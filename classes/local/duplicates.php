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
 * Records of the same book: found and joined into one.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * A book catalogued twice, or brought twice by an old program, has two records: its copies, loans and reviews are split.
 * The records that share an ISBN, and those with the same title (without its article) and author when one of them has no
 * ISBN, are offered to be joined (a record without an author goes with the first author's); the person chooses which
 * stays, and the other's copies, loans, holds, reviews, readings and the rest go to it, with whatever the one that stays
 * lacked (a summary, a cover).
 */
final class duplicates {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The tables whose rows belong to a record, moved as they are. */
    private const MOVED = ['item', 'hold', 'digital', 'voiceinvite', 'roomread', 'purchase', 'formatask'];

    /** @var array The tables where a row is one per record and something else: a second one goes. */
    private const ONE = ['review' => 'readerid', 'banktitle' => 'setid', 'readingitem' => 'listid'];

    /** @var string[] The fields of the record the one that stays takes from the other when it has none. */
    private const FILLED = ['subtitle', 'responsibility', 'isbn', 'edition', 'pubplace', 'pubyear', 'lang', 'extent', 'summary',
        'notes'];

    /**
     * The groups of records that look like the same book, those with more copies first.
     *
     * @param int $entityid
     * @return array[] Each a list of record ids, two at least.
     */
    public static function groups(int $entityid): array {
        global $DB;
        $records = $DB->get_records(self::T . 'record', ['entityid' => $entityid], 'id', 'id, title, isbn');
        if (!$records) {
            return [];
        }
        $authors = [];
        $rows = $DB->get_recordset_sql(
            "SELECT ra.recordid, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'author'
              WHERE a.entityid = :entityid
           ORDER BY ra.recordid, ra.sortorder, ra.id",
            ['entityid' => $entityid]
        );
        foreach ($rows as $row) {
            $authors[(int) $row->recordid] ??= text::normalise(catalogue::display_name((string) $row->label));
        }
        $rows->close();
        $byisbn = [];
        $bytitle = [];
        foreach ($records as $record) {
            $id = (int) $record->id;
            if ((string) $record->isbn !== '') {
                $byisbn[$record->isbn][] = $id;
            }
            // The title without its article, wherever the catalogue put it («El principito», «Principito, El»).
            $key = text::title_key((string) $record->title);
            if ($key !== '') {
                $bytitle[$key][$authors[$id] ?? ''][] = $id;
            }
        }
        $groups = array_values(array_filter($byisbn, fn($ids) => count($ids) > 1));
        foreach ($bytitle as $byauthor) {
            // A record without an author may be the book of any of them: it goes with the first.
            $none = $byauthor[''] ?? [];
            unset($byauthor['']);
            if ($byauthor) {
                $first = array_key_first($byauthor);
                $byauthor[$first] = array_merge($byauthor[$first], $none);
            } else {
                $byauthor = [$none];
            }
            foreach ($byauthor as $ids) {
                // The same title and author with different ISBNs are other editions; with one without ISBN, maybe not.
                $without = array_filter($ids, fn($id) => (string) $records[$id]->isbn === '');
                if (count($ids) > 1 && $without) {
                    $groups[] = $ids;
                }
            }
        }
        // One group for what was found both ways, and none for what someone said is not the same.
        $apart = self::apart_pairs($entityid);
        $seen = [];
        $found = [];
        foreach ($groups as $ids) {
            sort($ids);
            $key = implode('-', $ids);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $open = false;
            foreach ($ids as $i => $a) {
                foreach (array_slice($ids, $i + 1) as $b) {
                    $open = $open || !isset($apart[$a . '-' . $b]);
                }
            }
            if ($open) {
                $found[] = $ids;
            }
        }
        if (!$found) {
            return [];
        }
        $copies = $DB->get_records_sql_menu(
            'SELECT recordid, COUNT(1) FROM {' . self::T . 'item} WHERE entityid = :entityid GROUP BY recordid',
            ['entityid' => $entityid]
        );
        usort($found, fn($a, $b) => array_sum(array_map(fn($id) => (int) ($copies[$id] ?? 0), $b))
            <=> array_sum(array_map(fn($id) => (int) ($copies[$id] ?? 0), $a)));
        return $found;
    }

    /**
     * What tells two records apart at a glance.
     *
     * @param int $recordid
     * @return array id, title, subtitle, authors, isbn, publisher, year, copies, out (loans open), imported, created.
     */
    public static function describe(int $recordid): array {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $recordid], '*', MUST_EXIST);
        $labels = $DB->get_records_sql(
            "SELECT a.id, a.kind, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind IN ('author', 'publisher')
           ORDER BY ra.sortorder, ra.id",
            [$recordid]
        );
        $of = fn(string $kind) => array_values(array_map(
            fn($row) => (string) $row->label,
            array_filter($labels, fn($row) => $row->kind === $kind)
        ));
        return [
            'id' => (int) $record->id,
            'title' => (string) $record->title,
            'subtitle' => (string) $record->subtitle,
            'authors' => array_map([catalogue::class, 'display_name'], $of('author')),
            'isbn' => (string) $record->isbn,
            'publisher' => $of('publisher')[0] ?? '',
            'year' => (string) $record->pubyear,
            'copies' => $DB->count_records(self::T . 'item', ['recordid' => $recordid]),
            'out' => (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'loan} l JOIN {' . self::T
                . 'item} i ON i.id = l.itemid WHERE i.recordid = ? AND l.timereturned IS NULL', [$recordid]),
            'imported' => $DB->record_exists(self::T . 'source', ['tablename' => 'record', 'localid' => $recordid]),
            'created' => (int) $record->timecreated,
            'hassummary' => trim((string) $record->summary) !== '',
        ];
    }

    /**
     * Joins records of a centre into one: the others' copies and all that hangs from them go to it, it takes what it
     * lacked, and they go.
     *
     * @param int $entityid
     * @param int $keepid
     * @param int[] $goneids
     * @return int How many records went.
     */
    public static function join(int $entityid, int $keepid, array $goneids): int {
        global $DB;
        $keep = $DB->get_record(self::T . 'record', ['id' => $keepid, 'entityid' => $entityid]);
        if (!$keep) {
            return 0;
        }
        $joined = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach (array_unique(array_map('intval', $goneids)) as $goneid) {
            $gone = $goneid === $keepid ? null : $DB->get_record(self::T . 'record', ['id' => $goneid, 'entityid' => $entityid]);
            if (!$gone) {
                continue;
            }
            foreach (self::MOVED as $table) {
                $DB->set_field(self::T . $table, 'recordid', $keepid, ['recordid' => $goneid]);
            }
            foreach (self::ONE as $table => $other) {
                foreach ($DB->get_records(self::T . $table, ['recordid' => $goneid]) as $row) {
                    if ($DB->record_exists(self::T . $table, ['recordid' => $keepid, $other => $row->$other])) {
                        $DB->delete_records(self::T . $table, ['id' => $row->id]);
                    } else {
                        $DB->set_field(self::T . $table, 'recordid', $keepid, ['id' => $row->id]);
                    }
                }
            }
            foreach ($DB->get_records(self::T . 'recauth', ['recordid' => $goneid]) as $link) {
                if ($DB->record_exists(self::T . 'recauth', ['recordid' => $keepid, 'authorityid' => $link->authorityid])) {
                    $DB->delete_records(self::T . 'recauth', ['id' => $link->id]);
                } else {
                    $DB->set_field(self::T . 'recauth', 'recordid', $keepid, ['id' => $link->id]);
                }
            }
            // What came from another program keeps finding its record: a new import updates the one that stays.
            $DB->set_field(self::T . 'source', 'localid', $keepid, ['tablename' => 'record', 'localid' => $goneid,
                'entityid' => $entityid]);
            foreach (self::FILLED as $field) {
                if (trim((string) $keep->$field) === '' && trim((string) $gone->$field) !== '') {
                    $keep->$field = $gone->$field;
                }
            }
            // A cover the one that stays lacked.
            if (covers::url($keepid) === null && covers::url($goneid) !== null) {
                $file = get_file_storage()->get_file(
                    \context_system::instance()->id,
                    'local_oksigeniaayvurenda',
                    'cover',
                    $goneid,
                    '/',
                    'cover.jpg'
                );
                $source = (array) ((json_decode((string) $gone->extra, true) ?: [])['cover'] ?? []);
                if ($file) {
                    covers::store(
                        $keepid,
                        $file->get_content(),
                        (string) ($source['source'] ?? ''),
                        (string) ($source['url'] ?? '')
                    );
                    $keep->extra = $DB->get_field(self::T . 'record', 'extra', ['id' => $keepid]);
                }
            }
            covers::remove($goneid);
            $DB->delete_records(self::T . 'record', ['id' => $goneid]);
            $joined++;
        }
        if ($joined) {
            $keep->timemodified = time();
            $DB->update_record(self::T . 'record', $keep);
            $DB->set_field(self::T . 'record', 'searchtext', corrections::words($keep), ['id' => $keepid]);
        }
        $transaction->allow_commit();
        if ($joined) {
            catalogue::purge($entityid);
            \local_oksigeniaayvurenda\event\catalogue_corrected::fire($entityid, $keepid, ['what' => 'joined']);
        }
        return $joined;
    }

    /**
     * Notes that records someone looked at are not the same book: they are not offered again.
     *
     * @param int $entityid
     * @param int[] $ids
     */
    public static function apart(int $entityid, array $ids): void {
        global $DB;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $pairs = (array) ($settings['recordsapart'] ?? []);
        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                $pairs[] = $a . '-' . $b;
            }
        }
        $settings['recordsapart'] = array_slice(array_values(array_unique($pairs)), -5000);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * The pairs of records someone said are not the same book.
     *
     * @param int $entityid
     * @return array "a-b" => true.
     */
    private static function apart_pairs(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return array_fill_keys(array_map('strval', (array) ($settings['recordsapart'] ?? [])), true);
    }
}
