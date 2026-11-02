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
 * The reminder of a textbook bank's collecting, in the background.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

use local_oksigeniaayvurenda\local\bankround;

/**
 * Queued from the page of the round, for its readers or its tutors.
 */
class bank_reminder extends \core\task\adhoc_task {
    /**
     * Sends them.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => (int) $data->library]);
        if (!$library) {
            return;
        }
        if ($data->who === 'tutors') {
            bankround::remind_tutors($library, time());
        } else {
            bankround::remind_readers($library, time());
        }
    }
}
