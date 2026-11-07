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

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/characters.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    // Each character has its own card: what is sent is only that one's, and the other keeps what it had.
    $only = optional_param('only', '', PARAM_ALPHA);
    $do = optional_param('do', 'save', PARAM_ALPHA);
    if (in_array($only, characters::ROLES, true)) {
        if ($do === 'save') {
            $chosen = [];
            foreach (characters::of($entityid) as $role => $character) {
                $chosen[$role] = $role !== $only ? ['enabled' => $character['enabled'], 'name' => $character['name'],
                    'sex' => $character['sex'], 'signs' => $character['signs']] : [
                    'enabled' => optional_param($role . 'enabled', 0, PARAM_BOOL),
                    'name' => optional_param($role . 'name', '', PARAM_TEXT),
                    'sex' => optional_param($role . 'sex', 'f', PARAM_ALPHA),
                    'signs' => optional_param($role . 'signs', 0, PARAM_BOOL),
                ];
            }
            characters::save($entityid, $chosen);
            $notice = $str('sourcessaved');
        } else if ($do === 'noportrait') {
            \local_oksigeniaayvurenda\local\images::remove('portrait', $entityid, $only);
            $notice = $str('sourcessaved');
        } else if ($do === 'nopack') {
            characters::remove_pack($entityid, $only);
            $notice = $str('sourcessaved');
        } else if ($do === 'upload') {
            // One place to drop it: a picture is its portrait; a zip, the whole character with its poses and puppet.
            $upload = $_FILES[$only . 'file'] ?? null;
            $ok = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($upload['tmp_name']);
            $zip = $ok && (string) file_get_contents($upload['tmp_name'], false, null, 0, 2) === 'PK';
            if (!$ok) {
                $notice = $str('characterpackbad');
            } else if ($zip) {
                $kept = characters::store_pack($entityid, $only, $upload['tmp_name']);
                $notice = $kept ? $str('characterpackkept', $kept) : $str('characterpackbad');
            } else {
                $stored = \local_oksigeniaayvurenda\local\images::store_upload($only . 'file', 'portrait', $entityid, $only);
                $notice = $stored === false ? $str('librarieslookbadimage') : $str('sourcessaved');
            }
        }
    }
}
$samples = ['memory' => ['memorynothing', ['query' => $str('charactersamplequery')]], 'herald' => ['heraldnew', []]];
$roles = [];
foreach (characters::of($entityid) as $role => $character) {
    [$key, $a] = $samples[$role];
    $preview = characters::say($entityid, $role, $key, $a) ?? ['name' => $character['name'],
        'text' => $str('characteroff'), 'initial' => $character['initial'], 'role' => $role];
    $roles[] = [
        'role' => $role,
        'title' => $str('characterrole' . $role . $character['sex']),
        'job' => $str('characterjob' . $role),
        'enabled' => $character['enabled'],
        'name' => $character['name'],
        'female' => $character['sex'] === 'f',
        'male' => $character['sex'] === 'm',
        'herald' => $role === 'herald',
        'signs' => $character['signs'],
        'own' => $character['own'],
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
        'characterpreview', 'sourcessave', 'characterportraithint', 'characternoportrait', 'characterpackhint',
        'characternopack', 'characterdrop', 'characterdropor', 'characterdrophint', 'characterzipwhat'] as $key
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
