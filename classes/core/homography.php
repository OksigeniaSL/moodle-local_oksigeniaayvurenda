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
 * A flat box laid on a painted surface in perspective: a table, a board, a stand.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The projective transform that takes a box of w × h onto four painted corners, as CSS matrix3d, so real covers stand
 * on a painted stand and real names are written on a painted board, in its perspective. Plain PHP, without Moodle.
 */
final class homography {
    /**
     * The transform from the box (0, 0)–(w, h) to a quadrilateral.
     *
     * @param float $w
     * @param float $h
     * @param array $quad Four [x, y]: top left, top right, bottom right, bottom left.
     * @return float[] a, b, c, d, e, f, g, h: x' = (a x + b y + c) / (g x + h y + 1), y' = (d x + e y + f) / (same).
     */
    public static function solve(float $w, float $h, array $quad): array {
        [[$x0, $y0], [$x1, $y1], [$x2, $y2], [$x3, $y3]] = array_map(fn($p) => [(float) $p[0], (float) $p[1]], $quad);
        // From the unit square (Heckbert): (0, 0) → p0, (1, 0) → p1, (1, 1) → p2, (0, 1) → p3.
        $sx = $x0 - $x1 + $x2 - $x3;
        $sy = $y0 - $y1 + $y2 - $y3;
        $g = $k = 0.0;
        if (abs($sx) > 1e-9 || abs($sy) > 1e-9) {
            $dx1 = $x1 - $x2;
            $dx2 = $x3 - $x2;
            $dy1 = $y1 - $y2;
            $dy2 = $y3 - $y2;
            $det = $dx1 * $dy2 - $dx2 * $dy1;
            if (abs($det) < 1e-9) {
                throw new \InvalidArgumentException('Three corners in a line');
            }
            $g = ($sx * $dy2 - $dx2 * $sy) / $det;
            $k = ($dx1 * $sy - $sx * $dy1) / $det;
        }
        $a = $x1 - $x0 + $g * $x1;
        $b = $x3 - $x0 + $k * $x3;
        $d = $y1 - $y0 + $g * $y1;
        $e = $y3 - $y0 + $k * $y3;
        // From the box to the unit square: u = x / w, v = y / h.
        return [$a / $w, $b / $h, $x0, $d / $w, $e / $h, $y0, $g / $w, $k / $h];
    }

    /**
     * Where a point of the box lands.
     *
     * @param array $m From solve().
     * @param float $x
     * @param float $y
     * @return float[] [x, y]
     */
    public static function apply(array $m, float $x, float $y): array {
        $w = $m[6] * $x + $m[7] * $y + 1;
        return [($m[0] * $x + $m[1] * $y + $m[2]) / $w, ($m[3] * $x + $m[4] * $y + $m[5]) / $w];
    }

    /**
     * The transform as CSS, for an element of w × h with transform-origin 0 0.
     *
     * @param float $w
     * @param float $h
     * @param array $quad Four [x, y]: top left, top right, bottom right, bottom left.
     * @return string matrix3d(…)
     */
    public static function css(float $w, float $h, array $quad): string {
        [$a, $b, $c, $d, $e, $f, $g, $k] = self::solve($w, $h, $quad);
        // Column by column: x' = a x + b y + c, y' = d x + e y + f, w' = g x + k y + 1.
        $cells = [$a, $d, 0, $g, $b, $e, 0, $k, 0, 0, 1, 0, $c, $f, 0, 1];
        return 'matrix3d(' . implode(', ', array_map(fn($n) => self::number((float) $n), $cells)) . ')';
    }

    /**
     * A number short enough for CSS and exact enough for a perspective.
     *
     * @param float $n
     * @return string
     */
    private static function number(float $n): string {
        $text = rtrim(rtrim(sprintf('%.9F', $n), '0'), '.');
        return $text === '-0' || $text === '' ? '0' : $text;
    }
}
