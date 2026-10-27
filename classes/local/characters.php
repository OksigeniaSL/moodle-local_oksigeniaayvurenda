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
            $sex = ($mine['sex'] ?? ($role === 'herald' ? 'm' : 'f')) === 'm' ? 'm' : 'f';
            $drawn = $role === 'herald' ? 'pregonero' : 'memoria';
            $all[$role] = [
                'role' => $role,
                'enabled' => (bool) ($mine['enabled'] ?? true),
                'name' => $name !== '' ? $name : get_string($role . 'defaultname', 'local_oksigeniaayvurenda'),
                // The grandmother who remembers and the hummingbird who heralds: one of each.
                'sex' => $sex,
                // Gaudí's portrait, when the art package is there: one of the other sex if it has it, else the same
                // grandmother and hummingbird as their drawings whole.
                'portrait' => images::url('portrait', $entityid, $role)
                    ?? ($sex === ($role === 'herald' ? 'm' : 'f') ? null : screens::art($drawn . '-' . $sex))
                    ?? screens::art($drawn),
                'own' => images::url('portrait', $entityid, $role) !== null,
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
     * Which step of the characters the one looking sees in a centre (they grow with the reader, as the card does): the
     * sky's for a reader of the centre whose card is not the flower; the flower's for everyone else (the young, the
     * staff, whoever comes without an account).
     *
     * @param int $entityid
     * @return string '' for the flower's, or cielo.
     */
    public static function step(int $entityid): string {
        global $DB, $USER;
        if (!$entityid || !isloggedin() || isguestuser()) {
            return '';
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $key = 'step_' . $entityid . '_' . $USER->id;
        $step = $cache->get($key);
        if ($step === false) {
            $reader = $DB->get_record(
                'local_oksigeniaayvurenda_reader',
                ['entityid' => $entityid, 'userid' => $USER->id, 'status' => 'active'],
                '*',
                IGNORE_MULTIPLE
            );
            $step = $reader && cards::look_of($reader) !== 'flower' ? 'cielo' : '';
            $cache->set($key, $step);
        }
        return (string) $step;
    }

    /**
     * Gaudí's drawing of a character whole, in a pose, from the art package (null without it): the memory, or
     * Mainumby for the herald; of the sky's step when asked and drawn, else the flower's.
     *
     * @param string $role memory or herald.
     * @param string $pose saluda, senala, celebra, lee or piensa.
     * @param string $step '' or cielo.
     * @return string|null
     */
    public static function figure(string $role, string $pose, string $step = ''): ?string {
        $name = 'personajes/' . ($role === 'herald' ? 'mainumby' : 'memoria');
        return ($step === 'cielo' ? screens::art($name . '-cielo-' . $pose) : null) ?? screens::art($name . '-' . $pose);
    }

    /**
     * Gaudí's puppet of a character doing the gesture of a pose, from the art package: what the page needs to bring it to
     * life over the pose drawn still ('' without it, and the pose stays).
     *
     * @param string $role memory or herald.
     * @param string $pose saluda, senala, celebra, lee or piensa.
     * @param string $step '' or cielo.
     * @return string JSON: image, atlas, rig, anim and gesture.
     */
    public static function puppet(string $role, string $pose, string $step = ''): string {
        $name = $role === 'herald' ? 'mainumby' : 'memoria';
        // The puppet goes with the drawing it brings to life: the sky's drawing without its puppet stays still.
        if ($step === 'cielo' && screens::art('personajes/' . $name . '-cielo-' . $pose)) {
            $name .= '-cielo';
        }
        $parts = [
            'image' => screens::art('marionetas/' . $name),
            'atlas' => screens::art('marionetas/' . $name . '.atlas', 'json'),
            'rig' => screens::art('marionetas/' . $name . '.rig', 'json'),
            'anim' => screens::art('marionetas/' . $name . '.anim', 'json'),
        ];
        if (in_array(null, $parts, true)) {
            return '';
        }
        $gestures = $role === 'herald'
            ? ['saluda' => 'arrive', 'senala' => 'point', 'celebra' => 'celebrate', 'lee' => 'read', 'piensa' => 'idle']
            : ['saluda' => 'greet', 'senala' => 'point', 'celebra' => 'celebrate', 'lee' => 'read', 'piensa' => 'think'];
        return (string) json_encode($parts + ['gesture' => $gestures[$pose] ?? 'idle']);
    }

    /**
     * What a character says, in words that agree with its sex: a string key with the ending f or m, when there is one.
     *
     * @param int $entityid
     * @param string $role
     * @param string $key A string of the plugin; key + 'f' or 'm' if it changes with the sex.
     * @param array $a More for the string; the name is always there.
     * @param string $pose How it stands, when the art package draws it whole (saluda, senala, celebra, lee, piensa):
     *     only for a character without a portrait of the centre's own.
     * @return array|null name, text, initial, role, figure, puppet; null when the character is off.
     */
    public static function say(int $entityid, string $role, string $key, array $a = [], string $pose = ''): ?array {
        $character = self::of($entityid)[$role] ?? null;
        if (!$character || !$character['enabled']) {
            return null;
        }
        $sm = get_string_manager();
        $step = $pose !== '' && empty($character['own']) ? self::step($entityid) : '';
        $gendered = $key . $character['sex'];
        $key = $sm->string_exists($gendered, 'local_oksigeniaayvurenda') ? $gendered : $key;
        return [
            'name' => $character['name'],
            'text' => get_string($key, 'local_oksigeniaayvurenda', (object) ($a + ['name' => $character['name']])),
            'initial' => $character['initial'],
            'portrait' => $character['portrait'],
            'figure' => $pose !== '' && empty($character['own']) ? self::figure($role, $pose, $step) : null,
            'puppet' => $pose !== '' && empty($character['own']) ? self::puppet($role, $pose, $step) : '',
            'role' => $role,
            // Its voice, for a young reader, is added by the screen that knows the reader.
            'listen' => null,
        ];
    }
}
