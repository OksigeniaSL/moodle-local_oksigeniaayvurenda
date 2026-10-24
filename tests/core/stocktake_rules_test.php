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
 * Tests for what a stocktake makes of each copy.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\stocktake_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stocktake_rules::class)]
final class stocktake_rules_test extends \basic_testcase {
    /**
     * Each verdict.
     */
    public function test_verdict(): void {
        $this->assertSame('seen', stocktake_rules::verdict('available', 5, 5, false));
        $this->assertSame('seen', stocktake_rules::verdict('available', 5, null, false));
        $this->assertSame('seen', stocktake_rules::verdict('available', null, 5, false));
        $this->assertSame('misplaced', stocktake_rules::verdict('available', 5, 6, false));
        $this->assertSame('again', stocktake_rules::verdict('available', 5, 6, true));
        $this->assertSame('lent', stocktake_rules::verdict('onloan', 5, 5, false));
        $this->assertSame('found', stocktake_rules::verdict('lost', 5, 5, false));
        $this->assertSame('withdrawn', stocktake_rules::verdict('withdrawn', 5, 5, false));
    }
}
