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
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\memoir;
use local_oksigeniaayvurenda\local\screens;
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
$pct = fn(int $part, int $whole) => $whole ? (int) round(min($part, $whole) * 100 / $whole) : 0;
$figures = $report['figures'];
$before = $report['before'];
// Each figure big, and how it moved since the year before (an arrow, never a colour: more is not always better).
$delta = function (string $key) use ($figures, $before, $str) {
    if (!$before || !$before[$key]) {
        return false;
    }
    $change = (int) round(($figures[$key] - $before[$key]) * 100 / $before[$key]);
    $a = (object) ['change' => abs($change), 'year' => $before['name']];
    return ['text' => $str($change === 0 ? 'memoirsame' : ($change > 0 ? 'memoirup' : 'memoirdown'), $a),
        'up' => $change > 0, 'down' => $change < 0];
};
$tiles = [];
foreach (['loans', 'room', 'acquired', 'withdrawn', 'holdings'] as $key) {
    $tiles[] = ['value' => $num($figures[$key]), 'label' => $str('memoir' . $key), 'delta' => $delta($key)];
}
$loans = fn(int $n) => $n === 1 ? $str('memoirloanone') : $str('memoirloansof', $num($n));
// Of each stage or group, the part of its readers who came; and its loans.
$bars = fn(array $rows, string $name, string $whole) => array_map(fn($r) => [
    'label' => $r[$name],
    'width' => $pct($r['borrowers'], $r[$whole]),
    'value' => $str('memoircameof', (object) ['came' => $num($r['borrowers']), 'of' => $num($r[$whole])]),
    'loans' => $loans($r['loans']),
], $rows);
// The corners, against the one most books went out from.
$most = max(array_merge([1], array_column($report['corners'], 'loans')));
$corners = [];
foreach ($report['corners'] as $corner) {
    $art = screens::art('rincon-' . preg_replace('/[^a-z0-9]/', '', core_text::strtolower($corner['code'])));
    $corners[] = ['label' => $corner['corner'], 'width' => (int) round($corner['loans'] * 100 / $most),
        'value' => $num($corner['loans']), 'hasart' => (bool) $art, 'arturl' => $art ?: ''];
}
// What went out most, with its cover, or one of the house's colours with its title.
$covers = covers::urls(array_column($report['top'], 'id'));
$top = array_map(fn($t) => ['label' => $t['title'], 'value' => $loans($t['loans']), 'hascover' => isset($covers[$t['id']]),
    'coverurl' => $covers[$t['id']] ?? '', 'colour' => $t['id'] % 6], $report['top']);
$life = $report['life'];
$icon = fn(string $which) => [$which => true] + array_fill_keys(['sprout', 'reviews', 'voices', 'books', 'pupils'], false);
$came = $figures['readers'] ? [
    'value' => $pct($figures['came'], $figures['readers']) . ' %',
    'width' => $pct($figures['came'], $figures['readers']),
    'label' => $str('memoircame', (object) ['came' => $num($figures['came']), 'of' => $num($figures['readers'])]),
    'delta' => $before && $before['readers'] ? ['text' => $str('memoircamebefore', (object) [
        'share' => $pct($before['came'], $before['readers']), 'year' => $before['name']]), 'up' => false, 'down' => false] : false,
] : false;
$data = [
    'heading' => $str('memoirheading', $year->name),
    'centre' => format_string($DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid])),
    'intro' => $str('memoirintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'came' => $came,
    'tiles' => $tiles,
    'stages' => $bars($report['stages'], 'stage', 'readers'),
    'hasstages' => (bool) $report['stages'],
    'barsnote' => $str('memoirbarsnote'),
    'groups' => $bars($report['groups'], 'group', 'members'),
    'hasgroups' => (bool) $report['groups'],
    'top' => $top,
    'hastop' => (bool) $top,
    'corners' => $corners,
    'hascorners' => (bool) $corners,
    'life' => [
        ['value' => $num($life['challenges']), 'label' => $str('memoirchallenges')] + $icon('sprout'),
        ['value' => $num($life['reviews']), 'label' => $str('memoirreviews')] + $icon('reviews'),
        ['value' => $num($life['voices']), 'label' => $str('memoirvoices')] + $icon('voices'),
    ],
    'bank' => $report['bank'] ? [
        ['value' => $num($report['bank']['loans']), 'label' => $str('memoirbankloans')] + $icon('books'),
        ['value' => $num($report['bank']['pupils']), 'label' => $str('memoirbankpupils')] + $icon('pupils'),
    ] : [],
    'hasbank' => (bool) $report['bank'],
    'done' => $report['notes']['done'],
    'next' => $report['notes']['next'],
];
foreach (
    ['memoirfigures', 'memoirstages', 'memoirgroups', 'memoirtop', 'memoircorners', 'memoirlifeheading',
        'memoirbankheading', 'memoirdone', 'memoirnext', 'memoirsave', 'memoirprint', 'memoiranon'] as $key
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
