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
 * The school year: what its beginning asks, its end, last year in numbers and the day it starts.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\cohorts;
use local_oksigeniaayvurenda\local\year_change;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('do', '', PARAM_ALPHA);
    if ($action === 'sync') {
        $stats = cohorts::sync($entityid);
        $notice = $str('yearsynced', (object) $stats) . plans::left_out($stats);
    } else if ($action === 'start') {
        $ok = year_change::set_start($entityid, optional_param('month', 0, PARAM_INT), optional_param('day', 0, PARAM_INT));
        $notice = $str($ok ? 'sourcessaved' : 'yearbadday');
    }
}
$status = year_change::status($entityid);
$year = $status['year'];
$url = fn(string $page, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $page,
    ['library' => $library->slug] + $params
))->out(false);
$date = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'));

$cards = [
    ['number' => $status['groups'], 'label' => $str('yeargroups', $status['members']), 'url' => $url('readers.php')],
    ['number' => $status['tutors'] . ' / ' . $status['groups'], 'label' => $str('yeartutors'), 'url' => $url('tutors.php'),
        'late' => $status['notutor'] > 0],
    ['number' => $status['out'], 'label' => $str('yearout'), 'url' => $url('late.php', ['all' => 1])],
];
if ($status['left']) {
    $cards[] = ['number' => count($status['left']), 'label' => $str('yearleft'), 'url' => '#ayv-left', 'late' => true];
}
$last = null;
if ($status['last']) {
    $l = $status['last'];
    $last = [
        'heading' => $str('yearlast', $l['name']),
        'total' => $str('yearlasttotal', (object) ['loans' => $l['loans'], 'borrowers' => $l['borrowers'],
            'members' => $l['members']]),
        'groups' => array_map(fn($g) => ['group' => $g['group'], 'line' => $str('yearlastgroup', (object) $g),
            'share' => $g['members'] ? round(100 * $g['borrowers'] / $g['members']) : 0], $l['groups']),
    ];
}
[$month, $day] = array_map('intval', explode('-', (string) ($DB->get_field(
    'local_oksigeniaayvurenda_entity',
    'yearstart',
    ['id' => $entityid]
) ?: '09-01')));
$days = [];
for ($d = 1; $d <= 31; $d++) {
    $days[] = ['value' => $d, 'label' => $d, 'selected' => $d === $day];
}
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = ['value' => $m, 'label' => userdate(make_timestamp(2001, $m, 15, 12), '%B'), 'selected' => $m === $month];
}
$data = [
    'heading' => $str('yearheading', $year->name),
    'dates' => $str('yeardates', (object) ['start' => $date((int) $year->timestart),
        'end' => $date((int) $year->timeend - DAYSECS)]),
    'notice' => $notice,
    'ending' => $status['moment'] === 'end' && $status['out'] ? ['text' => $str('yearending', $status['out']),
        'url' => $url('late.php', ['all' => 1]), 'label' => $str('lateall')] : null,
    'cards' => $cards,
    'startintro' => $str('yearstartintro'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'sync' => $str('yearsync'),
    'left' => $status['left'],
    'hasleft' => (bool) $status['left'],
    'leftheading' => $str('yearleftheading'),
    'leftintro' => $str('yearleftintro'),
    'last' => $last,
    'startheading' => $str('yearstartheading'),
    'startnote' => $str('yearstartnote'),
    'days' => $days,
    'months' => $months,
    'daylabel' => $str('yearday'),
    'monthlabel' => $str('yearmonth'),
    'save' => $str('sourcessave'),
];
foreach ($data['left'] as &$row) {
    $row['bookslabel'] = $str($row['books'] === 1 ? 'yearleftone' : 'yearleftmany', $row['books']);
}
unset($row);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/year', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $url('readers.php'), 'label' => $str('readers')],
    ['url' => $url('desk.php'), 'label' => $str('desktitle')]]]
);
