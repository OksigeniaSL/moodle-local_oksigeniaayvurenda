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

namespace local_oksigeniaayvurenda\core;

/**
 * Tests for a box laid on a painted surface in perspective.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\homography
 */
#[\PHPUnit\Framework\Attributes\CoversClass(homography::class)]
final class homography_test extends \basic_testcase {
    /**
     * Each corner of the box lands on its painted corner, in perspective and without it; the middle of the box lands
     * where the diagonals of the painted surface cross.
     */
    public function test_corners(): void {
        foreach (
            [
                [[100, 200], [500, 260], [480, 700], [90, 640]],
                [[0, 0], [400, 0], [400, 300], [0, 300]],
                [[10, 10], [310, 60], [310, 360], [10, 310]],
            ] as $quad
        ) {
            $m = homography::solve(400, 300, $quad);
            foreach ([[0, 0], [400, 0], [400, 300], [0, 300]] as $i => [$x, $y]) {
                [$px, $py] = homography::apply($m, $x, $y);
                $this->assertEqualsWithDelta($quad[$i][0], $px, 1e-6);
                $this->assertEqualsWithDelta($quad[$i][1], $py, 1e-6);
            }
        }
        // The middle of the box, where the diagonals of a trapezium cross (not halfway down: it is further away).
        $m = homography::solve(200, 200, [[40, 0], [160, 0], [200, 100], [0, 100]]);
        [$x, $y] = homography::apply($m, 100, 100);
        $this->assertEqualsWithDelta(100.0, $x, 1e-6);
        $this->assertEqualsWithDelta(37.5, $y, 1e-6);
    }

    /**
     * The CSS has sixteen numbers in the order of matrix3d, and none written in a way CSS does not read.
     */
    public function test_css(): void {
        $this->assertSame(
            'matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1)',
            homography::css(10, 10, [[0, 0], [10, 0], [10, 10], [0, 10]])
        );
        $this->assertSame(
            'matrix3d(2, 0, 0, 0, 0, 3, 0, 0, 0, 0, 1, 0, 5, 7, 0, 1)',
            homography::css(10, 10, [[5, 7], [25, 7], [25, 37], [5, 37]])
        );
        $css = homography::css(400, 300, [[100, 200], [500, 260], [480, 700], [90, 640]]);
        $this->assertMatchesRegularExpression('/^matrix3d\((-?\d+(\.\d+)?, ){15}-?\d+(\.\d+)?\)$/', $css);
    }

    /**
     * Three corners in a line are no surface.
     */
    public function test_flat(): void {
        $this->expectException(\InvalidArgumentException::class);
        homography::solve(10, 10, [[0, 0], [10, 0], [20, 0], [5, 0]]);
    }
}
