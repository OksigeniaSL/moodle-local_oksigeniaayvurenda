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
 * Stocktakes of a library: starting one, reading the shelves, and what it found.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stocktake;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:catalogue', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $ref, 'id' => $id, 'view' => $view]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $library->slug] +
    $params))->out(false);
$now = time();
$notice = '';

// Starting one.
if (!$id && data_submitted() && confirm_sesskey()) {
    $name = trim(optional_param('name', '', PARAM_TEXT));
    $newid = stocktake::start($library, $name !== '' ? $name : $str('stocktakedefault', userdate(
        $now,
        get_string('strftimedate', 'langconfig')
    )), optional_param('location', 0, PARAM_INT) ?: null, (int) $USER->id, $now);
    redirect($url(['id' => $newid]));
}

if (!$id) {
    $list = [];
    foreach (stocktake::all($library) as $one) {
        $counts = stocktake::counts($one);
        $list[] = [
            'name' => $one->name,
            'url' => $url(['id' => $one->id] + ($one->status === 'open' ? [] : ['view' => 'results'])),
            'state' => $str($one->status === 'open' ? 'stocktakeopen' : 'stocktakeclosedon', userdate((int) ($one->timeclosed ?:
                $one->timecreated), get_string('strftimedateshort', 'langconfig'))),
            'progress' => $str('stocktakeprogress', (object) $counts),
        ];
    }
    $data = [
        'heading' => $str('stocktaketitle'),
        'intro' => $str('stocktakeintro'),
        'list' => $list,
        'haslist' => (bool) $list,
        'action' => $url(),
        'sesskey' => sesskey(),
        'places' => array_map(fn($p) => $p + ['label' => $p['label'] . ' (' . $p['count'] . ')'], stocktake::places($library)),
        'defaultname' => $str('stocktakedefault', userdate($now, get_string('strftimedate', 'langconfig'))),
    ];
    foreach (['stocktakenew', 'stocktakename', 'stocktakewhere', 'stocktakeall', 'stocktakestart', 'stocktakelist'] as $key) {
        $data[$key] = $str($key);
    }
    $main = $OUTPUT->render_from_template('local_oksigeniaayvurenda/stocktakes', $data);
    shell::output($str('stocktaketitle'), $main, $library, '', ['search' => false, 'nav' => [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/weed.php', ['library' => $library->slug]))->out(false),
            'label' => $str('weednav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
            'label' => $str('addnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
    ]]);
    exit;
}

$stocktake = stocktake::get($id, $library);
if (data_submitted() && confirm_sesskey() && $stocktake->status === 'open') {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'lose') {
        $notice = $str('stocktakelost', stocktake::lose($stocktake, $now));
    } else if ($do === 'recover') {
        $notice = $str('stocktakerecovered', stocktake::recover($stocktake, $now));
    } else if ($do === 'close') {
        stocktake::close($stocktake, $now);
        $stocktake = stocktake::get($id, $library);
        $notice = $str('stocktakeclosednow');
    }
    $view = 'results';
}

// The missing, as a spreadsheet.
if (optional_param('download', '', PARAM_ALPHA) === 'missing') {
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename(clean_filename($stocktake->name));
    $csv->add_data([$str('stocktakecoltitle'), $str('callnumber'), $str('stocktakecolcode'), $str('stocktakecolwhere')]);
    foreach (stocktake::results($stocktake)['missing'] as $copy) {
        $csv->add_data([$copy['title'], $copy['callnumber'], $copy['barcode'], $copy['where']]);
    }
    $csv->download_file();
    exit;
}

$counts = stocktake::counts($stocktake);
$scope = $stocktake->locationid ? (string) $DB->get_field(
    'local_oksigeniaayvurenda_authority',
    'label',
    ['id' => $stocktake->locationid]
) : $str('stocktakeall');
$nav = [['url' => $url(), 'label' => $str('stocktakelist')]];
if ($view === 'results' || $stocktake->status !== 'open') {
    // A page shows a few hundred of each list; the whole list of the missing is in the download.
    $shown = 300;
    $results = stocktake::results($stocktake, $shown);
    $data = [
        'heading' => $stocktake->name,
        'scope' => $scope,
        'notice' => $notice,
        'progress' => $str('stocktakeprogress', (object) $counts),
        'open' => $stocktake->status === 'open',
        'readurl' => $url(['id' => $id]),
        'action' => $url(['id' => $id]),
        'sesskey' => sesskey(),
        'downloadurl' => $url(['id' => $id, 'download' => 'missing']),
        'sections' => [],
    ];
    foreach (['missing', 'misplaced', 'lent', 'found'] as $kind) {
        $data['sections'][] = [
            'heading' => $str('stocktake' . $kind . 'heading', $counts[$kind]),
            'hint' => $str('stocktake' . $kind . 'hint'),
            'byplace' => $kind === 'missing' && count($results['missingbyplace']) > 1 ? implode(' · ', array_map(
                fn($p) => ($p['label'] !== '' ? $p['label'] : $str('stocktakenoplace')) . ': ' . $p['count'],
                $results['missingbyplace']
            )) : '',
            'more' => $counts[$kind] > $shown ? $str('stocktakemore', $counts[$kind] - $shown) : '',
            'copies' => $results[$kind],
            'hascopies' => (bool) $results[$kind],
            'empty' => $str('stocktakenone'),
            'lose' => $kind === 'missing' && $results[$kind] && $data['open'],
            'recover' => $kind === 'found' && $results[$kind] && $data['open'],
            'download' => $kind === 'missing' && $results[$kind],
        ];
    }
    $data['unknown'] = $results['unknown'] ? $str('stocktakeunknownlist', implode(', ', $results['unknown'])) : '';
    foreach (
        ['stocktakeback', 'stocktakelose', 'stocktakerecover', 'stocktakeclose', 'stocktakedownload',
            'stocktakecolwhere'] as $key
    ) {
        $data[$key] = $str($key);
    }
    $main = $OUTPUT->render_from_template('local_oksigeniaayvurenda/stocktakeresults', $data);
    shell::output($stocktake->name, $main, $library, '', ['search' => false, 'nav' => $nav]);
    exit;
}

// Reading the shelves.
$tools = shell::readers_tools($library);
$strings = [];
foreach (
    ['stocktakeseen', 'stocktakeagain', 'stocktakemisplaced', 'stocktakelent', 'stocktakefound', 'stocktakewithdrawn',
        'stocktakeotherlibrary', 'stocktakeunknown', 'stocktakepending', 'stocktakeoffline', 'stocktakereturn',
        'stocktakeprogress', 'deskbadletter'] as $key
) {
    $strings[$key] = $str($key);
}
$settings = json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]),
    true
) ?: [];
$data = [
    'heading' => $stocktake->name,
    'scope' => $scope,
    'prompt' => $str('stocktakeprompt'),
    'here' => $str('stocktakehere'),
    'nowhere' => $str('stocktakenowhere'),
    'places' => $stocktake->locationid ? [] : stocktake::places($library),
    'tools' => $tools['html'],
    'progress' => $str('stocktakeprogress', (object) $counts),
    'resultsurl' => $url(['id' => $id, 'view' => 'results']),
    'results' => $str('stocktakeresults'),
    'hint' => $str('stocktakehint'),
];
$data['hasplaces'] = (bool) $data['places'];
$main = $OUTPUT->render_from_template('local_oksigeniaayvurenda/stocktake', $data);
shell::output($stocktake->name, $main, $library, '', [
    'search' => false,
    'scripts' => ['camera', 'stocktake'],
    'nav' => $nav,
    'config' => [
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/stocktake_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'library' => $library->slug,
        'stocktake' => (int) $stocktake->id,
        'controlletter' => !empty($settings['controlletter']),
        'strings' => $strings + $tools['strings'],
    ] + $tools['config'],
]);
