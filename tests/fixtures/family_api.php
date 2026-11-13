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
 * A stand-in for the families of Oksigenia Family where it is not installed: only what the library asks of it.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniafamily;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (!class_exists('\local_oksigeniafamily\api', false)) {
    /**
     * Families in memory, for a test.
     */
    class api {
        /** @var array Families: adults and children. */
        private static array $families = [];

        /** @var int The last id given. */
        private static int $next = 0;

        /**
         * Forgets every family: one test's families are not the next one's.
         */
        public static function reset(): void {
            self::$families = [];
            self::$next = 0;
        }

        /**
         * A new family.
         *
         * @param string $source
         * @param string|null $sourcekey
         * @return int
         */
        public static function create_family(string $source = 'manual', ?string $sourcekey = null): int {
            $id = ++self::$next;
            self::$families[$id] = ['adults' => [], 'children' => []];
            return $id;
        }

        /**
         * An adult of a family.
         *
         * @param int $familyid
         * @param array $data
         * @return int
         */
        public static function add_adult(int $familyid, array $data): int {
            $id = ++self::$next;
            self::$families[$familyid]['adults'][$id] = (object) ($data + ['id' => $id, 'familyid' => $familyid,
                'firstname' => '', 'lastname' => '', 'email' => '', 'phone' => '', 'userid' => null]);
            return $id;
        }

        /**
         * A child of a family.
         *
         * @param int $familyid
         * @param int|null $userid
         * @param string|null $sourcekey
         * @return int
         */
        public static function add_child(int $familyid, ?int $userid, ?string $sourcekey = null): int {
            self::$families[$familyid]['children'][] = $userid;
            return ++self::$next;
        }

        /**
         * The adults of a child.
         *
         * @param int $childuserid
         * @return \stdClass[]
         */
        public static function adults_of_child(int $childuserid): array {
            $adults = [];
            foreach (self::$families as $family) {
                if (in_array($childuserid, $family['children'], true)) {
                    $adults += $family['adults'];
                }
            }
            return $adults;
        }
    }
}
