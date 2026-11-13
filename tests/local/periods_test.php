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
 * Tests for the periods of the reports: sheets touched, months and exact days.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\periods
 */
#[\PHPUnit\Framework\Attributes\CoversClass(periods::class)]
final class periods_test extends \advanced_testcase {
    /**
     * The week, the month, the three terms, the year and last year, each with its days; months from «Another» and exact
     * days; nothing chosen, the year.
     */
    public function test_periods(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $now = time();
        $all = periods::all($library, $now);
        $this->assertSame(periods::KEYS, array_column($all, 'key'));
        foreach ($all as $period) {
            $this->assertGreaterThan($period['from'], $period['to']);
            $this->assertNotSame('', $period['dates']);
        }
        $year = years::around((int) $library->entityid, $now);
        [$from, $to, $key] = periods::resolve($library, $now, '', '', '');
        $this->assertSame([(int) $year->timestart, (int) $year->timeend, 'curso'], [$from, $to, $key]);
        [$from, $to, $key] = periods::resolve($library, $now, 'semana', '', '');
        $this->assertSame('semana', $key);
        // Seven days, give or take the hour a change of clock moves.
        $this->assertEqualsWithDelta(7 * DAYSECS, $to - $from, HOURSECS);
        [$from, $to, $key] = periods::resolve($library, $now, '', '2026-10', '2026-11');
        $this->assertSame('otro', $key);
        $this->assertSame('2026-10-01', zones::date($from, '%Y-%m-%d', $library));
        $this->assertSame('2026-12-01', zones::date($to, '%Y-%m-%d', $library));
        [$from, $to] = periods::resolve($library, $now, '', '2026-10-05', '2026-10-07');
        $this->assertSame('2026-10-08', zones::date($to, '%Y-%m-%d', $library));
        $strip = periods::months($library, $now, '', fn(array $p) => json_encode($p));
        $this->assertCount(12, $strip);
        $this->assertNotSame('', $strip[0]['url']);
    }

    /**
     * A term is named by the first day the centre opens: past the days of rest of its week and past its closures.
     */
    public function test_opens(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $at = fn(string $day) => (new \DateTimeImmutable($day . ' 00:00', zones::of($library)))->getTimestamp();
        // Tuesday 22 December 2026, open.
        $this->assertSame('2026-12-22', zones::date(periods::opens($library, $at('2026-12-22')), '%Y-%m-%d', $library));
        // Saturday 26 December, the Monday after.
        $this->assertSame('2026-12-28', zones::date(periods::opens($library, $at('2026-12-26')), '%Y-%m-%d', $library));
        $this->assertTrue(closures::add((int) $library->entityid, '2026-12-23', '2027-01-07', 'Winter'));
        $this->assertSame('2027-01-08', zones::date(periods::opens($library, $at('2026-12-23')), '%Y-%m-%d', $library));
    }
}
