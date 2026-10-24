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
                fn($c) => ['library' => format_string($c->name), 'count' => (int) $c->copies],
                $copies
            ))];
        }
        return $list;
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
     * Adds a book: a new record if it has none, and its copies.
     *
     * @param \stdClass $library
     * @param array $description As the describers give it; ignored when $recordid is given.
     * @param array $copy count, barcodes (read from labels, or empty to make new ones), callnumber1-3, locationid,
     *     itemtype, serials (of material, in the order of the copies), notes.
     * @param int $recordid An existing record of the entity, or 0.
     * @return array recordid, newrecord, itemids, barcodes.
     * @throws \invalid_parameter_exception When a barcode is taken.
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
                array_merge($existing, $barcodes),
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
                'locationid' => !empty($copy['locationid']) ? (int) $copy['locationid'] : null,
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
        return ['recordid' => $recordid, 'newrecord' => $newrecord, 'itemids' => $itemids,
            'barcodes' => array_slice($barcodes, 0, $count), 'ruled' => $ruled];
    }

    /**
     * Takes back an addition just made: its copies, never lent, and the record if it was new and has no others.
     *
     * @param int $entityid
     * @param int $recordid
     * @param int[] $itemids
     * @param bool $newrecord
     * @return bool Whether it could.
     */
    public static function undo(int $entityid, int $recordid, array $itemids, bool $newrecord): bool {
        global $DB;
        if (!$itemids) {
            return false;
        }
        [$in, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        if ($DB->record_exists_select(self::T . 'loan', 'itemid ' . $in, $params)) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(
            self::T . 'item',
            "entityid = :entityid AND recordid = :recordid AND id $in",
            $params + ['entityid' => $entityid, 'recordid' => $recordid]
        );
        if ($newrecord && !$DB->record_exists(self::T . 'item', ['recordid' => $recordid])) {
            $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
            $DB->delete_records(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid]);
            covers::remove($recordid);
        }
        $transaction->allow_commit();
        return true;
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
        $fields = ['doctype', 'title', 'subtitle', 'responsibility', 'isbn', 'edition', 'pubplace', 'pubyear', 'lang',
            'extent', 'summary', 'notes'];
        $record = ['entityid' => $entityid, 'timecreated' => $now, 'timemodified' => $now];
        foreach ($fields as $field) {
            $value = $description['record'][$field] ?? null;
            $record[$field] = $value === null || $value === '' ? null : \core_text::substr((string) $value, 0, 1333);
        }
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
        $recordid = $DB->insert_record(self::T . 'record', $record);
        $seen = [];
        foreach ($links as $link) {
            if (isset($seen[$link['authorityid']])) {
                continue;
            }
            $seen[$link['authorityid']] = true;
            $DB->insert_record(self::T . 'recauth', $link + ['recordid' => $recordid]);
        }
        return $recordid;
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
    private static function authority(int $entityid, string $kind, string $label, int $now): int {
        global $DB;
        $label = \core_text::substr(trim($label), 0, 1333);
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
        return (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => $kind,
            'code' => $kind === 'class' ? \core_text::substr($label, 0, 100) : null, 'label' => $label,
            'timecreated' => $now, 'timemodified' => $now]);
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
}
