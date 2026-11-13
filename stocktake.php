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

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stockplan;
use local_oksigeniaayvurenda\local\stocktake;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$id = optional_param('id', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $ref, 'id' => $id, 'view' => $view]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $library->slug] +
    $params))->out(false);
$now = time();
$notice = '';

// Starting one: touching a shelf, or the whole library; it names itself with the day and what it covers.
if (!$id && data_submitted() && confirm_sesskey()) {
    $location = optional_param('location', 0, PARAM_INT);
    $label = '';
    foreach (stocktake::places($library) as $place) {
        $label = $place['id'] === $location ? $place['label'] : $label;
    }
    $name = trim(optional_param('name', '', PARAM_TEXT));
    $newid = stocktake::start(
        $library,
        $name !== '' ? $name : stockplan::name($library, $now, $label),
        $location ?: null,
        (int) $USER->id,
        $now
    );
    redirect($url(['id' => $newid]));
}

$seenof = fn($counts) => ['seen' => screens::number((int) ((array) $counts)['seen']),
    'of' => $str('stocktakecifra', screens::number((int) ((array) $counts)['expected']))];
$scopeof = fn($one) => $one->locationid ? (string) $DB->get_field(
    'local_oksigeniaayvurenda_authority',
    'label',
    ['id' => $one->locationid]
) : $str('stocktakeall');
// The herald by the title, reading while the page is still.
$crier = function (string $pose) use ($library) {
    $entityid = (int) $library->entityid;
    if (!characters::whole($entityid)) {
        return null;
    }
    $step = characters::step($entityid);
    return ['figure' => characters::figure('herald', $pose, $step), 'puppet' => characters::puppet('herald', $pose, $step)];
};
if (!$id) {
    // The ones done before, as minutes: the open one first.
    $minutes = [];
    foreach (stocktake::all($library) as $one) {
        $counts = stocktake::counts($one);
        $open = $one->status === 'open';
        $minutes[] = [
            'name' => $one->name,
            'url' => $url(['id' => $one->id] + ($open ? [] : ['view' => 'results'])),
            'open' => $open,
            'state' => $open ? $str('stocktakeactaopen', (object) ['scope' => $scopeof($one),
                'seen' => screens::number((int) $counts['seen']), 'expected' => screens::number((int) $counts['expected'])])
                : $str('stocktakeactaclosed', (object) ['scope' => $scopeof($one), 'date' => zones::date(
                    (int) ($one->timeclosed ?: $one->timecreated),
                    'strftimedate',
                    $library
                )]),
            'line' => $open ? $str('stocktakeactacontinue') : $str('stocktakeactamissing', (object) [
                'missing' => screens::number((int) $counts['missing']), 'expected' => screens::number((int) $counts['expected'])]),
        ];
    }
    $furniture = stockplan::furniture($library);
    $total = array_sum(array_column($furniture, 'count'));
    $data = [
        'heading' => $str('stocktaketitle'),
        'intro' => $str('stocktakestartintro'),
        'crier' => $crier('lee'),
        'action' => $url(),
        'sesskey' => sesskey(),
        'whole' => ['label' => $str('stocktakeall'), 'says' => $str('stocktakewholecount', (object) [
            'books' => '<b>' . screens::number($total) . '</b>', 'places' => count($furniture)]),
            'spines' => stockplan::spines(0, 0), 'quiet' => true],
        'furniture' => array_map(fn($f) => $f + ['says' => $str('stocktakebooks', '<b>' . $f['countlabel'] . '</b>'),
            'quiet' => true], $furniture),
        'before' => $str('stocktakebefore'),
        'minutes' => $minutes,
        'hasminutes' => (bool) $minutes,
    ];
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
    $place = optional_param('place', -1, PARAM_INT);
    foreach (stocktake::results($stocktake, 0, $place >= 0 ? $place : null)['missing'] as $copy) {
        $csv->add_data(\local_oksigeniaayvurenda\core\spreadsheet::row([$copy['title'], $copy['callnumber'], $copy['barcode'],
            $copy['where']]));
    }
    $csv->download_file();
    exit;
}

$counts = stocktake::counts($stocktake);
$scope = $scopeof($stocktake);
$nav = [['url' => $url(), 'label' => $str('stocktakelist')]];
$number = fn($n) => screens::number((int) $n);
if ($view === 'results' || $stocktake->status !== 'open') {
    // The plan says how many are missing from each shelf; touching one opens its list. Fifty at a time, «See all», and
    // the whole list in the download.
    $place = optional_param('place', -1, PARAM_INT);
    $place = $place >= 0 ? $place : null;
    $shown = max(50, min(5000, optional_param('show', 50, PARAM_INT)));
    $results = stocktake::results($stocktake, $shown, $place);
    $missingat = array_column($results['missingbyplace'], 'count', 'place');
    // While it is open nothing is missing yet, only not read yet: in quiet ink; «Missing», in coral, once closed.
    $open = $stocktake->status === 'open';
    $labels = array_column($results['missingbyplace'], 'label', 'place');
    $furniture = [];
    foreach (stockplan::furniture($library, $stocktake) as $f) {
        $missing = (int) ($missingat[$f['id']] ?? 0);
        $furniture[] = $f + ['url' => $url(['id' => $id, 'view' => 'results', 'place' => $f['id']]) . '#ayv-stock-missing',
            'here' => $place === $f['id'],
            'missing' => $missing === 0 ? $str($open ? 'stocktakeplaceallread' : 'stocktakeplacenone')
                : $str(
                    $open ? 'stocktakeunread' : ($missing === 1 ? 'stocktakeplacemissingone' : 'stocktakeplacemissing'),
                    $number($missing)
                ),
            'nonemissing' => $missing === 0, 'unread' => $open && $missing > 0];
    }
    $missinghere = $place === null ? (int) $counts['missing'] : (int) ($missingat[$place] ?? 0);
    $placelabel = $place === null ? '' : ($labels[$place] ?? '');
    if ($place !== null && $placelabel === '') {
        foreach ($furniture as $f) {
            $placelabel = $f['id'] === $place ? $f['label'] : $placelabel;
        }
    }
    $line = fn($copy) => ['title' => $copy['title'], 'note' => implode(' · ', array_filter([$copy['callnumber'],
        $copy['barcode'], $copy['readat'] !== '' ? $str('stocktakecolwhere') . ': ' . $copy['readat'] : '']))];
    $data = [
        'heading' => $stocktake->name,
        'intro' => $str($stocktake->status === 'open' ? 'stocktakeresultsintroopen' : 'stocktakeresultsintro', (object) [
            'scope' => $scope, 'date' => zones::date((int) ($stocktake->timeclosed ?: $now), 'strftimedate', $library)]),
        'crier' => $crier('lee'),
        'notice' => $notice,
        'lead' => $open ? ((int) $counts['missing'] === 0 ? $str('stocktakeplaceallread')
            : $str('stocktakesumleadopen', $number($counts['missing']))) : match ((int) $counts['missing']) {
                0 => $str('stocktakeplacenone'),
                1 => $str('stocktakesumleadone'),
                default => $str('stocktakesumlead', $number($counts['missing'])),
            },
        'rest' => $str('stocktakesumrest', (object) ['expected' => $number($counts['expected']),
            'misplaced' => $number($counts['misplaced']), 'lent' => $number($counts['lent']),
            'found' => $number($counts['found'])]),
        'furniture' => $furniture,
        'hasfurniture' => count($furniture) > 1 || $place !== null,
        'open' => $stocktake->status === 'open',
        'readurl' => $url(['id' => $id]),
        'action' => $url(['id' => $id]),
        'sesskey' => sesskey(),
        'sections' => [],
    ];
    // With several shelves and none touched, the missing wait on the plan: each shelf opens its own list, and the
    // download has them all.
    $choose = $place === null && count($furniture) > 1;
    foreach (['missing', 'misplaced', 'lent', 'found'] as $kind) {
        $total = $kind === 'missing' ? $missinghere : (int) $counts[$kind];
        $folded = $kind === 'missing' && $choose && $total > 0;
        $data['sections'][] = [
            'heading' => $kind === 'missing' && $placelabel !== ''
                ? $str($open ? 'stocktakeunreadin' : 'stocktakemissingin', (object) ['place' => $placelabel,
                    'count' => $number($total)])
                : $str($kind === 'missing' && $open ? 'stocktakeunread' : 'stocktake' . $kind . 'heading', $number($total)),
            'anchor' => 'ayv-stock-' . $kind,
            'hint' => $str('stocktake' . $kind . 'hint'),
            'lines' => $folded ? [] : array_map($line, $results[$kind]),
            'haslines' => !$folded && $results[$kind],
            'empty' => $folded ? $str('stocktakepickshelf') : $str('stocktakenone'),
            'allurl' => !$folded && $total > count($results[$kind])
                ? $url(['id' => $id, 'view' => 'results', 'show' => min(5000, $total)]
                + ($place !== null ? ['place' => $place] : [])) . '#ayv-stock-' . $kind : '',
            'alllabel' => $str('stocktakeseeall', $number($total)),
            'downloadurl' => $kind === 'missing' && $results[$kind]
                ? $url(['id' => $id, 'download' => 'missing'] + ($place !== null ? ['place' => $place] : [])) : '',
            'lose' => $kind === 'missing' && $results[$kind] && $data['open'] && $place === null,
            'recover' => $kind === 'found' && $results[$kind] && $data['open'],
        ];
    }
    $data['unknown'] = $results['unknown'] ? $str('stocktakeunknownlist', implode(', ', $results['unknown'])) : '';
    foreach (['stocktakeback', 'stocktakelose', 'stocktakerecover', 'stocktakeclose', 'stocktakedownloadsheet'] as $key) {
        $data[$key] = $str($key);
    }
    $main = $OUTPUT->render_from_template('local_oksigeniaayvurenda/stocktakeresults', $data);
    shell::output($stocktake->name, $main, $library, '', ['search' => false, 'nav' => $nav]);
    exit;
}

// Reading the shelves: the reader with how it goes, the plan where one says where one stands, and the last reads.
$tools = shell::readers_tools($library);
$strings = [];
foreach (
    ['stocktakeseen', 'stocktakeagain', 'stocktakemisplaced', 'stocktakelent', 'stocktakefound', 'stocktakewithdrawn',
        'stocktakeotherlibrary', 'stocktakeunknown', 'stocktakepending', 'stocktakeoffline', 'stocktakesession', 'stocktakeserver',
        'stocktakereturn', 'stocktakeprogress', 'stocktakecifra', 'deskbadletter'] as $key
) {
    $strings[$key] = $str($key);
}
$settings = json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]),
    true
) ?: [];
$furniture = stockplan::furniture($library, $stocktake);
$data = [
    'heading' => $stocktake->name,
    'intro' => $str($stocktake->locationid ? 'stocktakereadintroone' : 'stocktakereadintro', $scope),
    'crier' => $crier('lee'),
    'prompt' => $str('stocktakeprompt'),
    'cifra' => $seenof($counts),
    'tools' => $tools['html'],
    'where' => $str('stocktakewhereyouare'),
    'youarehere' => $str('stocktakeyouarehere'),
    'nowhere' => $str('stocktakenowhere'),
    'pick' => !$stocktake->locationid,
    'furniture' => array_map(fn($f) => $f + ['of' => $str('stocktakeplaceof', $f['countlabel']),
        'here' => (int) $stocktake->locationid === $f['id']], $furniture),
    'last' => $str('stocktakelast'),
    'resultsurl' => $url(['id' => $id, 'view' => 'results']),
    'results' => $str('stocktakeresults'),
    'hint' => $str('stocktakehint'),
];
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
        'place' => (int) $stocktake->locationid,
        'controlletter' => !empty($settings['controlletter']),
        'strings' => $strings + $tools['strings'],
    ] + $tools['config'],
]);
