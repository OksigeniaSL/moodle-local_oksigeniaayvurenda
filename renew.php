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
 * A reader gives one of their loans more time, from their dashboard, and goes back where they were.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\renewals;

require(__DIR__ . '/../../config.php');

require_login(null, false);
require_sesskey();
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/renew.php'));
$answer = renewals::mine(required_param('loan', PARAM_INT), (int) $USER->id);
$back = optional_param('returnurl', '', PARAM_LOCALURL) ?: (new moodle_url('/my/'))->out(false);
redirect(new moodle_url($back), $answer['message'], null, $answer['ok'] ? \core\output\notification::NOTIFY_SUCCESS
    : \core\output\notification::NOTIFY_WARNING);
