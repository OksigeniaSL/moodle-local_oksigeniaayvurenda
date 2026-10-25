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
 * The corners of a library as it names, orders and hides them: «Stories and poems» rather than «Language and
 * literature», in the order the children walk the room.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/corners.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $labels = optional_param_array('label', [], PARAM_TEXT);
    $orders = optional_param_array('order', [], PARAM_INT);
    $hidden = optional_param_array('hidden', [], PARAM_BOOL);
    $corners = [];
    foreach ($labels as $code => $label) {
        $corners[$code] = ['label' => $label, 'order' => $orders[$code] ?? 0, 'hidden' => !empty($hidden[$code])];
    }
    catalogue::save_corners($library, $corners);
    $notice = $str('cornerssaved');
}
$data = [
    'heading' => $str('cornerstitle'),
    'intro' => $str('cornersintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'corners' => array_map(fn($corner) => $corner + ['countlabel' => $str(
        $corner['count'] === 1 ? 'onebook' : 'books',
        $corner['count']
    )], catalogue::corners($library, true)),
    'homeurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
];
foreach (['cornersname', 'cornersorder', 'cornershide', 'cornerssave', 'cornersback'] as $key) {
    $data[$key] = $str($key);
}
shell::output(
    $str('cornerstitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/corners', $data),
    $library,
    '',
    ['search' => false]
);
