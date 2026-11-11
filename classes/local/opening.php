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
 * «To open your library»: the five steps a centre takes before its readers come in.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The catalogue, the readers, the loan rules, the team and opening: five steps, as few as the guides that are finished
 * (a list of seven is seldom ended), each marked from the centre's own data, never from having been seen, so closing it,
 * skipping it or doing it from the console all count the same. It shows while the centre is closed to its readers, on
 * the staff's home, at the top of «More» and at the desk, with who did each step and when, why one has to wait and who
 * does what this person may not. The rules and the team may also be done by saying that what there is will do («the
 * rules that come with it are fine», «for now, I run it»), and that is kept with who said it and when.
 */
final class opening {
    /** @var string[] The steps, in their order. */
    public const STEPS = ['catalogue', 'readers', 'rules', 'team', 'open'];

    /** @var array[] Of each step: the permission it needs, its page, its guided help, its colour and its height. */
    private const LOOK = [
        'catalogue' => ['catalogue', 'import.php', 'import', '#17574c', 'cream', 25],
        'readers' => ['manage', 'sources.php', 'readers', '#e2614a', 'ink', 22],
        'rules' => ['manage', 'rules.php', 'rules', '#f2b632', 'ink', 24],
        'team' => ['manage', '', 'keepers', '#7fb7a4', 'ink', 21],
        'open' => ['manage', 'open.php', '', '#22303f', 'cream', 26],
    ];

    /** @var string The preference that keeps the list folded, for whoever folded it. */
    public const FOLDED = 'local_oksigeniaayvurenda_openfolded';

    /**
     * Whether the list shows for a library and a person: while its centre is closed, to its staff.
     *
     * @param \stdClass $library
     * @param int|null $userid
     * @return bool
     */
    public static function shows(\stdClass $library, ?int $userid = null): bool {
        if (!access::closed((int) $library->entityid) || practice::is((int) $library->entityid)) {
            return false;
        }
        foreach (['desk', 'catalogue', 'manage'] as $what) {
            if (access::can('local/oksigeniaayvurenda:' . $what, $library, $userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The five steps of a centre: whether each is done, may be done now and by this person, with what it says.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return array[] key, done, blocked, can, meta (who and when, and how much), who (who does it instead).
     */
    public static function steps(\stdClass $library, int $userid): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
            ?: [];
        $libraries = array_map('intval', $DB->get_fieldset_select(
            'local_oksigeniaayvurenda_library',
            'id',
            'entityid = ?',
            [$entityid]
        ));
        $copies = $DB->count_records('local_oksigeniaayvurenda_item', ['entityid' => $entityid]);
        $readers = $DB->count_records_select(
            'local_oksigeniaayvurenda_reader',
            "entityid = ? AND status = 'active' AND readertype <> 'library'",
            [$entityid]
        );
        [$in, $params] = $libraries ? $DB->get_in_or_equal($libraries) : ['= ?', [0]];
        $rules = $DB->count_records_select('local_oksigeniaayvurenda_rule', 'libraryid ' . $in, $params);
        $team = self::team($entityid, $libraries, $userid);
        $said = fn(string $key) => !empty($settings['opening'][$key]) ? $settings['opening'][$key] : null;
        $found = [
            'catalogue' => [$copies > 0, $copies ? self::str('openmetacatalogue', format_float($copies, 0)) : '',
                ['catalogue_imported']],
            'readers' => [$readers > 0, $readers ? self::str('openmetareaders', format_float($readers, 0)) : '',
                ['readers_synced', 'reader_changed']],
            'rules' => [$rules > 0 || $said('rules'), $rules ? self::str('openmetarules', $rules)
                : ($said('rules') ? self::str('openmetarulesdefault') : ''), ['rules_changed', 'rules_kept']],
            'team' => [$team > 0 || $said('team'), $team ? self::str('openmetateam', $team)
                : ($said('team') ? self::str('openmetateamalone') : ''), ['keeper_added', 'team_alone']],
            'open' => [!access::closed($entityid), '', ['centre_opened']],
        ];
        $steps = [];
        foreach (self::STEPS as $key) {
            [$done, $meta, $events] = $found[$key];
            $done = (bool) $done;
            // Who did it and when, from Moodle's log: the centre keeps no one's id.
            $by = $done ? activity::last($entityid, $events) : null;
            if ($done && $by && !empty($by['userid'])) {
                $meta = trim($meta . ($meta !== '' ? ' · ' : '') . self::str('openmetaby', (object) [
                    'who' => fullname(\core_user::get_user($by['userid']) ?: (object) ['firstname' => '', 'lastname' => '']),
                    'when' => userdate($by['time'], get_string('strftimedate', 'langconfig')),
                ]));
            }
            $can = access::can('local/oksigeniaayvurenda:' . self::LOOK[$key][0], $library, $userid);
            $steps[$key] = ['key' => $key, 'done' => $done, 'can' => $can, 'meta' => $meta,
                'blocked' => $key === 'open' && !$done && !($found['catalogue'][0] && $found['readers'][0]),
                'who' => $can ? '' : self::keeper($library)];
        }
        return $steps;
    }

    /**
     * What the template open_steps takes, or null when the list does not show.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param string $id Its id in the page.
     * @param bool $big On the staff's home: the drawn shelf and the crier.
     * @param bool|null $folded Only the line; null for as the person left it.
     * @return array|null
     */
    public static function context(\stdClass $library, int $userid, string $id, bool $big, ?bool $folded = null): ?array {
        if (!self::shows($library, $userid)) {
            return null;
        }
        $steps = self::steps($library, $userid);
        $done = count(array_filter($steps, fn($step) => $step['done']));
        $now = null;
        foreach ($steps as $step) {
            if (!$step['done'] && !$step['blocked']) {
                $now = $step['key'];
                break;
            }
        }
        $now = $now ?? 'open';
        $rows = [];
        $n = 0;
        foreach ($steps as $key => $step) {
            [, $page, $piece, $bg, $ink, $cm] = self::LOOK[$key];
            $n++;
            $help = $piece !== '' && howto::playable($piece, $library) ? (new \moodle_url(
                '/local/oksigeniaayvurenda/help.php',
                ['library' => $library->slug, 'piece' => $piece]
            ))->out(false) : '';
            $rows[] = ['n' => $n, 'name' => self::str('openstep' . $key), 'short' => self::str('openshort' . $key),
                'what' => self::str('openwhat' . $key), 'url' => self::url($key, $page, $library), 'showurl' => $help,
                'bg' => $bg, 'ink' => $ink, 'cm' => $cm, 'done' => $step['done'], 'now' => $key === $now && !$step['done'],
                'todo' => !$step['done'] && $key !== $now, 'blocked' => $step['blocked'],
                'notyours' => !$step['done'] && !$step['can'], 'can' => $step['can'] && !$step['blocked'],
                'meta' => $step['meta'], 'reason' => $step['blocked'] ? self::str('openblocked') : '',
                'who' => $step['who'], 'confirm' => !$step['done'] && $step['can'] && in_array($key, ['rules', 'team'], true)
                    ? ['action' => (new \moodle_url('/local/oksigeniaayvurenda/open.php'))->out(false),
                    'sesskey' => sesskey(), 'library' => $library->slug, 'say' => $key,
                    'label' => self::str('opensay' . $key)] : null];
        }
        $folded = $folded ?? (bool) get_user_preferences(self::FOLDED, false, $userid);
        $entityid = (int) $library->entityid;
        $crier = $big && characters::whole($entityid) ? characters::figure('herald', 'lee', characters::step($entityid)) : null;
        return [
            'id' => $id, 'big' => $big, 'folded' => $folded,
            'eyebrow' => $big ? self::str('openeyebrow') : '',
            'title' => self::str('opentitle'),
            'lead' => self::str('openlead'),
            'count' => self::str('opencount', (object) ['done' => $done, 'total' => count($steps)]),
            'nowlabel' => self::str('opennow'),
            'nowname' => \core_text::strtolower(\core_text::substr(self::str('openstep' . $now), 0, 1))
                . \core_text::substr(self::str('openstep' . $now), 1),
            'nowurl' => self::url($now, self::LOOK[$now][1], $library),
            'dolabel' => self::str('opendo'), 'showlabel' => self::str('openshow'),
            'foldlabel' => self::str('openfold'), 'unfoldlabel' => self::str('openunfold'),
            'donelabel' => self::str('opendone'), 'nowmark' => self::str('opennowmark'),
            'steps' => $rows,
            'foldurl' => (new \moodle_url('/local/oksigeniaayvurenda/open.php'))->out(false),
            'sesskey' => sesskey(),
            'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', 'lee', characters::step($entityid))]
                : null,
        ];
    }

    /**
     * Keeps what someone said of a step: that the rules that come with it are fine, or that for now they run it alone.
     * The centre keeps only that it was said and when; who said it goes to Moodle's log, as everything done in it.
     *
     * @param int $entityid
     * @param string $key rules or team.
     */
    public static function say(int $entityid, string $key): void {
        global $DB;
        if (!in_array($key, ['rules', 'team'], true)) {
            return;
        }
        $settings = json_decode((string) $DB->get_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            ['id' => $entityid],
            MUST_EXIST
        ), true) ?: [];
        $settings['opening'][$key] = ['time' => time()];
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $entityid]);
        if ($key === 'rules') {
            \local_oksigeniaayvurenda\event\rules_kept::fire($entityid);
        } else {
            \local_oksigeniaayvurenda\event\team_alone::fire($entityid);
        }
    }

    /**
     * Who works in a centre besides whoever looks: the people given its staff's roles where it gives them, and those who
     * keep one of its libraries.
     *
     * @param int $entityid
     * @param int[] $libraries
     * @param int $userid
     * @return int
     */
    private static function team(int $entityid, array $libraries, int $userid): int {
        global $DB;
        $roles = array_filter([
            (int) $DB->get_field('role', 'id', ['shortname' => roles::LIBRARIAN]),
            (int) $DB->get_field('role', 'id', ['shortname' => roles::MANAGER]),
        ]);
        $people = [];
        if ($roles) {
            [$in, $params] = $DB->get_in_or_equal($roles);
            $people = $DB->get_fieldset_select(
                'role_assignments',
                'DISTINCT userid',
                'contextid = ? AND roleid ' . $in,
                array_merge([access::context($entityid)->id], $params)
            );
        }
        if ($libraries) {
            [$in, $params] = $DB->get_in_or_equal($libraries);
            $people = array_merge($people, $DB->get_fieldset_select(
                'local_oksigeniaayvurenda_keeper',
                'DISTINCT userid',
                'libraryid ' . $in,
                $params
            ));
        }
        return count(array_diff(array_unique(array_map('intval', $people)), [$userid]));
    }

    /**
     * Who runs a library, to name whoever does a step someone else may not: the first person given its manager's role,
     * or nobody named.
     *
     * @param \stdClass $library
     * @return string
     */
    private static function keeper(\stdClass $library): string {
        $users = get_users_by_capability(
            access::context((int) $library->entityid),
            'local/oksigeniaayvurenda:manage',
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
            'u.lastname, u.firstname',
            0,
            1
        );
        $user = reset($users);
        return $user ? self::str('opennotyours', fullname($user)) : self::str('opennotyoursanyone');
    }

    /**
     * Where «Do it» goes for a step.
     *
     * @param string $key
     * @param string $page
     * @param \stdClass $library
     * @return string
     */
    private static function url(string $key, string $page, \stdClass $library): string {
        if ($key === 'team') {
            return self::team_url($library);
        }
        return (new \moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
    }

    /**
     * Where the team of a library is made: giving the librarian's role where the centre gives it, for whoever may; else
     * choosing who keeps one library at its desk.
     *
     * @param \stdClass $library
     * @return string
     */
    public static function team_url(\stdClass $library): string {
        global $DB;
        $context = access::context((int) $library->entityid);
        $role = (int) $DB->get_field('role', 'id', ['shortname' => roles::LIBRARIAN]);
        return $role && has_capability('moodle/role:assign', $context)
            ? (new \moodle_url('/admin/roles/assign.php', ['contextid' => $context->id, 'roleid' => $role]))->out(false)
            : (new \moodle_url('/local/oksigeniaayvurenda/keepers.php', ['library' => $library->slug]))->out(false);
    }

    /**
     * A string of the plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string($key, 'local_oksigeniaayvurenda', $a);
    }
}
