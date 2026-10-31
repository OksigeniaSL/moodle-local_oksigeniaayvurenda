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
 * The sets of a textbook bank: each grade's titles, the groups it serves and what is short.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\bank;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\libraries;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/bank.php', ['library' => $ref]));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library || !bank::is($library)) {
    shell::missing($str('banknotbank'), $library);
}
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    $setid = optional_param('set', 0, PARAM_INT);
    if ($do === 'saveset') {
        bank::save_set($library, required_param('name', PARAM_TEXT), optional_param_array('groups', [], PARAM_TEXT), $setid);
        $notice = $str('banksaved');
    } else if ($do === 'removeset') {
        bank::remove_set($library, $setid);
        $notice = $str('bankremoved');
    } else if ($do === 'addtitle') {
        $notice = bank::add_title($library, $setid, required_param('record', PARAM_INT)) ? $str('banktitleadded') : '';
    } else if ($do === 'removetitle') {
        bank::remove_title($library, $setid, required_param('record', PARAM_INT));
        $notice = $str('banksaved');
    }
}
// Looking for a title for one set: the catalogue's own search.
$lookingfor = optional_param('set', 0, PARAM_INT);
$query = trim(core_text::substr(optional_param('q', '', PARAM_TEXT), 0, 200));
$found = $query !== '' ? catalogue::search($library, $query)['cards'] : [];
$groups = libraries::groups((int) $library->entityid);
$sets = [];
foreach (bank::needs($library, time()) as $need) {
    $set = array_values(array_filter(bank::sets($library), fn($s) => $s['id'] === $need['id']))[0];
    $sets[] = [
        'id' => $need['id'],
        'name' => $need['name'],
        'pupils' => $str('bankpupils', $need['pupils']),
        'chips' => array_map(fn($g) => ['chip' => $g], $set['groups']),
        'haschips' => (bool) $set['groups'],
        'titles' => array_map(fn($t) => $t + ['counts' => $str('bankcounts', (object) $t),
            'isshort' => $t['short'] > 0], $need['titles']),
        'hastitles' => (bool) $need['titles'],
        'groupoptions' => array_map(
            fn($g) => ['value' => $g, 'label' => $g, 'checked' => in_array($g, $set['groups'], true)],
            $groups
        ),
        'searching' => $lookingfor === $need['id'] && $query !== '',
        'query' => $lookingfor === $need['id'] ? $query : '',
        'found' => $lookingfor === $need['id'] ? array_map(fn($c) => ['recordid' => $c['id'], 'title' => $c['title'],
            'authors' => $c['authors']], array_slice($found, 0, 8)) : [],
    ];
}
$data = [
    'heading' => $str('bankheading', format_string($library->name)),
    'intro' => $str('bankintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'slug' => $library->slug,
    'sesskey' => sesskey(),
    'sets' => $sets,
    'hassets' => (bool) $sets,
    'groupoptions' => array_map(fn($g) => ['value' => $g, 'label' => $g, 'checked' => false], $groups),
    'hasgroups' => (bool) $groups,
];
foreach (
    ['banknone', 'banknewset', 'bankname', 'bankgroups', 'banknogroups', 'banksave', 'bankremove', 'bankaddtitle',
        'bankfind', 'bankfindbutton', 'bankadd', 'banknotitles', 'bankremovetitle', 'bankedit', 'banknothing'] as $key
) {
    $data[$key] = $str($key);
}
$desk = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/bank', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $desk, 'label' => $str('desktitle')], ['url' =>
        (new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $library->slug]))->out(false),
        'label' => $str('bankrollnav')]]]
);
