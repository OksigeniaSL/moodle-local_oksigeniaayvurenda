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
 * Printing labels with the barcode, and spine labels: one sample sheet to choose, and a page of their own to print them all.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\label_sheets;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\labelpage;
use local_oksigeniaayvurenda\local\labels;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$kind = optional_param('kind', 'labels', PARAM_ALPHA) === 'spines' ? 'spines' : 'labels';
$which = optional_param('which', '', PARAM_ALPHA);
$since = optional_param('since', '', PARAM_RAW_TRIMMED);
$since = preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) ? $since : '';
$codes = optional_param('codes', '', PARAM_TEXT);
$sheet = labels::chosen($library, $kind, optional_param('sheet', '', PARAM_ALPHANUMEXT));
$format = label_sheets::placed($sheet);
$start = max(1, min($format['per'], optional_param('start', 1, PARAM_INT)));
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/labels.php', ['library' => $library->slug, 'kind' => $kind]));
$zone = \local_oksigeniaayvurenda\local\zones::of($library);
// Nothing added today: the page opens on the last day something came in (an import of last week), not on «0 copies».
if ($which === '' && !labels::items($library, 'today', 0, [])) {
    $last = (int) $DB->get_field_sql(
        'SELECT MAX(timecreated) FROM {local_oksigeniaayvurenda_item} WHERE libraryid = :libraryid',
        ['libraryid' => $library->id]
    );
    if ($last) {
        $which = 'since';
        $since = (new DateTimeImmutable('@' . $last))->setTimezone($zone)->format('Y-m-d');
    }
}
$which = in_array($which, ['today', 'since', 'codes'], true) ? $which : 'today';
$here = array_filter(['library' => $library->slug, 'kind' => $kind, 'sheet' => $sheet, 'which' => $which, 'since' => $since,
    'codes' => $codes, 'start' => $start > 1 ? $start : ''], fn($v) => $v !== '');
// The two things kept: a sheet among the favourites (or out of them), and the print moved half a millimetre.
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    $back = $here + ['saved' => $do];
    if ($do === 'fav') {
        labels::favourite($library, $kind, $sheet);
        redirect(new moodle_url('/local/oksigeniaayvurenda/labels.php', $back, 'ayv-et-hoja'));
    }
    if ($do === 'nudge') {
        [$x, $y] = labels::own($library)['nudge'][$sheet] ?? [0.0, 0.0];
        $move = optional_param('move', '', PARAM_ALPHA);
        [$x, $y] = match ($move) {
            'left' => [$x - .5, $y],
            'right' => [$x + .5, $y],
            'up' => [$x, $y - .5],
            'down' => [$x, $y + .5],
            default => [0.0, 0.0],
        };
        labels::nudge($library, $sheet, $x, $y);
        redirect(new moodle_url('/local/oksigeniaayvurenda/labels.php', $back + ['adjust' => 1], 'ayv-et-ajuste'));
    }
    redirect(new moodle_url('/local/oksigeniaayvurenda/labels.php', $here));
}
$from = 0;
if ($since !== '') {
    $from = (new DateTimeImmutable($since . ' 00:00', $zone))->getTimestamp();
}
$items = labels::items($library, $which, $from, preg_split('/[\s,;]+/', $codes) ?: []);
// One more than fit says there are more.
$toomany = count($items) > labels::LIMIT;
$items = array_slice($items, 0, labels::LIMIT);
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
$nav = [['url' => $url('add.php'), 'label' => $str('addnav')], ['url' => $url('desk.php'), 'label' => $str('desktitle')],
    ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'labels']))
        ->out(false), 'label' => $str('helpnav')]];

// Printing: every sheet on a page of its own, which opens the print window; the test sheet, its frames numbered.
if (optional_param('print', 0, PARAM_BOOL)) {
    $test = optional_param('test', 0, PARAM_BOOL);
    [$nudgex, $nudgey] = labels::own($library)['nudge'][$sheet] ?? [0.0, 0.0];
    $pages = $test ? [['labels' => array_map(fn($n) => ['blank' => true, 'testnumber' => $n], range(1, $format['per']))]]
        : labels::sheets($items, $sheet, $start);
    $data = [
        'says' => $test ? $str('labelstest') : (count($pages) === 1 ? $str('labelsprintone') : $str(
            'labelsprintn',
            \local_oksigeniaayvurenda\local\screens::number(count($pages))
        )),
        'hint' => $str('labelsprinthint'),
        'printnow' => $str('printnow'),
        'back' => (new moodle_url(
            '/local/oksigeniaayvurenda/labels.php',
            $here + ($test ? ['adjust' => 1] : []),
            $test ? 'ayv-et-ajuste' : 'ayv-et-hoja'
        ))->out(false),
        'backlabel' => $str('labelsprintback'),
        'pages' => ($test || $items) ? $pages : [],
        'spines' => $kind === 'spines',
        'letter' => $format['paper'] === 'letter',
        'test' => (bool) $test,
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
    shell::output(
        $str('labelsheading'),
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/labels_print', $data),
        $library,
        '',
        ['search' => false, 'scripts' => ['labels'], 'paper' => $format['paper'], 'nav' => $nav]
    );
    exit;
}

$data = labelpage::context(
    $library,
    ['kind' => $kind, 'which' => $which, 'since' => $since, 'codes' => $codes,
    'sheet' => $sheet, 'start' => $start, 'adjust' => optional_param('adjust', 0, PARAM_BOOL)],
    $items,
    $toomany,
    optional_param('saved', '', PARAM_ALPHA)
);
shell::output($data['title'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/labels', $data), $library, '', [
    'search' => false,
    'nav' => $nav,
]);
