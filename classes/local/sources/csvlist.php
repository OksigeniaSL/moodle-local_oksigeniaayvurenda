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

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\core\readers\card_codes;
use local_oksigeniaayvurenda\core\text;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\practice;
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
    private const TYPES = ['st' => 'student', 't' => 'teacher', 's' => 'staff',
        'alumno' => 'student', 'alumna' => 'student', 'student' => 'student', 'estudiante' => 'student',
        'profesor' => 'teacher', 'profesora' => 'teacher', 'docente' => 'teacher', 'teacher' => 'teacher',
        'personal' => 'staff', 'pas' => 'staff', 'staff' => 'staff', 'familia' => 'family', 'family' => 'family'];

    /**
     * Loads the readers of a list.
     *
     * @param int $entityid
     * @param array[] $readers As csv_list gives them.
     * @param string $type The kind of reader when the list does not say it.
     * @param int|null $now
     * @param string $system Where the list comes from: csv, or a management program such as cifra.
     * @param bool $partly Past the centre's plan, leave out the readers who do not fit (a sync); when false, the caller
     *     refuses the whole list (plans::within).
     * @return array{created: int, updated: int, linked: int, total: int, overlimit: int, ids: int[]}
     */
    public static function load(
        int $entityid,
        array $readers,
        string $type = 'student',
        ?int $now = null,
        string $system = 'csv',
        bool $partly = true
    ): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['created' => 0, 'updated' => 0, 'linked' => 0, 'total' => 0, 'overlimit' => 0, 'ids' => []];
        $room = $partly ? plans::left($entityid, 'readers') : null;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $year = years::around($entityid, $now);
        $transaction = $DB->start_delegated_transaction();
        // What is there, all at once: a question per reader, every night, adds up.
        $known = $DB->get_records(
            self::T . 'reader',
            ['entityid' => $entityid],
            '',
            'id, cardcode, userid, status, readertype, firstname, lastname, email'
        );
        $codes = array_merge(
            array_column($known, 'cardcode'),
            \local_oksigeniaayvurenda\local\cataloguer::below_first_code($entityid, 'card')
        );
        $bycard = [];
        $byuser = [];
        foreach ($known as $one) {
            $bycard[\core_text::strtolower((string) $one->cardcode)] = (int) $one->id;
            if ($one->userid) {
                $byuser[(int) $one->userid] = (int) $one->id;
            }
        }
        $sources = $DB->get_records_menu(
            self::T . 'source',
            ['entityid' => $entityid, 'system' => $system, 'tablename' => 'reader'],
            '',
            'externalid, localid'
        );
        $members = [];
        foreach ($DB->get_records_select(self::T . 'member', 'yearid = :yearid', ['yearid' => $year->id]) as $row) {
            $members[(int) $row->readerid] = $row;
        }
        // Names that come twice in the list are told apart by their group.
        $names = array_count_values(array_map(fn($p) => self::key($p), $readers));
        // An email that comes twice, a family's, does not say whose account it is.
        $emails = array_count_values(array_filter(array_map(fn($p) => \core_text::strtolower($p['email'] ?? ''), $readers)));
        foreach ($readers as $person) {
            $key = self::key($person);
            if (($names[$key] ?? 0) > 1 && str_starts_with($key, 'name:')) {
                $key .= '|' . text::normalise($person['group'] ?? '');
            }
            $readerid = 0;
            if (($person['cardcode'] ?? '') !== '') {
                $readerid = $bycard[\core_text::strtolower($person['cardcode'])] ?? 0;
            }
            if (!$readerid) {
                $readerid = (int) ($sources[$key] ?? 0);
            }
            $lookup = $person;
            if (($emails[\core_text::strtolower($person['email'] ?? '')] ?? 0) > 1) {
                unset($lookup['email']);
            }
            $userid = self::account($lookup, $entityid);
            if (!$readerid && $userid) {
                $readerid = $byuser[$userid] ?? 0;
            }
            // An account that another reader already has is not taken from it.
            if ($userid && ($byuser[$userid] ?? $readerid) !== $readerid) {
                $userid = 0;
            }
            // A new reader, or one who had left, takes a place in the plan.
            if ($room !== null && (!$readerid || ($known[$readerid]->status ?? '') !== 'active')) {
                if ($room < 1) {
                    $stats['overlimit']++;
                    continue;
                }
                $room--;
            }
            $kind = self::TYPES[text::normalise($person['type'] ?? '')] ?? $type;
            // An account gives the name: the library does not keep a copy of it.
            $data = ['readertype' => $kind, 'status' => 'active', 'timemodified' => $now,
                'userid' => $userid ?: null,
                'firstname' => $userid ? null : \core_text::substr($person['firstname'] ?? '', 0, 100),
                'lastname' => $userid ? null : \core_text::substr($person['lastname'] ?? '', 0, 100),
                'email' => $userid ? null : (($person['email'] ?? '') !== '' ? $person['email'] : null)];
            if ($readerid) {
                // Only if something changed: most nights, nothing has.
                $was = $known[$readerid] ?? null;
                $changed = !$was;
                foreach (['readertype', 'status', 'userid', 'firstname', 'lastname', 'email'] as $field) {
                    $changed = $changed || (string) ($was->$field ?? '') !== (string) ($data[$field] ?? '');
                }
                if ($changed) {
                    $DB->update_record(self::T . 'reader', (object) (['id' => $readerid] + $data));
                }
                $stats['updated']++;
            } else {
                $code = ($person['cardcode'] ?? '') !== '' ? strtoupper($person['cardcode'])
                    : card_codes::next($codes, 1, !empty($settings['controlletter']))[0];
                $codes[] = $code;
                $readerid = (int) $DB->insert_record(self::T . 'reader', $data + ['entityid' => $entityid, 'cardcode' => $code,
                    'timecreated' => $now]);
                $bycard[\core_text::strtolower($code)] = $readerid;
                $stats['created']++;
            }
            $known[$readerid] = (object) (['id' => $readerid] + $data);
            if ($userid) {
                $byuser[$userid] = $readerid;
                $stats['linked']++;
            }
            if (!isset($sources[$key])) {
                $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'system' => $system, 'tablename' => 'reader',
                    'externalid' => $key, 'localid' => $readerid, 'timecreated' => $now]);
                $sources[$key] = $readerid;
            }
            $stats['ids'][] = (int) $readerid;
            if (($person['group'] ?? '') !== '') {
                $members[$readerid] = self::member(
                    $readerid,
                    (int) $year->id,
                    $person['group'],
                    $person['stage'] ?? '',
                    $now,
                    $system,
                    $members[$readerid] ?? null
                );
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
        // A management program gives each person an identifier of their own; an email may be a whole family's.
        if (($person['externalid'] ?? '') !== '') {
            return 'id:' . $person['externalid'];
        }
        foreach (['idnumber', 'email', 'username', 'cardcode'] as $field) {
            if (($person[$field] ?? '') !== '') {
                return $field . ':' . \core_text::strtolower($person[$field]);
            }
        }
        return 'name:' . text::normalise(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''));
    }

    /**
     * The account of the site with the same file number, user or email; none if it is not one only. With centres apart, only
     * one that is already of this centre: a list must not be a way of asking who has an account in another, or of taking it.
     *
     * @param array $person
     * @param int $entityid
     * @return int
     */
    private static function account(array $person, int $entityid): int {
        global $DB;
        if (practice::is($entityid)) {
            return 0;
        }
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
                return !access::apart() || self::of_the_centre((int) $ids[0], $entityid) ? (int) $ids[0] : 0;
            }
        }
        return 0;
    }

    /**
     * Whether an account is already of a centre: one of its readers, or with a role inside its category.
     *
     * @param int $userid
     * @param int $entityid
     * @return bool
     */
    private static function of_the_centre(int $userid, int $entityid): bool {
        global $DB;
        if ($DB->record_exists(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid])) {
            return true;
        }
        $own = access::context($entityid);
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :userid AND (ctx.id = :contextid OR " . $DB->sql_like('ctx.path', ':path') . ')',
            ['userid' => $userid, 'contextid' => $own->id, 'path' => $own->path . '/%']
        );
    }

    /**
     * Notes the group of a reader in a school year.
     *
     * @param int $readerid
     * @param int $yearid
     * @param string $group
     * @param string $stage
     * @param int $now
     * @param string $source Where the group comes from.
     * @param \stdClass|null $existing What the reader has this year, if anything.
     * @return \stdClass What the reader has now.
     */
    private static function member(
        int $readerid,
        int $yearid,
        string $group,
        string $stage,
        int $now,
        string $source,
        ?\stdClass $existing
    ): \stdClass {
        global $DB;
        $row = (object) ['groupname' => $group, 'stage' => $stage !== '' ? $stage : null, 'source' => $source];
        if (!$existing) {
            $row->id = $DB->insert_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $yearid, 'groupname' => $group,
                'stage' => $row->stage, 'source' => $source, 'timecreated' => $now]);
            return $row;
        }
        $row->id = $existing->id;
        if ($existing->groupname !== $group || (string) $existing->stage !== $stage) {
            $DB->update_record(self::T . 'member', $row);
        }
        return $row;
    }
}
