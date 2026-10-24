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
 * Suggestions while filling a box: the free copies whose book has the words typed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\lots;

require_login(null, false);
require_sesskey();
require_capability('local/oksigeniaayvurenda:desk', context_system::instance());
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$lot = lots::get(required_param('lot', PARAM_INT), $library);
$text = trim(required_param('q', PARAM_TEXT));
echo json_encode(['suggestions' => $lot->status === 'open' && preg_match('/\p{L}{2,}/u', $text) ? lots::suggest($lot, $text)
    : []]);
