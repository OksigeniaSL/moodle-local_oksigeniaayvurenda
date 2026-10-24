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
 * Adding material that is not a book: laptops, tablets, calculators, cameras, games… with their units.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:catalogue');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/material.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$lines = fn(string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen'));
$notice = '';
$error = '';
$recordid = optional_param('recordid', 0, PARAM_INT);
if (data_submitted() && confirm_sesskey()) {
    $name = trim(optional_param('name', '', PARAM_TEXT));
    $serials = $lines(optional_param('serials', '', PARAM_TEXT));
    $labels = $lines(optional_param('labels', '', PARAM_TEXT));
    $count = max(1, optional_param('count', 1, PARAM_INT), count($serials), count($labels));
    $description = ['record' => ['doctype' => 'device', 'title' => $name], 'authorities' => [], 'extra' => array_filter([
        'brand' => trim(optional_param('brand', '', PARAM_TEXT)),
        'model' => trim(optional_param('model', '', PARAM_TEXT)),
    ])];
    if ($name === '' && !$recordid) {
        $error = $str('materialnoname');
    } else {
        try {
            $added = cataloguer::add($library, $description, [
                'count' => $count,
                'barcodes' => $labels,
                'serials' => $serials,
                'locationid' => optional_param('locationid', 0, PARAM_INT),
                'itemtype' => optional_param('itemtype', 'device', PARAM_ALPHANUMEXT),
                'notes' => trim(optional_param('notes', '', PARAM_TEXT)),
            ], $recordid);
            $title = $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $added['recordid']]);
            $notice = $str('materialadded', (object) ['title' => $title, 'count' => count($added['barcodes']),
                'codes' => implode(', ', $added['barcodes'])]) . ($added['ruled'] ? ' ' . $str('materialrule') : '');
            catalogue::purge($entityid);
            $recordid = 0;
        } catch (invalid_parameter_exception $e) {
            $error = $str('addtaken', preg_replace('/^.*taken:/', '', $e->debuginfo ?? ''));
        }
    }
}
$existing = [['value' => 0, 'label' => $str('materialnew'), 'selected' => !$recordid]];
$devices = $DB->get_records(
    'local_oksigeniaayvurenda_record',
    ['entityid' => $entityid, 'doctype' => 'device'],
    'title',
    'id, title'
);
foreach ($devices as $record) {
    $existing[] = ['value' => $record->id, 'label' => $record->title, 'selected' => (int) $record->id === $recordid];
}
$locations = [['value' => 0, 'label' => $str('addnolocation')]];
foreach (cataloguer::locations($entityid) as $id => $label) {
    $locations[] = ['value' => $id, 'label' => $label];
}
$sm = get_string_manager();
$types = [];
foreach (cataloguer::itemtypes($entityid) as $type) {
    $types[] = ['value' => $type, 'label' => $sm->string_exists('itemtype' . $type, 'local_oksigeniaayvurenda')
        ? $str('itemtype' . $type) : $type, 'selected' => $type === 'device'];
}
$data = [
    'heading' => $str('materialtitle'),
    'intro' => $str('materialintro'),
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'existing' => $existing,
    'hasexisting' => count($existing) > 1,
    'locations' => $locations,
    'types' => $types,
];
$words = ['materialwhich', 'materialname', 'materialbrand', 'materialmodel', 'materialcount', 'materialserials',
    'materialserialshint', 'materiallabels', 'materiallabelshint', 'materialnotes', 'addlocation', 'addtype', 'materialsave'];
foreach ($words as $key) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('materialtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/material', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => $url('add.php'), 'label' => $str('addnav')], ['url' => $url('desk.php'), 'label' => $str('desktitle')]],
]);
