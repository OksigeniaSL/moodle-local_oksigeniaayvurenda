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
 * The librarian role.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * A role for the people who run the library, given at site level: they look up, lend, take back and catalogue. Created
 * when the plugin is installed, so that the administrator only has to give it.
 */
final class roles {
    /** @var string Short name of the role. */
    public const LIBRARIAN = 'ayvurendalibrarian';

    /**
     * Creates the librarian role if the site does not have it, and gives it its capabilities.
     *
     * @return int The role id.
     */
    public static function ensure_librarian(): int {
        global $DB;
        // On a fresh install this runs before Moodle reads the plugin's capabilities: read them first.
        update_capabilities('local_oksigeniaayvurenda');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::LIBRARIAN]);
        if (!$roleid) {
            $roleid = create_role(
                get_string('rolelibrarian', 'local_oksigeniaayvurenda'),
                self::LIBRARIAN,
                get_string('rolelibrarian_desc', 'local_oksigeniaayvurenda')
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }
        $system = \context_system::instance();
        foreach (['view', 'desk', 'catalogue'] as $capability) {
            assign_capability('local/oksigeniaayvurenda:' . $capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        $system->mark_dirty();
        return $roleid;
    }
}
