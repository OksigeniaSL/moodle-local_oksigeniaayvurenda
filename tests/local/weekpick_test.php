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
 * Tests for the book of the week a librarian picks (P9) and «the nose» of a review (P4).
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\weekpick
 * @covers     \local_oksigeniaayvurenda\local\reviews
 */
#[\PHPUnit\Framework\Attributes\CoversClass(weekpick::class)]
final class weekpick_test extends \advanced_testcase {
    /**
     * A pick lasts seven days with its line, a new one takes its place, it goes when taken away, and a record that
     * left the catalogue is no pick.
     */
    public function test_pick(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Elena']);
        $one = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $two = $gen->create_record(['entityid' => $library->entityid, 'title' => 'La isla del cometa']);
        $now = time();
        $this->assertNull(weekpick::of($library, $now));
        weekpick::pick($library, (int) $one->id, "  Para leer\n de un tirón.  ", (int) $user->id, $now);
        $pick = weekpick::of($library, $now + DAYSECS);
        $this->assertSame(
            [(int) $one->id, 'El faro dormido', 'Para leer de un tirón.', 'Elena'],
            [$pick['recordid'], $pick['title'], $pick['phrase'], $pick['name']]
        );
        $this->assertNull(weekpick::of($library, $now + 7 * DAYSECS));
        weekpick::pick($library, (int) $two->id, '', (int) $user->id, $now);
        $this->assertSame((int) $two->id, weekpick::of($library, $now)['recordid']);
        $DB->delete_records('local_oksigeniaayvurenda_record', ['id' => $two->id]);
        $this->assertNull(weekpick::of($library, $now));
        weekpick::pick($library, (int) $one->id, 'Otra vez.', (int) $user->id, $now);
        weekpick::clear($library);
        $this->assertNull(weekpick::of($library, $now));
    }

    /**
     * Since a review was published, the other readers who took the book; not its author, not before, not while it
     * waits.
     */
    public function test_followers(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $readers = [];
        for ($i = 0; $i < 4; $i++) {
            $readers[] = $gen->create_reader(['entityid' => $library->entityid]);
        }
        $now = time();
        $id = $DB->insert_record('local_oksigeniaayvurenda_review', ['entityid' => $library->entityid, 'recordid' => $record->id,
            'readerid' => $readers[0]->id, 'text' => 'Muy bueno.', 'status' => 'pending', 'forlittle' => 0,
            'timedecided' => null, 'timecreated' => $now - 100, 'timemodified' => $now - 100]);
        $loan = fn($reader, $when) => $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id,
            'readerid' => $reader->id, 'timeloaned' => $when]);
        $loan($readers[1], $now - 1000);
        $loan($readers[2], $now);
        $loan($readers[3], $now);
        $loan($readers[3], $now + 10);
        $loan($readers[0], $now);
        $this->assertSame(0, reviews::followers($DB->get_record('local_oksigeniaayvurenda_review', ['id' => $id])));
        $DB->update_record('local_oksigeniaayvurenda_review', ['id' => $id, 'status' => 'published', 'timedecided' => $now - 50]);
        $this->assertSame(2, reviews::followers($DB->get_record('local_oksigeniaayvurenda_review', ['id' => $id])));
    }
}
