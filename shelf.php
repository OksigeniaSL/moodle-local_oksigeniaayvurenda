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
 * The shelf of a classroom library: a name and a cover lend a book; a lent cover brings it back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\shelf;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$readerid = optional_param('reader', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:view', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/shelf.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library || !shelf::can_use($library, (int) $USER->id)) {
    throw new required_capability_exception($context, 'local/oksigeniaayvurenda:desk', 'nopermissions', '');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => new moodle_url('/local/oksigeniaayvurenda/shelf.php', ['library' => $library->slug] + $params);
$now = time();

if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    $itemid = optional_param('item', 0, PARAM_INT);
    $params = [];
    if ($do === 'lend' && $readerid) {
        $loanid = shelf::lend($library, $itemid, $readerid, (int) $USER->id, $now);
        $params = $loanid ? ['done' => 'lent', 'loan' => $loanid] : ['done' => 'notlent'];
    } else if ($do === 'back') {
        $loanid = shelf::give_back($library, $itemid, (int) $USER->id, $now);
        $params = $loanid ? ['done' => 'back', 'loan' => $loanid] : [];
        $readerid = 0;
    } else if ($do === 'lotback') {
        $back = \local_oksigeniaayvurenda\local\lots::give_back(\local_oksigeniaayvurenda\local\lots::get(
            required_param('lot', PARAM_INT),
            $library
        ), $now);
        $params = ['done' => 'lotback', 'home' => $back['home'], 'pending' => count($back['pending'])];
    } else if ($do === 'undo') {
        $loan = $DB->get_record('local_oksigeniaayvurenda_loan', ['id' => required_param('loan', PARAM_INT),
            'libraryid' => $library->id], '*', MUST_EXIST);
        (new desk($library, (int) $USER->id, $now))->undo((int) $loan->id, required_param('what', PARAM_ALPHA));
        $params = ['done' => 'undone'];
    }
    redirect(new moodle_url($url(($readerid ? ['reader' => $readerid] : []) + $params), null, 'ayv-what'));
}

$groups = shelf::groups($library);
$group = implode(', ', $groups);
$several = count($groups) > 1;
$nav = [['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
    'label' => $str('deskcatalogue')]];
if (has_capability('local/oksigeniaayvurenda:catalogue', $context)) {
    $nav[] = ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
        'label' => $str('addnav')];
}

if ($view === 'read') {
    $data = ['heading' => $str('shelfread'), 'group' => $group, 'children' => array_map(fn($c) => $c + ['showgroup' => $several,
        'hasbooks' => (bool) $c['books'], 'list' => implode(' · ', $c['books'])], shelf::read($library, $now)),
        'none' => $str('shelfreadnone'), 'back' => $url()->out(false), 'backlabel' => $str('shelfbacktoit')];
    shell::output(
        $str('shelfread'),
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/shelfread', $data),
        $library,
        '',
        ['search' => false, 'nav' => $nav]
    );
    exit;
}

$children = shelf::children($library, $now);
$chosen = null;
foreach ($children as $child) {
    if ($child['id'] === $readerid) {
        $chosen = $child;
    }
}
$done = optional_param('done', '', PARAM_ALPHA);
$notice = '';
$undo = null;
if (in_array($done, ['lent', 'back'], true)) {
    $loan = $DB->get_record('local_oksigeniaayvurenda_loan', ['id' => optional_param('loan', 0, PARAM_INT),
        'libraryid' => $library->id]);
    if ($loan) {
        $title = $DB->get_field_sql('SELECT r.title FROM {local_oksigeniaayvurenda_item} i
            JOIN {local_oksigeniaayvurenda_record} r ON r.id = i.recordid WHERE i.id = ?', [$loan->itemid]);
        $who = (new desk($library, 0, $now))->reader((int) $loan->readerid)['name'];
        $notice = $str($done === 'lent' ? 'shelflent' : 'shelfreturned', (object) ['title' => $title, 'name' => $who]);
        $undo = ['loan' => (int) $loan->id, 'what' => $done === 'lent' ? 'lent' : 'returned'];
    }
} else if ($done === 'lotback') {
    $notice = $str('shelflotback', (object) ['home' => optional_param('home', 0, PARAM_INT),
        'pending' => optional_param('pending', 0, PARAM_INT)]);
} else if ($done === 'undone') {
    $notice = $str('shelfundone');
} else if ($done === 'notlent') {
    $notice = $str('shelfnotlent');
}
$date = fn(?int $time) => $time ? userdate($time, get_string('strftimedateshort', 'langconfig')) : '—';
$boxes = [];
foreach (\local_oksigeniaayvurenda\local\lots::of_library($library)['received'] as $box) {
    if ($box['lot']->status === 'out') {
        $boxes[] = ['id' => (int) $box['lot']->id, 'line' => $str('shelfbox', (object) ['name' => $box['lot']->name,
            'from' => format_string((string) $DB->get_field(
                'local_oksigeniaayvurenda_library',
                'name',
                ['id' => $box['lot']->libraryid]
            )), 'due' => $date($box['lot']->timedue), 'copies' => $box['copies'],
            'lent' => $box['lent']])];
    }
}
// With several groups, the names go under each group.
$whogroups = [];
foreach ($children as $child) {
    $key = $several ? $child['group'] : '';
    $whogroups[$key] = $whogroups[$key] ?? ['label' => $key, 'names' => []];
    $whogroups[$key]['names'][] = $child + ['url' => $url(['reader' => $child['id']])->out(false) . '#ayv-what',
        'current' => $chosen !== null && $child['id'] === $chosen['id']];
}
$whogroups = array_values($whogroups);
$books = array_map(fn($b) => $b + ['lentlabel' => $b['lent'] !== '' ? $str('shelflentto', $b['lent']) : '',
    'canlend' => $b['lent'] === '' && $chosen !== null], shelf::books($library));
$data = [
    'heading' => $str('shelftitle', $group !== '' ? $group : format_string($library->name)),
    'notice' => $notice,
    'undo' => $undo,
    'action' => $url($readerid ? ['reader' => $readerid] : [])->out(false),
    'sesskey' => sesskey(),
    // A name takes the page down to the books, which on a phone are below a whole class of names.
    'whogroups' => $whogroups,
    'haschildren' => (bool) $children,
    'books' => $books,
    'hasbooks' => (bool) $books,
    'hint' => $chosen ? $str('shelfchosen', $chosen['name']) : $str('shelfchoosefirst'),
    'nogroup' => $group === '' ? $str('shelfnogroup') : '',
    'nochildren' => $group !== '' && !$children ? $str('shelfnochildren', $group) : '',
    'empty' => $str('shelfempty'),
    'readurl' => $url(['view' => 'read'])->out(false),
    'boxes' => $boxes,
];
foreach (['shelfwho', 'shelfwhat', 'shelfundo', 'shelfread', 'shelftapback', 'shelfboxback'] as $key) {
    $data[$key] = $str($key);
}
shell::output(
    $str('shelftitle', $group),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/shelf', $data),
    $library,
    '',
    ['search' => false, 'nav' => $nav]
);
