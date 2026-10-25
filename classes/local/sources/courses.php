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
 * Readers from Moodle courses.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\sources;

/**
 * For a centre whose classes are Moodle courses with groups instead of cohorts: the students of each chosen course are
 * readers, with their group of the course (or the course itself when it has none); its teachers are readers as
 * teachers. They are brought with the cohorts, by the same nightly task, and the first source that has a person wins.
 */
final class courses {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The courses of the site, with how many groups each has and how the entity chose them.
     *
     * @param int $entityid
     * @return array[] id, name, groups, chosen, stage, group.
     */
    public static function options(int $entityid): array {
        global $DB;
        $chosen = self::chosen($entityid);
        // A centre with its own category, only the courses in it.
        $categories = \local_oksigeniaayvurenda\local\access::categories($entityid);
        [$in, $params] = $categories === null ? ['IS NOT NULL', []] : $DB->get_in_or_equal($categories ?: [0], SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, (SELECT COUNT(1) FROM {groups} g WHERE g.courseid = c.id) AS groups
               FROM {course} c
              WHERE c.id <> :site AND c.category $in
           ORDER BY c.fullname, c.id",
            $params + ['site' => SITEID]
        );
        $options = [];
        foreach ($rows as $row) {
            $config = $chosen[$row->id] ?? null;
            $options[] = [
                'id' => (int) $row->id,
                'name' => format_string($row->fullname),
                'groups' => (int) $row->groups,
                'chosen' => $config !== null,
                'stage' => $config['stage'] ?? \local_oksigeniaayvurenda\core\readers\stage_guesser::guess($row->fullname),
                'group' => (bool) ($config['group'] ?? true),
            ];
        }
        return $options;
    }

    /**
     * The courses the entity chose.
     *
     * @param int $entityid
     * @return array Course id => stage, group.
     */
    public static function chosen(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $chosen = $settings['courses'] ?? [];
        // A centre with its own category, only the courses in it.
        $categories = \local_oksigeniaayvurenda\local\access::categories($entityid);
        if ($categories !== null && $chosen) {
            [$in, $params] = $DB->get_in_or_equal(array_map('intval', array_keys($chosen)), SQL_PARAMS_NAMED);
            [$incat, $catparams] = $DB->get_in_or_equal($categories ?: [0], SQL_PARAMS_NAMED, 'cat');
            $inside = $DB->get_fieldset_select('course', 'id', "id $in AND category $incat", $params + $catparams);
            $chosen = array_intersect_key($chosen, array_flip($inside));
        }
        return $chosen;
    }

    /**
     * Saves which courses bring readers.
     *
     * @param int $entityid
     * @param array $courses Course id => stage, group; only the chosen ones.
     */
    public static function save(int $entityid, array $courses): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $clean = [];
        $allowed = array_flip(array_column(self::options($entityid), 'id'));
        foreach ($courses as $courseid => $config) {
            if (!isset($allowed[(int) $courseid])) {
                continue;
            }
            $clean[(int) $courseid] = [
                'stage' => \core_text::substr(trim((string) ($config['stage'] ?? '')), 0, 100),
                'group' => !empty($config['group']),
            ];
        }
        $settings['courses'] = $clean;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * The people of the chosen courses: students with their group, teachers as teachers.
     *
     * @param int $entityid
     * @return array User id => type, group, stage.
     */
    public static function people(int $entityid): array {
        global $DB;
        $chosen = self::chosen($entityid);
        $people = [];
        foreach ($chosen as $courseid => $config) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
            if (!$course) {
                continue;
            }
            $context = \context_course::instance($course->id);
            $rows = $DB->get_recordset_sql(
                "SELECT ra.id, ra.userid, r.archetype,
                        (SELECT MIN(g.name)
                           FROM {groups_members} gm
                           JOIN {groups} g ON g.id = gm.groupid
                          WHERE gm.userid = ra.userid AND g.courseid = :courseid) AS groupname
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN {user} u ON u.id = ra.userid
                  WHERE ra.contextid = :contextid AND u.deleted = 0 AND u.suspended = 0
                        AND r.archetype IN ('student', 'teacher', 'editingteacher')
               ORDER BY ra.userid, ra.id",
                ['courseid' => $course->id, 'contextid' => $context->id]
            );
            foreach ($rows as $row) {
                if (isset($people[$row->userid])) {
                    continue;
                }
                $student = $row->archetype === 'student';
                $group = '';
                if ($student && $config['group']) {
                    $group = $row->groupname !== null ? format_string($row->groupname) : format_string($course->fullname);
                }
                $people[(int) $row->userid] = ['cohortid' => 0, 'type' => $student ? 'student' : 'teacher',
                    'group' => $group, 'stage' => $student ? $config['stage'] : ''];
            }
            $rows->close();
        }
        return $people;
    }
}
