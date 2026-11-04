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

namespace local_oksigeniaayvurenda\task;

/**
 * Makes many reader cards at once, away from the page that asked for them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class make_cardprint extends \core\task\adhoc_task {
    /**
     * Runs it.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $data->library]);
        if (!$library) {
            return;
        }
        raise_memory_limit(MEMORY_EXTRA);
        $file = \local_oksigeniaayvurenda\local\cardprint::make(
            $library,
            (array) $data->scope,
            (string) $data->format,
            (string) $data->label,
            (int) $data->userid
        );
        mtrace('Ayvurenda: cards of entity ' . $library->entityid . ': ' . ($file ? $file->get_filesize() . ' bytes' : 'nobody'));
    }
}
