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
 * Hook callbacks.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda;

use core\hook\navigation\primary_extend;

/**
 * What the library adds to the pages of Moodle.
 */
final class hook_callbacks {
    /**
     * In the top bar of the site: «Biblioteca», when there is one and the user may look it up.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        global $DB;
        if (!isloggedin() || isguestuser() || !has_capability('local/oksigeniaayvurenda:view', \context_system::instance())) {
            return;
        }
        if (!$DB->record_exists('local_oksigeniaayvurenda_library', [])) {
            return;
        }
        $hook->get_primaryview()->add(
            get_string('navlibrary', 'local_oksigeniaayvurenda'),
            new \moodle_url('/local/oksigeniaayvurenda/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_oksigeniaayvurenda_library'
        );
    }
}
