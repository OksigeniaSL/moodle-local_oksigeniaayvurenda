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
 * Asks once for the cover of a book that has none.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\task;

/**
 * Queued when a book without a cover is lent, comes back or is looked at: one question for that book, never a sweep
 * of the catalogue.
 */
class find_cover extends \core\task\adhoc_task {
    /**
     * Asks for the cover of the book, if it still has none.
     */
    public function execute(): void {
        \local_oksigeniaayvurenda\local\covers::find((int) ($this->get_custom_data()->recordid ?? 0));
    }
}
