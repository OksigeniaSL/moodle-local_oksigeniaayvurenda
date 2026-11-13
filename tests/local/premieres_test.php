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
 * Tests for «Premieres»: the first loan of a new copy.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\premieres
 */
#[\PHPUnit\Framework\Attributes\CoversClass(premieres::class)]
final class premieres_test extends \advanced_testcase {
    /**
     * A new copy's first loan premieres it, not the second; an old copy, or one without the day it came in, never; the
     * album knows who premiered what this year.
     */
    public function test_premiered(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $year = years::around((int) $library->entityid, time());
        $now = time();
        $first = $gen->create_reader(['entityid' => $library->entityid]);
        $second = $gen->create_reader(['entityid' => $library->entityid]);
        $new = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Recién llegado']);
        $old = $gen->create_record(['entityid' => $library->entityid, 'title' => 'De siempre']);
        $bare = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Sin fecha']);
        $newitem = $gen->create_item(['libraryid' => $library->id, 'recordid' => $new->id, 'timeacquired' => $now - 10 * DAYSECS]);
        $olditem = $gen->create_item(['libraryid' => $library->id, 'recordid' => $old->id, 'timeacquired' => $now - 400 * DAYSECS]);
        $bareitem = $gen->create_item(['libraryid' => $library->id, 'recordid' => $bare->id, 'timeacquired' => null]);
        $loan = fn($reader, $item, $when) => $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id,
            'readerid' => $reader->id, 'yearid' => $year->id, 'timeloaned' => $when]);
        $loan($first, $newitem, $now - 60);
        $this->assertTrue(premieres::premiered((int) $newitem->id, $now - 10 * DAYSECS, $now));
        $loan($second, $newitem, $now);
        $this->assertFalse(premieres::premiered((int) $newitem->id, $now - 10 * DAYSECS, $now));
        $loan($first, $olditem, $now);
        $this->assertFalse(premieres::premiered((int) $olditem->id, $now - 400 * DAYSECS, $now));
        $loan($first, $bareitem, $now);
        $this->assertFalse(premieres::premiered((int) $bareitem->id, null, $now));
        $this->assertSame([(int) $new->id], premieres::of((int) $first->id, (int) $year->id));
        $this->assertSame([], premieres::of((int) $second->id, (int) $year->id));
    }
}
