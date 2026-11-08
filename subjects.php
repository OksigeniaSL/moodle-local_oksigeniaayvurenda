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
 * Ayvurenda: the subjects of a centre's catalogue as one vocabulary, with those that look like one to join.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\subjects;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$query = trim(optional_param('q', '', PARAM_TEXT));
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/subjects.php', ['library' => $ref]));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
$entityid = (int) $library->entityid;
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'join') {
        $keep = required_param('keep', PARAM_INT);
        $changed = subjects::join($entityid, $keep, optional_param_array('ids', [], PARAM_INT));
        $notice = $changed === 1 ? $str('subjectsjoinedone') : $str('subjectsjoined', screens::number($changed));
    } else if ($do === 'rename') {
        subjects::rename($entityid, required_param('id', PARAM_INT), required_param('label', PARAM_TEXT));
        $notice = $str('subjectsrenamed');
    }
}
$all = subjects::all($entityid);
$groups = [];
foreach (array_slice(subjects::alike($entityid), 0, 40) as $n => $group) {
    $groups[] = ['n' => $n, 'subjects' => array_map(fn($subject, $i) => ['id' => $subject['id'], 'label' => $subject['label'],
        'count' => $str($subject['count'] === 1 ? 'subjectsbookone' : 'subjectsbooks', screens::number($subject['count'])),
        'first' => $i === 0, 'url' => $link('search.php', ['subject' => $subject['id']])], $group, array_keys($group))];
}
$shown = $query === '' ? $all : array_values(array_filter($all, fn($subject) => str_contains(
    \local_oksigeniaayvurenda\core\text::normalise($subject['label']),
    \local_oksigeniaayvurenda\core\text::normalise($query)
)));
$size = 100;
$rows = array_map(
    fn($subject) => ['id' => $subject['id'], 'label' => $subject['label'],
    'count' => screens::number($subject['count']), 'url' => $link('search.php', ['subject' => $subject['id']])],
    array_slice($shown, $from, $size)
);
$page = fn(int $start) => $link('subjects.php', ['q' => $query, 'from' => $start]);
$data = [
    'heading' => $str('subjectstitle'),
    'notice' => $notice,
    'intro' => $str('subjectsintro', screens::number(count($all))),
    'action' => $link('subjects.php'),
    'sesskey' => sesskey(),
    'query' => $query,
    'groups' => $groups,
    'hasgroups' => (bool) $groups,
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'library' => $library->slug,
    'pager' => count($shown) > $size ? [
        'prev' => $from > 0 ? $page(max(0, $from - $size)) : '',
        'next' => $from + $size < count($shown) ? $page($from + $size) : '',
        'line' => $str('readerspage', (object) ['from' => $from + 1, 'to' => min($from + $size, count($shown)),
            'total' => count($shown)]),
        'prevlabel' => $str('readersprev'),
        'nextlabel' => $str('readersnext'),
    ] : null,
];
foreach (
    ['subjectsalike', 'subjectsalikehint', 'subjectsjoin', 'subjectskeep', 'subjectsallheading', 'subjectsfilter',
        'subjectsfilterbutton', 'subjectsrename', 'subjectsrenamesave', 'subjectsnone', 'subjectsnamecol', 'subjectssee'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('subjectstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/subjects', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => $link('add.php'), 'label' => $str('menuadd')],
        ['url' => $link('index.php'), 'label' => $str('deskcatalogue')]],
]);
