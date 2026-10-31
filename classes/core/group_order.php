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
 * The order of a centre's groups and stages.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * Groups as a school lists them, not as the alphabet does: by stage (infant, primary, secondary, upper secondary,
 * vocational, then the rest), then by year, then by letter — «1.º Infantil A» before «1.º Primaria A», «2.º Primaria
 * B» before «10.º …». The stage comes from the reader's record when there is one, else from the group's own name.
 * Plain PHP, without Moodle.
 */
final class group_order {
    /** @var array<string, int> Words of each stage, in its place (normalised: lower case, no accents). */
    private const STAGES = [
        'infantil' => 1, 'preescolar' => 1, 'kindergarten' => 1, 'nursery' => 1, 'reception' => 1, 'early' => 1,
        'primaria' => 2, 'primary' => 2, 'elementary' => 2, 'basica' => 2,
        'secundaria' => 3, 'eso' => 3, 'secondary' => 3, 'middle' => 3, 'media' => 3,
        'bachillerato' => 4, 'bachiller' => 4, 'bach' => 4, 'sixth' => 4, 'high' => 4, 'preparatoria' => 4,
        'fp' => 5, 'ciclo' => 5, 'formacion' => 5, 'vocational' => 5, 'cfgm' => 5, 'cfgs' => 5,
    ];

    /**
     * Where a stage goes: 1 to 5 for the known ones, 8 for any other, 9 for none.
     *
     * @param string|null $stage A stage, or a group's name to find it in.
     * @return int
     */
    public static function stage(?string $stage): int {
        $words = preg_split('/[^a-z]+/', text::normalise((string) $stage)) ?: [];
        foreach ($words as $word) {
            if (isset(self::STAGES[$word])) {
                return self::STAGES[$word];
            }
        }
        return trim((string) $stage) === '' ? 9 : 8;
    }

    /**
     * Compares two groups: stage, then year (its first number), then the rest as people read it.
     *
     * @param string $a
     * @param string $b
     * @param string|null $stagea The stage of the first, if known.
     * @param string|null $stageb The stage of the second, if known.
     * @return int
     */
    public static function compare(string $a, string $b, ?string $stagea = null, ?string $stageb = null): int {
        $year = fn(string $g) => preg_match('/\d+/', $g, $m) ? (int) $m[0] : PHP_INT_MAX;
        return [self::stage($stagea ?? $a), $year($a), strnatcasecmp($a, $b)]
            <=> [self::stage($stageb ?? $b), $year($b), 0];
    }

    /**
     * Sorts a list of stage names in their place.
     *
     * @param string[] $stages
     * @return string[]
     */
    public static function stages(array $stages): array {
        usort($stages, fn($a, $b) => [self::stage($a), strnatcasecmp($a, $b)] <=> [self::stage($b), 0]);
        return array_values($stages);
    }
}
