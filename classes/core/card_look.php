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

    /** @var string Words of the youngest, whatever the country calls them (Infantil and Primaria, nursery and
     * kindergarten, reception, the French cycles, the Colombian jardín and transición, a public library's children). */
    private const YOUNG = 'infantil|primaria|preescolar|kinder|kindergarten|nursery|primary|elementary|inf|pri|ei|ep|reception|'
        . 'jardin|prejardin|transicion|parvulario|maternelle|elementaire|grundschule|cp|ce1|ce2|cm1|cm2|bebeteca|cuentacuentos';

    /** @var string Words of a grade counted by its number (Grado 2, Grade 7, Year 10, Klasse 3a, 4.º Básico). */
    private const NUMBERED = 'grado|grade|grades|year|yr|klasse|class|classe|clase|curso|basico|basica|ano|anno|ciclo basico';

    /** @var string Words of adults. */
    private const ADULT = 'bachillerato|bachiller|bac|fp|ciclo formativo|formativo|cfgm|cfgs|universidad|university|facultad|'
        . 'master|adultos|adults|adultas|mayores|senior|seniors|senioren|empleados|staff';

    /**
     * The look by what the centre calls the stage and the group; teachers, staff and families, none. What it does not
     * recognise gets the look given for that ($unknown): the lively sky in a school, the sober one where readers are
     * grown-ups.
     *
     * @param string $readertype
     * @param string $stage
     * @param string $group
     * @param string $unknown
     * @return string
     */
    public static function guess(string $readertype, string $stage, string $group, string $unknown = 'sky'): string {
        if ($readertype !== 'student') {
            return 'none';
        }
        $words = ' ' . text::normalise($stage . ' ' . $group) . ' ';
        // The first number of the group or the stage is its grade (the letter of «4º ESO A2» is not).
        $number = preg_match('/\d+/', $group . ' ' . $stage, $matches) ? (int) $matches[0] : null;
        if (preg_match('/ (' . self::YOUNG . ') /', $words)) {
            return 'flower';
        }
        if (preg_match('/ (eso|secundaria|secondary|sec|middle) /', $words)) {
            // First and second of secondary are twelve to fourteen.
            return in_array($number, [1, 2], true) ? 'sky' : 'skysober';
        }
        // A university degree («Grado en Historia») and the grown-ups.
        if (preg_match('/ grado en /', $words) || preg_match('/ (' . self::ADULT . ') /', $words)) {
            return 'skysober';
        }
        // Collège in France: sixième to quatrième, twelve to fourteen; troisième, fifteen.
        if (preg_match('/ ([3-6])eme /', $words, $french)) {
            return (int) $french[1] === 3 ? 'skysober' : 'sky';
        }
        // A grade by its number, as most of the world counts: up to sixth, the flower; seventh to ninth, the lively sky.
        if ($number !== null && preg_match('/ (' . self::NUMBERED . ') /', $words)) {
            return $number <= 6 ? 'flower' : ($number <= 9 ? 'sky' : 'skysober');
        }
        return in_array($unknown, self::LOOKS, true) ? $unknown : 'sky';
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
