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
 * Boxes a library lends to another of its centre: filling one, sending it, and what came back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\lots;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:desk', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/lots.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/lots.php', ['library' => $library->slug] + $params))
    ->out(false);
$now = time();
$date = fn(?int $time) => $time ? userdate($time, get_string('strftimedateshort', 'langconfig')) : '';
$others = $DB->get_records_select_menu(
    'local_oksigeniaayvurenda_library',
    'entityid = :entityid AND id <> :id',
    ['entityid' => $library->entityid, 'id' => $library->id],
    'name',
    'id, name'
);

if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'create') {
        $due = optional_param('due', '', PARAM_TEXT);
        $time = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? make_timestamp(
            (int) substr($due, 0, 4),
            (int) substr($due, 5, 2),
            (int) substr($due, 8, 2),
            23,
            59
        ) : null;
        $id = lots::create(
            $library,
            optional_param('name', '', PARAM_TEXT) ?: $str('lotdefault'),
            required_param('to', PARAM_INT),
            $time,
            (int) $USER->id,
            $now
        );
        redirect($url(['id' => $id]));
    }
    $lot = lots::get($id, $library);
    $params = ['id' => $id];
    if ($do === 'add') {
        $text = trim(required_param('code', PARAM_TEXT));
        $answer = lots::add($lot, $text);
        // Words instead of a code: the books that match, to choose.
        if ($answer['kind'] === 'notfound' && preg_match('/\p{L}{2,}/u', $text)) {
            redirect($url($params + ['q' => $text]));
        }
        $params += ['said' => $answer['kind'], 'title' => $answer['title']];
    } else if ($do === 'remove') {
        lots::remove($lot, required_param('item', PARAM_INT));
    } else if ($do === 'send') {
        $params += ['said' => 'sent', 'title' => lots::send($lot, $now)];
    } else if ($do === 'back') {
        $back = lots::give_back($lot, $now);
        $params += ['said' => 'back', 'title' => $back['home']];
    }
    redirect($url($params));
}

$nav = [['url' => (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false),
    'label' => $str('desktitle')]];
if ($id) {
    $lot = lots::get($id, $library);
    $said = optional_param('said', '', PARAM_ALPHA);
    $copies = lots::copies($lot);
    $q = $lot->status === 'open' ? trim(optional_param('q', '', PARAM_TEXT)) : '';
    $suggestions = $q !== '' ? lots::suggest($lot, $q) : [];
    $data = [
        'heading' => $str('lottitle', $lot->name),
        'where' => $str('lotwhere', (object) ['to' => format_string($others[$lot->tolibraryid] ?? ''),
            'due' => $date($lot->timedue) ?: '—']),
        'notice' => $said !== '' ? $str('lotsaid' . $said, optional_param('title', '', PARAM_TEXT))
            : ($q !== '' && !$suggestions ? $str('lotnosuggest', $q) : ''),
        'suggestions' => $suggestions,
        'q' => $q,
        'bad' => in_array($said, ['notfound', 'notfree', 'otherlibrary', 'inanother'], true),
        'open' => $lot->status === 'open',
        'out' => $lot->status === 'out',
        'back' => $lot->status === 'back',
        'mine' => (int) $lot->libraryid === (int) $library->id,
        'action' => $url(['id' => $id]),
        'sesskey' => sesskey(),
        'count' => $str('lotcount', count($copies)),
        'copies' => array_map(fn($c) => $c + ['state' => $c['home'] ? $str('lothome') : ($c['status'] === 'onloan'
            ? $str('lotlent') : '')], $copies),
        'pending' => $lot->status === 'back' ? array_map(fn($p) => ['line' => $p['who'] !== ''
            ? $str('lotpendingwho', (object) $p) : $p['title']], lots::pending($lot)) : [],
    ];
    $data['haspending'] = (bool) $data['pending'];
    $keys = ['lotscan', 'lotscanhint', 'lotremove', 'lotsend', 'lotgiveback', 'lotpendingheading', 'lotallhome', 'lotadd'];
    foreach ($keys as $key) {
        $data[$key] = $str($key);
    }
    $nav[] = ['url' => $url(), 'label' => $str('lotsnav')];
    shell::output($lot->name, $OUTPUT->render_from_template('local_oksigeniaayvurenda/lot', $data), $library, '', [
        'search' => false,
        'nav' => $nav,
        'scripts' => ['lots'],
        'config' => [
            'suggesturl' => (new moodle_url('/local/oksigeniaayvurenda/lots_ajax.php'))->out(false),
            'sesskey' => sesskey(),
            'library' => $library->slug,
            'lot' => (int) $lot->id,
            'strings' => ['lotadd' => $str('lotadd')],
        ],
    ]);
    exit;
}

$all = lots::of_library($library);
$row = fn(array $e) => [
    'url' => $url(['id' => $e['lot']->id]),
    'name' => $e['lot']->name,
    'state' => $str('lotstate' . $e['lot']->status, (object) ['due' => $date($e['lot']->timedue) ?: '—',
        'late' => $e['lot']->status === 'out' && $e['lot']->timedue && $e['lot']->timedue < $now]),
    'late' => $e['lot']->status === 'out' && $e['lot']->timedue && $e['lot']->timedue < $now,
    'counts' => $str('lotcounts', (object) ['copies' => $e['copies'], 'lent' => $e['lent'], 'missing' => $e['missing']]),
    'other' => format_string($others[$e['lot']->libraryid === $library->id ? $e['lot']->tolibraryid : $e['lot']->libraryid]
        ?? ''),
];
$data = [
    'heading' => $str('lotstitle'),
    'intro' => $str('lotsintro'),
    'sent' => array_map($row, $all['sent']),
    'received' => array_map($row, $all['received']),
    'action' => $url(),
    'sesskey' => sesskey(),
    'others' => array_map(fn($id, $name) => ['id' => $id, 'name' => format_string($name)], array_keys($others), $others),
    'hasothers' => (bool) $others,
];
$data['hassent'] = (bool) $data['sent'];
$data['hasreceived'] = (bool) $data['received'];
foreach (
    ['lotssent', 'lotsreceived', 'lotsnew', 'lotname', 'lotnamehint', 'lotto', 'lotdue', 'lotcreate', 'lotsnoothers',
        'lotlate'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('lotstitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/lots', $data),
    $library,
    '',
    ['search' => false, 'nav' => $nav]
);
