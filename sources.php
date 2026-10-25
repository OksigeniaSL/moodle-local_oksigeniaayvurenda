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

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\cohorts;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/sources.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
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
    cohorts::save((int) $library->entityid, $config);
    $courseconfig = [];
    foreach (optional_param_array('course', [], PARAM_INT) as $courseid => $on) {
        if ($on) {
            $courseconfig[$courseid] = ['stage' => optional_param_array('coursestage', [], PARAM_TEXT)[$courseid] ?? '',
                'group' => !empty(optional_param_array('coursegroup', [], PARAM_INT)[$courseid])];
        }
    }
    \local_oksigeniaayvurenda\local\sources\courses::save((int) $library->entityid, $courseconfig);
    $notice = $str('sourcessaved');
    if (optional_param('syncnow', false, PARAM_BOOL)) {
        $stats = cohorts::sync((int) $library->entityid);
        $notice = $str('sourcessynced', (object) $stats) . plans::left_out($stats);
    }
}
$types = [];
foreach (['student', 'teacher', 'staff', 'family'] as $type) {
    $types[] = ['value' => $type, 'label' => $str('readertype' . $type)];
}
$rows = [];
foreach (cohorts::options((int) $library->entityid) as $option) {
    $option['types'] = array_map(fn($t) => $t + ['selected' => $t['value'] === $option['type']], $types);
    $option['memberslabel'] = $str($option['members'] === 1 ? 'onemember' : 'members', $option['members']);
    $rows[] = $option;
}
$data = [
    'heading' => $str('readerssources'),
    'intro' => $str('sourcesintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'cohorts' => $rows,
    'courses' => array_map(
        fn($c) => $c + ['groupslabel' => $str('sourcesgroupsof', $c['groups'])],
        \local_oksigeniaayvurenda\local\sources\courses::options((int) $library->entityid)
    ),
    'outsideheading' => $str('sourcesoutside'),
    'outsideintro' => $str('sourcesoutsideintro'),
    'outside' => [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/connector.php', ['library' => $library->slug]))->out(false),
            'label' => $str('sourcescifra'), 'hint' => $str('sourcescifrahint')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/readerslist.php', ['library' => $library->slug]))->out(false),
            'label' => $str('sourcescsv'), 'hint' => $str('sourcescsvhint')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/import.php', ['library' => $library->slug]))->out(false),
            'label' => $str('sourcesabies'), 'hint' => $str('sourcesabieshint')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/lti.php', ['library' => $library->slug]))->out(false),
            'label' => $str('sourceslti'), 'hint' => $str('sourcesltihint')],
    ],
    'coursesheading' => $str('sourcescourses'),
    'coursesintro' => $str('sourcescoursesintro'),
    'hascohorts' => true,
    'cohortsheading' => $str('sourcescohorts'),
    'nocohorts' => $str('sourcesnocohorts'),
    'labels' => [
        'chosen' => $str('sourceschosen'),
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
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
