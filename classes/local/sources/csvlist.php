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
 * Readers from a CSV list.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\card_codes;
use local_oksigeniaayvurenda\core\text;
use local_oksigeniaayvurenda\local\years;

/**
 * Readers from a list, for a centre whose readers are not in Moodle cohorts, or that has no Moodle: each one is found
 * again by its card, by the key it came with (the file number, the email or the user) or, the first time, by the
 * account with that key, and linked to it; the others are readers without an account, with their card. Their group of
 * this school year is noted. A list can be loaded again: what changed is changed, nothing is repeated.
 */
final class csvlist {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array<string, string> Words of the kind of reader, without accents or case. */
    private const TYPES = ['alumno' => 'student', 'alumna' => 'student', 'student' => 'student', 'estudiante' => 'student',
        'profesor' => 'teacher', 'profesora' => 'teacher', 'docente' => 'teacher', 'teacher' => 'teacher',
        'personal' => 'staff', 'pas' => 'staff', 'staff' => 'staff', 'familia' => 'family', 'family' => 'family'];

    /**
     * Loads the readers of a list.
     *
     * @param int $entityid
     * @param array[] $readers As csv_list gives them.
     * @param string $type The kind of reader when the list does not say it.
     * @param int|null $now
     * @return array{created: int, updated: int, linked: int, total: int}
     */
    public static function load(int $entityid, array $readers, string $type = 'student', ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['created' => 0, 'updated' => 0, 'linked' => 0, 'total' => 0];
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $year = years::around($entityid, $now);
        $transaction = $DB->start_delegated_transaction();
        $codes = $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]);
        // Names that come twice in the list are told apart by their group.
        $names = array_count_values(array_map(fn($p) => self::key($p), $readers));
        foreach ($readers as $person) {
            $key = self::key($person);
            if (($names[$key] ?? 0) > 1 && str_starts_with($key, 'name:')) {
                $key .= '|' . text::normalise($person['group'] ?? '');
            }
            $readerid = 0;
            if (($person['cardcode'] ?? '') !== '') {
                $readerid = (int) $DB->get_field_select(self::T . 'reader', 'id', 'entityid = :entityid AND ' .
                    $DB->sql_equal('cardcode', ':code', false), ['entityid' => $entityid, 'code' => $person['cardcode']]);
            }
            if (!$readerid) {
                $readerid = (int) $DB->get_field(self::T . 'source', 'localid', ['entityid' => $entityid, 'system' => 'csv',
                    'tablename' => 'reader', 'externalid' => $key]);
            }
            $userid = self::account($person);
            if (!$readerid && $userid) {
                $readerid = (int) $DB->get_field(self::T . 'reader', 'id', ['entityid' => $entityid, 'userid' => $userid]);
            }
            // An account that another reader already has is not taken from it.
            if (
                $userid && $DB->record_exists_select(
                    self::T . 'reader',
                    'entityid = :entityid AND userid = :userid AND id <> :id',
                    ['entityid' => $entityid, 'userid' => $userid, 'id' => $readerid]
                )
            ) {
                $userid = 0;
            }
            $kind = self::TYPES[text::normalise($person['type'] ?? '')] ?? $type;
            // An account gives the name: the library does not keep a copy of it.
            $data = ['readertype' => $kind, 'status' => 'active', 'timemodified' => $now,
                'userid' => $userid ?: null,
                'firstname' => $userid ? null : \core_text::substr($person['firstname'] ?? '', 0, 100),
                'lastname' => $userid ? null : \core_text::substr($person['lastname'] ?? '', 0, 100),
                'email' => $userid ? null : (($person['email'] ?? '') !== '' ? $person['email'] : null)];
            if ($readerid) {
                $DB->update_record(self::T . 'reader', (object) (['id' => $readerid] + $data));
                $stats['updated']++;
            } else {
                $code = ($person['cardcode'] ?? '') !== '' ? strtoupper($person['cardcode'])
                    : card_codes::next($codes, 1, !empty($settings['controlletter']))[0];
                $codes[] = $code;
                $readerid = $DB->insert_record(self::T . 'reader', $data + ['entityid' => $entityid, 'cardcode' => $code,
                    'timecreated' => $now]);
                $stats['created']++;
            }
            if ($userid) {
                $stats['linked']++;
            }
            if (
                !$DB->record_exists(self::T . 'source', ['entityid' => $entityid, 'system' => 'csv', 'tablename' => 'reader',
                    'externalid' => $key])
            ) {
                $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'system' => 'csv', 'tablename' => 'reader',
                    'externalid' => $key, 'localid' => $readerid, 'timecreated' => $now]);
            }
            if (($person['group'] ?? '') !== '') {
                self::member($readerid, (int) $year->id, $person['group'], $person['stage'] ?? '', $now);
            }
            $stats['total']++;
        }
        $transaction->allow_commit();
        return $stats;
    }

    /**
     * The key a reader of a list is found again by: the file number, the email, the user, the card or, without any of
     * them, the name (not the group, which changes every year).
     *
     * @param array $person
     * @return string
     */
    public static function key(array $person): string {
        foreach (['idnumber', 'email', 'username', 'cardcode'] as $field) {
            if (($person[$field] ?? '') !== '') {
                return $field . ':' . \core_text::strtolower($person[$field]);
            }
        }
        return 'name:' . text::normalise(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''));
    }

    /**
     * The account of the site with the same file number, user or email; none if it is not one only.
     *
     * @param array $person
     * @return int
     */
    private static function account(array $person): int {
        global $DB;
        foreach (['idnumber', 'username', 'email'] as $field) {
            $value = trim($person[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $ids = $DB->get_fieldset_select(
                'user',
                'id',
                'deleted = 0 AND ' . $DB->sql_equal($field, ':value', false),
                ['value' => $value]
            );
            if (count($ids) === 1) {
                return (int) $ids[0];
            }
        }
        return 0;
    }

    /**
     * Notes the group of a reader in a school year.
     *
     * @param int $readerid
     * @param int $yearid
     * @param string $group
     * @param string $stage
     * @param int $now
     */
    private static function member(int $readerid, int $yearid, string $group, string $stage, int $now): void {
        global $DB;
        $existing = $DB->get_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $yearid], '*', IGNORE_MULTIPLE);
        if (!$existing) {
            $DB->insert_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $yearid, 'groupname' => $group,
                'stage' => $stage !== '' ? $stage : null, 'source' => 'csv', 'timecreated' => $now]);
        } else if ($existing->groupname !== $group || (string) $existing->stage !== $stage) {
            $DB->update_record(self::T . 'member', (object) ['id' => $existing->id, 'groupname' => $group,
                'stage' => $stage !== '' ? $stage : null, 'source' => 'csv']);
        }
    }
}
