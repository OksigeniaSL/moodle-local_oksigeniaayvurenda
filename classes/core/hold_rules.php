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
 * The rules of reservations.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * A reservation waits in a queue, first come first served, until a copy comes back; then the copy is set aside for a few
 * school days. Reserving online is a switch of the library, for everyone or by stage: a centre may want the youngest to
 * ask at the desk and the oldest to reserve from home. Staff reserve at the desk whatever the switch says. Part of the
 * core: plain PHP, with no call to Moodle.
 */
final class hold_rules {
    /** @var array The settings when a library has none. */
    public const DEFAULTS = ['online' => false, 'stages' => [], 'days' => 5, 'max' => 3, 'renew' => false];

    /**
     * The settings, with the defaults where something is missing.
     *
     * @param array $settings
     * @return array online, stages, days, max, renew.
     */
    public static function settings(array $settings): array {
        $settings += self::DEFAULTS;
        return [
            'online' => !empty($settings['online']),
            'stages' => array_values(array_filter(array_map('strval', (array) $settings['stages']), fn($s) => trim($s) !== '')),
            'days' => max(1, min(30, (int) $settings['days'])),
            'max' => max(1, min(20, (int) $settings['max'])),
            'renew' => !empty($settings['renew']),
        ];
    }

    /**
     * Whether a reader may reserve online: the switch is on, and their stage is one of those chosen (or none was
     * chosen). Teachers and staff have no stage: the switch alone says.
     *
     * @param array $settings As settings() gives them.
     * @param string $readertype
     * @param string|null $stage The reader's stage this year.
     * @return bool
     */
    public static function online(array $settings, string $readertype, ?string $stage): bool {
        if (empty($settings['online'])) {
            return false;
        }
        if (!$settings['stages'] || $readertype !== 'student') {
            return true;
        }
        $mine = text::normalise((string) $stage);
        foreach ($settings['stages'] as $chosen) {
            if (text::normalise($chosen) === $mine) {
                return true;
            }
        }
        return false;
    }

    /**
     * The order of a queue: who asked first.
     *
     * @param object[] $holds With id and timecreated.
     * @return object[]
     */
    public static function queue(array $holds): array {
        usort($holds, fn($a, $b) => [(int) $a->timecreated, (int) $a->id] <=> [(int) $b->timecreated, (int) $b->id]);
        return $holds;
    }

    /**
     * How many are before a reservation in its queue.
     *
     * @param object[] $holds The waiting reservations of the book.
     * @param int $holdid
     * @return int
     */
    public static function ahead(array $holds, int $holdid): int {
        foreach (self::queue($holds) as $position => $hold) {
            if ((int) $hold->id === $holdid) {
                return $position;
            }
        }
        return count($holds);
    }
}
