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
 * The readers of a library: the groups of this school year and who is in each.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\readers;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$group = optional_param('group', null, PARAM_TEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:desk', $context);
$PAGE->set_context($context);
$params = ['library' => $ref] + ($group !== null ? ['group' => $group] : []);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readers.php', $params));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params) => (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug] + $params))
    ->out(false);
if ($group === null) {
    $groups = [];
    foreach (readers::groups($library) as $row) {
        $groups[] = $row + ['url' => $url(['group' => $row['name']]),
            'countlabel' => $str($row['count'] === 1 ? 'onereader' : 'readerscount', $row['count'])];
    }
    $data = [
        'heading' => $str('readers'),
        'groups' => $groups,
        'hasgroups' => (bool) $groups,
        'nogroup' => ['url' => $url(['group' => '']), 'label' => $str('readersnogroup')],
        'empty' => $groups ? '' : $str('readersempty'),
        'manage' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/sources.php', ['library' => $library->slug]))->out(false),
            'label' => $str('readerssources'),
        ] : null,
        'match' => null,
        'tutors' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/tutors.php', ['library' => $library->slug]))->out(false),
            'label' => $str('tutorsnav'),
        ] : null,
        'list' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/readerslist.php', ['library' => $library->slug]))->out(false),
            'label' => $str('readerslistnav'),
        ] : null,
        'characters' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/characters.php', ['library' => $library->slug]))->out(false),
            'label' => $str('charactersnav'),
        ] : null,
        'rules' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/rules.php', ['library' => $library->slug]))->out(false),
            'label' => $str('rulesnav'),
        ] : null,
        'year' => has_capability('local/oksigeniaayvurenda:manage', $context) ? [
            'url' => (new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug]))->out(false),
            'label' => $str('yearnav'),
        ] : null,
    ];
    $unlinked = $DB->count_records_select(
        'local_oksigeniaayvurenda_reader',
        "entityid = :entityid AND userid IS NULL AND status = 'active'",
        ['entityid' => $library->entityid]
    );
    if ($unlinked && has_capability('local/oksigeniaayvurenda:manage', $context)) {
        $data['match'] = ['url' => (new moodle_url('/local/oksigeniaayvurenda/match.php', ['library' => $library->slug]))
            ->out(false), 'label' => $str('matchlink', $unlinked)];
    }
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/readers', $data);
} else {
    $list = readers::in_group($library, $group);
    $data = [
        'heading' => $group !== '' ? $group : $str('readersnogroup'),
        'readers' => $list,
        'hasreaders' => (bool) $list,
        'cardlabel' => $str('cardcode'),
        'loanslabel' => $str('deskloans'),
        'back' => ['url' => $url([]), 'label' => $str('readersback')],
        'empty' => $list ? '' : $str('readersnone'),
        'print' => $list ? ['url' => (new moodle_url('/local/oksigeniaayvurenda/print.php', ['library' => $library->slug,
            'group' => $group]))->out(false), 'label' => $str('printcards')] : null,
    ];
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/group', $data);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output($data['heading'], $html, $library, '', ['search' => false,
    'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]);
