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

use local_oksigeniaayvurenda\core\label_sheets;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\labels;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$kind = optional_param('kind', 'labels', PARAM_ALPHA);
$which = optional_param('which', 'today', PARAM_ALPHA);
$sincetext = optional_param('since', '', PARAM_TEXT);
$codestext = optional_param('codes', '', PARAM_TEXT);
$asked = optional_param('sheet', '', PARAM_ALPHANUMEXT);
$test = optional_param('test', 0, PARAM_BOOL);
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
$sheet = labels::chosen($library, $kind, $asked);
$which = in_array($which, ['today', 'since', 'codes'], true) ? $which : 'today';
// Nothing added today: the page opens on the last day something came in (an import of last week), not on «0 copies».
if (optional_param('which', '', PARAM_ALPHA) === '' && !labels::items($library, 'today', 0, [])) {
    $last = (int) $DB->get_field_sql(
        'SELECT MAX(timecreated) FROM {local_oksigeniaayvurenda_item} WHERE libraryid = :libraryid',
        ['libraryid' => $library->id]
    );
    if ($last) {
        $which = 'since';
        $sincetext = (new DateTimeImmutable('@' . $last))->setTimezone(core_date::get_user_timezone_object())->format('Y-m-d');
    }
}
$here = ['library' => $library->slug, 'kind' => $kind, 'sheet' => $sheet, 'which' => $which, 'since' => $sincetext,
    'codes' => $codestext];
// A favourite marked or unmarked, or the print moved for this sheet: kept for the library, and back to the page.
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'fav') {
        labels::favourite($library, $kind, $sheet);
    } else if ($do === 'nudge') {
        labels::nudge($library, $sheet, optional_param('x', 0, PARAM_FLOAT), optional_param('y', 0, PARAM_FLOAT));
    }
    redirect(new moodle_url('/local/oksigeniaayvurenda/labels.php', $here));
}
$own = labels::own($library);
[$nudgex, $nudgey] = $own['nudge'][$sheet] ?? [0.0, 0.0];
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
// One more than fit says there are more.
$toomany = count($items) > labels::LIMIT;
$items = array_slice($items, 0, labels::LIMIT);
$format = label_sheets::placed($sheet);
$mm = fn(float $value) => format_float($value, 2, true, true);
$name = fn(string $key) => $str('labelsmodel', (object) ['name' => label_sheets::MODELS[$key]['brand'] . ' '
    . label_sheets::MODELS[$key]['ref'], 'size' => $mm(label_sheets::MODELS[$key]['w']) . ' × '
    . $mm(label_sheets::MODELS[$key]['h']), 'per' => label_sheets::MODELS[$key]['cols'] * label_sheets::MODELS[$key]['rows']])
    . (label_sheets::MODELS[$key]['also'] !== '' ? ' · ' . label_sheets::MODELS[$key]['also'] : '');
// The favourites of this kind first, then every sheet by its brand (and the US Letter ones apart).
$option = fn(string $key) => ['value' => $key, 'label' => $name($key), 'selected' => $key === $sheet];
$groups = [];
if ($own['favs'][$kind]) {
    $groups[] = ['label' => $str('labelsfavs'), 'options' => array_map($option, $own['favs'][$kind])];
}
foreach (label_sheets::MODELS as $key => $model) {
    $group = $model['brand'] . ($model['paper'] === 'letter' ? ' · ' . $str('labelsletter') : '');
    $groups[$group] ??= ['label' => $group, 'options' => []];
    $groups[$group]['options'][] = $option($key);
}
$isfav = in_array($sheet, $own['favs'][$kind], true);
$data = [
    'heading' => $str($kind === 'spines' ? 'spinestitle' : 'labelstitle'),
    'action' => $PAGE->url->out_omit_querystring(),
    'library' => $library->slug,
    'kind' => $kind,
    'kinds' => array_map(
        fn($k) => ['value' => $k, 'label' => $str($k . 'title'), 'selected' => $k === $kind, 'isspine' => $k === 'spines'],
        ['labels', 'spines']
    ),
    'whiches' => array_map(
        fn($w) => ['value' => $w, 'label' => $str('labelswhich' . $w), 'selected' => $w === $which],
        ['today', 'since', 'codes']
    ),
    'since' => $sincetext,
    'istoday' => !in_array($which, ['since', 'codes'], true),
    'issince' => $which === 'since',
    'iscodes' => $which === 'codes',
    'cols' => $format['cols'],
    'cells' => array_map(fn($n) => ['n' => $n, 'used' => $n < $start, 'first' => $n === $start,
        'label' => $str('labelsstartat', $n)], range(1, $format['per'])),
    'codes' => $codestext,
    'sheetgroups' => array_values($groups),
    'sheet' => $sheet,
    'sesskey' => sesskey(),
    'which' => $which,
    'isfav' => $isfav,
    'favbutton' => $str($isfav ? 'labelsunfav' : 'labelsfav'),
    'nudgex' => $nudgex,
    'nudgey' => $nudgey,
    'nudged' => $nudgex || $nudgey ? $str('labelsnudged', implode($str('labelsand'), array_filter([
        $nudgex ? $str($nudgex > 0 ? 'labelsnudgeright' : 'labelsnudgeleft', $mm(abs($nudgex))) : '',
        $nudgey ? $str($nudgey > 0 ? 'labelsnudgedown' : 'labelsnudgeup', $mm(abs($nudgey))) : '',
    ]))) : '',
    'testurl' => (new moodle_url('/local/oksigeniaayvurenda/labels.php', $here + ['test' => 1]))->out(false),
    'test' => (bool) $test,
    'start' => $start,
    'count' => $str('labelscount', count($items)),
    'toomany' => $toomany ? $str('labelstoomany', labels::LIMIT) : '',
    'pages' => $test ? [['labels' => array_map(fn($n) => ['blank' => true, 'testnumber' => $n], range(1, $format['per']))]]
        : labels::sheets($items, $sheet, $start),
    'haspages' => $test || (bool) $items,
    'empty' => $str('labelsempty'),
    'spines' => $kind === 'spines',
    'letter' => $format['paper'] === 'letter',
    'style' => sprintf(
        '--cols: %d; --rows: %d; --w: %smm; --h: %smm; --top: %smm; --left: %smm; --gx: %smm; --gy: %smm; --dx: %smm; '
            . '--dy: %smm;',
        $format['cols'],
        $format['rows'],
        $format['w'],
        $format['h'],
        $format['top'],
        $format['left'],
        $format['gx'],
        $format['gy'],
        $nudgex,
        $nudgey
    ),
];
foreach (
    ['labelskind', 'labelswhich', 'labelssince', 'labelscodes', 'labelssheetlabel', 'labelsstart', 'labelsshow',
        'printnow', 'labelsmaphint', 'labelsnudge', 'labelsnudgetitle', 'labelsnudgehint', 'labelsnudgex', 'labelsnudgey',
        'labelstest', 'labelstesthint', 'sourcessave', 'offsiteclose'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/labels', $data), $library, '', [
    'search' => false,
    'scripts' => ['labels'],
    'paper' => $format['paper'],
    'nav' => [['url' => $url('add.php'), 'label' => $str('addnav')], ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'labels']))
            ->out(false), 'label' => $str('helpnav')]],
]);
