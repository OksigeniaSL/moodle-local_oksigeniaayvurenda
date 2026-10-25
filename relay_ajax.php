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
 * Codes read with a phone, from the phone to the desk.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\relay;

require(__DIR__ . '/../../config.php');

$action = required_param('action', PARAM_ALPHA);
require_login(null, false);
require_sesskey();
// The phone sends codes to its own user's desk or adding screen: for whoever works at a desk or catalogues anywhere.
if (!access::can('local/oksigeniaayvurenda:desk', null)) {
    access::require('local/oksigeniaayvurenda:catalogue', null);
}
$PAGE->set_context(context_system::instance());

if ($action === 'push') {
    relay::push((int) $USER->id, required_param('code', PARAM_TEXT));
    $answer = ['kind' => 'pushed'];
} else if ($action === 'pull') {
    $answer = ['kind' => 'codes', 'codes' => relay::pull((int) $USER->id)];
} else {
    throw new moodle_exception('invalidparameter', 'debug');
}
echo json_encode($answer, JSON_UNESCAPED_UNICODE);
