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
 * What a centre says about its subscribed resources.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\links as link_rules;

/**
 * A centre with a proxy or a link resolver says so once, and every link marked as subscribed opens from home. This is no
 * proxy: whoever opens it goes through the institution's, with its own login.
 */
final class links {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What a centre has.
     *
     * @param \stdClass|int $where
     * @return array proxy and resolver, or '' for none.
     */
    public static function settings($where): array {
        global $DB;
        $entityid = is_object($where) ? (int) $where->entityid : (int) $where;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return ['proxy' => (string) ($settings['linkproxy'] ?? ''), 'resolver' => (string) ($settings['linkresolver'] ?? '')];
    }

    /**
     * Where a link of the digital collection opens.
     *
     * @param \stdClass $library
     * @param \stdClass $item
     * @return string
     */
    public static function out(\stdClass $library, \stdClass $item): string {
        $url = (string) $item->url;
        return empty($item->subscribed) ? $url : link_rules::through($url, self::settings($library)['proxy']);
    }
}
