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
 * Adding books to the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\callnumber;
use local_oksigeniaayvurenda\core\readers\card_codes;
use local_oksigeniaayvurenda\core\text;

/**
 * A description (from a catalogue, from a file or typed) written into an entity's catalogue, with its copies in a
 * library; or more copies for a record the entity already has. Every way into the catalogue writes the same: the
 * record, its authorities found or made by name, and its words for the search.
 */
final class cataloguer {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Records of the entity with this ISBN, with their copies by library.
     *
     * @param int $entityid
     * @param string $isbn
     * @return array[] id, title, copies (list of library and count).
     */
    public static function existing(int $entityid, string $isbn): array {
        global $DB;
        $records = $DB->get_records(self::T . 'record', ['entityid' => $entityid, 'isbn' => $isbn], 'id', 'id, title');
        $list = [];
        foreach ($records as $record) {
            $copies = $DB->get_records_sql(
                'SELECT b.id, b.name, COUNT(i.id) AS copies
                   FROM {' . self::T . 'item} i
                   JOIN {' . self::T . 'library} b ON b.id = i.libraryid
                  WHERE i.recordid = :recordid
               GROUP BY b.id, b.name
               ORDER BY b.name',
                ['recordid' => $record->id]
            );
            $list[] = ['id' => (int) $record->id, 'title' => $record->title, 'copies' => array_values(array_map(
                fn($c) => ['library' => format_string($c->name, true, ['escape' => false]), 'count' => (int) $c->copies],
                $copies
            )), 'recent' => self::recent($entityid, (int) $record->id)];
        }
        return $list;
    }

    /**
     * What was added here of a record lately, by hand (not by an import): a book passed twice by mistake says so before
     * it becomes a copy nobody has.
     *
     * @param int $entityid
     * @param int $recordid
     * @param int|null $now
     * @return string|null The sentence, or null when nothing was added in the last 30 days.
     */
    public static function recent(int $entityid, int $recordid, ?int $now = null): ?string {
        global $DB;
        $now = $now ?? time();
        $items = $DB->get_records_sql(
            'SELECT i.id, i.barcode, i.timecreated
               FROM {' . self::T . 'item} i
              WHERE i.recordid = :recordid AND i.entityid = :entityid AND i.timecreated > :since
                AND NOT EXISTS (SELECT 1 FROM {' . self::T . "source} s WHERE s.tablename = 'item' AND s.localid = i.id)
           ORDER BY i.timecreated DESC, i.id DESC",
            ['recordid' => $recordid, 'entityid' => $entityid, 'since' => $now - 30 * DAYSECS]
        );
        if (!$items) {
            return null;
        }
        $last = (int) reset($items)->timecreated;
        $when = zones::date($last, '%Y%m%d', $entityid) === zones::date($now, '%Y%m%d', $entityid)
            ? get_string('addrecenttoday', 'local_oksigeniaayvurenda', zones::date($last, 'strftimetime', $entityid))
            : get_string('addrecentday', 'local_oksigeniaayvurenda', zones::date($last, 'strftimedayshort', $entityid));
        $codes = implode(', ', array_slice(array_column($items, 'barcode'), 0, 4)) . (count($items) > 4 ? '…' : '');
        return get_string(
            count($items) === 1 ? 'addrecentone' : 'addrecentmany',
            'local_oksigeniaayvurenda',
            (object) ['count' => count($items), 'when' => $when, 'codes' => $codes]
        );
    }

    /**
     * Records of the centre with the same title and, when both say it, an author in common: a book without an ISBN typed
     * twice would make a second record.
     *
     * @param int $entityid
     * @param string $title
     * @param string[] $authors
     * @return array[] id, title and author, three at most.
     */
    public static function similar(int $entityid, string $title, array $authors): array {
        global $DB;
        $words = text::words($title);
        if (!$words) {
            return [];
        }
        $conditions = [];
        $params = ['entityid' => $entityid];
        foreach (array_slice($words, 0, 6) as $i => $word) {
            $conditions[] = $DB->sql_like('searchtext', ':w' . $i, false, false);
            $params['w' . $i] = '%' . $DB->sql_like_escape($word) . '%';
        }
        $rows = $DB->get_records_select(
            self::T . 'record',
            'entityid = :entityid AND ' . implode(' AND ', $conditions),
            $params,
            'id',
            'id, title',
            0,
            50
        );
        $wanted = text::normalise($title);
        $theirs = array_map(fn($author) => text::words(catalogue::display_name($author)), array_filter($authors, 'strlen'));
        $found = [];
        foreach ($rows as $row) {
            if (text::normalise((string) $row->title) !== $wanted) {
                continue;
            }
            $names = $DB->get_fieldset_sql(
                'SELECT a.label FROM {' . self::T . 'recauth} ra JOIN {' . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid = ? AND a.kind = 'author' ORDER BY ra.sortorder, ra.id",
                [$row->id]
            );
            if ($theirs && $names) {
                $shared = false;
                foreach ($names as $name) {
                    $own = text::words(catalogue::display_name((string) $name));
                    foreach ($theirs as $words) {
                        // The surname at least: «Ana Montes» and «Montes, A.» are the same author.
                        if (array_intersect($own, $words)) {
                            $shared = true;
                        }
                    }
                }
                if (!$shared) {
                    continue;
                }
            }
            $found[] = ['id' => (int) $row->id, 'title' => format_string($row->title, true, ['escape' => false]),
                'author' => $names ? catalogue::display_name((string) $names[0]) : ''];
            if (count($found) === 3) {
                break;
            }
        }
        return $found;
    }

    /**
     * What to put on the spine: what the library wrote for this record before, or the proposal.
     *
     * @param \stdClass $library
     * @param array $description
     * @param int $recordid An existing record, or 0.
     * @return string[] The three lines.
     */
    public static function spine(\stdClass $library, array $description, int $recordid = 0): array {
        global $DB;
        if ($recordid) {
            $items = $DB->get_records(
                self::T . 'item',
                ['recordid' => $recordid],
                'id DESC',
                'id, callnumber1, callnumber2, callnumber3',
                0,
                1
            );
            if ($items) {
                $item = reset($items);
                return [(string) $item->callnumber1, (string) $item->callnumber2, (string) $item->callnumber3];
            }
        }
        return callnumber::propose(
            (string) ($description['authorities']['class'][0] ?? ''),
            (string) ($description['authorities']['author'][0] ?? ''),
            (string) $description['record']['title']
        );
    }

    /**
     * The number the new copy codes of a centre start at, as written (with its zeros, without its control letter); null
     * for none: then they follow the highest there is.
     *
     * @param string $raw
     * @return string|null
     */
    public static function first_code(string $raw): ?string {
        $from = preg_replace('/[^0-9]/', '', preg_replace('/[A-Za-z]$/', '', trim($raw)));
        return $from === '' || (int) $from === 0 ? null : $from;
    }

    /**
     * The code just before the first new copy or card code of a centre, to count from it with the codes there are: when
     * an old program kept printing labels or cards after the copy that came, the new codes start after its last one.
     *
     * @param int $entityid
     * @param string $kind copy or card.
     * @return string[] That code, or nothing when the centre did not say where they start.
     */
    public static function below_first_code(int $entityid, string $kind = 'copy'): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $from = self::first_code((string) ($settings[$kind === 'card' ? 'cardfrom' : 'copyfrom'] ?? ''));
        return $from === null ? [] : [str_pad((string) ((int) $from - 1), strlen($from), '0', STR_PAD_LEFT)];
    }

    /**
     * Adds a book: a new record if it has none, and its copies.
     *
     * @param \stdClass $library
     * @param array $description As the describers give it; ignored when $recordid is given.
     * @param array $copy count, barcodes (read from labels, or empty to make new ones), callnumber1-3, locationid,
     *     itemtype, serials (of material, in the order of the copies), notes.
     * @param int $recordid An existing record of the entity, or 0.
     * @return array recordid, newrecord, itemids, barcodes.
     * @throws \invalid_parameter_exception When a barcode is taken.
     * @throws plan_full When the copies do not fit in the centre's plan.
     */
    public static function add(\stdClass $library, array $description, array $copy, int $recordid = 0): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $now = time();
        $count = max(1, min(50, (int) ($copy['count'] ?? 1)));
        $barcodes = array_values(array_filter(array_map('trim', $copy['barcodes'] ?? []), 'strlen'));
        foreach ($barcodes as $barcode) {
            if ($DB->record_exists(self::T . 'item', ['entityid' => $entityid, 'barcode' => $barcode])) {
                throw new \invalid_parameter_exception('taken:' . $barcode);
            }
        }
        plans::check($entityid, 'copies', $count);
        // Two people adding at once (two desks the day a box comes) must not take the same code.
        $lock = self::lock('copies', $entityid);
        try {
            return self::add_now($library, $entityid, $recordid, $description, $copy, $barcodes, $count, $now);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Adds the copies, the centre's codes already held.
     *
     * @param \stdClass $library
     * @param int $entityid
     * @param int $recordid
     * @param array $description
     * @param array $copy
     * @param string[] $barcodes
     * @param int $count
     * @param int $now
     * @return array
     */
    private static function add_now(
        \stdClass $library,
        int $entityid,
        int $recordid,
        array $description,
        array $copy,
        array $barcodes,
        int $count,
        int $now
    ): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $newrecord = false;
        if (!$recordid) {
            $recordid = self::create_record($entityid, $description, $now);
            $newrecord = true;
        } else {
            $DB->get_record(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid], 'id', MUST_EXIST);
        }
        // The codes and register numbers follow the entity's highest ones.
        $existing = [];
        $regnumber = 0;
        $rows = $DB->get_recordset_select(
            self::T . 'item',
            'entityid = :entityid',
            ['entityid' => $entityid],
            '',
            'id, barcode, regnumber'
        );
        foreach ($rows as $row) {
            $existing[] = $row->barcode;
            if (ctype_digit((string) $row->regnumber)) {
                $regnumber = max($regnumber, (int) $row->regnumber);
            }
        }
        $rows->close();
        if (count($barcodes) < $count) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $barcodes = array_merge($barcodes, card_codes::next(
                array_merge($existing, $barcodes, self::below_first_code($entityid)),
                $count - count($barcodes),
                !empty($settings['controlletter']),
                6
            ));
        }
        $itemids = [];
        $serials = array_values(array_map('trim', $copy['serials'] ?? []));
        $device = ($description['record']['doctype'] ?? '') === 'device' ||
            ($recordid && $DB->get_field(self::T . 'record', 'doctype', ['id' => $recordid]) === 'device');
        foreach (array_slice($barcodes, 0, $count) as $index => $barcode) {
            $itemids[] = $DB->insert_record(self::T . 'item', [
                'entityid' => $entityid,
                'libraryid' => $library->id,
                'recordid' => $recordid,
                'barcode' => $barcode,
                'regnumber' => (string) ++$regnumber,
                'callnumber1' => self::line($copy['callnumber1'] ?? ''),
                'callnumber2' => self::line($copy['callnumber2'] ?? ''),
                'callnumber3' => self::line($copy['callnumber3'] ?? ''),
                'locationid' => self::own_location($entityid, (int) ($copy['locationid'] ?? 0)),
                'itemtype' => ($copy['itemtype'] ?? '') !== '' ? $copy['itemtype'] : 'normal',
                'serial' => ($serials[$index] ?? '') !== '' ? \core_text::substr($serials[$index], 0, 100) : null,
                'itemcondition' => $device ? 'good' : null,
                'notes' => ($copy['notes'] ?? '') !== '' ? $copy['notes'] : null,
                'status' => 'available',
                'timeacquired' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        // Material in a library whose rules do not cover it would never be lent.
        $ruled = $device && rules::cover((int) $library->id, ($copy['itemtype'] ?? '') !== '' ? $copy['itemtype'] : 'normal');
        $transaction->allow_commit();
        purchases::catalogued($library, $recordid, $now);
        return ['recordid' => $recordid, 'newrecord' => $newrecord, 'itemids' => $itemids,
            'barcodes' => array_slice($barcodes, 0, $count), 'ruled' => $ruled];
    }

    /**
     * Takes back an addition just made (today): its copies, never lent, and the record if it was new, has no other copies
     * and nothing else hangs from it.
     *
     * @param int $entityid
     * @param int $recordid
     * @param int[] $itemids
     * @param bool $newrecord
     * @return bool Whether it could.
     */
    public static function undo(int $entityid, int $recordid, array $itemids, bool $newrecord): bool {
        global $DB;
        if (!$itemids || !$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid])) {
            return false;
        }
        [$in, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        if (
            $DB->record_exists_select(self::T . 'loan', 'itemid ' . $in, $params)
            || $DB->record_exists_select(self::T . 'reserve', 'itemid ' . $in, $params)
        ) {
            return false;
        }
        // Only what was just added (today) is taken back, never older copies or an older record passed by mistake.
        $since = time() - DAYSECS;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(
            self::T . 'item',
            "entityid = :entityid AND recordid = :recordid AND id $in AND timecreated >= :since",
            $params + ['entityid' => $entityid, 'recordid' => $recordid, 'since' => $since]
        );
        $fresh = $DB->record_exists_select(self::T . 'record', 'id = :id AND timecreated >= :since', ['id' => $recordid,
            'since' => $since]);
        $held = false;
        foreach (['digital', 'review', 'hold', 'readingitem'] as $table) {
            $held = $held || $DB->record_exists(self::T . $table, ['recordid' => $recordid]);
        }
        if ($newrecord && $fresh && !$held && !$DB->record_exists(self::T . 'item', ['recordid' => $recordid])) {
            $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
            $DB->delete_records(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid]);
            covers::remove($recordid);
        }
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\addition_undone::fire($entityid, $recordid, ['count' => count($itemids)]);
        return true;
    }

    /**
     * A place of the entity's libraries, or nothing when the id is not one of them.
     *
     * @param int $entityid
     * @param int|null $locationid
     * @return int|null
     */
    public static function own_location(int $entityid, ?int $locationid): ?int {
        global $DB;
        return $locationid && $DB->record_exists(self::T . 'authority', ['id' => $locationid, 'entityid' => $entityid,
            'kind' => 'location']) ? $locationid : null;
    }

    /**
     * The places of the entity's libraries, by name.
     *
     * @param int $entityid
     * @return array Authority id => label.
     */
    public static function locations(int $entityid): array {
        global $DB;
        return $DB->get_records_menu(
            self::T . 'authority',
            ['entityid' => $entityid, 'kind' => 'location'],
            'label',
            'id, label'
        );
    }

    /**
     * The kinds of copy the entity uses, the usual one first.
     *
     * @param int $entityid
     * @return string[]
     */
    public static function itemtypes(int $entityid): array {
        global $DB;
        $types = $DB->get_fieldset_sql(
            'SELECT itemtype FROM {' . self::T . 'item} WHERE entityid = :entityid GROUP BY itemtype ORDER BY COUNT(1) DESC',
            ['entityid' => $entityid]
        );
        foreach (['normal', 'reference', 'device'] as $type) {
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        return array_values($types);
    }

    /**
     * Writes a new record with its authorities and its words.
     *
     * @param int $entityid
     * @param array $description
     * @param int $now
     * @return int
     */
    public static function create_record(int $entityid, array $description, int $now): int {
        global $DB;
        [$record, $links] = self::row($entityid, $description, $now);
        $recordid = $DB->insert_record(self::T . 'record', $record + ['entityid' => $entityid, 'timecreated' => $now,
            'timemodified' => $now]);
        self::link($recordid, $links);
        return $recordid;
    }

    /**
     * A record brought again from its program, changed there: its fields and authorities as the program says now.
     * What was added here (a cover) stays.
     *
     * @param int $recordid
     * @param int $entityid
     * @param array $description
     * @param int $now
     */
    public static function renew_record(int $recordid, int $entityid, array $description, int $now): void {
        global $DB;
        [$record, $links] = self::row($entityid, $description, $now);
        $old = (string) $DB->get_field(self::T . 'record', 'extra', ['id' => $recordid, 'entityid' => $entityid], MUST_EXIST);
        $record['extra'] = json_encode(array_merge(json_decode($old, true) ?: [], json_decode($record['extra'], true) ?: [])
            ?: new \stdClass());
        $DB->update_record(self::T . 'record', (object) (['id' => $recordid, 'timemodified' => $now] + $record));
        $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
        self::link($recordid, $links);
    }

    /**
     * A fingerprint of a record as it is here, fields and authorities: to know, when its program sends it again, whether
     * someone changed it here meanwhile.
     *
     * @param int $recordid
     * @return string
     */
    public static function own_hash(int $recordid): string {
        global $DB;
        $fields = ['doctype', 'title', 'subtitle', 'responsibility', 'isbn', 'edition', 'pubplace', 'pubyear', 'lang',
            'extent', 'summary', 'notes'];
        $row = (array) $DB->get_record(self::T . 'record', ['id' => $recordid], implode(', ', $fields));
        $links = $DB->get_records_sql(
            "SELECT ra.id, a.kind, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = :recordid
           ORDER BY ra.sortorder, ra.id",
            ['recordid' => $recordid]
        );
        return sha1(json_encode([
            array_map(fn($f) => isset($row[$f]) ? (string) $row[$f] : null, $fields),
            array_map(fn($l) => [$l->kind, $l->label], array_values($links)),
        ]));
    }

    /**
     * Writes the links of a record to its authorities, each once.
     *
     * @param int $recordid
     * @param array[] $links
     */
    private static function link(int $recordid, array $links): void {
        global $DB;
        $seen = [];
        foreach ($links as $link) {
            if (isset($seen[$link['authorityid']])) {
                continue;
            }
            $seen[$link['authorityid']] = true;
            $DB->insert_record(self::T . 'recauth', $link + ['recordid' => $recordid]);
        }
    }

    /**
     * The fields of a record and its links to authorities, from a description.
     *
     * @param int $entityid
     * @param array $description
     * @param int $now
     * @return array [the record's fields, its links].
     */
    private static function row(int $entityid, array $description, int $now): array {
        $fields = ['doctype', 'title', 'subtitle', 'responsibility', 'isbn', 'edition', 'pubplace', 'pubyear', 'lang',
            'extent', 'summary', 'notes'];
        $record = [];
        foreach ($fields as $field) {
            $value = $description['record'][$field] ?? null;
            $record[$field] = $value === null || $value === '' ? null : (string) $value;
        }
        $record = self::fit($record);
        $record['doctype'] = $record['doctype'] ?? 'book';
        $record['title'] = $record['title'] ?? '?';
        $record['pubyear'] = $record['pubyear'] !== null ? \core_text::substr($record['pubyear'], 0, 20) : null;
        $record['lang'] = $record['lang'] !== null && strlen($record['lang']) === 3 ? $record['lang'] : null;
        $extra = array_diff_key($description['extra'] ?? [], ['cover' => 1, 'isbns' => 1]);
        $record['extra'] = json_encode($extra ?: new \stdClass());
        $words = [$record['title'], $record['subtitle']];
        $links = [];
        $order = 0;
        foreach (['author', 'publisher', 'class', 'subject', 'collection', 'audience'] as $kind) {
            foreach ($description['authorities'][$kind] ?? [] as $label) {
                $links[] = ['authorityid' => self::authority($entityid, $kind, (string) $label, $now),
                    'role' => $kind === 'author' ? 'author' : null, 'sortorder' => $order++];
                if (in_array($kind, ['author', 'subject', 'collection'], true)) {
                    $words[] = $label;
                }
            }
        }
        $record['searchtext'] = text::normalise(implode(' ', array_filter($words)));
        return [$record, $links];
    }

    /**
     * An authority of the entity, found by its name or made.
     *
     * @param int $entityid
     * @param string $kind
     * @param string $label
     * @param int $now
     * @return int
     */
    public static function authority(int $entityid, string $kind, string $label, int $now): int {
        global $DB;
        $label = \core_text::substr(trim($label), 0, 1333);
        // A subject the centre has with other capitals, accents or number («misterios» for «Misterio») is that one.
        if ($kind === 'subject') {
            $found = subjects::find($entityid, $label);
            if ($found) {
                return $found;
            }
        }
        $field = $kind === 'class' ? 'code' : 'label';
        $id = $DB->get_field_select(
            self::T . 'authority',
            'id',
            'entityid = :entityid AND kind = :kind AND ' . $DB->sql_equal($field, ':label', false),
            ['entityid' => $entityid, 'kind' => $kind, 'label' => $label],
            IGNORE_MULTIPLE
        );
        if ($id) {
            return (int) $id;
        }
        $id = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => $kind,
            'code' => $kind === 'class' ? \core_text::substr($label, 0, 100) : null, 'label' => $label,
            'timecreated' => $now, 'timemodified' => $now]);
        if ($kind === 'subject') {
            subjects::made($entityid, $label, $id);
        }
        return $id;
    }

    /**
     * A line of the spine, or null.
     *
     * @param string $value
     * @return string|null
     */
    private static function line(string $value): ?string {
        $value = trim($value);
        return $value === '' ? null : \core_text::substr($value, 0, 100);
    }

    /**
     * The centre's lock for its new codes (copies or readers), held a few seconds at most; false if it could not be had
     * (then the unique index still says no to a repeated code).
     *
     * @param string $what copies or readers.
     * @param int $entityid
     * @return \core\lock\lock|false
     */
    public static function lock(string $what, int $entityid) {
        return \core\lock\lock_config::get_lock_factory('local_oksigeniaayvurenda')->get_lock($what . '_' . $entityid, 15);
    }

    /**
     * A record's fields cut to what the database keeps of each: a long edition statement or physical description from
     * another program must not stop a whole import.
     *
     * @param array $record
     * @return array
     */
    public static function fit(array $record): array {
        $lengths = ['doctype' => 30, 'title' => 1333, 'subtitle' => 1333, 'responsibility' => 1333, 'isbn' => 13,
            'edition' => 255, 'pubplace' => 255, 'pubyear' => 20, 'lang' => 3, 'extent' => 255];
        foreach ($lengths as $field => $most) {
            if (isset($record[$field]) && is_string($record[$field]) && \core_text::strlen($record[$field]) > $most) {
                $record[$field] = \core_text::substr($record[$field], 0, $most);
            }
        }
        return $record;
    }
}
