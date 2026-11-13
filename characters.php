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

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
    // Each character on its own stage: a gesture changes only that one's thing (the curtain, how it speaks, the signature,
    // its name), and the other character keeps what it had.
    $only = optional_param('character', '', PARAM_ALPHA) ?: optional_param('only', '', PARAM_ALPHA);
    $do = optional_param('do', '', PARAM_ALPHA);
    if (in_array($only, characters::ROLES, true) && in_array($do, ['', 'name'], true)) {
        $chosen = [];
        foreach (characters::of($entityid) as $role => $character) {
            $chosen[$role] = ['enabled' => $character['enabled'], 'name' => $character['name'], 'sex' => $character['sex'],
                'signs' => $character['signs']];
        }
        $show = optional_param('show', -1, PARAM_INT);
        $chosen[$only]['enabled'] = $show === -1 ? $chosen[$only]['enabled'] : (bool) $show;
        $gender = optional_param('gender', '', PARAM_ALPHA);
        $chosen[$only]['sex'] = in_array($gender, ['f', 'm'], true) ? $gender : $chosen[$only]['sex'];
        $signs = optional_param('signs', -1, PARAM_INT);
        $chosen[$only]['signs'] = $signs === -1 ? $chosen[$only]['signs'] : (bool) $signs;
        if ($do === 'name') {
            $chosen[$only]['name'] = optional_param('name', '', PARAM_TEXT);
        }
        characters::save($entityid, $chosen);
        redirect(new moodle_url(
            '/local/oksigeniaayvurenda/characters.php',
            ['library' => $library->slug, 'saved' => $only],
            'ayv-pj-' . $only
        ));
    } else if (in_array($only, characters::ROLES, true)) {
        if ($do === 'reset' || $do === 'noportrait') {
            \local_oksigeniaayvurenda\local\images::remove('portrait', $entityid, $only);
        }
        if ($do === 'reset' || $do === 'nopack') {
            characters::remove_pack($entityid, $only);
        }
        if (in_array($do, ['reset', 'noportrait', 'nopack'], true)) {
            redirect(new moodle_url(
                '/local/oksigeniaayvurenda/characters.php',
                ['library' => $library->slug, 'saved' => $only],
                'ayv-pj-' . $only
            ));
        }
        if ($do === 'upload') {
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
// Each one big on its stage, saying one of its lines: the memory when it does not find something, the herald with the
// new books; off stage, the same figure in shadow behind the curtain, without its bubble.
$samples = ['memory' => ['memorynothing', ['query' => $str('charactersamplequery')]], 'herald' => ['heraldnew', []]];
$poses = ['memory' => 'piensa', 'herald' => 'saluda'];
$saved = optional_param('saved', '', PARAM_ALPHA);
$roles = [];
foreach (characters::of($entityid) as $role => $character) {
    [$key, $a] = $samples[$role];
    $said = $character['enabled'] ? characters::say($entityid, $role, $key, $a) : null;
    $figure = $said['figure'] ?? null;
    $figure = $figure ?: ($character['portrait'] ?? null) ?: characters::figure(
        $role,
        $poses[$role],
        characters::step($entityid)
    );
    $roles[] = [
        'role' => $role,
        'title' => $str('characterrole' . $role . $character['sex']),
        'job' => $str('characterjob' . $role),
        'enabled' => $character['enabled'],
        'name' => $character['name'],
        'figure' => $figure,
        'alt' => $character['name'] . ', ' . $str('characterrole' . $role . $character['sex']),
        'bubble' => $said['text'] ?? '',
        'female' => $character['sex'] === 'f',
        'herald' => $role === 'herald',
        'signs' => (bool) $character['signs'],
        'own' => $character['own'],
        'justsaved' => $saved === $role,
    ];
}
$data = [
    'heading' => $str('characterstitle'),
    'intro' => $str('charactersintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'savedword' => $str('hiwsaved'),
    'roles' => $roles,
];
foreach (
    ['charactername', 'charactersigns', 'characterdrop', 'characterdropor', 'characterdrophint', 'characterstageout',
        'characterstagein', 'charactertalks', 'charactersayf', 'charactersaym', 'characternamehint', 'characternamesave',
        'characterother', 'characterreset', 'sourcessave'] as $key
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
