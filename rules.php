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
 * The loan rules of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\rules;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/rules.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$sm = get_string_manager();
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $rows = [];
    foreach (optional_param_array('readertype', [], PARAM_ALPHANUMEXT) as $index => $readertype) {
        if (optional_param('remove' . $index, 0, PARAM_BOOL)) {
            continue;
        }
        $row = ['readertype' => $readertype];
        foreach (
            ['itemtype' => PARAM_ALPHANUMEXT, 'loandays' => PARAM_INT, 'maxloans' => PARAM_INT,
                'renewals' => PARAM_INT, 'renewdays' => PARAM_INT] as $field => $type
        ) {
            $row[$field] = optional_param_array($field, [], $type)[$index] ?? null;
        }
        // The empty row only counts when it was filled in.
        if ($index === 'new' && empty($row['loandays']) && $readertype === '') {
            continue;
        }
        $rows[] = $row;
    }
    $notice = $str('rulessaved', rules::save((int) $library->id, $rows));
}
$readertypes = $DB->get_fieldset_sql(
    'SELECT DISTINCT readertype FROM {local_oksigeniaayvurenda_reader} WHERE entityid = ?',
    [$library->entityid]
);
$readertypes = array_values(array_unique(array_merge(['*', 'student', 'teacher', 'staff'], $readertypes)));
$itemtypes = array_merge(['*'], cataloguer::itemtypes((int) $library->entityid));
$label = function (string $kind, string $value) use ($str, $sm): string {
    if ($value === '*') {
        return $str('rulesany');
    }
    return $sm->string_exists($kind . $value, 'local_oksigeniaayvurenda') ? $str($kind . $value) : $value;
};
$options = fn(array $values, string $kind, string $chosen) => array_map(fn($v) => ['value' => $v,
    'label' => $label($kind, $v), 'selected' => $v === $chosen], $values);
$rows = [];
foreach (rules::of((int) $library->id) as $index => $rule) {
    $rows[] = ['index' => $index, 'readertypes' => $options($readertypes, 'readertype', $rule['readertype']),
        'itemtypes' => $options($itemtypes, 'itemtype', $rule['itemtype']), 'loandays' => $rule['loandays'],
        'maxloans' => $rule['maxloans'], 'renewals' => $rule['renewals'], 'renewdays' => $rule['renewdays'],
        'removable' => true];
}
$rows[] = ['index' => 'new', 'readertypes' => $options($readertypes, 'readertype', '*'),
    'itemtypes' => $options($itemtypes, 'itemtype', '*'), 'loandays' => '', 'maxloans' => '', 'renewals' => '',
    'renewdays' => '', 'removable' => false, 'isnew' => true];
$parent = $library->parentid ? $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $library->parentid]) : null;
$data = [
    'heading' => $str('rulestitle'),
    'intro' => $str('rulesintro'),
    'inherited' => count($rows) > 1 ? '' : ($parent ? $str('rulesparent', format_string($parent->name))
        : $str('rulesdefault')),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
];
foreach (
    ['rulesreader', 'rulesitem', 'rulesdays', 'rulesmax', 'rulesrenewals', 'rulesrenewdays', 'rulesremove',
        'rulesnew', 'sourcessave'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('rulestitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/rules', $data), $library, '', [
    'search' => false,
    'nav' => [
        ['url' => $url('readers.php'), 'label' => $str('readers')],
        ['url' => $url('desk.php'), 'label' => $str('desktitle')],
    ],
]);
