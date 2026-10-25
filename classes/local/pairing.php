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
 *
 * If the platform brought its readers before pairing, the ones in both are there twice: the reader the platform made,
 * with nothing of its own yet (no loans, holds, voices), is still offered, and pairing melts it into the one there was.
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
     * @return array sure, doubt, none (as matcher gives them), readers (id => id, name, group, type), people (number from
     *     1 => id, name, group, person, made: the reader the platform made for them, or 0; type) and skipped (readers of
     *     types the platform does not bring).
     */
    public static function piles(int $entityid, ?array $people = null): array {
        global $DB;
        $people = $people ?? self::people($entityid);
        $paired = $DB->get_records_menu(self::T . 'source', ['entityid' => $entityid, 'system' => self::SYSTEM,
            'tablename' => 'reader'], '', 'id, externalid');
        $made = self::made($entityid);
        $taken = array_diff_key(array_flip($paired), $made);
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
            'id, userid, firstname, lastname, readertype'
        );
        // Each reader is compared only with people of their own type; the types the platform does not bring (teachers,
        // when it brings only students) stay out, neither in doubt nor as gone.
        $types = array_flip(array_map(fn($p) => (string) ($p['type'] ?? 'student'), $people));
        $skipped = 0;
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
            if (!isset($types[$row->readertype])) {
                $skipped++;
                continue;
            }
            $name = $row->userid ? ($names[(int) $row->userid] ?? '') : trim($row->firstname . ' ' . $row->lastname);
            $readers[(int) $row->id] = ['id' => (int) $row->id, 'name' => $name, 'group' => '', 'type' => $row->readertype];
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
                'person' => $person, 'made' => $made[csvlist::key($person)] ?? 0,
                'type' => (string) ($person['type'] ?? 'student')];
        }
        $piles = [matcher::SURE => [], matcher::DOUBT => [], matcher::NONE => []];
        foreach (array_keys($types) as $type) {
            $these = array_values(array_filter($readers, fn($r) => $r['type'] === $type));
            $those = array_values(array_map(
                fn($c) => ['id' => $c['id'], 'name' => $c['name'], 'group' => $c['group']],
                array_filter($candidates, fn($c) => $c['type'] === $type)
            ));
            $found = matcher::piles($these, $those);
            $piles[matcher::SURE] += $found[matcher::SURE];
            $piles[matcher::DOUBT] += $found[matcher::DOUBT];
            $piles[matcher::NONE] = array_merge($piles[matcher::NONE], $found[matcher::NONE]);
        }
        return $piles + ['readers' => $readers, 'people' => $candidates, 'skipped' => $skipped];
    }

    /**
     * Pairs a reader with a person of the platform: from now on the platform keeps that reader up to date.
     *
     * @param int $entityid
     * @param int $readerid
     * @param array $person As the platform gives it.
     * @param int|null $made The reader the platform made for that person, as piles() says; null asks for it.
     * @return bool Whether it was paired now: not if either side already was.
     */
    public static function link(int $entityid, int $readerid, array $person, ?int $made = null): bool {
        global $DB;
        $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $entityid], 'id', MUST_EXIST);
        $key = csvlist::key($person);
        $where = ['entityid' => $entityid, 'system' => self::SYSTEM, 'tablename' => 'reader'];
        if ($DB->record_exists(self::T . 'source', $where + ['localid' => $readerid])) {
            return false;
        }
        $source = $DB->get_record(self::T . 'source', $where + ['externalid' => $key]);
        if (!$source) {
            $DB->insert_record(self::T . 'source', $where + ['externalid' => $key, 'localid' => $readerid,
                'timecreated' => time()]);
            return true;
        }
        $made = $made ?? (int) (self::made($entityid)[$key] ?? 0);
        if (!$made || $made !== (int) $source->localid) {
            return false;
        }
        self::melt($entityid, $readerid, $made);
        return true;
    }
    /**
     * The readers the platform made that have nothing of their own yet: no loans, holds or voices, and no other source.
     *
     * @param int $entityid
     * @return int[] The platform's key => reader id.
     */
    private static function made(int $entityid): array {
        global $DB;
        $others = [];
        foreach (['loan', 'hold', 'digital', 'voiceconsent', 'voiceinvite'] as $table) {
            $others[] = "NOT EXISTS (SELECT 1 FROM {" . self::T . $table . "} o WHERE o.readerid = r.id)";
        }
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT s.externalid, r.id
               FROM {" . self::T . "source} s
               JOIN {" . self::T . "reader} r ON r.id = s.localid AND r.entityid = s.entityid
              WHERE s.entityid = :entityid AND s.system = :system AND s.tablename = 'reader'
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "source} x WHERE x.entityid = s.entityid
                                AND x.tablename = 'reader' AND x.localid = r.id AND x.system <> s.system)
                AND " . implode(' AND ', $others),
            ['entityid' => $entityid, 'system' => self::SYSTEM]
        ));
    }

    /**
     * Melts the reader the platform made into the one there was: this year's group and the account come with it.
     *
     * @param int $entityid
     * @param int $keepid The reader there was.
     * @param int $madeid The one the platform made.
     */
    private static function melt(int $entityid, int $keepid, int $madeid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $keep = $DB->get_record(self::T . 'reader', ['id' => $keepid, 'entityid' => $entityid], '*', MUST_EXIST);
        $made = $DB->get_record(self::T . 'reader', ['id' => $madeid, 'entityid' => $entityid], '*', MUST_EXIST);
        // The platform's group wins where both have one the same year.
        foreach ($DB->get_records(self::T . 'member', ['readerid' => $madeid]) as $member) {
            $DB->delete_records(self::T . 'member', ['readerid' => $keepid, 'yearid' => $member->yearid]);
        }
        $DB->set_field(self::T . 'member', 'readerid', $keepid, ['readerid' => $madeid]);
        reviews::move($madeid, $keepid);
        $DB->set_field(self::T . 'source', 'localid', $keepid, ['entityid' => $entityid, 'system' => self::SYSTEM,
            'tablename' => 'reader', 'localid' => $madeid]);
        $DB->delete_records(self::T . 'reader', ['id' => $madeid]);
        $update = ['id' => $keepid, 'status' => 'active', 'timemodified' => time()];
        if (!$keep->userid && $made->userid) {
            // The account gives the name from now on: the library does not keep a copy.
            $update += ['userid' => $made->userid, 'firstname' => null, 'lastname' => null, 'email' => null];
        }
        $DB->update_record(self::T . 'reader', (object) $update);
        $transaction->allow_commit();
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
        $params = ['entityid' => $entityid, 'system' => self::SYSTEM];
        // Once the platform has brought someone, only the types it brings: teachers it does not have are not waiting.
        $types = $DB->get_fieldset_sql(
            "SELECT DISTINCT r.readertype
               FROM {" . self::T . "reader} r
               JOIN {" . self::T . "source} s ON s.localid = r.id AND s.entityid = r.entityid
              WHERE r.entityid = :entityid AND s.system = :system AND s.tablename = 'reader'",
            $params
        );
        $only = '';
        if ($types) {
            [$in, $typeparams] = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
            $only = " AND r.readertype $in";
            $params += $typeparams;
        }
        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::T . "reader} r
              WHERE r.entityid = :entityid AND r.status = 'active'$only
                AND NOT EXISTS (SELECT 1 FROM {" . self::T . "source} s WHERE s.entityid = r.entityid
                                AND s.system = :system AND s.tablename = 'reader' AND s.localid = r.id)",
            $params
        );
    }
}
