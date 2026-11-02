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
 * The passwords and keys a centre gives the plugin, encrypted in the settings with the key Moodle keeps in its data folder: a dump
 * of the database does not give them away.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class secrets {
    /** @var string The plugin's settings. */
    private const COMPONENT = 'local_oksigeniaayvurenda';

    /**
     * Keeps one.
     *
     * @param string $name
     * @param string $value
     */
    public static function put(string $name, string $value): void {
        set_config($name, \core\encryption::encrypt($value), self::COMPONENT);
    }

    /**
     * Gives one back; one kept before they were encrypted comes as it is, and one that cannot be opened (the data folder is
     * not this one) comes empty, so the centre is asked for it again.
     *
     * @param string $name
     * @return string
     */
    public static function get(string $name): string {
        $value = (string) get_config(self::COMPONENT, $name);
        if (!preg_match('/^(sodium|openssl):/', $value)) {
            return $value;
        }
        try {
            return \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
