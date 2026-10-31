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
 * Tests for copies on reserve for a course.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\reserves
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reserves::class)]
final class reserves_test extends \advanced_testcase {
    /**
     * Two of three copies go on reserve for a course, free ones first; the desk lends one for the day, says so and does
     * not renew it; once the reserve is over, the copy goes back to its rules. Taking it off, and a course gone, end it.
     */
    public function test_reserve(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $DB->insert_record('local_oksigeniaayvurenda_rule', ['libraryid' => $library->id, 'readertype' => '*', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 0, 'renewals' => 2, 'renewdays' => 7, 'timecreated' => $now, 'timemodified' => $now]);
        $one = $gen->create_item(['libraryid' => $library->id, 'status' => 'onloan']);
        $two = $gen->create_item(['libraryid' => $library->id, 'recordid' => $one->recordid]);
        $three = $gen->create_item(['libraryid' => $library->id, 'recordid' => $one->recordid]);
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame(0, reserves::put($library, (int) $one->recordid, (int) $course->id, 2, 5, $now + WEEKSECS, $now));
        $this->assertSame(2, reserves::put($library, (int) $one->recordid, (int) $course->id, 2, 0, $now + WEEKSECS, $now));
        $this->assertNotNull(reserves::active((int) $two->id, $now));
        $this->assertNotNull(reserves::active((int) $three->id, $now));
        $this->assertNull(reserves::active((int) $one->id, $now));
        $this->assertSame(2, reserves::of_library($library, $now)[$one->recordid][$course->id]['copies']);
        // At the desk: back the same day, said so, and not renewed.
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $desk = new desk($library, (int) get_admin()->id, $now);
        $desk->scan((string) $reader->cardcode);
        $lent = $desk->scan((string) $two->barcode, (int) $reader->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertStringContainsString($course->shortname, $lent['message']);
        $due = (int) $DB->get_field('local_oksigeniaayvurenda_loan', 'timedue', ['id' => $lent['loanid']]);
        $tz = \core_date::get_server_timezone_object();
        $this->assertSame(
            (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->format('Y-m-d'),
            (new \DateTimeImmutable('@' . $due))->setTimezone($tz)->format('Y-m-d')
        );
        $this->assertNull($desk->renewable((int) $lent['loanid']));
        $this->assertSame('stop', $desk->renew((int) $lent['loanid'])['kind']);
        // Once over, its own rules again.
        $later = new desk($library, (int) get_admin()->id, $now + 2 * WEEKSECS);
        $this->assertNotNull($later->renewable((int) $lent['loanid']));
        // Off reserve, and with the course gone.
        reserves::end($library, (int) $one->recordid, (int) $course->id);
        $this->assertSame([], reserves::of_library($library, $now));
        reserves::put($library, (int) $one->recordid, (int) $course->id, 1, 1, $now + WEEKSECS, $now);
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_reserve'));
    }
}
