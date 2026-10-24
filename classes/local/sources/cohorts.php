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
        $rows = $DB->get_records_sql(
            'SELECT c.id, c.name, c.idnumber, COUNT(m.id) AS members
               FROM {cohort} c
          LEFT JOIN {cohort_members} m ON m.cohortid = c.id
           GROUP BY c.id, c.name, c.idnumber
           ORDER BY c.name, c.id'
        );
        $options = [];
        foreach ($rows as $row) {
            $config = $chosen[$row->id] ?? null;
            $options[] = [
                'id' => (int) $row->id,
                'name' => format_string($row->name),
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
        foreach ($cohorts as $cohortid => $config) {
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
    }

    /**
     * Brings the readers of the chosen cohorts.
     *
     * @param int $entityid
     * @param int|null $now
     * @return array{created: int, updated: int, left: int, back: int, total: int}
     */
    public static function sync(int $entityid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $stats = ['created' => 0, 'updated' => 0, 'left' => 0, 'back' => 0, 'total' => 0];
        $settings = self::settings($entityid);
        $chosen = $settings['cohorts'] ?? [];
        if (!$chosen) {
            return $stats;
        }
        // Who is in the chosen cohorts: the first cohort that has a user says its kind and its group.
        [$in, $params] = $DB->get_in_or_equal(array_keys($chosen), SQL_PARAMS_NAMED);
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
                'group' => $config['group'] ? format_string($row->name) : '',
                'stage' => $config['stage'],
            ];
        }
        $rows->close();

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
            $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]),
            count($newusers),
            !empty($settings['controlletter'])
        );
        foreach ($people as $userid => $person) {
            $reader = $byuser[$userid] ?? null;
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
                self::member($readerid, (int) $year->id, $person['group'], $person['stage'], $now);
            }
            $stats['total']++;
        }
        foreach ($byuser as $userid => $reader) {
            if (!isset($people[$userid]) && $reader->status === 'active') {
                $DB->update_record(self::T . 'reader', (object) ['id' => $reader->id, 'status' => 'left', 'timemodified' => $now]);
                $stats['left']++;
            }
        }
        $transaction->allow_commit();
        return $stats;
    }

    /**
     * The reader's group this school year: one row per year, changed if the reader moved group within it.
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
}
