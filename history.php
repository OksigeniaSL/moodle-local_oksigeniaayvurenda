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
 * Ayvurenda: everything a reader took out, year by year, for the library staff.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\profile;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$id = required_param('id', PARAM_INT);
$start = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
$own = $library && access::keeps($library);
if (!$own) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$back = array_filter(['group' => optional_param('group', '', PARAM_TEXT), 'type' => optional_param('type', '', PARAM_ALPHA),
    'list' => optional_param('list', '', PARAM_ALPHA), 'q' => optional_param('q', '', PARAM_TEXT)]);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/history.php', ['library' => $ref, 'id' => $id] + $back));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
if (!$library) {
    shell::missing($str('readernothere'));
}
$now = time();
$record = (new desk($library, (int) $USER->id, $now, $own))->allowed($id) ? profile::of($library, $id, $now) : null;
if (!$record) {
    shell::missing($str('readernothere'), $library);
}
\local_oksigeniaayvurenda\event\reader_viewed::fire((int) $library->entityid, $id);
// Whoever only keeps this library sees what it lent them.
$limited = $own && !access::can('local/oksigeniaayvurenda:desk', $library);
$total = profile::history_total($library, $id, $limited);
$loans = profile::history($library, $id, $start, $limited);
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$day = fn(int $time) => zones::date($time, 'strftimedate', $library);
$covers = covers::urls(array_column($loans, 'recordid'));
$notes = ['loved' => $str('noteloved'), 'unfinished' => $str('noteunfinished'), 'more' => $str('notemore')];
$places = count(array_unique(array_column($loans, 'library')));
$years = [];
foreach ($loans as $loan) {
    $years[$loan['year']] ??= ['year' => $loan['year'], 'rows' => []];
    $years[$loan['year']]['rows'][] = [
        'title' => format_string($loan['title'], true, ['escape' => false]),
        'url' => $link('record.php', ['id' => $loan['recordid']]),
        'hascover' => isset($covers[$loan['recordid']]),
        'coverurl' => $covers[$loan['recordid']] ?? '',
        'colour' => $loan['recordid'] % 6,
        'when' => $loan['returned'] ? $str('historyspan', (object) ['from' => $day($loan['lent']), 'to' => $day($loan['returned'])])
            : $str('historyout', (object) ['from' => $day($loan['lent']), 'due' => $day($loan['due'])]),
        'isout' => !$loan['returned'],
        'note' => $notes[$loan['note']] ?? '',
        'library' => $places > 1 ? $loan['library'] : '',
    ];
}
$page = fn(int $from) => $link('history.php', ['id' => $id] + $back + ['from' => $from]);
$size = profile::HISTORY;
$data = [
    'backurl' => $link('reader.php', ['id' => $id] + $back),
    'backlabel' => $record['name'],
    'backword' => $str('profileback'),
    'heading' => $str('historytitle'),
    'name' => $record['name'],
    'summary' => $total === 1 ? $str('historytotalone')
        : $str('historytotal', \local_oksigeniaayvurenda\local\screens::number($total)),
    'years' => array_values($years),
    'empty' => $total ? '' : $str('historyempty'),
    'pager' => $total > $size ? [
        'prev' => $start > 0 ? $page(max(0, $start - $size)) : '',
        'next' => $start + $size < $total ? $page($start + $size) : '',
        'line' => $str('readerspage', (object) ['from' => $start + 1, 'to' => min($start + $size, $total), 'total' => $total]),
        'prevlabel' => $str('readersprev'),
        'nextlabel' => $str('readersnext'),
    ] : null,
];
shell::output(
    $str('historytitle') . ': ' . $record['name'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/reader_history', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $link('desk.php'), 'label' => $str('desktitle')],
        ['url' => $link('readers.php'), 'label' => $str('readers')]]]
);
