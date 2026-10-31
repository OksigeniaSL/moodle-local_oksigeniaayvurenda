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
 * Tests for the cells of the lists opened in a spreadsheet.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\spreadsheet
 */
#[\PHPUnit\Framework\Attributes\CoversClass(spreadsheet::class)]
final class spreadsheet_test extends \basic_testcase {
    /**
     * A formula is defused; numbers and plain words stay.
     */
    public function test_cells(): void {
        $this->assertSame("'=HYPERLINK(\"http://x\")", spreadsheet::cell('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(A1)", spreadsheet::cell('@SUM(A1)'));
        $this->assertSame("'+34 600", spreadsheet::cell('+34 600'));
        $this->assertSame('-3', spreadsheet::cell(-3));
        $this->assertSame('12', spreadsheet::cell(12));
        $this->assertSame('El faro', spreadsheet::cell('El faro'));
        $this->assertSame('', spreadsheet::cell(null));
        $this->assertSame(['a', "'=1+1"], spreadsheet::row(['x' => 'a', 'y' => '=1+1']));
    }
}
