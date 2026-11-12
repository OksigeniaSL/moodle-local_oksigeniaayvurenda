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
 * Tests for the sheet of the year: its days, a day touched and each gesture.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\yearsheet
 */
#[\PHPUnit\Framework\Attributes\CoversClass(yearsheet::class)]
final class yearsheet_test extends \advanced_testcase {
    /**
     * Sends a gesture as the page would.
     *
     * @param \stdClass $library
     * @param array $post
     * @return array
     */
    private function gesture(\stdClass $library, array $post): array {
        $_POST = $post;
        $back = yearsheet::apply($library, time());
        $_POST = [];
        return $back;
    }

    /**
     * Closing a day, several days and taking a closure away; the date to have everything back and taking it away; the
     * days closed every week and the start of the week; a term end out of order changes nothing.
     */
    public function test_gestures(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, time());
        $day = fn(int $offset) => zones::date((int) $year->timestart + $offset * DAYSECS, '%Y-%m-%d', $library);
        $this->gesture($library, ['closeday' => $day(40)]);
        $this->assertCount(1, closures::of($entityid));
        $this->gesture($library, ['closefrom' => $day(100), 'closeto' => $day(110), 'closedname' => 'Vacaciones']);
        $ranges = closures::of($entityid);
        $this->assertCount(2, $ranges);
        $this->assertContains('Vacaciones', array_column($ranges, 'label'));
        $this->gesture($library, ['removeclosed' => $day(40) . '|' . $day(40)]);
        $this->assertCount(1, closures::of($entityid));
        $this->gesture($library, ['deadline' => $day(280)]);
        $this->assertNotNull(closures::return_by($entityid));
        $this->gesture($library, ['deadline' => '']);
        $this->assertNull(closures::return_by($entityid));
        $this->gesture($library, ['weekclosed' => ['3' => '1']]);
        $this->assertContains(3, zones::rest_days($library));
        $this->gesture($library, ['weekclosed' => ['3' => '0']]);
        $this->assertNotContains(3, zones::rest_days($library));
        $this->gesture($library, ['weekstart' => '7']);
        $this->assertSame(7, zones::first_weekday($library));
        $ends = challenges::ends($year);
        // The first term ending after the second: nothing changes.
        $back = $this->gesture($library, ['termend' => '1:' . zones::date($ends[1] + 5 * DAYSECS, '%Y-%m-%d', $library)]);
        $this->assertSame(1, $back[1]['order']);
        $this->assertSame($ends, challenges::ends($year));
        $this->gesture($library, ['termend' => '1:' . zones::date($ends[0] - 5 * DAYSECS, '%Y-%m-%d', $library)]);
        $this->assertLessThan($ends[0], challenges::ends($year)[0]);
        $this->gesture($library, ['termsreset' => '1']);
        $this->assertSame($ends, challenges::ends($year));
    }

    /**
     * Twelve months from the start of the year, each day with what it is; a day touched says what it is now and what can
     * be done with it, and a closure being marked asks for its name.
     */
    public function test_sheet_and_card(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, time());
        $start = zones::date((int) $year->timestart, '%Y-%m-%d', $library);
        closures::add($entityid, $start, $start, 'Fiesta');
        $sheet = yearsheet::months($library, $year, time(), $start, '', '/local/oksigeniaayvurenda/year.php');
        $this->assertCount(12, $sheet['months']);
        $this->assertCount(7, $sheet['letters']);
        $first = array_values(array_filter($sheet['months'][0]['cells'], fn($c) => empty($c['blank'])))[0];
        $this->assertTrue($first['first']);
        $this->assertTrue($first['closed']);
        $this->assertTrue($first['touched']);
        $this->assertStringContainsString('Fiesta', $first['aria']);
        $str = skins::speaker($library);
        $card = yearsheet::card($library, $year, $start, '', $str);
        $this->assertSame('removeclosed', $card['actions'][0]['name']);
        $other = zones::date((int) $year->timestart + 30 * DAYSECS, '%Y-%m-%d', $library);
        $card = yearsheet::card($library, $year, $other, '', $str);
        $this->assertContains('closeday', array_column($card['actions'], 'name'));
        $this->assertContains('deadline', array_column($card['actions'], 'name'));
        $range = yearsheet::card($library, $year, $other, $start, $str)['range'];
        $this->assertSame($start, $range['from']);
        $this->assertSame($other, $range['to']);
        $this->assertNull(yearsheet::card($library, $year, 'nonsense', '', $str));
        $phrases = yearsheet::rules($library, $year, $str, 'ayv-cs-rest');
        $this->assertContains('ayv-cs-closures', array_column($phrases, 'id'));
        $this->assertTrue($phrases[0]['justsaved']);
    }
}
