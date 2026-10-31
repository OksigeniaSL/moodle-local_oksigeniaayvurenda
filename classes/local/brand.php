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
 * The look of a reader's library, for an app to dress itself with.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Where many libraries share one site (ayvurenda.com), an app can show each reader their own: the name of their
 * centre, the colour, the picture and the line of its main library. Oksigenia Aty asks for it through the callback
 * `local_oksigeniaayvurenda_oksigeniaaty_user_brand()`; the official Moodle app shows the site's.
 */
final class brand {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The house's colours, by the number a library's card keeps. */
    private const COLOURS = ['#17574c', '#e2654c', '#f0b43c', '#2c7a6a', '#0f3a33', '#17574c'];

    /**
     * The look of the library a user reads at: their centre's main library (the one marked as the site's main seat,
     * else its central one, else its first). Null for whoever is not an active reader, or reads at a centre still open
     * only to its staff.
     *
     * @param int $userid
     * @return array|null name (the centre), library, colour (#rrggbb), logo (address, or null), tagline, url (its home).
     */
    public static function of_user(int $userid): ?array {
        global $DB;
        $readers = $DB->get_records(self::T . 'reader', ['userid' => $userid, 'status' => 'active'], 'id', 'id, entityid');
        foreach ($readers as $reader) {
            $entityid = (int) $reader->entityid;
            if (access::closed($entityid)) {
                continue;
            }
            $libraries = $DB->get_records_select(
                self::T . 'library',
                'entityid = :entityid AND kind <> :bank',
                ['entityid' => $entityid, 'bank' => bank::KIND],
                "kind = 'central' DESC, id"
            );
            if (!$libraries) {
                continue;
            }
            $main = reset($libraries);
            foreach ($libraries as $library) {
                if (libraries::look($library)['featured']) {
                    $main = $library;
                    break;
                }
            }
            $look = libraries::look($main);
            return [
                'name' => format_string((string) $DB->get_field(self::T . 'entity', 'name', ['id' => $entityid])),
                'library' => format_string($main->name),
                'colour' => self::COLOURS[$look['colour']] ?? self::COLOURS[0],
                'logo' => $look['image'],
                'tagline' => $look['motto'],
                'url' => (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $main->slug]))->out(false),
            ];
        }
        return null;
    }
}
