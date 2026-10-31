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
 * Tests for digital items only for the students of a course.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\fragments
 */
#[\PHPUnit\Framework\Attributes\CoversClass(fragments::class)]
final class fragments_test extends \advanced_testcase {
    /**
     * A chapter given to a course: its enrolled students open it until the date, another course's do not, the library
     * always; the course's readings offer it; the record says so; and a course gone takes it back to the library.
     */
    public function test_fragment(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Historia del mar', 'isbn' => '9788400000000']);
        $now = time();
        $id = digital::add_link(
            $library,
            (int) $record->id,
            'https://example.org/capitulo-3.pdf',
            ['title' => 'Capítulo 3', 'licence' => 'rights', 'audience' => ['students'], 'stages' => [], 'download' => false],
            (int) get_admin()->id,
            $now
        );
        $mine = $this->getDataGenerator()->create_course(['fullname' => 'Historia 1.º']);
        $other = $this->getDataGenerator()->create_course(['fullname' => 'Lengua 1.º']);
        readings::add(readings::start($mine, $library, 0), (int) $record->id, '', true);
        $theirs = readings::start($other, $library, 0);
        readings::add($theirs, (int) $record->id, '', true);
        $ana = $this->getDataGenerator()->create_user();
        $leo = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($ana->id, $mine->id, 'student');
        $this->getDataGenerator()->enrol_user($leo->id, $other->id, 'student');
        $this->assertEqualsCanonicalizing(
            [(int) $mine->id, (int) $other->id],
            array_keys(fragments::courses($library, (int) $record->id))
        );
        fragments::set($id, (int) $mine->id, ' Capítulo 3 ', $now + WEEKSECS, $now);
        $item = digital::get($id, $library);
        $see = fn(int $userid) => digital::visible($item, digital::viewer((int) $library->entityid, $userid, $now));
        $this->assertTrue($see((int) $ana->id));
        $this->assertFalse($see((int) $leo->id));
        $this->assertTrue($see((int) get_admin()->id));
        // The course's readings offer it; the other course's do not.
        $this->assertTrue(readings::items(readings::of_course((int) $mine->id), $library)[0]['digital']);
        $this->assertFalse(readings::items($theirs, $library)[0]['digital']);
        // The record.
        $log = fragments::log($library);
        $this->assertSame(
            ['Historia del mar', '9788400000000', 'Capítulo 3', 'Historia 1.º', 1],
            [$log[0]['title'], $log[0]['isbn'], $log[0]['pages'], $log[0]['course'], $log[0]['students']]
        );
        // Over: only the library.
        $DB->set_field('local_oksigeniaayvurenda_fragment', 'timeend', $now - 1, ['digitalid' => $id]);
        $this->assertFalse($see((int) $ana->id));
        // Back to whoever it was for, and a course gone.
        fragments::set($id, 0, '', 0, $now);
        $this->assertNull(fragments::of_item($id));
        fragments::set($id, (int) $mine->id, '', $now + WEEKSECS, $now);
        delete_course($mine, false);
        $this->assertNull(fragments::of_item($id));
    }
}
