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
 * The skin of a centre's library: a school, a secondary school, or a university or academy.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Each centre chooses the skin of its library, and with it how much play and fantasy there is on top: not childish at a
 * university nor academic at a school. Under every skin the reader's own step by age goes on (the flower, the sky), and
 * play is chosen and can be turned off. What each skin is, in the project's DESIGN.md.
 */
final class skins {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The skins, the school's first: it is the one a centre has until it chooses. */
    public const SKINS = ['school', 'secondary', 'university'];

    /**
     * The skin of a centre.
     *
     * @param int $entityid
     * @return string One of SKINS.
     */
    public static function of(int $entityid): string {
        global $DB;
        if (!$entityid) {
            return self::SKINS[0];
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $skin = $cache->get('skin_' . $entityid);
        if ($skin === false) {
            $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $skin = in_array($settings['skin'] ?? '', self::SKINS, true) ? $settings['skin'] : self::SKINS[0];
            $cache->set('skin_' . $entityid, $skin);
        }
        return $skin;
    }

    /**
     * Whether the characters of a centre stand whole and move, and speak with their voices: at a school and a secondary
     * school; at a university or academy they are a portrait and a signature, with no voice of their own.
     *
     * @param int $entityid
     * @return bool
     */
    public static function lively(int $entityid): bool {
        return self::of($entityid) !== 'university';
    }

    /**
     * Forgets the skin kept for this request, after it changes.
     *
     * @param int $entityid
     */
    public static function forget(int $entityid): void {
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('skin_' . $entityid);
    }
}
