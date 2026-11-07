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
 * The home of a library: search, corners and what came in last.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/index.php', $ref !== '' ? ['library' => $ref] : []));

if (!$library) {
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/choose', screens::choose());
    shell::output(get_string('pluginname', 'local_oksigeniaayvurenda'), $html);
    die();
}
// A bank of textbooks is not a library to visit: its pupil sees the books lent for the year, and until when.
if (
    \local_oksigeniaayvurenda\local\bank::is($library) && !access::can('local/oksigeniaayvurenda:desk', $library)
        && !access::keeps($library)
) {
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/bank_mine', screens::bank_mine($library));
    shell::output(get_string('bankminetitle', 'local_oksigeniaayvurenda'), $html, $library, '', ['search' => false]);
    die();
}
$html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/home', screens::home($library));
// The home has its own search, at the top: the phone's magnifier takes the focus there.
shell::output(get_string('homeheading', 'local_oksigeniaayvurenda'), $html, $library, '', ['search' => false, 'lens' => true]);
