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
 * Weeding: the candidates, taking the chosen out of the collection, and the records to print and sign.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stocktake;
use local_oksigeniaayvurenda\local\weeding;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$recordid = optional_param('record', 0, PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/weed.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/weed.php', ['library' => $library->slug] + $params))
    ->out(false);
$now = time();
$date = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'));

if (data_submitted() && confirm_sesskey()) {
    $restore = optional_param('restore', 0, PARAM_INT);
    if ($restore) {
        weeding::restore($restore, $library, $now);
        redirect($url(['record' => $recordid]));
    }
    $chosen = optional_param_array('item', [], PARAM_INT);
    $why = optional_param_array('why', [], PARAM_ALPHA);
    $id = weeding::withdraw(
        $library,
        array_keys(array_filter($chosen)),
        optional_param('reason', '', PARAM_TEXT),
        $why,
        (int) $USER->id,
        $now
    );
    redirect($id ? $url(['record' => $id]) : $url());
}

if ($recordid) {
    // The record, to print and sign.
    $found = weeding::record($recordid, $library);
    $copies = array_map(fn($c) => $c + ['whylabel' => $str('weedwhy' . ($c['why'] ?: 'chosen'))], $found['copies']);
    $data = [
        'heading' => $str('weedrecordtitle'),
        'library' => format_string($library->name),
        'date' => $date((int) $found['record']->timecreated),
        'reason' => (string) $found['record']->reason,
        'count' => $str('weedrecordcount', count(array_filter($copies, fn($c) => !$c['restored']))),
        'copies' => $copies,
        'action' => $url(['record' => $recordid]),
        'sesskey' => sesskey(),
    ];
    foreach (
        ['weedrecordlibrary', 'weedrecorddate', 'weedrecordreason', 'weedcoltitle', 'callnumber', 'weedcolcode',
            'weedcolwhy', 'weedrestore', 'weedrestored', 'weedsignlibrarian', 'weedsignhead', 'printnow'] as $key
    ) {
        $data[$key] = $str($key);
    }
    shell::output(
        $str('weedrecordtitle'),
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/weedrecord', $data),
        $library,
        '',
        ['search' => false, 'nav' => [['url' => $url(), 'label' => $str('weedtitle')]]]
    );
    exit;
}

$filters = [
    'unused' => optional_param('unused', 5, PARAM_INT),
    'before' => optional_param('before', 0, PARAM_INT),
    'worn' => optional_param('worn', 1, PARAM_BOOL),
    'lost' => optional_param('lost', 1, PARAM_BOOL),
    'place' => optional_param('place', 0, PARAM_INT),
];
$found = weeding::candidates($library, $filters, $now);
$rows = array_map(fn($r) => $r + [
    'whylabel' => $str('weedwhy' . $r['why']),
    'lastlabel' => $r['lastloan'] ? $str('weedlastloan', $date($r['lastloan'])) : $str('weedneverlent'),
], $found['rows']);
$data = [
    'heading' => $str('weedtitle'),
    'intro' => $str('weedintro'),
    'self' => $url(),
    'action' => $url(),
    'sesskey' => sesskey(),
    'library' => $library->slug,
    'unused' => array_map(fn($y) => ['value' => $y, 'label' => $y ? $str('weedunusedyears', $y) : $str('weednofilter'),
        'selected' => (int) $filters['unused'] === $y], [0, 2, 3, 5, 10]),
    'before' => $filters['before'] ?: '',
    'worn' => (bool) $filters['worn'],
    'lost' => (bool) $filters['lost'],
    'places' => array_map(fn($p) => $p + ['selected' => (int) $filters['place'] === $p['id']], stocktake::places($library)),
    'total' => $str('weedtotal', $found['total']),
    'more' => $found['total'] > count($rows) ? $str('weedmore', $found['total'] - count($rows)) : '',
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'records' => array_map(fn($r) => $r + ['url' => $url(['record' => $r['id']]), 'when' => $date($r['time']),
        'countlabel' => $str('weedrecordcount', $r['copies'])], weeding::records($library)),
];
$data['hasrecords'] = (bool) $data['records'];
foreach (
    ['weedfilters', 'weedunused', 'weedbefore', 'weedworn', 'weedlost', 'weedplace', 'stocktakeall', 'weedshow',
        'weedall', 'weedreason', 'weedreasonhint', 'weedgo', 'weednone', 'weedrecords', 'weedcoltitle', 'callnumber',
        'weedcolcode', 'weedcolwhy'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('weedtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/weed', $data), $library, '', [
    'search' => false,
    'nav' => [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $library->slug]))->out(false),
            'label' => $str('stocktakenav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
            'label' => $str('addnav')],
    ],
]);
