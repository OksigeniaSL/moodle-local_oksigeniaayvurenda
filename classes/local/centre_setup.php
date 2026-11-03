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

namespace local_oksigeniaayvurenda\local;

/**
 * Getting a centre ready from the console, as its screens would: its names, whether it is open to its readers yet,
 * its two characters and the cohorts its readers come from. For whoever runs a site and prepares a centre before its
 * staff sit down at it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class centre_setup {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The kinds of reader a cohort can bring. */
    public const TYPES = ['student', 'teacher', 'staff', 'family'];

    /**
     * Changes what is asked and leaves the rest as it is.
     *
     * @param int $entityid
     * @param array $changes Any of: name, library (the central library's name), closed (bool), memory, herald (names),
     *     heraldsex (f or m), heraldpack (path of a zip with the herald's own drawings), cohorts (as cohorts() reads it),
     *     copyfrom (the number new copy codes start at; 0 for after the highest there is).
     * @return string[] What changed, in words for the console.
     */
    public static function apply(int $entityid, array $changes): array {
        global $DB;
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], '*', MUST_EXIST);
        $done = [];
        if (isset($changes['name']) && trim($changes['name']) !== '') {
            $DB->update_record(self::T . 'entity', (object) ['id' => $entity->id, 'name' => trim($changes['name']),
                'timemodified' => time()]);
            $done[] = 'centre name: ' . trim($changes['name']);
        }
        if (isset($changes['library']) && trim($changes['library']) !== '') {
            $central = self::central($entityid);
            $DB->update_record(self::T . 'library', (object) ['id' => $central->id, 'name' => trim($changes['library']),
                'timemodified' => time()]);
            $done[] = 'library name: ' . trim($changes['library']);
        }
        if (isset($changes['closed'])) {
            access::set_closed($entityid, (bool) $changes['closed']);
            $done[] = $changes['closed'] ? 'open only to its staff' : 'open to its readers';
        }
        $names = array_intersect_key($changes, array_flip(['memory', 'herald', 'heraldsex']));
        if ($names) {
            self::characters($entityid, $names);
            $done[] = 'characters: ' . json_encode($names, JSON_UNESCAPED_UNICODE);
        }
        if (!empty($changes['heraldpack'])) {
            $kept = characters::store_pack($entityid, 'herald', $changes['heraldpack']);
            if ($kept === null) {
                throw new \moodle_exception('error', 'error', '', null, 'The zip cannot be opened: ' . $changes['heraldpack']);
            }
            $done[] = 'herald drawings: ' . $kept . ' files';
        }
        if (isset($changes['copyfrom'])) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $from = cataloguer::first_code((string) $changes['copyfrom']);
            if ($from === null) {
                unset($settings['copyfrom']);
            } else {
                $settings['copyfrom'] = $from;
            }
            $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
            $done[] = 'new copy codes: ' . ($from === null ? 'after the highest there is' : 'from ' . $from);
        }
        if (isset($changes['cohorts'])) {
            $cohorts = self::cohorts($entityid, (string) $changes['cohorts']);
            sources\cohorts::save($entityid, $cohorts);
            $done[] = 'cohorts: ' . count($cohorts);
        }
        access::forget();
        return $done;
    }

    /**
     * Reads the cohorts a centre takes its readers from: «INF=student, PRI=student:Primaria, 1A=student:Primaria:group,
     * ESO *=student::group, CLAUSTRO=teacher». Each cohort by its id number (or its exact name), or every cohort whose id
     * number or name starts so when it ends in *; the kind of reader, the stage (guessed from each cohort's name when
     * left out) and «group» when the cohort's name is the readers' class.
     *
     * @param int $entityid
     * @param string $list
     * @return array cohortid => type, stage, group; as cohorts::save() takes it.
     */
    public static function cohorts(int $entityid, string $list): array {
        global $DB;
        $chosen = [];
        foreach (array_filter(array_map('trim', explode(',', $list))) as $entry) {
            [$key, $how] = array_pad(array_map('trim', explode('=', $entry, 2)), 2, 'student');
            [$type, $stage, $group] = array_pad(array_map('trim', explode(':', $how)), 3, '');
            $type = $type === '' ? 'student' : $type;
            if (!in_array($type, self::TYPES, true)) {
                throw new \moodle_exception('error', 'error', '', null, "Unknown kind of reader for $key: $type");
            }
            if (str_ends_with($key, '*')) {
                $start = $DB->sql_like_escape(substr($key, 0, -1)) . '%';
                $found = $DB->get_records_select(
                    'cohort',
                    $DB->sql_like('idnumber', ':a', false) . ' OR ' . $DB->sql_like('name', ':b', false),
                    ['a' => $start, 'b' => $start],
                    'name',
                    'id, name'
                );
            } else {
                $cohort = $DB->get_record('cohort', ['idnumber' => $key], 'id, name', IGNORE_MULTIPLE)
                    ?: $DB->get_record('cohort', ['name' => $key], 'id, name', IGNORE_MULTIPLE);
                $found = $cohort ? [$cohort] : [];
            }
            if (!$found) {
                throw new \moodle_exception('error', 'error', '', null, "There is no cohort $key");
            }
            foreach ($found as $cohort) {
                $chosen[(int) $cohort->id] = [
                    'type' => $type,
                    'stage' => $stage !== '' ? $stage
                        : \local_oksigeniaayvurenda\core\readers\stage_guesser::guess($cohort->name),
                    'group' => $group === 'group',
                ];
            }
        }
        return $chosen;
    }

    /**
     * What a centre is like now, for the console.
     *
     * @param int $entityid
     * @return array
     */
    public static function describe(int $entityid): array {
        global $DB;
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], '*', MUST_EXIST);
        $characters = characters::of($entityid);
        $cohorts = array_values(array_filter(sources\cohorts::options($entityid), fn($c) => $c['chosen']));
        return [
            'centre' => (int) $entity->id . ' ' . $entity->name,
            'open' => access::closed($entityid) ? 'only to its staff' : 'to its readers',
            'year starts' => (string) $entity->yearstart,
            'libraries' => array_map(
                fn($l) => $l->id . ' ' . $l->name . ' (' . $l->kind . ')',
                array_values($DB->get_records(self::T . 'library', ['entityid' => $entityid], 'id'))
            ),
            'characters' => array_map(
                fn($c) => $c['name'] . ' (' . $c['sex'] . ($c['own'] ? ', own drawings' : '') . ')',
                $characters
            ),
            'cohorts' => array_map(fn($c) => $c['name'] . ': ' . $c['members'] . ' ' . $c['type'] . ', ' . $c['stage'] .
                ($c['group'] ? ', its name is the group' : ''), $cohorts),
            'readers' => $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']),
            'copies' => $DB->count_records(self::T . 'item', ['entityid' => $entityid]),
            'new codes' => ($from = cataloguer::below_first_code($entityid))
                ? 'from ' . str_pad((string) ((int) $from[0] + 1), strlen($from[0]), '0', STR_PAD_LEFT)
                : 'after the highest there is',
        ];
    }

    /**
     * The centre's own library, the one the others hang from.
     *
     * @param int $entityid
     * @return \stdClass
     */
    private static function central(int $entityid): \stdClass {
        global $DB;
        $libraries = $DB->get_records(self::T . 'library', ['entityid' => $entityid, 'parentid' => null], 'id');
        foreach ($libraries as $library) {
            if ($library->kind === 'central') {
                return $library;
            }
        }
        if (!$libraries) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        return reset($libraries);
    }

    /**
     * Names the two characters, and says whether the herald is a woman or a man, keeping the rest as it was.
     *
     * @param int $entityid
     * @param array $names memory, herald, heraldsex.
     */
    private static function characters(int $entityid, array $names): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $chosen = [];
        foreach (characters::ROLES as $role) {
            $mine = $settings['characters'][$role] ?? [];
            $chosen[$role] = [
                'enabled' => (bool) ($mine['enabled'] ?? true),
                'name' => (string) ($names[$role] ?? ($mine['name'] ?? '')),
                'sex' => $role === 'herald' && isset($names['heraldsex']) ? $names['heraldsex'] : ($mine['sex'] ?? ''),
                'signs' => !empty($mine['signs']),
            ];
        }
        characters::save($entityid, $chosen);
    }
}
