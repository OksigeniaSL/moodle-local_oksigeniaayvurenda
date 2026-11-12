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
 * Readers given their way into the library on the web: one, from their record, or a whole class; by e-mail, or on a sheet
 * to print that is shown once.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\entry;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$readerid = optional_param('reader', 0, PARAM_INT);
$group = optional_param('group', '', PARAM_TEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$params = ['library' => $ref] + ($readerid ? ['reader' => $readerid] : ['group' => $group]);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/entries.php', $params));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
$now = time();
if ($readerid) {
    $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $readerid, 'entityid' => $library->entityid]);
    if (!$reader || $reader->status === 'deleted') {
        shell::missing($str('readernothere'), $library);
    }
    $waiting = entry::may($reader) ? [$reader] : [];
    $backlabel = trim($reader->firstname . ' ' . $reader->lastname) ?: (string) $reader->cardcode;
    $back = (new moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $ref, 'id' => $readerid]))->out(false);
} else {
    $waiting = entry::waiting($library, $group, $now);
    $backlabel = $group;
    $back = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $ref, 'group' => $group]))->out(false);
}
$data = [
    'heading' => $str('entryheading'),
    'back' => $back,
    'backword' => $str('profileback'),
    'backlabel' => $backlabel,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'go' => $str($readerid ? 'entrybutton' : 'entrygroupgo'),
    'busy' => $str('entrybusy'),
    'sheets' => [],
    'hassheets' => false,
    'notice' => '',
    'ask' => '',
];
if ($waiting && data_submitted() && confirm_sesskey()) {
    $done = $readerid ? (function () use ($waiting) {
        $given = entry::give($waiting[0]);
        return ['mailed' => $given['state'] === 'mailed' ? 1 : 0, 'sheets' => $given['state'] === 'sheet' ? [$given] : [],
            'taken' => $given['state'] === 'taken' ? 1 : 0, 'email' => $given['email'] ?? ''];
    })() : entry::give_group($library, $group, $now);
    $site = (new moodle_url('/'))->out(false);
    $data['sheets'] = array_map(fn($sheet) => $sheet + [
        'title' => $str('entrysheettitle'),
        'libraryname' => format_string($library->name, true, ['escape' => false]),
        'userlabel' => $str('entrysheetuser'),
        'passwordlabel' => $str('entrysheetpassword'),
        'hint' => $str('entrysheethint', preg_replace('#^https?://#', '', rtrim($site, '/'))),
    ], $done['sheets']);
    $data['hassheets'] = (bool) $done['sheets'];
    $words = [];
    if ($done['mailed']) {
        $words[] = $readerid ? $str('entrydonemailed', $done['email'] ?? '')
            : $str($done['mailed'] === 1 ? 'entrygroupmailedone' : 'entrygroupmailed', $done['mailed']);
    }
    if ($done['sheets']) {
        $words[] = $str('entrygroupkeep');
    }
    if ($done['taken']) {
        $words[] = $str($readerid ? 'entrytaken' : 'entrygrouptaken', $done['taken']);
    }
    $data['notice'] = implode(' ', $words);
    $data['printlabel'] = $str('entrysheetprint');
} else if ($waiting) {
    $count = count($waiting);
    $data['ask'] = $readerid ? $str('entryhint') : $str(
        $count === 1 ? 'entrygroupintroone' : 'entrygroupintro',
        (object) ['count' => \local_oksigeniaayvurenda\local\screens::number($count), 'group' => $group]
    );
} else {
    $data['notice'] = $str($readerid ? 'entryalready' : 'entrygroupnone');
}
$html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/entries', $data);
// A page with passwords on it is not kept by the browser of a shared desk computer.
@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
shell::output($data['heading'], $html, $library, '', ['search' => false]);
