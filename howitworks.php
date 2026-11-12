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
 * «How your library works»: the centre's settings and the library's loan rules as sentences, each changed in its place.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\workings;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/howitworks.php', ['library' => $library->slug]));

// Each gesture sends only what it changes; back to the sentence touched, with its new words.
if (data_submitted() && confirm_sesskey()) {
    $anchor = workings::apply($library);
    $back = ['library' => $library->slug, 'saved' => $anchor];
    redirect(new moodle_url('/local/oksigeniaayvurenda/howitworks.php', $back, $anchor));
}
$data = workings::context($library, $PAGE->url->out(false), optional_param('saved', '', PARAM_ALPHANUMEXT));
shell::output($data['title'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/howitworks', $data), $library, '', [
    'search' => false,
]);
