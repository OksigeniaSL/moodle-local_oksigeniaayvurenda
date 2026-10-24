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
 * The two characters of the library: the memory and the herald.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Two characters with different trades give everyone a simple map: the memory is asked (searching, recommending,
 * what each reader read) and the herald tells what happens (the new books, what grew, the notices). By default the two
 * of Ayvurenda, the same in every centre; each centre gives them its name and sex, so the words agree, or turns them
 * off. They speak little and never hold anything up.
 */
final class characters {
    /** @var string[] The two trades. */
    public const ROLES = ['memory', 'herald'];

    /**
     * The characters of an entity, as it set them or as they come.
     *
     * @param int $entityid
     * @return array Role => enabled, name, sex (f or m), signs (the herald signs the notices), initial.
     */
    public static function of(int $entityid): array {
        global $DB;
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        ) ?: [];
        $chosen = $settings['characters'] ?? [];
        $all = [];
        foreach (self::ROLES as $role) {
            $mine = $chosen[$role] ?? [];
            $name = trim((string) ($mine['name'] ?? ''));
            $all[$role] = [
                'role' => $role,
                'enabled' => (bool) ($mine['enabled'] ?? true),
                'name' => $name !== '' ? $name : get_string($role . 'defaultname', 'local_oksigeniaayvurenda'),
                // The grandmother who remembers and the hummingbird who heralds: one of each.
                'sex' => ($mine['sex'] ?? ($role === 'herald' ? 'm' : 'f')) === 'm' ? 'm' : 'f',
                'signs' => $role === 'herald' && !empty($mine['signs']),
                // Until the puppets come, the initial of the name, not a makeshift drawing.
                'initial' => \core_text::strtoupper(\core_text::substr($name !== '' ? $name
                    : get_string($role . 'defaultname', 'local_oksigeniaayvurenda'), 0, 1)),
            ];
        }
        return $all;
    }

    /**
     * Keeps what the entity chose.
     *
     * @param int $entityid
     * @param array $chosen Role => enabled, name, sex, signs.
     */
    public static function save(int $entityid, array $chosen): void {
        global $DB;
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        ) ?: [];
        foreach (self::ROLES as $role) {
            $mine = $chosen[$role] ?? [];
            $settings['characters'][$role] = [
                'enabled' => !empty($mine['enabled']),
                'name' => \core_text::substr(trim((string) ($mine['name'] ?? '')), 0, 60),
                'sex' => in_array($mine['sex'] ?? '', ['f', 'm'], true) ? $mine['sex'] : ($role === 'herald' ? 'm' : 'f'),
                'signs' => $role === 'herald' && !empty($mine['signs']),
            ];
        }
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * What a character says, in words that agree with its sex: a string key with the ending f or m, when there is one.
     *
     * @param int $entityid
     * @param string $role
     * @param string $key A string of the plugin; key + 'f' or 'm' if it changes with the sex.
     * @param array $a More for the string; the name is always there.
     * @return array|null name, text, initial, role; null when the character is off.
     */
    public static function say(int $entityid, string $role, string $key, array $a = []): ?array {
        $character = self::of($entityid)[$role] ?? null;
        if (!$character || !$character['enabled']) {
            return null;
        }
        $sm = get_string_manager();
        $gendered = $key . $character['sex'];
        $key = $sm->string_exists($gendered, 'local_oksigeniaayvurenda') ? $gendered : $key;
        return [
            'name' => $character['name'],
            'text' => get_string($key, 'local_oksigeniaayvurenda', (object) ($a + ['name' => $character['name']])),
            'initial' => $character['initial'],
            'role' => $role,
        ];
    }
}
