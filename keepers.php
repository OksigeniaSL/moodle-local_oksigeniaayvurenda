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
 * Who keeps a library: the centre's staff choose, among its readers with an account, who works at its desk only.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\keepers;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/keepers.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$query = trim(core_text::substr(optional_param('q', '', PARAM_TEXT), 0, 100));
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $userid = required_param('user', PARAM_INT);
    if (optional_param('do', '', PARAM_ALPHA) === 'remove') {
        keepers::remove($library, $userid);
        $notice = $str('keepersremoved');
    } else if (keepers::add($library, $userid, (int) $USER->id)) {
        $notice = $str('keepersadded');
    }
}
$found = $query !== '' ? keepers::candidates($library, $query) : [];
$data = [
    'heading' => $str('keepersheading', format_string($library->name)),
    'intro' => $str('keepersintro'),
    'notice' => $notice,
    // The search stays after giving or taking away.
    'action' => (new moodle_url($PAGE->url, $query !== '' ? ['q' => $query] : []))->out(false),
    'slug' => $library->slug,
    'sesskey' => sesskey(),
    'keepers' => keepers::of((int) $library->id),
    'haskeepers' => (bool) keepers::of((int) $library->id),
    'none' => $str('keepersnone'),
    'query' => $query,
    'found' => array_map(fn($c) => $c + ['typelabel' => get_string_manager()->string_exists(
        'keepertype' . $c['type'],
        'local_oksigeniaayvurenda'
    ) ? $str('keepertype' . $c['type']) : ''], $found),
    'hasfound' => (bool) $found,
    'nothing' => $query !== '' && !$found ? $str('keepersnothing') : '',
];
foreach (['keepersnow', 'keeperssearch', 'keeperssearchbutton', 'keepersadd', 'keepersremove'] as $key) {
    $data[$key] = $str($key);
}
// In a classroom library, they serve the readers of its groups only.
$settings = json_decode((string) ($library->settings ?? ''), true) ?: [];
$data['groupsnote'] = !empty($settings['groups']) ? $str('keepersgroupshint', implode(', ', (array) $settings['groups'])) : '';
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/keepers', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => (new moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
        'label' => $str('librariesnav')]]]
);
