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
 * Tests for the days of the libraries.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\libdays
 */
#[\PHPUnit\Framework\Attributes\CoversClass(libdays::class)]
final class libdays_test extends \advanced_testcase {
    /**
     * World Read Aloud Day is the first Wednesday of February; its week is told by every library, and not by one that
     * leaves it out.
     */
    public function test_read_aloud(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $zone = zones::of($entityid);
        $rule = libdays::DAYS['readaloud'];
        $this->assertSame('2026-02-04', libdays::date($rule, 2026, $zone)->format('Y-m-d'));
        $this->assertSame('2027-02-03', libdays::date($rule, 2027, $zone)->format('Y-m-d'));
        $at = fn(string $day) => (new \DateTimeImmutable($day . ' 12:00', $zone))->getTimestamp();
        $this->assertSame([], libdays::this_week($entityid, $at('2027-01-27')));
        $this->assertSame('readaloud', libdays::this_week($entityid, $at('2027-02-01'))[0]['key']);
        $this->assertSame('readaloud', libdays::this_week($entityid, $at('2027-02-07'))[0]['key']);
        $this->assertSame([], libdays::this_week($entityid, $at('2027-02-08')));

        $kinds = fn(string $day, bool $staff) => array_column(moments::of($library, $at($day), $staff), 'key', 'kind');
        $this->assertSame('momentdayreadaloud', $kinds('2027-02-01', false)['day']);
        $this->assertSame('momentdayreadaloudtoday', $kinds('2027-02-03', true)['day']);
        $this->assertSame('momentdayreadaloudpast', $kinds('2027-02-05', false)['day']);
        $moment = moments::of($library, $at('2027-02-01'), false)[0];
        $this->assertStringContainsString('ayvurande.php', $moment['url']);
        $this->assertStringContainsString('3', $moment['text']);

        $off = json_encode(['daysoff' => ['readaloud']]);
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', $off, ['id' => $entityid]);
        \cache::make('local_oksigeniaayvurenda', 'moments')->purge();
        $this->assertSame([], libdays::this_week($entityid, $at('2027-02-03')));
        $this->assertArrayNotHasKey('day', $kinds('2027-02-03', true));
    }
}
