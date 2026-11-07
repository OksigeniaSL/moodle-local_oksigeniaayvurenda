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
 * Ayvurenda: records of the same book, side by side, to join into one or to say they are not the same.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\duplicates;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/duplicates.php', ['library' => $ref]));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
$entityid = (int) $library->entityid;
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $ids = optional_param_array('ids', [], PARAM_INT);
    if (optional_param('do', '', PARAM_ALPHA) === 'apart') {
        duplicates::apart($entityid, $ids);
        $notice = $str('duplicatesapartdone');
    } else {
        $keep = required_param('keep', PARAM_INT);
        $joined = duplicates::join($entityid, $keep, $ids);
        $notice = $joined ? $str('duplicatesjoined', format_string((string) $DB->get_field(
            'local_oksigeniaayvurenda_record',
            'title',
            ['id' => $keep]
        ), true, ['escape' => false])) : '';
    }
}
$groups = duplicates::groups($entityid);
$size = 20;
$shown = array_slice($groups, $from, $size);
$covers = covers::urls(array_merge([], ...$shown));
$cards = [];
foreach ($shown as $n => $ids) {
    $records = array_map([duplicates::class, 'describe'], $ids);
    // The one with more copies stays, by default; with as many, the oldest.
    usort($records, fn($a, $b) => [-$a['copies'], $a['created']] <=> [-$b['copies'], $b['created']]);
    $cards[] = ['n' => $from + $n, 'records' => array_map(fn($r, $i) => [
        'id' => $r['id'],
        'title' => format_string($r['title'], true, ['escape' => false]),
        'subtitle' => format_string($r['subtitle'], true, ['escape' => false]),
        'authors' => implode(' · ', $r['authors']),
        'facts' => implode(' · ', array_filter([$r['publisher'], $r['year'], $r['isbn']])),
        'copies' => $r['copies'] === 1 ? $str('duplicatescopyone') : $str('duplicatescopies', screens::number($r['copies'])),
        'out' => $r['out'] ? $str('duplicatesout', screens::number($r['out'])) : '',
        'origin' => $r['imported'] ? $str('duplicatesimported') : $str(
            'duplicatesadded',
            zones::date($r['created'], 'strftimedate', $library)
        ),
        'summary' => $r['hassummary'] ? $str('duplicatessummary') : '',
        'url' => $link('record.php', ['id' => $r['id']]),
        'hascover' => isset($covers[$r['id']]),
        'coverurl' => $covers[$r['id']] ?? '',
        'colour' => $r['id'] % 6,
        'first' => $i === 0,
    ], $records, array_keys($records))];
}
$page = fn(int $start) => $link('duplicates.php', ['from' => $start]);
$data = [
    'heading' => $str('duplicatestitle'),
    'intro' => $groups ? $str('duplicatesintro', screens::number(count($groups))) : $str('duplicatesnone'),
    'notice' => $notice,
    'action' => $link('duplicates.php', ['from' => $from]),
    'sesskey' => sesskey(),
    'groups' => $cards,
    'pager' => count($groups) > $size ? [
        'prev' => $from > 0 ? $page(max(0, $from - $size)) : '',
        'next' => $from + $size < count($groups) ? $page($from + $size) : '',
        'line' => $str('readerspage', (object) ['from' => $from + 1, 'to' => min($from + $size, count($groups)),
            'total' => count($groups)]),
        'prevlabel' => $str('readersprev'),
        'nextlabel' => $str('readersnext'),
    ] : null,
];
foreach (['duplicateskeep', 'duplicatesjoin', 'duplicatesapart', 'duplicatesjoinhint'] as $key) {
    $data[$key] = $str($key);
}
shell::output(
    $str('duplicatestitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/duplicates', $data),
    $library,
    '',
    [
    'search' => false,
    'nav' => [['url' => $link('catalogued.php'), 'label' => $str('menucataloged')],
        ['url' => $link('subjects.php'), 'label' => $str('menusubjects')]],
    ]
);
