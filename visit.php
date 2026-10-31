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
 * The library one visits: its hall and its rooms, painted, with its own corners, books and characters in them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\visit;
use local_oksigeniaayvurenda\local\window;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$room = optional_param('room', visit::HALL, PARAM_ALPHANUMEXT);
$library = catalogue::library($ref);
// Whoever is not of the library visits it only where its window shows the catalogue, as with the shelves.
$public = $library && window::catalogue_open($library)
    && (!isloggedin() || isguestuser() || !access::can('local/oksigeniaayvurenda:view', $library));
if (!$public) {
    require_login(null, false);
    $context = access::context($library);
    shell::require('local/oksigeniaayvurenda:view', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/visit.php', ['library' => $ref]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library) {
    shell::missing($str('visitnone'));
}
$data = visit::page($library, $room, $public, null, max(0, optional_param($room === visit::HALL ? 'sort' : 'hunt', 0, PARAM_INT)));
if (!$data) {
    shell::missing($str('visitnone'), $library);
}
foreach (
    ['visitplaces', 'visitsound', 'visitvolume', 'visitpanleft', 'visitpanright', 'visitclose', 'searchlabel', 'searchplaceholder',
        'searchbutton', 'huntplay', 'huntshelf', 'huntrecord', 'huntagain', 'huntlens', 'sortplay', 'sortwhich', 'sortdone',
        'sortdonehint', 'sortagain'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['title'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/visit', $data), $library, '', [
    'search' => false,
    'scripts' => array_merge(['visit'], $data['hunt'] ? ['hunt'] : [], $data['sort'] ? ['shelve'] : []),
]);
