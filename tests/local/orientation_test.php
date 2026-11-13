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
 * Tests for «La orientación con insignia»: the hidden book found in three rooms, and the badge of the site.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\orientation
 */
#[\PHPUnit\Framework\Attributes\CoversClass(orientation::class)]
final class orientation_test extends \advanced_testcase {
    /**
     * Off, nothing; on, each room counts once, two finds in one room are one; the third room gives the badge of the site
     * once, made the first time; without badges on the site, the orientation still ends, without one.
     */
    public function test_route(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $now = time();
        $find = fn(string $corner, int $when) => $DB->insert_record('local_oksigeniaayvurenda_find', ['entityid' =>
            $library->entityid, 'readerid' => $reader->id, 'recordid' => $gen->create_record(['entityid' =>
            $library->entityid, 'title' => 'Escondido ' . $corner . $when])->id, 'lanterns' => 3, 'corner' => $corner,
            'timefound' => $when]);
        $this->assertNull(orientation::step($reader, $now));
        orientation::save((int) $library->entityid, true, ['Infantil', 'Primaria']);
        $this->assertSame(['Primaria'], orientation::settings((int) $library->entityid)['stages']);
        orientation::save((int) $library->entityid, true, []);
        $find('8', $now - 300);
        $find('8', $now - 200);
        $this->assertSame(['done' => 1, 'left' => 2, 'finished' => false, 'badge' => null], orientation::step($reader, $now));
        $find('5', $now - 100);
        $this->assertSame(1, orientation::step($reader, $now)['left']);
        // Without badges on the site it ends all the same.
        $CFG->enablebadges = 0;
        $find('9', $now - 50);
        $this->assertSame(['8', '5', '9'], orientation::rooms((int) $reader->id));
        $this->assertTrue(orientation::done((int) $reader->id));
        $this->assertSame(['done' => 3, 'left' => 0, 'finished' => false, 'badge' => null], orientation::step($reader, $now));
        // With badges, the badge of the site, made once with its picture, and given once.
        $CFG->enablebadges = 1;
        $image = make_request_directory() . '/insignia.png';
        $gd = imagecreatetruecolor(64, 64);
        imagepng($gd, $image);
        $badgeid = orientation::badge((int) $library->entityid, $image);
        $this->assertSame($badgeid, orientation::badge((int) $library->entityid, $image));
        $this->assertSame(BADGE_STATUS_ACTIVE, (int) $DB->get_field('badge', 'status', ['id' => $badgeid]));
        $step = orientation::step($reader, $now);
        $this->assertSame([true, $badgeid], [$step['finished'], $step['badge']]);
        $this->assertTrue($DB->record_exists('badge_issued', ['badgeid' => $badgeid, 'userid' => $user->id]));
        $this->assertFalse(orientation::step($reader, $now)['finished']);
        $this->assertSame(1, $DB->count_records('badge_issued', ['badgeid' => $badgeid]));
        $this->assertNotSame('', orientation::says($step));
    }
}
