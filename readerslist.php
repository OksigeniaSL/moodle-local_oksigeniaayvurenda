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
 * Readers from a list: a CSV file from the school's program or a spreadsheet.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\readers\csv_list;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\csvlist;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readerslist.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$text = '';
$notice = '';
$step = 'paste';
if (data_submitted() && confirm_sesskey()) {
    $text = optional_param('csv', '', PARAM_RAW);
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $text = (string) file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 5 * 1024 * 1024);
    }
    $list = csv_list::read($text);
    $map = csv_list::guess($list['columns']);
    $chosen = optional_param_array('column', null, PARAM_ALPHANUMEXT);
    if ($chosen !== null) {
        $map = array_filter($chosen, fn($field) => in_array($field, csv_list::FIELDS, true));
    }
    if (optional_param('load', 0, PARAM_BOOL)) {
        $type = optional_param('type', 'student', PARAM_ALPHA);
        $stats = csvlist::load((int) $library->entityid, csv_list::readers($list['rows'], $map), $type);
        $notice = $str('readerslistloaded', (object) $stats);
        $text = '';
    } else if ($list['columns']) {
        $step = 'check';
    }
}
$data = ['heading' => $str('readerslisttitle'), 'intro' => $str('readerslistintro'), 'notice' => $notice,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'paste' => $step === 'paste', 'check' => null];
if ($step === 'check') {
    $columns = [];
    foreach ($list['columns'] as $index => $heading) {
        $options = [['value' => '', 'label' => $str('readerslistignore'), 'selected' => !isset($map[$index])]];
        foreach (csv_list::FIELDS as $field) {
            $options[] = ['value' => $field, 'label' => $str('readerslistfield' . $field),
                'selected' => ($map[$index] ?? '') === $field];
        }
        $columns[] = ['index' => $index, 'heading' => $heading, 'options' => $options];
    }
    $preview = array_map(
        fn($row) => ['cells' => array_map(fn($c) => ['value' => $c], $row)],
        array_slice($list['rows'], 0, 5)
    );
    $types = array_map(
        fn($t) => ['value' => $t, 'label' => $str('readertype' . $t), 'selected' => $t === 'student'],
        ['student', 'teacher', 'staff', 'family']
    );
    $data['check'] = ['columns' => $columns, 'preview' => $preview, 'types' => $types, 'csv' => $text,
        'count' => $str('readerslistcount', count(csv_list::readers($list['rows'], $map)))];
}
foreach (
    ['readerslistpaste', 'readerslistfile', 'readerslistnext', 'readerslistcolumns', 'readerslisttype',
        'readerslistload', 'readerslistpreview'] as $key
) {
    $data[$key] = $str($key);
}
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/readerslist', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
