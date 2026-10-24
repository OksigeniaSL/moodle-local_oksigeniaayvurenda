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

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\late;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$all = optional_param('all', false, PARAM_BOOL);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:desk');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $ref, 'all' => (int) $all]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$groups = late::by_group($library, !$all);
foreach ($groups as &$group) {
    $group['heading'] = $group['group'] !== '' ? $group['group'] : $str('readersnogroup');
    foreach ($group['lines'] as &$line) {
        $line['when'] = $line['days'] > 0 ? $str($line['days'] === 1 ? 'desklateoneday' : 'desklatedays', $line['days'])
            : $str('lateuntil', $line['due']);
        $line['islate'] = $line['days'] > 0;
    }
}
unset($group, $line);
$data = [
    'heading' => $str($all ? 'lateallheading' : 'lateheading'),
    'groups' => $groups,
    'hasgroups' => (bool) $groups,
    'empty' => $str($all ? 'lateallempty' : 'lateempty'),
    'switch' => ['url' => (new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug,
        'all' => (int) !$all]))->out(false), 'label' => $str($all ? 'lateonly' : 'lateall')],
];
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/late', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]
);
