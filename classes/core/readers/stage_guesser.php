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
 * A guess of the stage from a group's name.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

/**
 * Proposes the stage of a group from its name («ESO 3º C» → «ESO», «3º Primaria A» → «Primaria», «Year 5 Blue» →
 * «Year»), for the librarian to confirm or change. Groups are named in every way in the world, so this only fills
 * the field: it never decides.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class stage_guesser {
    /** @var string[] Small words that are never a stage. */
    private const SKIP = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'of', 'the', 'and', 'grupo', 'group', 'curso', 'clase'];

    /**
     * The first word of the name that looks like a stage: letters, two or more, not an ordinal or a group letter.
     *
     * @param string $name
     * @return string Empty when nothing looks like one.
     */
    public static function guess(string $name): string {
        $words = preg_split('/[\s,;:\/\-]+/u', trim($name)) ?: [];
        foreach ($words as $word) {
            $clean = trim($word, " .º°ª()[]");
            if (!preg_match('/^\p{L}{2,}$/u', $clean)) {
                continue;
            }
            if (in_array(mb_strtolower($clean), self::SKIP, true)) {
                continue;
            }
            return $clean;
        }
        return '';
    }
}
