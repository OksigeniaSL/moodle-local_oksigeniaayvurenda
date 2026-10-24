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
 * Tests for the flower of the reader's card.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\flower
 */
#[\PHPUnit\Framework\Attributes\CoversClass(flower::class)]
final class flower_test extends \basic_testcase {
    /**
     * No path alone makes it flower: one path to the top is a sprout; it takes three to flower.
     */
    public function test_stages(): void {
        $this->assertSame(0, flower::stage(flower::levels([])));
        $this->assertSame(
            ['back' => 3, 'explore' => 0, 'tell' => 0, 'stay' => 0],
            flower::levels(['back' => 50])
        );
        $this->assertSame(1, flower::stage(flower::levels(['back' => 50])));
        $this->assertSame(2, flower::stage(flower::levels(['back' => 50, 'stay' => 50])));
        $this->assertSame(3, flower::stage(flower::levels(['back' => 10, 'explore' => 7, 'tell' => 6])));
        $this->assertSame(3, flower::stage(flower::levels(['back' => 99, 'explore' => 99, 'tell' => 99, 'stay' => 99])));
        $this->assertSame(1, flower::stage(flower::levels(['back' => 2, 'explore' => 2, 'tell' => 1])));
    }

    /**
     * The counts come from the desk: days with a loan or a return, corners, notes, books finished and read again.
     */
    public function test_counts(): void {
        $loans = [
            ['day' => '2026-10-01', 'returnday' => '2026-10-08', 'corner' => '8', 'recordid' => 1, 'note' => 'loved',
                'returned' => true],
            ['day' => '2026-10-08', 'returnday' => '2026-10-15', 'corner' => '5', 'recordid' => 2, 'note' => 'unfinished',
                'returned' => true],
            ['day' => '2026-10-15', 'returnday' => null, 'corner' => '', 'recordid' => 1, 'note' => null,
                'returned' => false],
            ['day' => '2026-10-16', 'returnday' => '2026-10-20', 'corner' => '8', 'recordid' => 1, 'note' => null,
                'returned' => true],
        ];
        $this->assertSame(['back' => 5, 'explore' => 2, 'tell' => 2, 'stay' => 3], flower::counts($loans));
    }
}
