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
 * Tests for why a copy is a candidate for weeding.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\weeding_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(weeding_rules::class)]
final class weeding_rules_test extends \basic_testcase {
    /**
     * Years as catalogues write them; each reason, the strongest first; never what is lent or set aside.
     */
    public function test_year_and_why(): void {
        $this->assertSame(2005, weeding_rules::year('c2005'));
        $this->assertSame(1998, weeding_rules::year('[1998?]'));
        $this->assertSame(2005, weeding_rules::year('2005-2007'));
        $this->assertNull(weeding_rules::year('s.f.'));
        $now = make_timestamp(2026, 10, 24);
        $all = ['unused' => 5, 'before' => 2000, 'worn' => true, 'lost' => true];
        $copy = fn(array $c) => $c + ['status' => 'available', 'condition' => 'good', 'year' => 2020,
            'lastloan' => $now - 86400, 'acquired' => null];
        $this->assertNull(weeding_rules::why($copy([]), $all, $now));
        $this->assertSame('lost', weeding_rules::why($copy(['status' => 'lost', 'condition' => 'broken']), $all, $now));
        $this->assertNull(weeding_rules::why($copy(['status' => 'lost']), ['lost' => false] + $all, $now));
        $this->assertSame('worn', weeding_rules::why($copy(['condition' => 'missingpart', 'year' => 1990]), $all, $now));
        $this->assertSame('old', weeding_rules::why($copy(['year' => 1990]), $all, $now));
        $this->assertSame('unused', weeding_rules::why($copy(['lastloan' => null]), $all, $now));
        $this->assertSame('unused', weeding_rules::why($copy(['lastloan' => $now - 6 * 365 * 86400]), $all, $now));
        $this->assertNull(weeding_rules::why($copy(['lastloan' => null, 'acquired' => $now - 86400]), $all, $now));
        foreach (['onloan', 'held', 'withdrawn'] as $status) {
            $this->assertNull(weeding_rules::why($copy(['status' => $status, 'condition' => 'broken']), $all, $now));
        }
    }
}
