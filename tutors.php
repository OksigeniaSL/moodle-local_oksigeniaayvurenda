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
 * The tutor of each group this school year.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\tutors;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/tutors.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$year = years::around((int) $library->entityid, time());
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $picked = optional_param_array('tutor', [], PARAM_INT);
    $names = optional_param_array('group', [], PARAM_TEXT);
    $candidates = tutors::candidates((int) $library->entityid);
    foreach ($names as $index => $group) {
        $userid = (int) ($picked[$index] ?? 0);
        if ($userid === 0 || isset($candidates[$userid])) {
            tutors::set((int) $year->id, $group, $userid);
        }
    }
    $notice = $str('sourcessaved');
}
$candidates = tutors::candidates((int) $library->entityid);
$rows = [];
foreach (tutors::groups((int) $library->entityid, (int) $year->id) as $index => $row) {
    $options = [['value' => 0, 'label' => $str('tutornone'), 'selected' => !$row['userid']]];
    foreach ($candidates as $userid => $name) {
        $options[] = ['value' => $userid, 'label' => $name, 'selected' => $userid === $row['userid']];
    }
    $rows[] = ['index' => $index, 'group' => $row['group'], 'options' => $options, 'proposed' => $row['proposed']];
}
$data = [
    'heading' => $str('tutorsheading', $year->name),
    'intro' => $str('tutorsintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'empty' => $str('readersempty'),
    'proposedlabel' => $str('tutorproposed'),
    'save' => $str('sourcessave'),
    'nocandidates' => $candidates ? '' : $str('tutornocandidates'),
];
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/tutors', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
