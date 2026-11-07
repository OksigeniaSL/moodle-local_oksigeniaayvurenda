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
 * Tests for what the library shows on the personal area.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\dashboard
 */
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class dashboard_test extends \advanced_testcase {
    /**
     * Days in words: today, tomorrow, yesterday, the day of the week within six days, and with its number further away.
     */
    public function test_when(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $noon = (new \DateTimeImmutable('2026-11-04 12:00:00', zones::of($library)))->getTimestamp();
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $this->assertSame($str('whentoday'), dashboard::when($noon + HOURSECS, $library, $noon));
        $this->assertSame($str('whentomorrow'), dashboard::when($noon + DAYSECS, $library, $noon));
        $this->assertSame($str('whenyesterday'), dashboard::when($noon - DAYSECS, $library, $noon));
        $weekday = zones::date($noon + 3 * DAYSECS, '%A', $library);
        $this->assertSame($str('whenweekday', $weekday), dashboard::when($noon + 3 * DAYSECS, $library, $noon));
        $far = $noon + 20 * DAYSECS;
        $this->assertSame(
            $str('whendate', zones::date($far, '%A', $library) . ' 24'),
            dashboard::when($far, $library, $noon)
        );
    }

    /**
     * A teacher sees the readings of their course against its students, the short first; whoever lends sees what
     * waits at the desk, as counts; a book late at home says so in words.
     */
    public function test_teacher_and_desk(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $course = $this->getDataGenerator()->create_course(['shortname' => '3.º A']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        foreach ([1, 2, 3] as $i) {
            $this->getDataGenerator()->create_and_enrol($course, 'student');
        }
        $short = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $plenty = $gen->create_record(['entityid' => $library->entityid, 'title' => 'La nube']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $short->id]);
        foreach ([1, 2, 3, 4] as $i) {
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $plenty->id]);
        }
        $list = readings::start($course, $library, (int) $teacher->id);
        readings::add($list, (int) $plenty->id, '', true);
        readings::add($list, (int) $short->id, '', true);
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $teacher->id, 'readertype' => 'teacher']);

        $centre = dashboard::for_user((int) $teacher->id, $now)['centres'][0];
        $readings = array_values(array_filter(array_column($centre['sections'], 'readings')))[0];
        $this->assertSame(['El faro dormido', 'La nube'], array_column($readings['rows'], 'title'), 'the short one first');
        $this->assertSame([false, true], array_column($readings['rows'], 'ok'));
        $this->assertSame('3.º A', $readings['rows'][0]['course']);

        // A book late at the desk: the admin, who lends, counts it; the teacher, who does not, has no desk.
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $short->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timedue' => $now - 3 * DAYSECS]);
        $this->assertNull(dashboard::for_user((int) $teacher->id, $now)['desk']);
        $desk = dashboard::for_user((int) get_admin()->id, $now)['desk']['one'];
        $this->assertSame([1], array_column($desk['rows'], 'number'));
        $this->assertTrue($desk['rows'][0]['late']);
        // The teacher's own book, late, in words.
        $books = array_values(array_filter(array_column(
            dashboard::for_user((int) $teacher->id, $now)['centres'][0]['sections'],
            'books'
        )))[0];
        $this->assertSame('El faro dormido', $books['items'][0]['title']);
        $this->assertNotSame('', $books['items'][0]['late']);
        $this->assertNull($books['items'][0]['renew']);
    }
}
