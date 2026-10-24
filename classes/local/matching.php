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
 * Matching readers without an account with the accounts of the site.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\readers\matcher;

/**
 * Readers who came without an account (from Abies, from a list) against the accounts of the site: the members of the
 * cohorts the entity chose, or every account when it chose none. Linking keeps the reader that came with its card
 * and history; if the account already had a reader of its own (brought from a cohort), the two become one, with the
 * card of the first and the group of the second.
 */
final class matching {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The three piles, with names and groups to show.
     *
     * @param int $entityid
     * @return array{sure: array, doubt: array, none: array, accounts: array, readers: array}
     */
    public static function piles(int $entityid): array {
        $readers = self::unlinked($entityid);
        $accounts = self::accounts($entityid);
        $piles = matcher::piles(array_values($readers), array_values($accounts));
        return $piles + ['readers' => $readers, 'accounts' => $accounts];
    }

    /**
     * Links a reader with an account; a reader the account already had becomes part of it.
     *
     * @param int $entityid
     * @param int $readerid A reader without an account.
     * @param int $userid
     */
    public static function link(int $entityid, int $readerid, int $userid): void {
        global $DB;
        $now = time();
        $keep = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid], '*', MUST_EXIST);
        if ($keep->userid) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $type = $keep->readertype;
        $other = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid]);
        if ($other) {
            // This year's group comes from the cohort: the old reader gives way where both have a group the same year.
            foreach ($DB->get_records(self::T . 'member', ['readerid' => $other->id]) as $member) {
                $DB->delete_records(self::T . 'member', ['readerid' => $keep->id, 'yearid' => $member->yearid]);
            }
            $DB->set_field(self::T . 'member', 'readerid', $keep->id, ['readerid' => $other->id]);
            $DB->set_field(self::T . 'loan', 'readerid', $keep->id, ['readerid' => $other->id]);
            $DB->delete_records(self::T . 'source', ['tablename' => 'reader', 'localid' => $other->id]);
            $DB->delete_records(self::T . 'reader', ['id' => $other->id]);
            $type = $other->readertype;
        }
        // The account gives the name from now on: the library does not keep a copy.
        $DB->update_record(self::T . 'reader', (object) ['id' => $keep->id, 'userid' => $userid, 'readertype' => $type,
            'firstname' => null, 'lastname' => null, 'email' => null, 'status' => 'active', 'timemodified' => $now]);
        $transaction->allow_commit();
    }

    /**
     * Marks readers without an account as gone: they keep their card and history, but are no longer active readers.
     *
     * @param int $entityid
     * @param int[] $readerids
     */
    public static function leave(int $entityid, array $readerids): void {
        global $DB;
        if (!$readerids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($readerids, SQL_PARAMS_NAMED);
        $DB->set_field_select(
            self::T . 'reader',
            'status',
            'left',
            "entityid = :entityid AND userid IS NULL AND id $in",
            $params + ['entityid' => $entityid]
        );
    }

    /**
     * Active readers without an account, with their name and their latest group.
     *
     * @param int $entityid
     * @return array Reader id => id, name, group.
     */
    private static function unlinked(int $entityid): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'reader',
            "entityid = :entityid AND userid IS NULL AND status = 'active'",
            ['entityid' => $entityid],
            'lastname, firstname, id',
            'id, firstname, lastname'
        );
        $readers = [];
        foreach ($rows as $row) {
            $readers[$row->id] = ['id' => (int) $row->id, 'name' => trim($row->firstname . ' ' . $row->lastname), 'group' => ''];
        }
        // The latest group of each: members ordered by year, the last one wins.
        $members = $DB->get_recordset_sql(
            "SELECT m.id, m.readerid, m.groupname
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "year} y ON y.id = m.yearid
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE r.entityid = :entityid AND r.userid IS NULL
           ORDER BY y.timestart, m.id",
            ['entityid' => $entityid]
        );
        foreach ($members as $member) {
            if (isset($readers[$member->readerid])) {
                $readers[$member->readerid]['group'] = (string) $member->groupname;
            }
        }
        $members->close();
        return $readers;
    }

    /**
     * The accounts to match with: members of the chosen cohorts, with the cohort as group, or every account.
     *
     * @param int $entityid
     * @return array User id => id, name, group.
     */
    private static function accounts(int $entityid): array {
        global $DB, $CFG;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $cohorts = array_keys($settings['cohorts'] ?? []);
        if ($cohorts) {
            [$in, $params] = $DB->get_in_or_equal($cohorts, SQL_PARAMS_NAMED);
            $rows = $DB->get_recordset_sql(
                "SELECT m.id AS rowid, u.id, u.firstname, u.lastname, c.name AS groupname
                   FROM {cohort_members} m
                   JOIN {cohort} c ON c.id = m.cohortid
                   JOIN {user} u ON u.id = m.userid
                  WHERE m.cohortid $in AND u.deleted = 0",
                $params
            );
        } else {
            $rows = $DB->get_recordset_select(
                'user',
                'deleted = 0 AND id <> :guest',
                ['guest' => $CFG->siteguest],
                '',
                'id, firstname, lastname'
            );
        }
        $accounts = [];
        foreach ($rows as $row) {
            if (!isset($accounts[$row->id])) {
                $accounts[$row->id] = ['id' => (int) $row->id, 'name' => trim($row->firstname . ' ' . $row->lastname),
                    'group' => (string) ($row->groupname ?? '')];
            }
        }
        $rows->close();
        return $accounts;
    }
}
