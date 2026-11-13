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
 * «Llévatelo»: the hidden book a reader found in the visit, with the lanterns left, kept until they take it home.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\carry;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\orientation;

require_login(null, false);
require_sesskey();
// The route's block is drawn here from a template, and the badge may be given: both need the page's context.
$PAGE->set_context(context_system::instance());
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
access::require('local/oksigeniaayvurenda:view', $library);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
// Only a reader with a card keeps what they found: the game is the same for everyone else.
$reader = $DB->get_record(
    'local_oksigeniaayvurenda_reader',
    ['entityid' => $library->entityid, 'userid' => $USER->id, 'status' => 'active'],
    '*',
    IGNORE_MULTIPLE
);
$kept = $reader && carry::found(
    $library,
    (int) $reader->id,
    required_param('corner', PARAM_ALPHANUM),
    required_param('turn', PARAM_INT),
    required_param('record', PARAM_INT),
    required_param('lanterns', PARAM_INT),
    time()
);
// A room more of the orientation, and its badge with the third (P8): what the game shows, drawn here.
$route = $kept ? orientation::step($reader, time()) : null;
$shown = $route && ($route['left'] > 0 || $route['finished']) ? $OUTPUT->render_from_template(
    'local_oksigeniaayvurenda/orientation_step',
    orientation::shown($reader, $library, $route) + ['avance' => null, 'insignia' => null]
) : '';
echo json_encode(['kept' => $kept, 'orientation' => $route ? ['done' => $route['done'], 'left' => $route['left'],
    'finished' => $route['finished'], 'says' => orientation::says($route), 'html' => $shown] : null]);
