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
 * The corners of a library as it names, orders and hides them: «Stories and poems» rather than «Language and
 * literature», in the order the children walk the room.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/corners.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $labels = optional_param_array('label', [], PARAM_TEXT);
    $orders = optional_param_array('order', [], PARAM_INT);
    $hidden = optional_param_array('hidden', [], PARAM_BOOL);
    // The order is the place in the list: an arrow moves a corner one place, and the places are written 10, 20 and on.
    $move = optional_param('move', '', PARAM_RAW_TRIMMED);
    $codes = array_keys($labels);
    usort($codes, fn($a, $b) => [$orders[$a] ?? 0, array_search($a, array_keys($labels))]
        <=> [$orders[$b] ?? 0, array_search($b, array_keys($labels))]);
    if (preg_match('/^(up|down):(.+)$/', $move, $m) && ($at = array_search($m[2], array_map('strval', $codes), true)) !== false) {
        $to = $m[1] === 'up' ? $at - 1 : $at + 1;
        if ($to >= 0 && $to < count($codes)) {
            [$codes[$at], $codes[$to]] = [$codes[$to], $codes[$at]];
        }
    }
    $corners = [];
    foreach ($codes as $i => $code) {
        $corners[$code] = ['label' => $labels[$code], 'order' => 10 * ($i + 1), 'hidden' => !empty($hidden[$code])];
    }
    catalogue::save_corners($library, $corners);
    $notice = $move !== '' ? '' : $str('cornerssaved');
}
// Each corner with its drawing, its place in the list and whether it can go up or down.
$list = array_values(catalogue::corners($library, true));
$rows = [];
foreach ($list as $i => $corner) {
    $rows[] = $corner + [
        'countlabel' => $str($corner['count'] === 1 ? 'onebook' : 'books', \local_oksigeniaayvurenda\local\screens::number(
            (int) $corner['count']
        )),
        'art' => \local_oksigeniaayvurenda\local\screens::art('rincon-'
            . preg_replace('/[^a-z0-9]/', '', core_text::strtolower((string) $corner['code']))),
        'first' => $i === 0,
        'last' => $i === count($list) - 1,
        'position' => $str('cornersposition', (object) ['at' => $i + 1, 'of' => count($list)]),
    ];
}
$data = [
    'heading' => $str('cornerstitle'),
    'intro' => $str('cornersintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'corners' => $rows,
    'hascorners' => (bool) $rows,
    'importurl' => (new moodle_url('/local/oksigeniaayvurenda/import.php', ['library' => $library->slug]))->out(false),
    'homeurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
];
foreach (
    ['cornersname', 'cornershide', 'cornerssave', 'cornersback', 'cornersup', 'cornersdown', 'cornersblank',
        'cornersnone', 'cornersimport'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('cornerstitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/corners', $data),
    $library,
    '',
    ['search' => false]
);
