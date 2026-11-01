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
 * The books that are late, or all those out, by group.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\late;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$all = optional_param('all', false, PARAM_BOOL);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $ref, 'all' => (int) $all]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug]
    + $params))->out(false);
$now = time();
$notice = '';
// Tell a group's tutor now, without waiting for the week's notice.
if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'tell') {
    $year = \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, $now);
    $groupname = optional_param('group', '', PARAM_TEXT);
    $told = 0;
    foreach ($DB->get_records('local_oksigeniaayvurenda_tutor', ['yearid' => $year->id, 'groupname' => $groupname]) as $tutor) {
        $tutor->entityid = (int) $library->entityid;
        $told += \local_oksigeniaayvurenda\local\notices::tell_tutor($tutor, $now) ? 1 : 0;
    }
    $notice = $str($told ? 'latetutortold' : 'latenotutor', $groupname);
}
$groups = late::by_group($library, !$all, $now);
// The spans of lateness, as cards that filter: more than 30 days, 15 to 30, under 15.
$span = optional_param('span', '', PARAM_ALPHA);
$spanof = fn(int $days) => $days > 30 ? 'old' : ($days >= 15 ? 'mid' : 'new');
$counts = ['old' => 0, 'mid' => 0, 'new' => 0];
foreach ($groups as $group) {
    foreach ($group['lines'] as $line) {
        if ($line['days'] > 0) {
            $counts[$spanof($line['days'])]++;
        }
    }
}
$only = optional_param('group', '', PARAM_TEXT);
$covers = \local_oksigeniaayvurenda\local\covers::urls(array_merge(...array_map(
    fn($g) => array_column($g['lines'], 'recordid'),
    $groups ?: [['lines' => []]]
)));
$shown = [];
foreach ($groups as $group) {
    if ($only !== '' && $group['group'] !== $only) {
        continue;
    }
    $lines = [];
    $readers = [];
    $oldest = 0;
    foreach ($group['lines'] as $line) {
        if (!$all && $span !== '' && isset($counts[$span]) && $spanof($line['days']) !== $span) {
            continue;
        }
        $readers[$line['readerid']] = true;
        $oldest = max($oldest, $line['days']);
        $lines[] = $line + [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $library->slug,
                'id' => $line['readerid']]))->out(false),
            'cover' => $covers[$line['recordid']] ?? null,
            'colour' => $line['recordid'] % 6,
            'when' => $line['days'] > 0 ? $str($line['days'] === 1 ? 'desklateoneday' : 'desklatedays', $line['days'])
                : $str('lateuntil', $line['due']),
            // Red only past two weeks: what is just late stays calm.
            'tone' => $line['days'] > 14 ? 'late' : ($line['days'] > 0 ? 'soon' : 'ok'),
        ];
    }
    if (!$lines) {
        continue;
    }
    $name = $group['group'] !== '' ? $group['group'] : $str('readersnogroup');
    $shown[] = [
        'heading' => $name,
        'group' => $group['group'],
        'summary' => $oldest > 0
            ? $str(count($readers) === 1 ? 'lategroupone' : 'lategroup', (object) ['readers' => count($readers), 'days' => $oldest])
            : $str(count($readers) === 1 ? 'lategroupcountone' : 'lategroupcount', count($readers)),
        'lines' => $lines,
        'urgent' => $oldest > 30,
        'open' => $only !== '',
        'tellable' => !$all && $group['group'] !== '',
        'printurl' => $url(['group' => $group['group'], 'all' => (int) $all]),
    ];
}
$data = [
    'heading' => $str($all ? 'lateallheading' : 'lateheading'),
    'notice' => $notice,
    'tabs' => [
        ['label' => $str('latetablate'), 'url' => $url(), 'current' => !$all],
        ['label' => $str('latetaball'), 'url' => $url(['all' => 1]), 'current' => $all],
    ],
    'spans' => $all ? [] : array_map(fn($key) => ['key' => $key, 'count' => \local_oksigeniaayvurenda\local\screens::number(
        $counts[$key]
    ), 'label' => $str('latespan' . $key), 'url' => $url($span === $key ? [] : ['span' => $key]),
        'current' => $span === $key], ['old', 'mid', 'new']),
    'groups' => $shown,
    'hasgroups' => (bool) $shown,
    'only' => $only !== '',
    'allurl' => $url(['all' => (int) $all]),
    'empty' => $str($all ? 'lateallempty' : 'lateempty'),
    'action' => $url(['all' => (int) $all]),
    'sesskey' => sesskey(),
];
foreach (['latetell', 'lateprint', 'lateallgroups', 'printnow'] as $key) {
    $data[$key] = $str($key);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/late', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]
);
