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
 * Matching readers without an account with the accounts of the site, in three piles.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\matching;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/match.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('do', '', PARAM_ALPHA);
    $piles = matching::piles($entityid);
    $done = 0;
    if ($action === 'sure') {
        foreach ($piles['sure'] as $readerid => $userid) {
            matching::link($entityid, (int) $readerid, (int) $userid);
            $done++;
        }
    } else if ($action === 'doubt') {
        foreach (optional_param_array('pick', [], PARAM_INT) as $readerid => $userid) {
            if ($userid > 0 && in_array($userid, $piles['doubt'][$readerid] ?? [], true)) {
                matching::link($entityid, (int) $readerid, (int) $userid);
                $done++;
            }
        }
    } else if ($action === 'none') {
        matching::leave($entityid, $piles['none']);
        $done = count($piles['none']);
    }
    $notice = $str('matchdone', $done);
}
$piles = matching::piles($entityid);
$sure = [];
foreach (array_slice($piles['sure'], 0, 40, true) as $readerid => $userid) {
    $reader = $piles['readers'][$readerid];
    $account = $piles['accounts'][$userid];
    $sure[] = ['reader' => $reader['name'], 'readergroup' => $reader['group'], 'account' => $account['name'],
        'accountgroup' => $account['group']];
}
$doubt = [];
foreach (array_slice($piles['doubt'], 0, 60, true) as $readerid => $candidates) {
    $reader = $piles['readers'][$readerid];
    $options = [];
    foreach ($candidates as $userid) {
        $account = $piles['accounts'][$userid];
        $options[] = ['userid' => $userid, 'name' => $account['name'], 'group' => $account['group']];
    }
    $doubt[] = ['readerid' => $readerid, 'name' => $reader['name'], 'group' => $reader['group'], 'options' => $options];
}
$data = [
    'heading' => $str('matchheading'),
    'intro' => $str('matchintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'sureheading' => $str('matchsure', count($piles['sure'])),
    'sure' => $sure,
    'hassure' => (bool) $piles['sure'],
    'more' => count($piles['sure']) > count($sure) ? $str('matchmore', count($piles['sure']) - count($sure)) : '',
    'surebutton' => $str('matchsurebutton', count($piles['sure'])),
    'doubtheading' => $str('matchdoubt', count($piles['doubt'])),
    'doubt' => $doubt,
    'hasdoubt' => (bool) $piles['doubt'],
    'nobody' => $str('matchnobody'),
    'doubtbutton' => $str('matchdoubtbutton'),
    'noneheading' => $str('matchnone', count($piles['none'])),
    'noneintro' => $str('matchnoneintro'),
    'hasnone' => (bool) $piles['none'],
    'nonebutton' => $str('matchnonebutton'),
    'empty' => !$piles['sure'] && !$piles['doubt'] && !$piles['none'] ? $str('matchempty') : '',
];
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/match', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
