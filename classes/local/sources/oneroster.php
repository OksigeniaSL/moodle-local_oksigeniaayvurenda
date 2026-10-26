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

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\oneroster as roster;
use local_oksigeniaayvurenda\local\tutors;
use local_oksigeniaayvurenda\local\years;

/**
 * Readers from a OneRoster export, the zip that school management programs give: who is there with their group,
 * who left (in a whole export, whoever came from it before and is not there now), and the tutor of each group when
 * its teacher has an account.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class oneroster {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The files of the export that matter here. */
    private const FILES = ['manifest.csv', 'users.csv', 'roles.csv', 'orgs.csv', 'classes.csv', 'enrollments.csv'];

    /**
     * The files of an export, by name, from its zip (in a folder or not).
     *
     * @param string $path
     * @return array<string, string>
     * @throws \moodle_exception When it is not a zip with users.csv.
     */
    public static function files(string $path): array {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \moodle_exception('onerosternozip', 'local_oksigeniaayvurenda');
        }
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = basename((string) $zip->getNameIndex($i));
            if (in_array($name, self::FILES, true) && !isset($files[$name])) {
                $files[$name] = (string) $zip->getFromIndex($i);
            }
        }
        $zip->close();
        if (!isset($files['users.csv'])) {
            throw new \moodle_exception('onerosternozip', 'local_oksigeniaayvurenda');
        }
        return $files;
    }

    /**
     * Brings the readers of an export.
     *
     * @param int $entityid
     * @param array $found What the core reader gave (readers, gone, tutors, mode).
     * @param int|null $now
     * @param bool $partly Past the centre's plan, leave out who does not fit; when false, the caller refuses it whole.
     * @return array created, updated, linked, total, overlimit, left, tutors.
     */
    public static function load(int $entityid, array $found, ?int $now = null, bool $partly = true): array {
        global $DB;
        $now = $now ?? time();
        $stats = csvlist::load($entityid, $found['readers'], 'student', $now, 'oneroster', $partly);
        $here = array_flip($stats['ids']);
        // Who left: those the export says, and, in a whole export, those who came from it and are not in it.
        $sources = $DB->get_records_menu(self::T . 'source', ['entityid' => $entityid, 'system' => 'oneroster',
            'tablename' => 'reader'], '', 'externalid, localid');
        $leaving = [];
        foreach ($sources as $key => $readerid) {
            if (in_array($key, $found['gone'], true) || (($found['mode'] ?? 'bulk') === 'bulk' && !isset($here[$readerid]))) {
                $leaving[(int) $readerid] = true;
            }
        }
        $left = 0;
        foreach (array_keys($leaving) as $readerid) {
            $left += $DB->count_records(self::T . 'reader', ['id' => $readerid, 'status' => 'active']);
            $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $readerid, 'status' => 'active']);
        }
        // The tutor of each group, when its teacher has an account and the group has no tutor yet.
        $year = years::around($entityid, $now);
        $tutors = 0;
        foreach ($found['tutors'] ?? [] as $group => $teacher) {
            $userid = (int) $DB->get_field_sql(
                'SELECT r.userid
                   FROM {' . self::T . 'source} s
                   JOIN {' . self::T . 'reader} r ON r.id = s.localid
                  WHERE s.entityid = :entityid AND s.system = :system AND s.tablename = :tablename AND s.externalid = :key',
                ['entityid' => $entityid, 'system' => 'oneroster', 'tablename' => 'reader', 'key' => 'id:' . $teacher]
            );
            if ($userid && !$DB->record_exists(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => $group])) {
                tutors::set((int) $year->id, $group, $userid);
                $tutors++;
            }
        }
        unset($stats['ids']);
        return $stats + ['left' => $left, 'tutors' => $tutors];
    }
}
