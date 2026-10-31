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
 * Tests for which copies are kept.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\retention
 */
#[\PHPUnit\Framework\Attributes\CoversClass(retention::class)]
final class retention_test extends \basic_testcase {
    /**
     * Seven days, the newest of four weeks and of three months, out of every day of a hundred.
     */
    public function test_keep(): void {
        $days = [];
        $day = new \DateTimeImmutable('2026-10-31', new \DateTimeZone('UTC'));
        for ($i = 0; $i < 100; $i++) {
            $days[] = $day->modify("-$i days")->format('Ymd');
        }
        $kept = retention::keep($days, 7, 4, 3);
        $this->assertSame(array_slice($days, 0, 7), array_slice($kept, 0, 7));
        // 31 Oct is a Saturday: weeks of 26 Oct, 19 Oct (newest day: the 25th), 12 Oct (the 18th), 5 Oct (the 11th).
        foreach (['20261025', '20261018', '20261011'] as $sunday) {
            $this->assertContains($sunday, $kept);
        }
        // Months: October (the 31st), September (the 30th), August (the 31st).
        $this->assertContains('20260930', $kept);
        $this->assertContains('20260831', $kept);
        $this->assertNotContains('20260731', $kept);
        // Seven days, two more weeks (the 18th and the 11th) and two more months.
        $this->assertCount(7 + 2 + 2, $kept);
        $this->assertSame([], retention::keep(['nonsense'], 7, 4, 3));
        $this->assertSame(['20261031'], retention::keep(['20261031', '20261031'], 7, 4, 3));
    }
}
