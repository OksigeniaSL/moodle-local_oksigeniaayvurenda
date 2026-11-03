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
 * Readers from Moodle cohorts.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\card_codes;
use local_oksigeniaayvurenda\local\plans;

/**
 * Brings the readers of an entity from the Moodle cohorts it chose: each chosen cohort says what kind of reader its
 * members are and, for groups of students, the stage. The library keeps, for each school year, the group every reader
 * was in, because Moodle only knows the cohort of today. A reader who leaves every cohort is not deleted: it stays,
 * as having left, with its history.
 *
 * Settings of the entity (JSON): cohorts => [cohortid => [type, stage, group (whether its name is the reader's group)]].
 */
final class cohorts {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The cohorts of the site, with the ones the entity chose and how.
     *
     * @param int $entityid
     * @return array[] id, name, members, chosen, type, stage and group.
     */
    public static function options(int $entityid): array {
        global $DB;
        $settings = self::settings($entityid);
        $chosen = $settings['cohorts'] ?? [];
        [$where, $params] = self::where($entityid);
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.name, c.idnumber, COUNT(m.id) AS members
               FROM {cohort} c
          LEFT JOIN {cohort_members} m ON m.cohortid = c.id
              WHERE $where
           GROUP BY c.id, c.name, c.idnumber
           ORDER BY c.name, c.id",
            $params
        );
        $options = [];
        foreach ($rows as $row) {
            $config = $chosen[$row->id] ?? null;
            $options[] = [
                'id' => (int) $row->id,
                'name' => format_string($row->name, true, ['escape' => false]),
                'members' => (int) $row->members,
                'chosen' => $config !== null,
                'type' => $config['type'] ?? 'student',
                'stage' => $config['stage'] ?? \local_oksigeniaayvurenda\core\readers\stage_guesser::guess($row->name),
                'group' => (bool) ($config['group'] ?? true),
            ];
        }
        return $options;
    }

    /**
     * Saves which cohorts bring readers, and how.
     *
     * @param int $entityid
     * @param array $cohorts cohortid => [type, stage, group]; only the chosen ones.
     */
    public static function save(int $entityid, array $cohorts): void {
        global $DB;
        $clean = [];
        foreach (self::only_allowed($entityid, $cohorts) as $cohortid => $config) {
            $type = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($config['type'] ?? 'student'))) ?: 'student';
            $clean[(int) $cohortid] = [
                'type' => substr($type, 0, 30),
                'stage' => \core_text::substr(trim((string) ($config['stage'] ?? '')), 0, 100),
                'group' => !empty($config['group']),
            ];
        }
        $settings = self::settings($entityid);
        $settings['cohorts'] = $clean;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
        $DB->set_field(self::T . 'entity', 'timemodified', time(), ['id' => $entityid]);
        \local_oksigeniaayvurenda\event\connector_changed::fire($entityid, null, ['kind' => 'cohorts']);
    }

    /**
     * Brings the readers of the chosen cohorts.
     *
     * @param int $entityid
     * @param int|null $now
     * @param bool $byhand A person is looking: half the readers may leave.
     * @return array{created: int, updated: int, left: int, back: int, total: int, overlimit: int}
     */
    public static function sync(int $entityid, ?int $now = null, bool $byhand = false): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['created' => 0, 'updated' => 0, 'left' => 0, 'back' => 0, 'total' => 0, 'overlimit' => 0];
        $settings = self::settings($entityid);
        $chosen = self::only_allowed($entityid, $settings['cohorts'] ?? []);
        if (!$chosen && !courses::chosen($entityid)) {
            return $stats;
        }
        // Who is in the chosen cohorts: the first cohort that has a user says its kind and its group.
        [$in, $params] = $DB->get_in_or_equal(array_keys($chosen) ?: [0], SQL_PARAMS_NAMED);
        $rows = $DB->get_recordset_sql(
            "SELECT m.id, m.userid, m.cohortid, c.name
               FROM {cohort_members} m
               JOIN {cohort} c ON c.id = m.cohortid
               JOIN {user} u ON u.id = m.userid
              WHERE m.cohortid $in AND u.deleted = 0
           ORDER BY m.cohortid, m.userid",
            $params
        );
        $people = [];
        $order = array_flip(array_keys($chosen));
        foreach ($rows as $row) {
            $config = $chosen[$row->cohortid];
            $current = $people[$row->userid] ?? null;
            if ($current !== null && $order[$current['cohortid']] <= $order[$row->cohortid]) {
                continue;
            }
            $people[$row->userid] = [
                'cohortid' => (int) $row->cohortid,
                'type' => $config['type'],
                'group' => $config['group'] ? format_string($row->name, true, ['escape' => false]) : '',
                'stage' => $config['stage'],
            ];
        }
        $rows->close();
        // Then the courses: whoever is in a cohort keeps the kind it gave; when that cohort only says the stage (a cohort
        // for all of a stage, not a class), the course's group says the class.
        foreach (courses::people($entityid) as $userid => $person) {
            if (!isset($people[$userid])) {
                $people[$userid] = $person;
                continue;
            }
            if ($people[$userid]['group'] === '' && $person['group'] !== '') {
                $people[$userid]['group'] = $person['group'];
            }
            if ($people[$userid]['stage'] === '' && $person['stage'] !== '') {
                $people[$userid]['stage'] = $person['stage'];
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $year = \local_oksigeniaayvurenda\local\years::around($entityid, $now);
        $readers = $DB->get_records_select(
            self::T . 'reader',
            'entityid = :entityid AND userid IS NOT NULL',
            ['entityid' => $entityid],
            '',
            'id, userid, readertype, status'
        );
        $byuser = [];
        foreach ($readers as $reader) {
            $byuser[$reader->userid] = $reader;
        }
        $newusers = array_diff(array_keys($people), array_keys($byuser));
        $codes = card_codes::next(
            array_merge(
                $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]),
                \local_oksigeniaayvurenda\local\cataloguer::below_first_code($entityid, 'card')
            ),
            count($newusers),
            !empty($settings['controlletter'])
        );
        // Past the centre's plan, who does not fit is left out until there is room.
        $room = plans::left($entityid, 'readers');
        // All at once: a question per reader, every night, adds up.
        $members = self::members($entityid, (int) $year->id);
        foreach ($people as $userid => $person) {
            $reader = $byuser[$userid] ?? null;
            if ($room !== null && ($reader === null || $reader->status !== 'active')) {
                if ($room < 1) {
                    $stats['overlimit']++;
                    continue;
                }
                $room--;
            }
            if ($reader === null) {
                $readerid = $DB->insert_record(self::T . 'reader', [
                    'entityid' => $entityid,
                    'userid' => $userid,
                    'cardcode' => array_shift($codes),
                    'readertype' => $person['type'],
                    'status' => 'active',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $stats['created']++;
            } else {
                $readerid = (int) $reader->id;
                if ($reader->readertype !== $person['type'] || $reader->status !== 'active') {
                    if ($reader->status === 'left') {
                        $stats['back']++;
                    } else {
                        $stats['updated']++;
                    }
                    $DB->update_record(self::T . 'reader', (object) ['id' => $readerid, 'readertype' => $person['type'],
                        'status' => 'active', 'timemodified' => $now]);
                }
            }
            if ($person['group'] !== '') {
                self::member($readerid, (int) $year->id, $person['group'], $person['stage'], $now, $members[$readerid] ?? null);
            }
            $stats['total']++;
        }
        // Readers who came from a list, a management program or Abies leave when their origin says so, not here.
        $elsewhere = array_flip($DB->get_fieldset_select(
            self::T . 'source',
            'localid',
            "entityid = :entityid AND tablename = 'reader'",
            ['entityid' => $entityid]
        ));
        $ours = array_filter($byuser, fn($reader) => $reader->status === 'active' && !isset($elsewhere[$reader->id]));
        $leaving = array_diff_key($ours, $people);
        if (leavers::allow($entityid, 'moodle', count($ours), count($leaving), $byhand, self::some_gone($settings), $now)) {
            foreach ($leaving as $reader) {
                $DB->update_record(self::T . 'reader', (object) ['id' => $reader->id, 'status' => 'left', 'timemodified' => $now]);
                $stats['left']++;
            }
        } else {
            $stats['held'] = count($leaving);
        }
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\readers_synced::tell($entityid, 'moodle', $stats);
        return $stats;
    }

    /**
     * Whether a chosen cohort no longer exists: it says nothing about who was in it.
     *
     * @param array $settings
     * @return bool
     */
    private static function some_gone(array $settings): bool {
        global $DB;
        $ids = array_map('intval', array_keys($settings['cohorts'] ?? []));
        if (!$ids) {
            return false;
        }
        [$in, $params] = $DB->get_in_or_equal($ids);
        return $DB->count_records_select('cohort', "id $in", $params) < count($ids);
    }

    /**
     * The groups of a centre's readers this school year.
     *
     * @param int $entityid
     * @param int $yearid
     * @return array Reader => their row.
     */
    private static function members(int $entityid, int $yearid): array {
        global $DB;
        $members = [];
        $rows = $DB->get_recordset_sql(
            'SELECT m.id, m.readerid, m.groupname, m.stage
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'reader} r ON r.id = m.readerid
              WHERE m.yearid = :yearid AND r.entityid = :entityid',
            ['yearid' => $yearid, 'entityid' => $entityid]
        );
        foreach ($rows as $row) {
            $members[(int) $row->readerid] = $row;
        }
        $rows->close();
        return $members;
    }

    /**
     * The reader's group this school year: one row per year, changed if the reader moved group within it.
     *
     * @param int $readerid
     * @param int $yearid
     * @param string $group
     * @param string $stage
     * @param int $now
     * @param \stdClass|null $existing What the reader has this year, if anything.
     */
    private static function member(int $readerid, int $yearid, string $group, string $stage, int $now, ?\stdClass $existing): void {
        global $DB;
        if (!$existing) {
            $DB->insert_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $yearid, 'groupname' => $group,
                'stage' => $stage !== '' ? $stage : null, 'source' => 'cohort', 'timecreated' => $now]);
        } else if ($existing->groupname !== $group || (string) $existing->stage !== $stage) {
            $DB->update_record(self::T . 'member', (object) ['id' => $existing->id, 'groupname' => $group,
                'stage' => $stage !== '' ? $stage : null, 'source' => 'cohort']);
        }
    }

    /**
     * The settings of an entity.
     *
     * @param int $entityid
     * @return array
     */
    private static function settings(int $entityid): array {
        global $DB;
        return json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
    }

    /**
     * Which cohorts a centre may bring readers from: with its own category, the cohorts of that category and those
     * inside it; else all of the site's.
     *
     * @param int $entityid
     * @return array SQL condition on {cohort} c, and its parameters.
     */
    private static function where(int $entityid): array {
        global $DB;
        $categories = \local_oksigeniaayvurenda\local\access::categories($entityid);
        if ($categories === null) {
            return ['1 = 1', []];
        }
        $contexts = array_map(fn($id) => (int) \context_coursecat::instance($id)->id, $categories);
        [$in, $params] = $DB->get_in_or_equal($contexts ?: [0], SQL_PARAMS_NAMED, 'ctx');
        return ["c.contextid $in", $params];
    }

    /**
     * Keeps only the cohorts a centre may bring readers from.
     *
     * @param int $entityid
     * @param array $cohorts Cohort id => anything.
     * @return array
     */
    private static function only_allowed(int $entityid, array $cohorts): array {
        global $DB;
        if (!$cohorts || \local_oksigeniaayvurenda\local\access::categories($entityid) === null) {
            return $cohorts;
        }
        [$where, $params] = self::where($entityid);
        [$in, $idparams] = $DB->get_in_or_equal(array_map('intval', array_keys($cohorts)), SQL_PARAMS_NAMED, 'id');
        $ids = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE c.id $in AND $where", $idparams + $params);
        return array_intersect_key($cohorts, array_flip($ids));
    }
}
