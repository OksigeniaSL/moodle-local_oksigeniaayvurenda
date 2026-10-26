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
 * The people of a OneRoster export, as readers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

/**
 * OneRoster (1EdTech), the CSV files that school management programs export to share their rosters: users, their
 * roles, schools (orgs), classes and who is in each (enrollments), in a zip with a manifest. Version 1.2 keeps each
 * user's role in roles.csv; 1.1 kept it in users.csv: both are read, by the names of the columns. The group of a
 * student is their homeroom class; the teacher of a homeroom is its tutor. This turns the files into readers as the
 * lists of the library take them. Plain PHP, without Moodle.
 */
final class oneroster {
    /** @var array<string, string> OneRoster roles, as kinds of reader. */
    private const KINDS = ['student' => 'student', 'teacher' => 'teacher', 'aide' => 'teacher', 'proctor' => 'teacher',
        'administrator' => 'staff', 'principal' => 'staff', 'siteAdministrator' => 'staff', 'counselor' => 'staff',
        'districtAdministrator' => 'staff', 'systemAdministrator' => 'staff', 'parent' => 'family',
        'guardian' => 'family', 'relative' => 'family'];

    /**
     * The readers of an export.
     *
     * @param array $files File name (users.csv…) => its text.
     * @param string $school The sourcedId of the school to take, or empty for every one.
     * @param bool $families Whether parents, guardians and relatives come as readers.
     * @param int $now
     * @return array readers (as the lists take them, with externalid), gone (keys of who is to leave), tutors (group =>
     *     externalid of its teacher), schools (sourcedId => name), mode (bulk or delta), version.
     * @throws \InvalidArgumentException When users.csv is not there.
     */
    public static function readers(array $files, string $school, bool $families, int $now): array {
        if (!isset($files['users.csv'])) {
            throw new \InvalidArgumentException('users.csv');
        }
        $manifest = [];
        foreach (self::table($files['manifest.csv'] ?? '') as $row) {
            $manifest[$row['propertyName'] ?? ''] = $row['value'] ?? '';
        }
        $schools = [];
        foreach (self::table($files['orgs.csv'] ?? '') as $row) {
            if (in_array($row['type'] ?? '', ['school', ''], true) && ($row['sourcedId'] ?? '') !== '') {
                $schools[$row['sourcedId']] = trim($row['name'] ?? '') ?: $row['sourcedId'];
            }
        }
        // What each user is, and where: roles.csv (1.2) or the columns of users.csv (1.1).
        $users = self::by_id(self::table($files['users.csv']));
        $roles = [];
        foreach (self::table($files['roles.csv'] ?? '') as $row) {
            if (self::alive($row, $now) && ($row['userSourcedId'] ?? '') !== '') {
                $roles[$row['userSourcedId']][] = ['role' => $row['role'] ?? '', 'org' => $row['orgSourcedId'] ?? '',
                    'primary' => ($row['roleType'] ?? '') === 'primary'];
            }
        }
        foreach ($users as $id => $user) {
            if (!isset($roles[$id]) && ($user['role'] ?? '') !== '') {
                foreach (array_filter(array_map('trim', explode(',', $user['orgSourcedIds'] ?? ''))) ?: [''] as $org) {
                    $roles[$id][] = ['role' => $user['role'], 'org' => $org, 'primary' => true];
                }
            }
        }
        // The classes, and who is in each.
        $classes = self::by_id(self::table($files['classes.csv'] ?? ''));
        $homerooms = [];
        $taught = [];
        foreach (self::table($files['enrollments.csv'] ?? '') as $row) {
            $class = $classes[$row['classSourcedId'] ?? ''] ?? null;
            if (!$class || !self::alive($row, $now) || ($school !== '' && ($class['schoolSourcedId'] ?? '') !== $school)) {
                continue;
            }
            $title = trim($class['title'] ?? '');
            $user = $row['userSourcedId'] ?? '';
            if (($class['classType'] ?? '') === 'homeroom' && $title !== '') {
                if (($row['role'] ?? '') === 'student') {
                    $homerooms[$user] = $title;
                } else if (($row['role'] ?? '') === 'teacher' && (!isset($taught[$title]) || ($row['primary'] ?? '') === 'true')) {
                    $taught[$title] = $user;
                }
            }
            $roles[$user] = $roles[$user] ?? [['role' => $row['role'] ?? '', 'org' => $class['schoolSourcedId'] ?? '',
                'primary' => true]];
        }
        $readers = [];
        $gone = [];
        foreach ($users as $id => $user) {
            $theirs = array_values(array_filter($roles[$id] ?? [], fn($r) => $school === '' || $r['org'] === $school
                || $r['org'] === '' || ($user['primaryOrgSourcedId'] ?? '') === $school));
            usort($theirs, fn($a, $b) => (int) $b['primary'] - (int) $a['primary']);
            $kind = self::KINDS[$theirs[0]['role'] ?? ''] ?? null;
            if ($kind === null || ($kind === 'family' && !$families)) {
                continue;
            }
            if (($user['status'] ?? '') === 'tobedeleted' || strtolower($user['enabledUser'] ?? 'true') === 'false') {
                $gone[] = 'id:oneroster:' . $id;
                continue;
            }
            $group = $kind === 'student' ? ($homerooms[$id] ?? '') : '';
            $readers[] = array_filter([
                'externalid' => 'oneroster:' . $id,
                'firstname' => trim(($user['preferredGivenName'] ?? '') !== '' ? $user['preferredGivenName']
                    : ($user['givenName'] ?? '')),
                'lastname' => trim(($user['preferredFamilyName'] ?? '') !== '' ? $user['preferredFamilyName']
                    : ($user['familyName'] ?? '')),
                'email' => trim($user['email'] ?? ''),
                'idnumber' => trim($user['identifier'] ?? ''),
                'username' => trim($user['username'] ?? ''),
                'group' => $group,
                'stage' => $group !== '' ? stage_guesser::guess($group) : '',
                'type' => $kind,
            ], fn($v) => $v !== '');
        }
        $tutors = [];
        foreach ($taught as $title => $teacher) {
            if (isset($users[$teacher])) {
                $tutors[$title] = 'oneroster:' . $teacher;
            }
        }
        return ['readers' => $readers, 'gone' => $gone, 'tutors' => $tutors, 'schools' => $schools,
            'mode' => $manifest['file.users'] ?? 'bulk', 'version' => $manifest['oneroster.version'] ?? ''];
    }

    /**
     * Whether a row counts now: not to be deleted and not ended.
     *
     * @param array $row
     * @param int $now
     * @return bool
     */
    private static function alive(array $row, int $now): bool {
        if (($row['status'] ?? '') === 'tobedeleted') {
            return false;
        }
        $end = trim($row['endDate'] ?? '');
        return $end === '' || strtotime($end . ' 23:59:59') === false || strtotime($end . ' 23:59:59') >= $now;
    }

    /**
     * The rows of a table by their sourcedId.
     *
     * @param array[] $rows
     * @return array<string, array>
     */
    private static function by_id(array $rows): array {
        $byid = [];
        foreach ($rows as $row) {
            if (($row['sourcedId'] ?? '') !== '') {
                $byid[$row['sourcedId']] = $row;
            }
        }
        return $byid;
    }

    /**
     * The rows of a CSV file of OneRoster (UTF-8, commas, a header row), each by the names of the columns.
     *
     * @param string $text
     * @return array[]
     */
    public static function table(string $text): array {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (trim($text) === '') {
            return [];
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);
        $header = array_map('trim', fgetcsv($stream, null, ',', '"', '') ?: []);
        $rows = [];
        while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if ($cells === [null]) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }
        fclose($stream);
        return $rows;
    }
}
