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

namespace local_oksigeniaayvurenda\local;

/**
 * «Propose an improvement» on the screens of the staff and the tutors, where the site has Oksigenia Marandu: the person
 * marks on a picture of what they see what they would change, and whoever the site chose hears of it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class improve {
    /** The piece of Oksigenia Marandu that every tool of the site shares. */
    private const API = '\local_oksigeniamarandu\api';

    /**
     * What the piece needs on this page: its script, its styles and the data to print.
     *
     * @param \stdClass|null $library
     * @param int $userid
     * @return array|null Null where there is nothing to offer: no Marandu, or neither staff nor tutor here.
     */
    public static function assets(?\stdClass $library, int $userid): ?array {
        if (!$library || !class_exists(self::API) || !method_exists(self::API, 'proposal_assets')) {
            return null;
        }
        if (!self::works_here($library, $userid)) {
            return null;
        }
        try {
            return (self::API)::proposal_assets();
        } catch (\Throwable $e) {
            // Another plugin never stops the library.
            debugging('Ayvurenda could not add «Propose an improvement»: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Whether someone works in this library: its staff, whoever keeps it or the tutor of a class of its centre.
     *
     * @param \stdClass $library
     * @param int $userid
     * @return bool
     */
    public static function works_here(\stdClass $library, int $userid): bool {
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        if (
            access::can('local/oksigeniaayvurenda:desk', $library, $userid)
            || access::can('local/oksigeniaayvurenda:catalogue', $library, $userid)
            || challenges::tutored((int) $library->entityid, $userid)
        ) {
            return true;
        }
        foreach (keepers::libraries($userid) as $kept) {
            if ((int) $kept->entityid === (int) $library->entityid) {
                return true;
            }
        }
        return false;
    }

    /**
     * The button, with the name of the screen it is on.
     *
     * @param string $screen The title of the page.
     * @return string
     */
    public static function button(string $screen): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('local_oksigeniaayvurenda/improve', [
            'screen' => $screen,
            'label' => get_string('proposal:button', 'local_oksigeniamarandu'),
        ]);
    }
}
