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
 * Makes the copy of a centre's files and sends it out of the server: it can take a while.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class make_filecopy extends \core\task\adhoc_task {
    /**
     * Runs it.
     */
    public function execute(): void {
        $entityid = (int) $this->get_custom_data()->entity;
        raise_memory_limit(MEMORY_EXTRA);
        $file = \local_oksigeniaayvurenda\local\filecopy::make($entityid);
        mtrace('Ayvurenda: files of entity ' . $entityid . ': ' . $file->get_filesize() . ' bytes');
        $out = \local_oksigeniaayvurenda\local\offsite::send_files($entityid, $file);
        if ($out['sent'] || $out['failed']) {
            mtrace('Ayvurenda: sent to ' . $out['sent'] . ' places' . ($out['failed'] ? ', failed: ' .
                implode(', ', $out['failed']) : ''));
        }
    }
}
