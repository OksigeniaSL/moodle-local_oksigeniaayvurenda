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
 * My class: the tutor of a group sees the challenge of the term and chooses it, the class's garden and what the class
 * reads, never who.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\challenges;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\wishes;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$asked = optional_param('group', '', PARAM_TEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $ref] +
    ($asked !== '' ? ['group' => $asked] : []) + (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
$now = time();
$groups = challenges::tutored($entityid, (int) $USER->id, $now);
// Inside the guided help, the practice library's groups, as their tutor would see them.
$help = optional_param('help', 0, PARAM_BOOL) && \local_oksigeniaayvurenda\local\practice::is($entityid);
if ($help) {
    $groups = \local_oksigeniaayvurenda\local\practice::groups();
}
// The library's staff may open any group's page as its tutor sees it.
if ($asked !== '' && !in_array($asked, $groups, true) && has_capability('local/oksigeniaayvurenda:desk', $context)) {
    $groups = [$asked];
}

// A pupil sees their own class as its tutor does, without choosing anything: its challenge, its island, what it reads.
// One with no group this year is not told about tutors: their home is the place.
$pupil = null;
if (!$groups && !has_capability('local/oksigeniaayvurenda:desk', $context)) {
    $reader = $DB->get_record(
        'local_oksigeniaayvurenda_reader',
        ['entityid' => $entityid, 'userid' => $USER->id, 'status' => 'active', 'readertype' => 'student'],
        'id',
        IGNORE_MULTIPLE
    );
    $member = $reader ? $DB->get_record(
        'local_oksigeniaayvurenda_member',
        ['readerid' => $reader->id, 'yearid' => years::around($entityid, $now)->id],
        'groupname',
        IGNORE_MULTIPLE
    ) : null;
    if ($member && trim((string) $member->groupname) !== '') {
        $pupil = (string) $member->groupname;
    } else if ($reader) {
        redirect(new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]));
    }
}

$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $group = required_param('group', PARAM_TEXT);
    if (in_array($group, $groups, true) && optional_param('do', '', PARAM_ALPHA) === 'wish') {
        // The book the class chose for a challenge it met.
        $challengeid = required_param('challengeid', PARAM_INT);
        $theirs = $DB->record_exists('local_oksigeniaayvurenda_challenge', ['id' => $challengeid, 'groupname' => $group]);
        if (
            $theirs && wishes::ask($entityid, $challengeid, required_param('title', PARAM_TEXT), optional_param(
                'note',
                '',
                PARAM_TEXT
            ), (int) $USER->id, $now)
        ) {
            $notice = $str('wishsaved');
        }
    } else if (in_array($group, $groups, true)) {
        $kind = required_param('challenge', PARAM_ALPHA);
        if ($kind === 'off' || isset(challenges::KINDS[$kind])) {
            challenges::choose($entityid, $group, $kind, $now);
            $notice = $str('challengesaved');
        }
    }
}

// What is shown: the tutor's groups, or the pupil's own class (the form above only ever changes a tutor's).
$shown = $pupil !== null ? [$pupil] : $groups;
$year = $shown ? years::around($entityid, $now) : null;
$data = [];
foreach ($shown as $i => $group) {
    $challenge = challenges::of($entityid, $group, $now);
    $off = challenges::stage_off($entityid, (int) $year->id, $group);
    // Once met, the tutor may start another one this term (JV-D5).
    $choices = challenges::choices($challenge);
    $planted = challenges::garden($entityid, $group, $now);
    // How many steps each challenge would have for this class: «everyone» is its readers this year.
    $steps = ['corners' => challenges::KINDS['corners'], 'notes' => challenges::KINDS['notes'],
        'everyone' => max(1, $DB->count_records_select(
            'local_oksigeniaayvurenda_member',
            'yearid = :yearid AND groupname = :groupname',
            ['yearid' => $year->id, 'groupname' => $group]
        ))];
    $garden = array_map(
        fn($p) => ['label' => $str('challengeplanted', $p['term']) . ' · ' . $str('challengekind' . $p['kind'])],
        $planted
    );
    $reads = screens::cards(catalogue::class_reads($library, $group, (int) $year->id, $now), $library);
    // Each challenge met is rewarded with more reading: the class chooses the next book.
    $rewards = [];
    $boat = false;
    foreach (wishes::rewards($entityid, $group, $now) as $reward) {
        $wish = $reward['wish'];
        $boat = $boat || ($wish && $wish->status === 'got');
        $rewards[] = [
            'challengeid' => (int) $reward['challenge']->id,
            'label' => $str('challengeplanted', $reward['challenge']->term) . ' · ' .
                $str('challengekind' . $reward['challenge']->kind),
            'ask' => $pupil === null && (!$wish || $wish->status === 'declined'),
            'state' => $wish ? $str('wishstate' . $wish->status, format_string($wish->title, true, ['escape' => false])) : '',
        ];
    }
    $data[] = [
        'index' => $i,
        'group' => $group,
        'offstage' => $off !== null ? $str('classoff', $off) : '',
        'current' => $challenge ? challenges::view($challenge, $entityid, $pupil === null) : null,
        'canchoose' => $off === null && $pupil === null,
        // The challenge of the term as cards (Jujol): each with what it asks and its garland, still empty.
        'kinds' => array_map(fn($k) => array_merge($k, [
            // The card «This term's» is the one chosen; choosing another after one met marks none (the met one is above).
            'checked' => !$choices['again'] && $k['checked'],
            'off' => $k['value'] === 'off',
            'label' => $k['value'] === 'off' ? $str('challengecardoff') : $k['label'],
            'phrase' => match ($k['value']) {
                'corners' => $str('challengecorners', challenges::KINDS['corners']),
                'notes' => $str('challengenotes', challenges::KINDS['notes']),
                'everyone' => $str('challengeeveryone'),
                default => $str('challengecardoffphrase'),
            },
            'steps' => $k['value'] === 'off' ? null : ['progresslabel' => $str('challengecardsteps', $steps[$k['value']]),
                'marks' => challenges::garland(0, $steps[$k['value']])],
        ]), $choices['kinds']),
        'choose' => $choices['choose'],
        'choosehint' => $choices['choosehint'],
        'save' => $choices['save'],
        'garden' => $garden,
        'hasgarden' => (bool) $garden,
        // The garden drawn, when the art package brings the island.
        'island' => $island = ($layers = screens::island(
            count($planted),
            in_array('everyone', array_column($planted, 'kind'), true),
            $boat
        )) ? ['islandlayers' => array_map(fn($u) => ['layer' => $u], $layers),
            'islandlabel' => $str('islandlabel', count($planted)),
            // The same island in 3D, over the layers, where the site allows it.
            'islandstate' => get_config('local_oksigeniaayvurenda', 'island3d') !== '0' ? json_encode(['met' => count($planted),
                'everyone' => in_array('everyone', array_column($planted, 'kind'), true), 'boat' => $boat,
                // The ship of the term (P5): the last week it waits, the last day it sails, among sparks if met.
                'ship' => challenges::ship($entityid, $now), 'shipmet' => $challenge && !empty($challenge->complete)]) : '',
            'islandkey' => $library->id . '-' . md5($group),
            // Gaudí's models of the art package, for the pieces that have one; the drawn ones without them.
            'islandassets' => json_encode(array_filter(['hut' => screens::art('isla3d/caseta-q', 'glb'),
                'ship' => screens::art('isla3d/velero', 'glb')]))] : null,
        // The board «Enseñar a la clase» (Jujol): the class's, on the whiteboard, for its tutor, while the term has a
        // challenge.
        'board' => ($board = $pupil === null && $challenge ? challenges::board($challenge, $group, $entityid, $now) : null)
            ? $board + ['island' => $island, 'islandlabel' => $island['islandlabel'] ?? '', 'page' => false,
            'pageurl' => (new moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $library->slug, 'pizarra' => $group]))
                ->out(false),
            'back' => (new moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $library->slug]))->out(false),
            'show' => $str('boardshow'), 'showhint' => $str('boardshowhint'), 'smaller' => $str('boardsmaller'),
            'bigger' => $str('boardbigger'), 'full' => $str('boardfull'), 'exit' => $str('boardexit'), 'listen' => $str('listen')]
            : null,
        // The year's booklets (Jujol): for the tutor, from three weeks before the last day, to print the class's at once.
        'booklets' => $pupil === null && $now >= ($bookletsfrom = \local_oksigeniaayvurenda\local\booklet::classes_end($year)
            - 21 * DAYSECS) && $now < $bookletsfrom + (21 + \local_oksigeniaayvurenda\local\booklet::ONCARD) * DAYSECS ? [
            'bandmedal' => str_replace('role="img"', 'aria-hidden="true"', \local_oksigeniaayvurenda\local\bookletview::medal(
                'flower',
                3,
                ['stay' => 3, 'back' => 3, 'explore' => 2, 'tell' => 3],
                '',
                36
            )),
            'bandtitle' => $str('bookletcourse'),
            'bandhint' => $str('bookletcoursehint'),
            'bandleaving' => $str('bookletcourseleaving'),
            'printurl' => (new moodle_url('/local/oksigeniaayvurenda/booklet.php', ['library' => $library->slug,
                'group' => $group]))
                ->out(false),
            'printtext' => $str('bookletcourseprint'),
            'lifeurl' => (new moodle_url('/local/oksigeniaayvurenda/booklet.php', ['library' => $library->slug, 'group' => $group,
                'life' => 1]))->out(false),
            'lifetext' => $str('bookletcourselife'),
            'sheets' => $str('bookletcoursesheets', $steps['everyone']),
        ] : null,
        'cards' => $reads,
        'hasreads' => (bool) $reads,
        'rewards' => $rewards,
        'hasrewards' => (bool) $rewards,
    ];
}

// What the page needs: the island in 3D where there is one to draw, and the board where there is a challenge.
$scripts = array_merge(
    array_filter(array_map(fn($g) => $g['island']['islandstate'] ?? '', $data)) ? ['isla3d', 'island'] : [],
    array_filter(array_column($data, 'board')) ? ['barco'] : []
);
// The board as a page of its own (its button, without script): only the board of that group.
$asboard = optional_param('pizarra', '', PARAM_TEXT);
foreach ($asboard !== '' ? $data : [] as $one) {
    if ($one['group'] === $asboard && $one['board']) {
        shell::output(
            $str('boardshow') . ' · ' . $one['group'],
            $OUTPUT->render_from_template('local_oksigeniaayvurenda/class_board', array_merge($one['board'], ['page' => true])),
            $library,
            '',
            ['search' => false, 'scripts' => $scripts]
        );
        die();
    }
}
shell::output($str('classtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/class', [
    'heading' => $str('classtitle'),
    // The rules of the challenge only where there is a class; without one, the empty island and where to go.
    'intro' => $pupil !== null ? $str('classintropupil') : ($groups ? $str('classintro') : ''),
    'notice' => $notice,
    'none' => $shown ? '' : $str('classnone'),
    'nobody' => $shown ? null : [
        'herald' => \local_oksigeniaayvurenda\local\characters::say(
            (int) $library->entityid,
            'herald',
            'classnoneherald',
            [],
            'lee'
        ),
        'islandbase' => \local_oksigeniaayvurenda\local\screens::art('isla/base'),
        'readsurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false)
            . '#ayv-recent',
        'readslabel' => $str('classnonereads'),
        'stacksurl' => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug]))->out(false),
        'stackslabel' => $str('classnonestacks'),
    ],
    'groups' => $data,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'choose' => $str('challengechoose'),
    'chosen' => $str('challengechosen'),
    'choosehint' => $str('challengechoosehint'),
    'save' => $str('challengesave'),
    'gardenheading' => $str('challengegarden'),
    'gardenempty' => $str('challengegardenempty'),
    'readsheading' => $str('homeclass'),
    'readshint' => $str('homeclasshint'),
    'readsempty' => $str('classreadsempty'),
    'shelfprev' => $str('shelfprev'),
    'shelfnext' => $str('shelfnext'),
    'rewardheading' => $str('rewardheading'),
    'rewardhint' => $str('rewardhint'),
    'wishtitle' => $str('wishtitle'),
    'wishnote' => $str('wishnote'),
    'wishnotehint' => $str('wishnotehint'),
    'wishsend' => $str('wishsend'),
]), $library, '', [
    'search' => false,
    // The island in 3D, only where there is one to draw.
    'scripts' => $scripts,
    // The guided help is the tutor's: a pupil has nothing to choose here.
    'nav' => optional_param('help', 0, PARAM_BOOL) || $pupil !== null ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'myclass']
    ))->out(false), 'label' => $str('helpnav')]],
]);
