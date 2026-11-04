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

use local_oksigeniaayvurenda\core\shelf_order;
use local_oksigeniaayvurenda\core\text;
use local_oksigeniaayvurenda\event\catalogue_corrected;

/**
 * Correcting a record or a copy after it was catalogued, or taking out what was entered by mistake.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class corrections {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The fields of a record that are corrected as they are. */
    private const FIELDS = ['title', 'subtitle', 'isbn', 'edition', 'pubplace', 'pubyear', 'summary', 'notes'];

    /**
     * What the form of a record starts with.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function record_form(\stdClass $record): array {
        global $DB;
        $form = [];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) $record->$field;
        }
        $labels = fn(string $kind) => implode("\n", $DB->get_fieldset_sql(
            'SELECT a.label
               FROM {' . self::T . 'recauth} ra
               JOIN {' . self::T . 'authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind = ?
           ORDER BY ra.sortorder, ra.id',
            [$record->id, $kind]
        ));
        return $form + ['authors' => $labels('author'), 'subjects' => $labels('subject')];
    }

    /**
     * Corrects a record: its words, its authors and subjects, and with them what the search finds.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param array $form
     * @param int|null $now
     * @return bool
     */
    public static function record(\stdClass $library, int $recordid, array $form, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $record = $DB->get_record(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid]);
        $title = trim((string) ($form['title'] ?? ''));
        if (!$record || $title === '') {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($form[$field] ?? ''));
            $record->$field = $value === '' ? null : ($field === 'summary' || $field === 'notes'
                ? $value : \core_text::substr($value, 0, $field === 'pubyear' ? 20 : 1333));
        }
        $record->title = \core_text::substr($title, 0, 1333);
        $record->timemodified = $now;
        foreach (['author', 'subject'] as $kind) {
            self::links($record, $kind, (string) ($form[$kind . 's'] ?? ''), $now);
        }
        $record->searchtext = self::words($record);
        $DB->update_record(self::T . 'record', $record);
        $transaction->allow_commit();
        catalogue_corrected::fire((int) $library->entityid, $recordid, ['what' => 'record']);
        return true;
    }

    /**
     * Puts the authors or the subjects of a record as written, one a line.
     *
     * @param \stdClass $record
     * @param string $kind
     * @param string $lines
     * @param int $now
     */
    private static function links(\stdClass $record, string $kind, string $lines, int $now): void {
        global $DB;
        $old = $DB->get_records_sql(
            'SELECT ra.id
               FROM {' . self::T . 'recauth} ra
               JOIN {' . self::T . 'authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind = ?',
            [$record->id, $kind]
        );
        if ($old) {
            $DB->delete_records_list(self::T . 'recauth', 'id', array_keys($old));
        }
        $order = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . self::T . 'recauth} WHERE recordid = ?',
            [$record->id]
        );
        $seen = [];
        foreach (preg_split('/\R/', $lines) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $id = cataloguer::authority((int) $record->entityid, $kind, $line, $now);
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $id,
                    'role' => $kind === 'author' ? 'author' : null, 'sortorder' => ++$order]);
            }
        }
    }

    /**
     * What the search looks in, as cataloguer makes it.
     *
     * @param \stdClass $record
     * @return string
     */
    private static function words(\stdClass $record): string {
        global $DB;
        $labels = $DB->get_fieldset_sql(
            'SELECT a.label
               FROM {' . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind IN ('author', 'subject', 'collection')",
            [$record->id]
        );
        return text::normalise(implode(' ', array_filter(array_merge([$record->title, $record->subtitle], $labels))));
    }

    /**
     * Corrects a copy of this library. Its code is what the desk reads, so no other copy of the centre can have it.
     *
     * @param \stdClass $library
     * @param int $itemid
     * @param array $form barcode, callnumber1, callnumber2, callnumber3, locationid, itemtype, notes.
     * @param int|null $now
     * @return string An empty string, or why not.
     */
    public static function copy(\stdClass $library, int $itemid, array $form, ?int $now = null): string {
        global $DB;
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $library->id]);
        $code = trim((string) ($form['barcode'] ?? ''));
        if (!$item) {
            return 'recordnotfound';
        }
        if ($code === '') {
            return 'correctnocode';
        }
        $taken = $DB->record_exists_select(
            self::T . 'item',
            'entityid = ? AND barcode = ? AND id <> ?',
            [$item->entityid, $code, $itemid]
        );
        if ($taken) {
            return 'correctcodetaken';
        }
        $item->barcode = \core_text::substr($code, 0, 100);
        foreach (['callnumber1', 'callnumber2', 'callnumber3'] as $line) {
            $value = trim((string) ($form[$line] ?? ''));
            $item->$line = $value === '' ? null : \core_text::substr($value, 0, 100);
        }
        $item->locationid = cataloguer::own_location((int) $item->entityid, (int) ($form['locationid'] ?? 0) ?: null);
        if (trim((string) ($form['itemtype'] ?? '')) !== '') {
            $item->itemtype = trim((string) $form['itemtype']);
        }
        $notes = trim((string) ($form['notes'] ?? ''));
        $item->notes = $notes === '' ? null : $notes;
        $item->timemodified = $now ?? time();
        $record = $DB->get_record(self::T . 'record', ['id' => $item->recordid], 'id, title');
        $item->shelfkey = shelf_order::key(
            $item->callnumber1,
            $item->callnumber2,
            $item->callnumber3,
            (string) $record->title,
            (int) $record->id
        );
        $DB->update_record(self::T . 'item', $item);
        catalogue_corrected::fire((int) $library->entityid, $itemid, ['what' => 'copy']);
        return '';
    }

    /**
     * What keeps a copy in the catalogue: anything that happened to it. Those are taken out by withdrawing them.
     *
     * @param int $itemid
     * @return bool
     */
    private static function has_history(int $itemid): bool {
        global $DB;
        return $DB->record_exists(self::T . 'loan', ['itemid' => $itemid]);
    }

    /**
     * Takes out a copy entered by mistake.
     *
     * @param \stdClass $library
     * @param int $itemid
     * @return string An empty string, or why not.
     */
    public static function remove_copy(\stdClass $library, int $itemid): string {
        global $DB;
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $library->id]);
        if (!$item) {
            return 'recordnotfound';
        }
        if (self::has_history($itemid)) {
            return 'correcthashistory';
        }
        $DB->delete_records(self::T . 'item', ['id' => $itemid]);
        catalogue_corrected::fire((int) $library->entityid, $itemid, ['what' => 'copyremoved']);
        return '';
    }

    /**
     * Takes out a record entered by mistake, with its copies, when nothing has happened to any of them.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return string An empty string, or why not.
     */
    public static function remove_record(\stdClass $library, int $recordid): string {
        global $DB;
        if (!$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid])) {
            return 'recordnotfound';
        }
        $items = $DB->get_fieldset_select(self::T . 'item', 'id', 'recordid = ?', [$recordid]);
        foreach ($items as $itemid) {
            if (self::has_history((int) $itemid)) {
                return 'correcthashistory';
            }
        }
        foreach (
            ['hold', 'digital', 'voiceinvite', 'review', 'roomread', 'banktitle', 'readingitem', 'purchase',
            'formatask'] as $table
        ) {
            if ($DB->record_exists(self::T . $table, ['recordid' => $recordid])) {
                return 'correcthashistory';
            }
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::T . 'item', ['recordid' => $recordid]);
        $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
        $DB->delete_records(self::T . 'record', ['id' => $recordid]);
        $transaction->allow_commit();
        catalogue_corrected::fire((int) $library->entityid, $recordid, ['what' => 'recordremoved']);
        return '';
    }
}
