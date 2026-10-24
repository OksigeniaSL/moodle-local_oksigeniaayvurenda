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
 * The two characters of the library: their names, their sex, and whether they appear.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/characters.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $chosen = [];
    foreach (characters::ROLES as $role) {
        $chosen[$role] = [
            'enabled' => optional_param($role . 'enabled', 0, PARAM_BOOL),
            'name' => optional_param($role . 'name', '', PARAM_TEXT),
            'sex' => optional_param($role . 'sex', 'f', PARAM_ALPHA),
            'signs' => optional_param($role . 'signs', 0, PARAM_BOOL),
        ];
    }
    characters::save($entityid, $chosen);
    $notice = $str('sourcessaved');
}
$samples = ['memory' => ['memorynothing', ['query' => $str('charactersamplequery')]], 'herald' => ['heraldnew', []]];
$roles = [];
foreach (characters::of($entityid) as $role => $character) {
    [$key, $a] = $samples[$role];
    $preview = characters::say($entityid, $role, $key, $a) ?? ['name' => $character['name'],
        'text' => $str('characteroff'), 'portrait' => $character['portrait']];
    $roles[] = [
        'role' => $role,
        'title' => $str('characterrole' . $role),
        'job' => $str('characterjob' . $role),
        'enabled' => $character['enabled'],
        'name' => $character['name'],
        'female' => $character['sex'] === 'f',
        'male' => $character['sex'] === 'm',
        'herald' => $role === 'herald',
        'signs' => $character['signs'],
        'preview' => $preview,
    ];
}
$data = [
    'heading' => $str('characterstitle'),
    'intro' => $str('charactersintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'roles' => $roles,
];
foreach (
    ['characterenabled', 'charactername', 'charactersex', 'charactersexf', 'charactersexm', 'charactersigns',
        'characterpreview', 'sourcessave'] as $key
) {
    $data[$key] = $str($key);
}
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/characters', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
