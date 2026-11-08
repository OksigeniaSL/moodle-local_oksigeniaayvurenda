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

use local_oksigeniaayvurenda\core\loan_rules;

/**
 * Tests for a centre's week: the day it starts and the days its libraries close every week.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\zones
 */
#[\PHPUnit\Framework\Attributes\CoversClass(zones::class)]
final class week_test extends \advanced_testcase {
    /**
     * Saturday and Sunday off unless the centre says; a week that starts on Monday or on Sunday; a loan that would fall
     * due on a Friday where Friday and Saturday are off, on Sunday instead.
     */
    public function test_week(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        libraries::update_entity($entityid, ['timezone' => 'Asia/Jerusalem']);
        $this->assertSame([6, 7], zones::rest_days($entityid));
        $tz = zones::of($entityid);
        // Wednesday 11 November 2026, at noon.
        $now = (new \DateTimeImmutable('2026-11-11 12:00:00', $tz))->getTimestamp();

        libraries::update_entity($entityid, ['weekstart' => 1]);
        $this->assertSame(1, zones::first_weekday($entityid));
        $this->assertSame((new \DateTimeImmutable('2026-11-09 00:00:00', $tz))->getTimestamp(), zones::week_start($entityid, $now));
        libraries::update_entity($entityid, ['weekstart' => 7]);
        $this->assertSame((new \DateTimeImmutable('2026-11-08 00:00:00', $tz))->getTimestamp(), zones::week_start($entityid, $now));
        libraries::update_entity($entityid, ['weekstart' => 0]);
        $this->assertContains(zones::first_weekday($entityid), [1, 7], 'as Moodle says for the site or its language');

        // Friday and Saturday off.
        libraries::update_entity($entityid, ['restdays' => [5, 6]]);
        $this->assertSame([5, 6], zones::rest_days($entityid));
        $due = loan_rules::due($now, 2, $tz, true, [], zones::rest_days($entityid));
        $this->assertSame((new \DateTimeImmutable('2026-11-15 23:59:59', $tz))->getTimestamp(), $due, 'Friday 13 is off: Sunday');
        $this->assertSame(
            (new \DateTimeImmutable('2026-11-13 23:59:59', $tz))->getTimestamp(),
            loan_rules::due($now, 2, $tz),
            'with Saturday and Sunday off, Friday'
        );
        // Back to Saturday and Sunday, kept as nothing.
        libraries::update_entity($entityid, ['restdays' => [7, 6]]);
        $this->assertSame([6, 7], zones::rest_days($entityid));
        // Open every day.
        libraries::update_entity($entityid, ['restdays' => []]);
        $this->assertSame([], zones::rest_days($entityid));
    }
}
