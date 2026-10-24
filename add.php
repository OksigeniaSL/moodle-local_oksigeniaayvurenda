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
 * Adding books by their ISBN, one after another.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\lookup;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:catalogue', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true) ?: [];
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$sm = get_string_manager();
$libraries = [];
foreach (catalogue::libraries() as $other) {
    if ((int) $other->entityid === $entityid) {
        $libraries[] = ['value' => $other->slug, 'label' => format_string($other->name), 'selected' => $other->id == $library->id];
    }
}
$locations = [['value' => 0, 'label' => $str('addnolocation')]];
foreach (cataloguer::locations($entityid) as $id => $label) {
    $locations[] = ['value' => $id, 'label' => $label];
}
$types = [];
foreach (cataloguer::itemtypes($entityid) as $type) {
    $types[] = ['value' => $type, 'label' => $sm->string_exists('itemtype' . $type, 'local_oksigeniaayvurenda')
        ? $str('itemtype' . $type) : $type];
}
$names = \local_oksigeniaayvurenda\local\practice::is($entityid) ? [$str('practicecatalogue')]
    : array_map(fn($s) => lookup::PRESETS[$s]['name'], lookup::sources($entityid));
$data = [
    'heading' => $str('addtitle'),
    'prompt' => $str('addprompt'),
    'libraries' => $libraries,
    'multiple' => count($libraries) > 1,
    'locations' => $locations,
    'types' => $types,
    'sources' => $str('addsources', count($names) > 1
        ? implode(', ', array_slice($names, 0, -1)) . $str('addand') . end($names) : reset($names)),
];
foreach (
    ['addlibrary', 'addspine', 'addlocation', 'addtype', 'addcount', 'addcodes', 'addcodesnew', 'addcodesscan',
        'addcodesscanhint', 'addsave', 'addfieldtitle', 'addfieldsubtitle', 'addfieldauthor', 'addfieldpublisher',
        'addfieldyear', 'addfieldpages', 'addcancel'] as $key
) {
    $data[$key] = $str($key);
}
$strings = [];
foreach (
    ['addnotisbn', 'additemcode', 'addlooking', 'addfound', 'addnotfound', 'addexisting', 'addexistingcopies',
        'addaddcopy', 'addotheredition', 'addspineproposed', 'addspinebefore', 'addsaved', 'addundo', 'addundone',
        'addcantundo', 'adderror', 'addnocover', 'addbadletter'] as $key
) {
    $strings[$key] = $str($key);
}
$tools = shell::readers_tools($library);
// Inside the guided help, the page shows only the field and what it does.
$help = optional_param('help', 0, PARAM_BOOL);
$data['tools'] = $help ? '' : $tools['html'];
shell::output($str('addtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/add', $data), $library, '', [
    'search' => false,
    'scripts' => ['camera', 'add'],
    'nav' => $help ? [] : [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false),
            'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/material.php', ['library' => $library->slug]))->out(false),
            'label' => $str('materialnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/labels.php', ['library' => $library->slug]))->out(false),
            'label' => $str('labelsnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'add']))
            ->out(false), 'label' => $str('helpnav')],
    ],
    'config' => [
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/add_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'controlletter' => !empty($settings['controlletter']),
        'strings' => $strings + $tools['strings'],
    ] + $tools['config'],
]);
