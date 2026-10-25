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
 * Printing labels with the barcode, and spine labels.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\labels;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$kind = optional_param('kind', 'labels', PARAM_ALPHA);
$which = optional_param('which', 'today', PARAM_ALPHA);
$sincetext = optional_param('since', '', PARAM_TEXT);
$codestext = optional_param('codes', '', PARAM_TEXT);
$sheet = optional_param('sheet', $kind === 'spines' ? '5x13' : '3x8', PARAM_ALPHANUMEXT);
$start = optional_param('start', 1, PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/labels.php', ['library' => $ref, 'kind' => $kind]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$kind = $kind === 'spines' ? 'spines' : 'labels';
$sheet = isset(labels::SHEETS[$sheet]) ? $sheet : '3x8';
$since = 0;
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $sincetext, $parts)) {
    $since = (int) make_timestamp((int) $parts[1], (int) $parts[2], (int) $parts[3]);
}
$items = labels::items(
    $library,
    in_array($which, ['today', 'since', 'codes'], true) ? $which : 'today',
    $since,
    preg_split('/[\s,;]+/', $codestext) ?: []
);
$format = labels::SHEETS[$sheet];
$data = [
    'heading' => $str($kind === 'spines' ? 'spinestitle' : 'labelstitle'),
    'action' => $PAGE->url->out_omit_querystring(),
    'library' => $library->slug,
    'kind' => $kind,
    'kinds' => array_map(
        fn($k) => ['value' => $k, 'label' => $str($k . 'title'), 'selected' => $k === $kind],
        ['labels', 'spines']
    ),
    'whiches' => array_map(
        fn($w) => ['value' => $w, 'label' => $str('labelswhich' . $w), 'selected' => $w === $which],
        ['today', 'since', 'codes']
    ),
    'since' => $sincetext,
    'codes' => $codestext,
    'sheets' => array_map(fn($s) => ['value' => $s, 'label' => $str('labelssheet', (object) labels::SHEETS[$s]),
        'selected' => $s === $sheet], array_keys(labels::SHEETS)),
    'start' => $start,
    'count' => $str('labelscount', count($items)),
    'pages' => labels::sheets($items, $sheet, $start),
    'haspages' => (bool) $items,
    'empty' => $str('labelsempty'),
    'spines' => $kind === 'spines',
    'style' => sprintf(
        '--cols: %d; --rows: %d; --w: %smm; --h: %smm; --top: %smm; --left: %smm;',
        $format['columns'],
        $format['rows'],
        $format['width'],
        $format['height'],
        $format['top'],
        $format['left']
    ),
];
foreach (
    ['labelskind', 'labelswhich', 'labelssince', 'labelscodes', 'labelssheetlabel', 'labelsstart', 'labelsshow',
        'printnow'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/labels', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => $url('add.php'), 'label' => $str('addnav')], ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'labels']))
            ->out(false), 'label' => $str('helpnav')]],
]);
