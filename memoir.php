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
 * The library's report of a school year, to print or copy into the centre's own: figures added up, and the team's words.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\memoir;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/memoir.php', ['library' => $ref]));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
// The year asked for, of this centre; else the one going on.
$yearid = optional_param('year', 0, PARAM_INT);
$year = $yearid ? $DB->get_record('local_oksigeniaayvurenda_year', ['id' => $yearid, 'entityid' => $entityid]) : null;
$year = $year ?: years::around($entityid, time());
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    memoir::save_notes($entityid, (int) $year->id, optional_param('done', '', PARAM_TEXT), optional_param('next', '', PARAM_TEXT));
    $notice = $str('memoirsaved');
}
$report = memoir::of($library, $year);
$num = fn(int $n) => number_format($n, 0, '', get_string('thousandssep', 'langconfig'));
$share = fn(int $part, int $whole) => $whole ? round($part * 100 / $whole) . ' %' : '—';
$figures = $report['figures'];
$before = $report['before'];
$rows = [];
foreach (['loans', 'borrowers', 'room', 'acquired', 'withdrawn', 'holdings'] as $key) {
    $rows[] = ['label' => $str('memoir' . $key), 'now' => $num($figures[$key]),
        'before' => $before ? $num($before[$key]) : '—'];
}
$data = [
    'heading' => $str('memoirheading', $year->name),
    'centre' => format_string($DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid])),
    'intro' => $str('memoirintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'yearname' => $year->name,
    'beforename' => $before['name'] ?? '—',
    'came' => $str('memoircame', (object) ['came' => $num($figures['borrowers']), 'of' => $num($figures['readers']),
        'share' => $share($figures['borrowers'], $figures['readers'])]),
    'figures' => $rows,
    'stages' => array_map(fn($s) => $s + ['sharecame' => $share($s['borrowers'], $s['readers'])], $report['stages']),
    'hasstages' => (bool) $report['stages'],
    'groups' => array_map(fn($g) => $g + ['sharecame' => $share($g['borrowers'], $g['members'])], $report['groups']),
    'hasgroups' => (bool) $report['groups'],
    'top' => $report['top'],
    'hastop' => (bool) $report['top'],
    'corners' => $report['corners'],
    'hascorners' => (bool) $report['corners'],
    'life' => $str('memoirlife', (object) $report['life']),
    'bank' => $report['bank'] ? $str('memoirbank', (object) $report['bank']) : '',
    'done' => $report['notes']['done'],
    'next' => $report['notes']['next'],
];
foreach (
    ['memoirfigures', 'memoirthisyear', 'memoirstages', 'memoirstage', 'memoirgroups', 'memoirgroup', 'memoirreaders',
        'memoircamecol', 'memoirloans', 'memoirtop', 'memoircorners', 'memoircorner', 'memoirlifeheading', 'memoirbankheading',
        'memoirdone', 'memoirnext', 'memoirsave', 'memoirprint', 'memoiranon', 'memoirtitle'] as $key
) {
    $data[$key] = $str($key);
}
$reports = (new moodle_url('/local/oksigeniaayvurenda/reports.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/memoir', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $reports, 'label' => $str('reportsnav')]]]
);
