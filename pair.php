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
 * Pairing the readers already there with the people of Cifra Educación, before it first brings its readers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\pairing;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/pair.php', ['library' => $ref]));
// Asking the platform for everyone takes a while the first time.
core_php_time_limit::raise(300);

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
try {
    $piles = pairing::piles($entityid);
} catch (\Throwable $e) {
    shell::refuse($str('pairfailed', $e->getMessage()), $library);
}
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('do', '', PARAM_ALPHA);
    $done = 0;
    if ($action === 'sure') {
        foreach ($piles['sure'] as $readerid => $index) {
            $done += (int) pairing::link(
                $entityid,
                (int) $readerid,
                $piles['people'][$index]['person'],
                $piles['people'][$index]['made']
            );
        }
    } else if ($action === 'doubt') {
        foreach (optional_param_array('pick', [], PARAM_INT) as $readerid => $index) {
            if ($index > 0 && in_array($index, $piles['doubt'][$readerid] ?? [], true)) {
                $done += (int) pairing::link(
                    $entityid,
                    (int) $readerid,
                    $piles['people'][$index]['person'],
                    $piles['people'][$index]['made']
                );
            }
        }
    } else if ($action === 'none') {
        pairing::leave($entityid, $piles['none']);
        $done = count($piles['none']);
    }
    $notice = $str('matchdone', $done);
    $piles = pairing::piles($entityid);
}
$sure = [];
foreach (array_slice($piles['sure'], 0, 40, true) as $readerid => $index) {
    $reader = $piles['readers'][$readerid];
    $person = $piles['people'][$index];
    $sure[] = ['reader' => $reader['name'], 'readergroup' => $reader['group'], 'account' => $person['name'],
        'accountgroup' => $person['group']];
}
$doubt = [];
foreach (array_slice($piles['doubt'], 0, 60, true) as $readerid => $candidates) {
    $reader = $piles['readers'][$readerid];
    $options = [];
    foreach ($candidates as $index) {
        $person = $piles['people'][$index];
        $options[] = ['userid' => $index, 'name' => $person['name'], 'group' => $person['group']];
    }
    $doubt[] = ['readerid' => $readerid, 'name' => $reader['name'], 'group' => $reader['group'], 'options' => $options];
}
$data = [
    'heading' => $str('pairheading'),
    'intro' => $str('pairintro'),
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
    'noneheading' => $str('pairnone', count($piles['none'])),
    'noneintro' => $str('pairnoneintro'),
    'hasnone' => (bool) $piles['none'],
    'nonebutton' => $str('matchnonebutton'),
    'empty' => !$piles['sure'] && !$piles['doubt'] && !$piles['none'] ? $str('pairempty') : '',
    'skipped' => $piles['skipped'] ? $str('pairskipped', $piles['skipped']) : '',
];
$connector = (new moodle_url('/local/oksigeniaayvurenda/connector.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/match', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $connector, 'label' => $str('pairback')]]]
);
