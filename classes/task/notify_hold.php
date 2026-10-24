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
 * Tells a reader that the book they reserved is set aside for them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

/**
 * Queued when a copy is set aside, a couple of minutes later so that an «Undo» at the desk takes it back first.
 */
class notify_hold extends \core\task\adhoc_task {
    /**
     * Sends the message, if the copy is still set aside.
     */
    public function execute(): void {
        \local_oksigeniaayvurenda\local\holds::notify((int) ($this->get_custom_data()->holdid ?? 0));
    }
}
