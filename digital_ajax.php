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
 * Keeps where someone is in a digital book or recording, and their bookmarks.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\digital;

require_login(null, false);
require_sesskey();
$item = $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => required_param('item', PARAM_INT)], '*', MUST_EXIST);
access::require('local/oksigeniaayvurenda:view', (int) $item->entityid);
if (!digital::visible($item, digital::viewer((int) $item->entityid, (int) $USER->id))) {
    throw new required_capability_exception(
        access::context((int) $item->entityid),
        'local/oksigeniaayvurenda:desk',
        'nopermissions',
        ''
    );
}
$position = optional_param('position', null, PARAM_RAW_TRIMMED);
$bookmarks = optional_param('bookmarks', null, PARAM_RAW);
digital::keep_place((int) $item->id, (int) $USER->id, $position, $bookmarks !== null ? (json_decode($bookmarks, true) ?: [])
    : null);
echo json_encode(['ok' => true]);
