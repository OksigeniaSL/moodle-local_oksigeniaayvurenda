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
 * The same person with two readers, which happens on coming from another program: finding them and putting them
 * together.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * Readers with the same words in their name, of the same type, are offered as likely the same person; any two can be
 * put together by their card codes. A name alone never joins anyone: the librarian chooses which one stays, and that
 * one keeps everything of both (loans, holds, groups, voices, where they came from); the other card stops working.
 */
final class twins {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] Tables whose rows belong to a reader. */
    private const OWNED = ['loan', 'hold', 'digital', 'voiceconsent', 'voiceinvite'];

    /**
     * Active readers that look like the same person: same words in the name, same type; in the order of the name.
     *
     * @param int $entityid
     * @param string $query Only names with all these words.
     * @return array[] Each a list of reader ids, two or more.
     */
    public static function likely(int $entityid, string $query = ''): array {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT r.id, r.readertype, COALESCE(u.firstname, r.firstname) AS firstname,
                    COALESCE(u.lastname, r.lastname) AS lastname
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.status = 'active'",
            ['entityid' => $entityid]
        );
        $wanted = text::words($query);
        $groups = [];
        foreach ($rows as $row) {
            $words = text::words($row->firstname . ' ' . $row->lastname);
            if (count($words) < 2 || array_diff($wanted, $words)) {
                continue;
            }
            $sorted = $words;
            sort($sorted);
            $key = implode(' ', $sorted) . '|' . $row->readertype;
            $groups[$key][] = (int) $row->id;
        }
        $rows->close();
        ksort($groups);
        return array_values(array_filter($groups, fn($ids) => count($ids) > 1));
    }

    /**
     * What a librarian needs to tell two readers apart.
     *
     * @param int $entityid
     * @param int[] $ids
     * @return array[] id => id, name, cardcode, type, group, loans (all), out (not returned).
     */
    public static function describe(int $entityid, array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_map('intval', $ids), SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.cardcode, r.readertype, r.firstname, r.lastname, r.userid,
                    u.firstname AS ufirstname, u.lastname AS ulastname,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l WHERE l.readerid = r.id) AS loans,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l WHERE l.readerid = r.id AND l.timereturned IS NULL) AS outnow
               FROM {" . self::T . "reader} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.entityid = :entityid AND r.id $in",
            $params + ['entityid' => $entityid]
        );
        $year = years::around($entityid, time());
        $groups = $DB->get_records_menu(self::T . 'member', ['yearid' => $year->id], '', 'readerid, groupname');
        $described = [];
        foreach ($ids as $id) {
            $row = $rows[$id] ?? null;
            if (!$row) {
                continue;
            }
            $first = $row->userid ? $row->ufirstname : $row->firstname;
            $last = $row->userid ? $row->ulastname : $row->lastname;
            $described[(int) $id] = ['id' => (int) $id, 'name' => trim($first . ' ' . $last) ?: $row->cardcode,
                'cardcode' => $row->cardcode, 'type' => $row->readertype, 'group' => (string) ($groups[$id] ?? ''),
                'loans' => (int) $row->loans, 'out' => (int) $row->outnow, 'account' => (bool) $row->userid];
        }
        return $described;
    }

    /**
     * The reader with a card code.
     *
     * @param int $entityid
     * @param string $code
     * @return int 0 if none.
     */
    public static function by_code(int $entityid, string $code): int {
        global $DB;
        $code = trim($code);
        if ($code === '') {
            return 0;
        }
        return (int) $DB->get_field_select(self::T . 'reader', 'id', 'entityid = :entityid AND ' .
            $DB->sql_equal('cardcode', ':code', false), ['entityid' => $entityid, 'code' => $code]);
    }

    /**
     * Puts the second reader into the first: everything of it passes to the first, and it is removed.
     *
     * @param int $entityid
     * @param int $keepid The one that stays, with its card.
     * @param int $goneid
     * @throws \moodle_exception When both have an account, and they are not the same.
     */
    public static function join(int $entityid, int $keepid, int $goneid): void {
        global $DB;
        if ($keepid === $goneid) {
            return;
        }
        $keep = $DB->get_record(self::T . 'reader', ['id' => $keepid, 'entityid' => $entityid], '*', MUST_EXIST);
        $gone = $DB->get_record(self::T . 'reader', ['id' => $goneid, 'entityid' => $entityid], '*', MUST_EXIST);
        if ($keep->userid && $gone->userid && (int) $keep->userid !== (int) $gone->userid) {
            throw new \moodle_exception('twinsaccounts', 'local_oksigeniaayvurenda');
        }
        $transaction = $DB->start_delegated_transaction();
        // The group of a year the one that stays already has wins.
        foreach ($DB->get_records(self::T . 'member', ['readerid' => $keepid], '', 'id, yearid') as $member) {
            $DB->delete_records(self::T . 'member', ['readerid' => $goneid, 'yearid' => $member->yearid]);
        }
        $DB->set_field(self::T . 'member', 'readerid', $keepid, ['readerid' => $goneid]);
        foreach (self::OWNED as $table) {
            $DB->set_field(self::T . $table, 'readerid', $keepid, ['readerid' => $goneid]);
        }
        $DB->set_field(self::T . 'source', 'localid', $keepid, ['entityid' => $entityid, 'tablename' => 'reader',
            'localid' => $goneid]);
        $DB->delete_records(self::T . 'reader', ['id' => $goneid]);
        $update = ['id' => $keepid, 'timemodified' => time()];
        if ($gone->status === 'active') {
            $update['status'] = 'active';
        }
        if (!$keep->userid && $gone->userid) {
            // The account gives the name from now on: the library does not keep a copy.
            $update += ['userid' => $gone->userid, 'firstname' => null, 'lastname' => null, 'email' => null];
        } else if (!$keep->userid && !$keep->email && $gone->email) {
            $update['email'] = $gone->email;
        }
        $DB->update_record(self::T . 'reader', (object) $update);
        $transaction->allow_commit();
    }
}
