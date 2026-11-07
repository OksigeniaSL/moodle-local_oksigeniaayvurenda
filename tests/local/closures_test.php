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

namespace local_oksigeniaayvurenda\local;

/**
 * The days a centre is closed.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\closures
 */
#[\PHPUnit\Framework\Attributes\CoversClass(closures::class)]
final class closures_test extends \advanced_testcase {
    /**
     * Ranges kept in order, in either order of their dates, never longer than a summer nor with days that do not exist;
     * the days from yesterday on; taken away one by one; and another centre's are its own.
     */
    public function test_ranges(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entityid = (int) $gen->create_library()->entityid;
        $other = (int) $gen->create_library()->entityid;
        $now = (new \DateTimeImmutable('2026-12-01 12:00', zones::of($entityid)))->getTimestamp();
        $this->assertTrue(closures::add($entityid, '2027-01-07', '2026-12-23', 'Navidad'));
        $this->assertTrue(closures::add($entityid, '2026-12-08', '2026-12-08', ''));
        $this->assertTrue(closures::add($entityid, '2026-11-01', '2026-11-02', 'Pasado'));
        $this->assertFalse(closures::add($entityid, '2026-02-30', '2026-03-02', 'No existe'));
        $this->assertFalse(closures::add($entityid, '2026-06-01', '2026-12-31', 'Demasiado'));
        $ranges = closures::of($entityid);
        $this->assertSame(['2026-11-01', '2026-12-08', '2026-12-23'], array_column($ranges, 'from'));
        $this->assertSame('2027-01-07', $ranges[2]['to']);
        $days = closures::days($entityid, $now);
        $this->assertCount(1 + 16, $days);
        $this->assertArrayHasKey('2026-12-31', $days);
        $this->assertArrayNotHasKey('2026-11-01', $days);
        $this->assertSame([], closures::days($other, $now));

        // A book lent on 20 December for 14 days would be due on Sunday 3 January: it is due on Friday 8.
        $lent = (new \DateTimeImmutable('2026-12-20 10:00', zones::of($entityid)))->getTimestamp();
        $due = \local_oksigeniaayvurenda\core\loan_rules::due($lent, 14, zones::of($entityid), true, $days);
        $this->assertSame('2027-01-08', zones::date($due, '%Y-%m-%d', $entityid));

        closures::remove($entityid, '2026-12-23', '2027-01-07');
        $this->assertSame(['2026-11-01', '2026-12-08'], array_column(closures::of($entityid), 'from'));
    }
}
