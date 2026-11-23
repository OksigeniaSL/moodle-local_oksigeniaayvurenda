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
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$recordid = optional_param('record', 0, PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/weed.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/weed.php', ['library' => $library->slug] + $params
    + (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])))->out(false);
$now = time();
$date = fn(int $time) => zones::date($time, 'strftimedate', $library);

if (data_submitted() && confirm_sesskey()) {
    $restore = optional_param('restore', 0, PARAM_INT);
    if ($restore) {
        weeding::restore($restore, $library, $now);
        redirect($url(['record' => $recordid]));
    }
    $do = optional_param('do', '', PARAM_ALPHA);
    // The box: a copy in or out with a touch, a whole shelf of this page in, or emptied; it stays between pages and days.
    if ($do === 'box') {
        $box = optional_param('box', 0, PARAM_INT);
        if ($box) {
            weeding::box_change($library, [$box], 'toggle');
            redirect($PAGE->url->out(false) . '#ayv-ex-' . $box);
        }
        $shelf = optional_param('boxshelf', '', PARAM_ALPHANUM);
        if ($shelf !== '') {
            $boxfilters = ['unused' => max(0, optional_param('unused', 5, PARAM_INT)),
                'before' => max(0, optional_param('before', 0, PARAM_INT)), 'worn' => 1, 'lost' => 1,
                'place' => optional_param('place', 0, PARAM_INT)];
            $boxwhy = optional_param('why', 'unused', PARAM_ALPHA);
            $rows = array_values(array_filter(
                weeding::candidates($library, $boxfilters, $now, 0)['rows'],
                fn($row) => $row['why'] === $boxwhy
            ));
            $page = max(0, optional_param('page', 0, PARAM_INT));
            $onpage = array_slice($rows, $page * 50, 50);
            weeding::box_change($library, array_column(
                array_filter($onpage, fn($row) => 'p' . (int) $row['locationid'] === $shelf),
                'id'
            ), 'in');
        }
        redirect($PAGE->url->out(false) . '#ayv-ex-caja');
    }
    if ($do === 'boxempty') {
        weeding::box_change($library, [], 'empty');
        redirect($PAGE->url->out(false));
    }
    // The record is made of what is in the box.
    $id = weeding::withdraw($library, weeding::box($library), optional_param('reason', '', PARAM_TEXT), [], (int) $USER->id, $now);
    if ($id) {
        weeding::box_change($library, [], 'empty');
    }
    redirect($id ? $url(['record' => $id]) : $url());
}

if ($recordid) {
    // The record, to print and sign.
    $found = weeding::record($recordid, $library);
    $copies = array_map(fn($c) => $c + ['whylabel' => $str('weedwhy' . ($c['why'] ?: 'chosen'))], $found['copies']);
    $data = [
        'heading' => $str('weedrecordtitle'),
        'library' => format_string($library->name, true, ['escape' => false]),
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
        ['search' => false, 'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => $url(),
            'label' => $str('weedtitle')]]]
    );
    exit;
}

// Every candidate, each by its reason (lost, worn, old, unused): the cards count them and the list shows one reason.
$filters = [
    'unused' => max(0, optional_param('unused', 5, PARAM_INT)),
    'before' => max(0, optional_param('before', 0, PARAM_INT)),
    'worn' => 1,
    'lost' => 1,
    'place' => optional_param('place', 0, PARAM_INT),
];
$found = weeding::candidates($library, $filters, $now, 0);
$byreason = ['lost' => [], 'worn' => [], 'old' => [], 'unused' => []];
foreach ($found['rows'] as $row) {
    $byreason[$row['why']][] = $row;
}
// The small and decidable first: the first reason that has any, unless one was chosen.
$why = optional_param('why', '', PARAM_ALPHA);
if (!isset($byreason[$why])) {
    $why = 'unused';
    foreach (['lost', 'worn', 'old', 'unused'] as $reason) {
        if ($byreason[$reason]) {
            $why = $reason;
            break;
        }
    }
}
$keep = ['unused' => $filters['unused'], 'before' => $filters['before'], 'place' => $filters['place']];
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$reasons = [];
foreach ($byreason as $reason => $list) {
    $label = $reason === 'unused' ? $str('weedcardunused', $filters['unused'])
        : ($reason === 'old' ? ($filters['before'] ? $str('weedcardold', $filters['before']) : $str('weedcardoldnone'))
        : $str('weedcard' . $reason));
    $reasons[] = ['key' => $reason, 'label' => $label, 'count' => $number(count($list)),
        'hint' => $str('weedcardhint' . $reason . ($reason === 'old' && $filters['before'] ? 'set' : '')),
        'url' => $url($keep + ['why' => $reason]), 'current' => $reason === $why,
        'empty' => !$list];
}
// The chosen reason, by place and shelf mark, fifty at a time.
$list = $byreason[$why];
$perpage = 50;
$pages = max(1, (int) ceil(count($list) / $perpage));
$page = min(max(0, optional_param('page', 0, PARAM_INT)), $pages - 1);
$slice = array_slice($list, $page * $perpage, $perpage);
$places = [];
foreach (stocktake::places($library) as $place) {
    $places[(int) $place['id']] = $place['label'];
}
$covers = \local_oksigeniaayvurenda\local\covers::urls(array_column($slice, 'recordid'));
$inbox = array_flip(weeding::box($library));
$groups = [];
$keys = [];
foreach ($slice as $row) {
    $where = $places[$row['locationid']] ?? $str('weednoplace');
    $keys[$where] = 'p' . (int) $row['locationid'];
    $groups[$where][] = $row + [
        'inbox' => isset($inbox[(int) $row['id']]),
        'lastout' => $row['lastloan'] ? $str('weedlastout', zones::date((int) $row['lastloan'], '%Y', $library))
            : $str('weedlastoutnever'),
        'cover' => $covers[$row['recordid']] ?? null,
        'colour' => $row['recordid'] % 6,
        'initial' => core_text::strtoupper(core_text::substr(trim($row['title']), 0, 1)),
        'detail' => trim(implode(' · ', array_filter([
            $row['year'] ? (string) $row['year'] : '',
            $row['callnumber'],
            $row['barcode'],
        ]))),
    ];
}
$records = array_map(fn($r) => $r + ['url' => $url(['record' => $r['id']]), 'when' => $date($r['time']),
    'countlabel' => $str('weedrecordcount', $r['copies'])], weeding::records($library));
$view = optional_param('view', '', PARAM_ALPHA) === 'records' ? 'records' : 'candidates';
$data = [
    'heading' => $str('weedtitle'),
    // Before weeding, the old books that still go out: old is not the same as dead.
    'life' => \local_oksigeniaayvurenda\local\life::weed($library, time()),
    'intro' => $str('weedintroshort'),
    'action' => $url($keep + ['why' => $why, 'page' => $page]),
    'sesskey' => sesskey(),
    'library' => $library->slug,
    'tabs' => [
        ['label' => $str('weedtabcandidates'), 'url' => $url($keep), 'current' => $view === 'candidates'],
        ['label' => $str('weedtabrecords', count($records)), 'url' => $url($keep + ['view' => 'records']),
            'current' => $view === 'records'],
    ],
    'oncandidates' => $view === 'candidates',
    'onrecords' => $view === 'records',
    'reasons' => $reasons,
    'why' => $why,
    'groups' => array_map(
        fn($name, $rows) => ['name' => $name, 'key' => $keys[$name], 'rows' => $rows],
        array_keys($groups),
        $groups
    ),
    'hasrows' => (bool) $slice,
    'empty' => $str('weednonereason'),
    'pageline' => $pages > 1 ? $str('weedpage', (object) ['page' => $page + 1, 'pages' => $pages,
        'count' => $number(count($list))]) : '',
    'prev' => $page > 0 ? $url($keep + ['why' => $why, 'page' => $page - 1]) : '',
    'next' => $page < $pages - 1 ? $url($keep + ['why' => $why, 'page' => $page + 1]) : '',
    'records' => $records,
    'hasrecords' => (bool) $records,
    // To refine: the years without lending, the year of publication, the place.
    // «Refine», in sight: each choice a link, as it is a view; the year of publication five years at a time.
    'refine' => [
        'unused' => array_map(fn($y) => ['value' => $y, 'label' => $y ? $str('weedunusedyears', $y) : $str('weednofilter'),
            'selected' => $filters['unused'] === $y, 'url' => $url(['unused' => $y] + $keep + ['why' => $why])], [0, 2, 3, 5, 10]),
        'before' => (function () use ($filters, $url, $keep, $why, $now, $library, $str) {
            $thisyear = (int) zones::date($now, '%Y', $library);
            $year = (int) $filters['before'];
            $at = fn(int $y) => $url(['before' => $y] + $keep + ['why' => $why]);
            return ['year' => $year ?: false, 'lessurl' => $at(($year ?: $thisyear) - 5),
                'moreurl' => $at(min($thisyear, ($year ?: $thisyear - 10) + 5)), 'anyurl' => $at(0),
                'anyyear' => $str('weedanyyear')];
        })(),
        'allurl' => $url(['place' => 0] + $keep + ['why' => $why]),
        'allplaces' => !$filters['place'],
        'places' => array_map(fn($p) => ['label' => $p['label'], 'selected' => (int) $filters['place'] === (int) $p['id'],
            'url' => $url(['place' => (int) $p['id']] + $keep + ['why' => $why])], stocktake::places($library)),
    ],
    // The box beside the shelves: what is in it, to take out one by one, and the record made of it.
    'box' => (function () use ($library, $str) {
        $items = weeding::box_items($library);
        return ['count' => count($items), 'countlabel' => count($items) === 1 ? $str('weedboxcountone')
            : $str('weedboxcount', count($items)), 'hasitems' => (bool) $items, 'items' => $items];
    })(),
    'backvalue' => '',
    'lesslabel' => $str('weedbeforeless'),
    'morelabel' => $str('weedbeforemore'),
];
foreach (
    ['weedrefine', 'weedunused', 'weedbefore', 'weedplace', 'stocktakeall', 'weedshow', 'weedallpage', 'weedreason',
        'weedreasonhint', 'weedgo', 'weedgoconfirm', 'weedgohint', 'weedmarkedone', 'weedmarkedmany', 'weedmarkednone',
        'weedrecords', 'weednorecords', 'weedprev', 'weednext', 'offsiteclose', 'weedallshelf', 'weedtobox', 'weedinbox',
        'weedtakeout', 'weedboxword', 'weedboxempty', 'weedboxclear'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('weedtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/weed', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/stocktake.php', ['library' => $library->slug]))->out(false),
            'label' => $str('stocktakenav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
            'label' => $str('addnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'weed']))
            ->out(false), 'label' => $str('helpnav')],
    ],
]);
