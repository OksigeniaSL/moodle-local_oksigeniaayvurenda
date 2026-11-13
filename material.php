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
 * Material that is not a book: laptops, tablets, calculators, cameras, games… as tiles with their units, − and +.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\busy;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\materialshelf;
use local_oksigeniaayvurenda\local\plan_full;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/material.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$lines = fn(string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen'));
$notice = '';
$error = '';
$opened = optional_param('kind', '', PARAM_ALPHA);
// The «−» and «+» of a tile: one unit less (the last one in its place) or one more, where its units live; back to the tile.
if (data_submitted() && confirm_sesskey() && (optional_param('more', 0, PARAM_INT) || optional_param('less', 0, PARAM_INT))) {
    $more = optional_param('more', 0, PARAM_INT);
    $less = optional_param('less', 0, PARAM_INT);
    $thing = null;
    foreach (materialshelf::things($library) as $one) {
        $thing = $one['id'] === ($more ?: $less) ? $one : $thing;
    }
    if ($thing && $more) {
        try {
            cataloguer::add($library, ['record' => ['doctype' => 'device', 'title' => $thing['title']], 'authorities' => [],
                'extra' => []], ['count' => 1, 'locationid' => $thing['where'], 'itemtype' => 'device'], $thing['id']);
        } catch (plan_full | busy $e) {
            $error = $e->getMessage();
        }
    } else if ($thing) {
        materialshelf::less($library, $thing['id'], (int) $USER->id);
    }
    catalogue::purge($entityid);
    if (!$error) {
        redirect(new moodle_url('/local/oksigeniaayvurenda/material.php', ['library' => $library->slug,
            'saved' => 'm' . ($more ?: $less)], 'm' . ($more ?: $less)));
    }
} else if (data_submitted() && confirm_sesskey()) {
    // A new thing, from its dashed tile: its name already written, how many, where it lives and, folded, the rest.
    $name = trim(optional_param('name', '', PARAM_TEXT));
    $serials = $lines(optional_param('serials', '', PARAM_TEXT));
    $labels = $lines(optional_param('labels', '', PARAM_TEXT));
    $count = max(1, optional_param('count', 1, PARAM_INT), count($serials), count($labels));
    $kind = in_array($opened, materialshelf::KINDS, true) ? $opened : 'otra';
    $description = ['record' => ['doctype' => 'device', 'title' => $name], 'authorities' => [], 'extra' => array_filter([
        'kind' => $kind,
        'brand' => trim(optional_param('brand', '', PARAM_TEXT)),
        'model' => trim(optional_param('model', '', PARAM_TEXT)),
    ])];
    if ($name === '') {
        $error = $str('materialnoname');
    } else {
        try {
            $added = cataloguer::add($library, $description, [
                'count' => $count,
                'barcodes' => $labels,
                'serials' => $serials,
                'locationid' => optional_param('locationid', 0, PARAM_INT),
                'itemtype' => 'device',
                'notes' => trim(optional_param('notes', '', PARAM_TEXT)),
            ]);
            $title = $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $added['recordid']]);
            $notice = $str('materialadded', (object) ['title' => $title, 'count' => count($added['barcodes']),
                'codes' => implode(', ', $added['barcodes'])]) . ($added['ruled'] ? ' ' . $str('materialrule') : '');
            catalogue::purge($entityid);
            $opened = '';
        } catch (invalid_parameter_exception $e) {
            $error = $str('addtaken', preg_replace('/^.*taken:/', '', $e->debuginfo ?? ''));
        } catch (plan_full | busy $e) {
            $error = $e->getMessage();
        }
    }
}
$saved = optional_param('saved', '', PARAM_ALPHANUMEXT);
$places = cataloguer::locations($entityid);
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$tiles = [];
foreach (materialshelf::things($library) as $thing) {
    $codes = $thing['codes'];
    $tiles[] = [
        'id' => $thing['id'],
        'title' => $thing['title'],
        'drawing' => materialshelf::drawing($thing['kind']),
        'lives' => $thing['where'] && isset($places[$thing['where']]) ? $str('materiallives', $places[$thing['where']]) : '',
        'units' => $number($thing['units']),
        'unitsword' => $str($thing['units'] === 1 ? 'materialunit' : 'materialunits'),
        'lent' => $thing['lent'] ? $str($thing['lent'] === 1 ? 'materiallentone' : 'materiallent', $number($thing['lent'])) : '',
        'nonelent' => $str('materialnonelent'),
        'canless' => $thing['inplace'] > 0,
        'lesslabel' => $str('materialless', $thing['title']),
        'morelabel' => $str('materialmore1', $thing['title']),
        'data' => array_values(array_filter([
            !empty($thing['extra']['brand']) ? ['term' => $str('materialbrand'), 'value' => $thing['extra']['brand']] : null,
            !empty($thing['extra']['model']) ? ['term' => $str('materialmodel'), 'value' => $thing['extra']['model']] : null,
            $codes ? ['term' => $str('materialcodes'), 'value' => count($codes) > 2 ? $str('materialcodesrange', (object) [
                'first' => reset($codes), 'last' => end($codes)]) : implode(', ', $codes)] : null,
        ])),
        'labelsurl' => (new moodle_url('/local/oksigeniaayvurenda/labels.php', ['library' => $library->slug, 'which' => 'codes',
            'codes' => implode("\n", $codes)]))->out(false),
        'recordurl' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
            'id' => $thing['id']]))->out(false),
        'justsaved' => $saved === 'm' . $thing['id'],
    ];
}
$placechips = fn(string $kind) => array_merge([['value' => 0, 'label' => $str('addnolocation'), 'id' => 'ayv-mt-' . $kind . '-s0',
    'checked' => true]], array_map(fn($id, $label) => ['value' => $id, 'label' => $label, 'id' => 'ayv-mt-' . $kind . '-s' . $id,
    'checked' => false], array_keys($places), array_values($places)));
$new = [];
foreach (materialshelf::KINDS as $kind) {
    $new[] = [
        'kind' => $kind,
        'drawing' => materialshelf::drawing($kind),
        'label' => $str('materialkind' . $kind),
        'open' => $opened === $kind,
        'counts' => array_map(fn($n) => ['n' => $n, 'id' => 'ayv-mt-' . $kind . '-' . $n, 'checked' => $n === 1,
            'prev' => 'ayv-mt-' . $kind . '-' . max(1, $n - 1), 'next' => 'ayv-mt-' . $kind . '-' . min(50, $n + 1),
            'word' => $str($n === 1 ? 'materialunit' : 'materialunits')], range(1, 50)),
        'places' => $placechips($kind),
    ];
}
$entity = (int) $library->entityid;
$step = \local_oksigeniaayvurenda\local\characters::step($entity);
$data = [
    'heading' => $str('materialtitle'),
    'intro' => $str('materialintro'),
    'crier' => \local_oksigeniaayvurenda\local\characters::whole($entity) ? [
        'figure' => \local_oksigeniaayvurenda\local\characters::figure('herald', $saved !== '' ? 'celebra' : 'lee', $step),
        'puppet' => \local_oksigeniaayvurenda\local\characters::puppet('herald', $saved !== '' ? 'celebra' : 'lee', $step),
    ] : null,
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'savedword' => $str('hiwsaved'),
    'tiles' => $tiles,
    'hastiles' => (bool) $tiles,
    'new' => $new,
];
$words = ['materialhave', 'materialnone', 'materialother', 'materialotherhint', 'materialdata', 'materiallabelsprint',
    'materialrecord', 'materialname', 'materialhowmany', 'materialwhere', 'materialmorefolded', 'materialbrand', 'materialmodel',
    'materialserials', 'materiallabels', 'materialsave', 'materialcountlabel'];
foreach ($words as $key) {
    $data[$key] = $str($key);
}
// With nothing yet, there is no «another»: just «Add».
if (!$tiles) {
    $data['materialother'] = $str('materialsave');
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('materialtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/material', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => $url('add.php'), 'label' => $str('addnav')],
        ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'material']))
            ->out(false), 'label' => $str('helpnav')]],
]);
