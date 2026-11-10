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
 * Where the readers of an entity come from: the cohorts of the site.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\cohorts;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$keep = optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [];
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/sources.php', ['library' => $ref] + $keep));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    shell::no_practice($library);
    // Each sheet saves its own.
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'cohorts') {
        $chosen = optional_param_array('chosen', [], PARAM_INT);
        $types = optional_param_array('type', [], PARAM_ALPHANUMEXT);
        $stages = optional_param_array('stage', [], PARAM_TEXT);
        $groups = optional_param_array('group', [], PARAM_INT);
        $config = [];
        foreach ($chosen as $cohortid => $on) {
            if ($on) {
                $config[$cohortid] = ['type' => $types[$cohortid] ?? 'student', 'stage' => $stages[$cohortid] ?? '',
                    'group' => !empty($groups[$cohortid])];
            }
        }
        cohorts::save($entityid, $config);
    } else if ($do === 'courses') {
        $courseconfig = [];
        $stages = optional_param_array('coursestage', [], PARAM_TEXT);
        $groups = optional_param_array('coursegroup', [], PARAM_INT);
        foreach (optional_param_array('course', [], PARAM_INT) as $courseid => $on) {
            if ($on) {
                $courseconfig[$courseid] = ['stage' => $stages[$courseid] ?? '', 'group' => !empty($groups[$courseid])];
            }
        }
        \local_oksigeniaayvurenda\local\sources\courses::save($entityid, $courseconfig);
    }
    $notice = $str('sourcessaved');
    if (optional_param('syncnow', false, PARAM_BOOL)) {
        $stats = cohorts::sync($entityid, null, true);
        $notice = $str('sourcessynced', (object) $stats) . plans::left_out($stats);
    }
}
$types = [];
foreach (['student', 'teacher', 'staff', 'family'] as $type) {
    $types[] = ['value' => $type, 'label' => $str('readertype' . $type)];
}
$rows = [];
// The practice library shows the places, and nothing of the site's own.
$practice = \local_oksigeniaayvurenda\local\practice::is($entityid);
foreach ($practice ? [] : cohorts::options($entityid) as $option) {
    $option['types'] = array_map(fn($t) => $t + ['selected' => $t['value'] === $option['type']], $types);
    $option['memberslabel'] = $str($option['members'] === 1 ? 'onemember' : 'members', $option['members']);
    $rows[] = $option;
}
$courses = array_map(
    fn($c) => $c + ['groupslabel' => (int) $c['groups'] === 1 ? $str('sourcesgroupsofone') : $str('sourcesgroupsof', $c['groups'])],
    $practice ? [] : \local_oksigeniaayvurenda\local\sources\courses::options($entityid)
);
$page = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/' . $file, ['library' => $library->slug]))->out(false);
// Each place says whether it is in use: how many readers it brought, or what is chosen, or «not used».
$from = \local_oksigeniaayvurenda\local\readers::from_sources($entityid);
$brought = function (string $place) use ($from, $str, $number) {
    $count = $from[$place] ?? 0;
    return $count ? ($count === 1 ? $str('onereader') : $str('readerscount', $number($count))) : '';
};
$chosencohorts = count(array_filter(array_column($rows, 'chosen')));
$chosencourses = count(array_filter(array_column($courses, 'chosen')));
$cifra = \local_oksigeniaayvurenda\local\sources\cifra::settings($entityid);
$cifraon = $cifra['url'] !== '' && ($cifra['haskey'] || $cifra['hassecret']);
// The readers of a OneRoster API and of its zip are the same ones: the connection says it, not the zip.
$rosterapi = \local_oksigeniaayvurenda\local\sources\oneroster_api::connected($entityid);
$rosterapion = $rosterapi && \local_oksigeniaayvurenda\local\sources\oneroster_api::settings($entityid)['enabled'];
$cards = [
    ['key' => 'list', 'state' => $brought('csv'), 'url' => $page('readerslist.php'), 'sheet' => ''],
    ['key' => 'oneroster', 'state' => $rosterapi ? '' : $brought('oneroster'), 'url' => $page('oneroster.php'), 'sheet' => ''],
    ['key' => 'onerosterapi', 'url' => $page('onerosterapi.php'), 'sheet' => '', 'off' => !plans::allows($entityid, 'connectors'),
        'state' => $rosterapi ? $str($rosterapion ? 'sourcestatenight' : 'sourcestatelinked') : ''],
    ['key' => 'cohorts', 'url' => '', 'sheet' => 'ayv-src-cohorts',
        'state' => $chosencohorts ? $str('sourcestatecohorts', $number($chosencohorts)) : ''],
    ['key' => 'courses', 'url' => '', 'sheet' => 'ayv-src-courses',
        'state' => $chosencourses ? $str('sourcestatecourses', $number($chosencourses)) : ''],
    ['key' => 'lti', 'state' => $brought('lti'), 'url' => $page('lti.php'), 'sheet' => '',
        'off' => !plans::allows($entityid, 'lti')],
    ['key' => 'cifra', 'url' => $page('connector.php'), 'sheet' => '', 'off' => !plans::allows($entityid, 'connectors'),
        'state' => $cifraon ? $str($cifra['enabled'] ? 'sourcestatenight' : 'sourcestatelinked') : ''],
    ['key' => 'abies', 'state' => $brought('abies'), 'url' => $page('import.php'), 'sheet' => ''],
];
// Abies is a program of Spanish schools: its card, in Spanish, or where something already came from it.
if (!str_starts_with(current_language(), 'es') && $brought('abies') === '') {
    $cards = array_values(array_filter($cards, fn($card) => $card['key'] !== 'abies'));
}
if (\local_oksigeniaayvurenda\local\skins::no_courses($entityid)) {
    // What a school's programs and platforms bring: not for a town's library.
    $school = ['oneroster', 'onerosterapi', 'courses', 'lti', 'cifra'];
    $cards = array_values(array_filter($cards, fn($card) => !in_array($card['key'], $school, true)));
}
$data = [
    'heading' => $str('readerssources'),
    'intro' => $str('sourcesintroshort'),
    'notice' => $notice,
    'held' => \local_oksigeniaayvurenda\local\sources\leavers::held($entityid),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'cards' => array_map(fn($card) => [
        'title' => $str('sourcecard' . $card['key']),
        'hint' => $str('sourcecard' . $card['key'] . 'hint'),
        'go' => $str('sourcecard' . $card['key'] . 'go'),
        'state' => !empty($card['off']) ? $str('sourcestateoff') : ($card['state'] ?: $str('sourcestateunused')),
        'inuse' => $card['state'] !== '' && empty($card['off']),
        'url' => $card['url'],
        'sheet' => $card['sheet'],
        'is' . $card['key'] => true,
    ], $cards),
    'cohorts' => $rows,
    'courses' => $courses,
    'cohortsheading' => $str('sourcescohorts'),
    'cohortsintro' => $str('sourcesintro'),
    'coursesheading' => $str('sourcescourses'),
    'coursesintro' => $str('sourcescoursesintro'),
    'nocohorts' => $str('sourcesnocohorts'),
    'nocourses' => $str('sourcesnocourses'),
    'offsiteclose' => $str('offsiteclose'),
    'labels' => [
        'type' => $str('sourcestype'),
        'stage' => $str('sourcesstage'),
        'group' => $str('sourcesgroup'),
        'save' => $str('sourcessave'),
        'syncnow' => $str('sourcessyncnow'),
    ],
];
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/sources', $data),
    $library,
    '',
    ['search' => false, 'nav' => $keep ? [] : [['url' => $readersurl, 'label' => $str('readers')]]]
);
