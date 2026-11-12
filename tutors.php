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
 * The tutor of each group this school year: a face on the shelf of its stage, changed from one tray of faces.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\tutors;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$help = optional_param('help', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/tutors.php', ['library' => $ref] + ($help ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
$year = years::around($entityid, time());
$groups = tutors::groups($entityid, (int) $year->id);
$candidates = tutors::candidates($entityid);
$back = fn(array $params, string $anchor = '') => new moodle_url(
    '/local/oksigeniaayvurenda/tutors.php',
    ['library' => $library->slug] + ($help ? ['help' => 1] : []) + $params,
    $anchor
);
// A face touched saves that group's tutor; «as last year» saves all the proposed at once. Back to the group's card.
if (data_submitted() && confirm_sesskey()) {
    if (optional_param('acceptall', 0, PARAM_BOOL)) {
        foreach ($groups as $row) {
            if ($row['proposed'] && isset($candidates[$row['userid']])) {
                tutors::set((int) $year->id, $row['group'], (int) $row['userid']);
            }
        }
        redirect($back(['saved' => 1]));
    }
    $group = optional_param('group', '', PARAM_TEXT);
    $userid = optional_param('tutor', -1, PARAM_INT);
    $index = array_search($group, array_column($groups, 'group'), true);
    if ($index !== false && ($userid === 0 || isset($candidates[$userid]))) {
        tutors::set((int) $year->id, $group, $userid);
    }
    redirect($back(['saved' => 1], $index !== false ? 'g' . $index : ''));
}
$open = optional_param('grupo', -1, PARAM_INT);
$saved = optional_param('saved', 0, PARAM_BOOL);
// A face: the person's photo if they have one and the centre shows them, else their initials, in a colour of the house.
$colours = ['green', 'coral', 'sun', 'night', 'green2'];
$photos = \local_oksigeniaayvurenda\local\profile::photos_on($entityid);
$face = function (int $userid, string $name) use ($colours, $photos, $PAGE): array {
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = core_text::strtoupper(core_text::substr($words[0] ?? '', 0, 1) . core_text::substr($words[1] ?? '', 0, 1));
    $user = $photos ? core_user::get_user($userid) : null;
    $photo = '';
    if ($user && !empty($user->picture) && empty($user->deleted)) {
        $picture = new user_picture($user);
        $picture->size = 100;
        $photo = $picture->get_url($PAGE)->out(false);
    }
    return ['initials' => $initials, 'colour' => $colours[$userid % count($colours)], 'photo' => $photo];
};
// What each person already looks after, to say it in the tray.
$looks = [];
foreach ($groups as $row) {
    if ($row['userid'] && !$row['proposed']) {
        $looks[$row['userid']][] = $row['group'];
    }
}
$stages = [];
$with = 0;
$missing = null;
$proposed = 0;
foreach ($groups as $index => $row) {
    $userid = (int) $row['userid'];
    $gone = $userid && !isset($candidates[$userid]) ? core_user::get_user($userid) : null;
    $name = $userid ? ($candidates[$userid] ?? ($gone ? $str('tutorgone', fullname($gone)) : '')) : '';
    $with += $userid && !$row['proposed'] ? 1 : 0;
    $proposed += $row['proposed'] ? 1 : 0;
    if (!$userid && $missing === null) {
        $missing = $index;
    }
    $stage = (string) ($row['stage'] ?? '');
    // Each stage on its shelf, in the colours of the house in turn.
    $stages[$stage] ??= ['stage' => $stage, 'colour' => ['green', 'coral', 'sun', 'night'][count($stages) % 4], 'cards' => []];
    $stages[$stage]['cards'][] = [
        'id' => 'g' . $index,
        'url' => $back(['grupo' => $index], 'ayv-tu-bandeja')->out(false),
        'group' => $row['group'],
        'who' => $userid ? $name : $str('tutornone'),
        'none' => !$userid,
        'proposed' => (bool) $row['proposed'],
        'chosen' => $index === $open,
        'aria' => $str('tutorscardaria', (object) ['group' => $row['group'], 'who' => $userid ? $name : $str('tutornone')]),
        'face' => $userid ? $face($userid, $name) : null,
    ];
}
$tray = null;
if (isset($groups[$open])) {
    $row = $groups[$open];
    $people = [];
    foreach ($candidates as $userid => $name) {
        $already = array_values(array_diff($looks[$userid] ?? [], [$row['group']]));
        $people[] = ['userid' => $userid, 'name' => $name, 'face' => $face($userid, $name),
            'already' => $already ? $str('tutorsalready', implode(', ', $already)) : '',
            'current' => $userid === (int) $row['userid'] && !$row['proposed']];
    }
    $tray = [
        'group' => $row['group'],
        'heading' => $str('tutorstrayheading', '<b>' . s($row['group']) . '</b>'),
        'action' => $back([], 'g' . $open)->out(false),
        'release' => $back([], 'g' . $open)->out(false),
        'people' => $people,
        'nonecurrent' => !(int) $row['userid'],
    ];
}
$crier = characters::whole($entityid)
    ? characters::figure('herald', $saved ? 'celebra' : 'lee', characters::step($entityid)) : null;
$data = [
    'heading' => $str('tutorsheading', $year->name),
    'intro' => $str('tutorsintroface'),
    'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet(
        'herald',
        $saved ? 'celebra' : 'lee',
        characters::step($entityid)
    )] : null,
    'saved' => $saved ? $str('hiwsaved') : '',
    'sesskey' => sesskey(),
    'action' => $back([])->out(false),
    'count' => $groups ? $str('tutorscount', (object) ['with' => $with, 'all' => count($groups)]) : '',
    'missing' => $missing !== null ? ['url' => $back(['grupo' => $missing], 'ayv-tu-bandeja')->out(false),
        'label' => $str('tutorsmissing'), 'group' => $groups[$missing]['group']] : null,
    'proposed' => $proposed ? ['text' => $str($proposed === 1 ? 'tutorsproposedone' : 'tutorsproposed', $proposed),
        'accept' => $str('tutorsaccept')] : null,
    'tray' => $tray,
    'stages' => array_values($stages),
    'hasgroups' => (bool) $groups,
    'empty' => $str('readersempty'),
    'nocandidates' => $candidates ? '' : $str('tutornocandidates'),
    'search' => $str('tutorssearch'),
    'searchlabel' => $str('tutorssearchlabel'),
    'releaselabel' => $str('tutorsrelease'),
    'nobody' => $str('tutornone'),
    'nobodyhint' => $str('tutorsnobodyhint'),
    'proposedmark' => $str('tutorproposed'),
];
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/tutors', $data),
    $library,
    '',
    ['search' => false, 'nav' => $help ? [] : [['url' => $readersurl, 'label' => $str('readers')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'tutors']))
            ->out(false), 'label' => $str('helpnav')]]]
);
