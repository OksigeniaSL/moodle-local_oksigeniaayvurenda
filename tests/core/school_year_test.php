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
 * Tests for the core class school_year.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\school_year
 */
#[\PHPUnit\Framework\Attributes\CoversClass(school_year::class)]
final class school_year_test extends \basic_testcase {
    /**
     * A year that starts in September spans two calendar years.
     */
    public function test_north(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        $year = school_year::around((new \DateTimeImmutable('2026-10-23 10:00', $tz))->getTimestamp(), '09-01', $tz);
        $this->assertSame('2026-2027', $year['name']);
        $this->assertSame((new \DateTimeImmutable('2026-09-01 00:00', $tz))->getTimestamp(), $year['timestart']);
        $this->assertSame((new \DateTimeImmutable('2027-09-01 00:00', $tz))->getTimestamp(), $year['timeend']);
        $march = school_year::around((new \DateTimeImmutable('2027-03-01 10:00', $tz))->getTimestamp(), '09-01', $tz);
        $this->assertSame('2026-2027', $march['name']);
        $eve = school_year::around((new \DateTimeImmutable('2026-08-31 23:59', $tz))->getTimestamp(), '09-01', $tz);
        $this->assertSame('2025-2026', $eve['name']);
    }

    /**
     * A year that starts in February is named after its calendar year.
     */
    public function test_south(): void {
        $tz = new \DateTimeZone('America/Asuncion');
        $january = school_year::around((new \DateTimeImmutable('2026-01-10 10:00', $tz))->getTimestamp(), '02-15', $tz);
        $this->assertSame('2025', $january['name']);
        $march = school_year::around((new \DateTimeImmutable('2026-03-01 10:00', $tz))->getTimestamp(), '02-15', $tz);
        $this->assertSame('2026', $march['name']);
    }

    /**
     * The start has to be a real MM-DD.
     */
    public function test_bad_start(): void {
        $this->expectException(\InvalidArgumentException::class);
        school_year::around(time(), '13-01', new \DateTimeZone('UTC'));
    }
}
