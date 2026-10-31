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
 * What the desk asks the server: one action per request, answered in JSON.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$action = required_param('action', PARAM_ALPHA);
require_login(null, false);
require_sesskey();
$library = catalogue::library($ref);
$own = access::keeps($library);
if (!$own) {
    access::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
// A scan kept while the network was down comes later with the moment it was made, so the dates are those of that moment
// (from the last three days, never later than now).
$at = optional_param('at', 0, PARAM_INT);
$desk = new desk($library, (int) $USER->id, $at && $at <= time() && $at > time() - 3 * DAYSECS ? $at : null, $own);
switch ($action) {
    case 'scan':
        $answer = $desk->scan(
            core_text::substr(required_param('code', PARAM_TEXT), 0, 200),
            optional_param('readerid', 0, PARAM_INT),
            optional_param('force', false, PARAM_BOOL)
        );
        break;
    case 'reader':
        $answer = ['kind' => 'reader', 'sound' => 'ok', 'message' => '',
            'reader' => $desk->reader_panel(required_param('readerid', PARAM_INT))];
        break;
    case 'renew':
        $answer = $desk->renew(required_param('loanid', PARAM_INT));
        break;
    case 'note':
        $answer = $desk->note(required_param('loanid', PARAM_INT), optional_param('note', '', PARAM_ALPHA));
        break;
    case 'condition':
        $answer = $desk->condition(
            required_param('loanid', PARAM_INT),
            required_param('when', PARAM_ALPHA),
            required_param('condition', PARAM_ALPHA)
        );
        break;
    case 'undo':
        $answer = $desk->undo(
            required_param('loanid', PARAM_INT),
            required_param('what', PARAM_ALPHA),
            optional_param('previousdue', 0, PARAM_INT)
        );
        break;
    case 'room':
        $answer = $desk->room(required_param('itemid', PARAM_INT));
        break;
    case 'roomundo':
        $answer = $desk->room_undo(required_param('roomid', PARAM_INT));
        break;
    case 'today':
        $answer = ['kind' => 'today'] + $desk->today();
        break;
    default:
        throw new moodle_exception('invalidparameter', 'debug');
}
echo json_encode($answer, JSON_UNESCAPED_UNICODE);
