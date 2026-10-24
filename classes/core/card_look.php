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
 * Which card a reader sees, by age.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The flower is for Infantil and Primaria; from twelve on, the sky (a constellation for each path and the moon as the
 * stage), lively from twelve to fourteen and sober from fifteen and for adults; and whoever wants it, the classic look
 * (a list, without a metaphor) or none at all. Teachers, staff and families see no game unless they turn it on. The
 * centre decides by stage; from twelve on, each reader may choose. Nobody is ranked. Part of the core: plain PHP.
 */
final class card_look {
    /** @var string[] The looks a centre can give a stage. */
    public const LOOKS = ['flower', 'sky', 'skysober', 'classic', 'none'];

    /** @var string[] What a reader may choose for themselves (not with the flower). */
    public const OWN = ['sky', 'classic', 'none'];

    /**
     * The look by what the centre calls the stage and the group; teachers, staff and families, none.
     *
     * @param string $readertype
     * @param string $stage
     * @param string $group
     * @return string
     */
    public static function guess(string $readertype, string $stage, string $group): string {
        if ($readertype !== 'student') {
            return 'none';
        }
        $words = ' ' . text::normalise($stage . ' ' . $group) . ' ';
        if (preg_match('/ (infantil|primaria|preescolar|kinder|kindergarten|nursery|primary|elementary|inf|pri|ei|ep) /', $words)) {
            return 'flower';
        }
        if (preg_match('/ (eso|secundaria|secondary|sec) /', $words)) {
            // First and second of secondary are twelve to fourteen.
            return preg_match('/(^|\D)([12])(\D|$)/', $group . ' ' . $stage, $matches) ? 'sky' : 'skysober';
        }
        if (
            preg_match(
                '/ (bachillerato|bachiller|bac|fp|ciclo|grado|universidad|university|facultad|master|adultos|adults) /',
                $words
            )
        ) {
            return 'skysober';
        }
        return 'sky';
    }

    /**
     * The look a reader sees: the centre's for the stage, else the guess; then their own choice, except with the
     * flower, which the centre decides.
     *
     * @param string $guess
     * @param string|null $centre
     * @param string|null $own sky, classic or none.
     * @return string
     */
    public static function resolve(string $guess, ?string $centre, ?string $own): string {
        $base = in_array($centre, self::LOOKS, true) ? $centre : $guess;
        if ($base === 'flower' || !in_array($own, self::OWN, true)) {
            return $base;
        }
        if ($own === 'sky') {
            return in_array($base, ['sky', 'skysober'], true) ? $base : 'skysober';
        }
        return $own;
    }
}
