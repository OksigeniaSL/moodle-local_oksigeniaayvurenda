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
 * The places of a library, as the furniture of the room: each with the name its readers see, written on its plate; the
 * old names kept for searching; the pairs that look the same, joined with a touch; new places and empty ones removed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\placesview;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/places.php', ['library' => $library->slug]));

if (data_submitted() && confirm_sesskey()) {
    placesview::apply_places($library);
    redirect($PAGE->url);
}
$data = placesview::places($library, $PAGE->url->out(false));
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/places', $data), $library, '', [
    'search' => false,
]);
