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
 * Tests for the reading lists of the courses.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\readings
 */
#[\PHPUnit\Framework\Attributes\CoversClass(readings::class)]
final class readings_test extends \advanced_testcase {
    /**
     * A course takes its books from the library of the centre whose category holds it; elsewhere, from the site's.
     */
    public function test_library_for(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $site = $gen->create_library();
        $other = $gen->create_library();
        $category = $this->getDataGenerator()->create_category();
        $inside = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $DB->set_field('local_oksigeniaayvurenda_entity', 'categoryid', $category->id, ['id' => $other->entityid]);
        $course = $this->getDataGenerator()->create_course(['category' => $inside->id]);
        $this->assertSame((int) $other->id, (int) readings::library_for($course)->id);
        $loose = $this->getDataGenerator()->create_course();
        $this->assertSame((int) $site->id, (int) readings::library_for($loose)->id);
    }

    /**
     * A teacher adds books of the library's centre (once each), gives them a topic, a word and whether they are
     * required, moves them and takes them off; the list keeps the order and says whether each is free.
     */
    public function test_list(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $stranger = $gen->create_record(['entityid' => $gen->create_entity()->id]);
        $one = $gen->create_item(['libraryid' => $library->id]);
        $two = $gen->create_item(['libraryid' => $library->id, 'status' => 'onloan']);
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $list = readings::start($course, $library, (int) $teacher->id);
        $this->assertSame((int) $list->id, (int) readings::start($course, $library, (int) $teacher->id)->id);
        $this->assertTrue(readings::add($list, (int) $one->recordid, '  Tema 1 ', true));
        $this->assertFalse(readings::add($list, (int) $one->recordid));
        $this->assertFalse(readings::add($list, (int) $stranger->id));
        $this->assertTrue(readings::add($list, (int) $two->recordid));
        $items = readings::items($list, $library);
        $this->assertSame([(int) $one->recordid, (int) $two->recordid], array_column($items, 'recordid'));
        $this->assertSame(['Tema 1', ''], array_column($items, 'topic'));
        $this->assertSame([true, false], array_column($items, 'required'));
        $this->assertSame(['free', 'out'], array_map(fn($i) => $i['card']['state'], $items));
        readings::change($list, $items[1]['id'], 'Tema 1', true, ' Para la semana 3 ');
        readings::move($list, $items[1]['id'], -1);
        $items = readings::items($list, $library);
        $this->assertSame([(int) $two->recordid, (int) $one->recordid], array_column($items, 'recordid'));
        $this->assertSame('Para la semana 3', $items[0]['note']);
        $this->assertFalse($items[0]['digital']);
        $DB->insert_record('local_oksigeniaayvurenda_digital', ['entityid' => $library->entityid, 'recordid' => $one->recordid,
            'kind' => 'link', 'format' => 'link', 'licence' => 'open', 'audience' => 'students', 'download' => 0,
            'status' => 'published', 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertTrue(readings::items($list, $library)[1]['digital']);
        // A book moves among those of its topic only: the third, alone in its topic, stays where it is.
        $three = $gen->create_item(['libraryid' => $library->id]);
        readings::add($list, (int) $three->recordid, 'Tema 2');
        $last = readings::items($list, $library)[2]['id'];
        readings::move($list, $last, -1);
        $this->assertSame((int) $three->recordid, readings::items($list, $library)[2]['recordid']);
        readings::remove($list, $items[0]['id']);
        readings::remove($list, $last);
        $this->assertSame([(int) $one->recordid], array_column(readings::items($list, $library), 'recordid'));
        delete_course($course, false);
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_readinglist', ['id' => $list->id]));
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_readingitem', ['listid' => $list->id]));
    }

    /**
     * The library sees each required title with its courses' students against its copies and who waits for it; the
     * most students per copy first, and what is only recommended does not count.
     */
    public function test_demand(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $plenty = $gen->create_item(['libraryid' => $library->id]);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $plenty->recordid]);
        $scarce = $gen->create_item(['libraryid' => $library->id]);
        $missing = $gen->create_record(['entityid' => $library->entityid]);
        $loose = $gen->create_item(['libraryid' => $library->id]);
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        foreach ([[$a, 3], [$b, 2]] as [$course, $students]) {
            for ($i = 0; $i < $students; $i++) {
                $this->getDataGenerator()->enrol_user($this->getDataGenerator()->create_user()->id, $course->id, 'student');
            }
        }
        $first = readings::start($a, $library, 0);
        readings::add($first, (int) $plenty->recordid, '', true);
        readings::add($first, (int) $scarce->recordid, '', true);
        readings::add($first, (int) $loose->recordid);
        $second = readings::start($b, $library, 0);
        readings::add($second, (int) $scarce->recordid, '', true);
        readings::add($second, (int) $missing->id, '', true);
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $scarce->recordid,
            'readerid' => $reader->id, 'status' => 'waiting', 'timecreated' => time(), 'timemodified' => time()]);
        $demand = readings::demand($library);
        $this->assertSame(
            [(int) $missing->id, (int) $scarce->recordid, (int) $plenty->recordid],
            array_column($demand, 'recordid')
        );
        $this->assertSame([2, 5, 3], array_column($demand, 'students'));
        $this->assertSame([0, 1, 2], array_column($demand, 'copies'));
        $this->assertSame([0, 1, 0], array_column($demand, 'waiting'));
        $this->assertCount(2, $demand[1]['courses']);
    }
}
