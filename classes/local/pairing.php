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
 * Pairing the readers already there (from Abies, from a list) with the people of a management platform, before the
 * platform first brings its readers, so that it brings them up to date instead of twice.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\readers\cifra_people;
use local_oksigeniaayvurenda\core\readers\matcher;
use local_oksigeniaayvurenda\local\sources\cifra;
use local_oksigeniaayvurenda\local\sources\csvlist;

/**
 * The same three piles as with the accounts of the site: those that match by themselves, accepted with one tap; those
 * in doubt, one by one; those the platform does not have. Pairing a reader only notes the platform's identifier of
 * that person against them: what the reader has (card, history, loans) stays, and the platform keeps them up to date.
 */
final class pairing {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The platform paired with. */
    public const SYSTEM = 'cifra';

    /**
     * The platform's people as readers, asked once an hour at most: asking takes a while.
     *
     * @param int $entityid
     * @param bool $fresh Ask again.
     * @return array[] As cifra_people::readers() gives them.
     */
    public static function people(int $entityid, bool $fresh = false): array {
        $cache = \cache::make('local_oksigeniaayvurenda', 'platformpeople');
        $cached = $fresh ? false : $cache->get($entityid);
        if ($cached !== false && $cached['time'] > time() - HOURSECS) {
            return $cached['people'];
        }
        $people = cifra_people::readers(cifra::people($entityid), time())['readers'];
        $cache->set($entityid, ['time' => time(), 'people' => $people]);
        return $people;
    }

    /**
     * The three piles: the readers not paired yet against the people not paired yet.
     *
     * @param int $entityid
     * @param array[]|null $people The platform's, or asked for.
     * @return array sure, doubt, none (as matcher gives them), readers (id => id, name, group) and people (number from
     *     1 => id, name, group, person).
     */
    public static function piles(int $entityid, ?array $people = null): array {
        global $DB;
        $people = $people ?? self::people($entityid);
        $paired = $DB->get_records_menu(self::T . 'source', ['entityid' => $entityid, 'system' => self::SYSTEM,
            'tablename' => 'reader'], '', 'id, externalid');
        $taken = array_flip($paired);
        $pairedreaders = array_flip(array_map('intval', $DB->get_fieldset_select(
            self::T . 'source',
            'localid',
            'entityid = :entityid AND system = :system AND tablename = :tablename',
            ['entityid' => $entityid, 'system' => self::SYSTEM, 'tablename' => 'reader']
        )));
        $readers = [];
        $rows = $DB->get_records_select(
            self::T . 'reader',
            "entityid = :entityid AND status = 'active'",
            ['entityid' => $entityid],
            'lastname, firstname, id',
            'id, userid, firstname, lastname'
        );
        $names = [];
        $users = array_filter(array_map(fn($r) => (int) $r->userid, $rows));
        if ($users) {
            foreach ($DB->get_records_list('user', 'id', $users) as $user) {
                $names[(int) $user->id] = fullname($user);
            }
        }
        foreach ($rows as $row) {
            if (isset($pairedreaders[(int) $row->id])) {
                continue;
            }
            $name = $row->userid ? ($names[(int) $row->userid] ?? '') : trim($row->firstname . ' ' . $row->lastname);
            $readers[(int) $row->id] = ['id' => (int) $row->id, 'name' => $name, 'group' => ''];
        }
        $year = years::around($entityid, time());
        foreach ($DB->get_records(self::T . 'member', ['yearid' => $year->id], '', 'id, readerid, groupname') as $member) {
            if (isset($readers[(int) $member->readerid])) {
                $readers[(int) $member->readerid]['group'] = (string) $member->groupname;
            }
        }
        $candidates = [];
        // Numbered from 1: 0 is «none of these» in the form.
        foreach (array_values($people) as $index => $person) {
            if (isset($taken[csvlist::key($person)])) {
                continue;
            }
            $name = trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''));
            $candidates[$index + 1] = ['id' => $index + 1, 'name' => $name, 'group' => (string) ($person['group'] ?? ''),
                'person' => $person];
        }
        $piles = matcher::piles(array_values($readers), array_values(array_map(fn($c) => ['id' => $c['id'],
            'name' => $c['name'], 'group' => $c['group']], $candidates)));
        return $piles + ['readers' => $readers, 'people' => $candidates];
    }

    /**
     * Pairs a reader with a person of the platform: from now on the platform keeps that reader up to date.
     *
     * @param int $entityid
     * @param int $readerid
     * @param array $person As the platform gives it.
     * @return bool Whether it was paired now: not if either side already was.
     */
    public static function link(int $entityid, int $readerid, array $person): bool {
        global $DB;
        $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid], 'id', MUST_EXIST);
        $key = csvlist::key($person);
        $where = ['entityid' => $entityid, 'system' => self::SYSTEM, 'tablename' => 'reader'];
        if (
            $DB->record_exists(self::T . 'source', $where + ['externalid' => $key]) ||
                $DB->record_exists(self::T . 'source', $where + ['localid' => $readerid])
        ) {
            return false;
        }
        $DB->insert_record(self::T . 'source', $where + ['externalid' => $key, 'localid' => $readerid,
            'timecreated' => time()]);
        return true;
    }

    /**
     * Readers the platform does not have: they stop being active readers and keep their history.
     *
     * @param int $entityid
     * @param int[] $readerids
     */
    public static function leave(int $entityid, array $readerids): void {
        global $DB;
        if (!$readerids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal(array_map('intval', $readerids), SQL_PARAMS_NAMED);
        $DB->set_field_select(
            self::T . 'reader',
            'status',
            'left',
            "entityid = :entityid AND id $in",
            $params + ['entityid' => $entityid]
        );
    }

    /**
     * How many readers are not paired with the platform yet.
     *
     * @param int $entityid
     * @return int
     */
    public static function unpaired(int $entityid): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::T . "reader} r
              WHERE r.entityid = :entityid AND r.status = 'active'
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "source} s WHERE s.entityid = r.entityid
                                AND s.system = :system AND s.tablename = 'reader' AND s.localid = r.id)",
            ['entityid' => $entityid, 'system' => self::SYSTEM]
        );
    }
}
