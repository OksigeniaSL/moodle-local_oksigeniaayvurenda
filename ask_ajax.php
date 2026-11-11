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
 * «What do you want to do?», answered as someone types in «More»: the tasks and settings that answer it, each with what to
 * do in a line, where to go, its guided help and, for a setting, what it is now.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\howto;

require_login(null, false);
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
$query = trim(core_text::substr(optional_param('q', '', PARAM_TEXT), 0, 200));
header('Content-Type: application/json; charset=utf-8');
if (!$library || $query === '' || !howto::may_ask($library)) {
    echo json_encode(['answers' => []]);
    die();
}
$PAGE->set_context(context_system::instance());
echo json_encode(howto::answers($query, $library, (int) $USER->id));
