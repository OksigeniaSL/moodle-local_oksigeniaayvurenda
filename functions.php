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
 * «What your library can do» (Jujol, 14 November): every function of the library, what it solves and what it did this
 * school year; those with a light are turned on and off right here.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\abilities;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/functions.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$fn = optional_param('fn', '', PARAM_ALPHA);
if (
    $fn !== '' && data_submitted() && confirm_sesskey() && in_array($fn, abilities::LIGHTS, true)
    && access::can('local/oksigeniaayvurenda:manage', $library)
) {
    abilities::light($library, $fn, optional_param('on', 0, PARAM_BOOL));
    redirect(new moodle_url('/local/oksigeniaayvurenda/functions.php', ['library' => $library->slug], 'ayv-em-' . $fn));
}
$html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/abilities', [
    'heading' => $str('functionsheading'),
    'intro' => $str('functionsintro'),
    'showme' => $str('timelylateshow'),
    'shelves' => abilities::shelves($library, time()),
]);
shell::output($str('functionsheading'), $html, $library, '', ['search' => false]);
