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
 * Tests for the books classes choose as the reward for a challenge met.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\wishes
 */
#[\PHPUnit\Framework\Attributes\CoversClass(wishes::class)]
final class wishes_test extends \advanced_testcase {
    /**
     * Only a challenge met earns a choice; the library gets it or not; a new choice replaces one it could not get; and
     * the herald tells the class for a while.
     */
    public function test_reward(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = years::around($entityid, $now);
        $tutor = $this->getDataGenerator()->create_user();
        $open = $DB->insert_record('local_oksigeniaayvurenda_challenge', ['yearid' => $year->id, 'groupname' => '3.º A',
            'term' => 1, 'kind' => 'corners', 'timecreated' => $now, 'timemodified' => $now]);
        $this->assertSame(0, wishes::ask($entityid, $open, 'Los pájaros del faro', '', (int) $tutor->id, $now));
        $this->assertSame([], wishes::rewards($entityid, '3.º A', $now));

        $DB->set_field('local_oksigeniaayvurenda_challenge', 'timecompleted', $now, ['id' => $open]);
        $rewards = wishes::rewards($entityid, '3.º A', $now);
        $this->assertCount(1, $rewards);
        $this->assertNull($rewards[0]['wish']);
        $this->assertSame(0, wishes::ask($entityid, $open, '  ', '', (int) $tutor->id, $now));
        $id = wishes::ask($entityid, $open, 'Los pájaros del faro', 'Por los dibujos', (int) $tutor->id, $now);
        $this->assertSame(1, wishes::waiting($entityid));
        $this->assertSame('3.º A', wishes::asked($entityid)[0]->groupname);
        // Asked once, a second choice does not replace it while the library has it.
        $this->assertSame($id, wishes::ask($entityid, $open, 'Otro', '', (int) $tutor->id, $now));
        $this->assertSame('Los pájaros del faro', $DB->get_field('local_oksigeniaayvurenda_wish', 'title', ['id' => $id]));

        wishes::decide($id, $entityid, false, (int) get_admin()->id, $now);
        $this->assertNull(wishes::arrived($entityid, '3.º A', $now));
        $this->assertSame($id, wishes::ask($entityid, $open, 'El mar de noche', '', (int) $tutor->id, $now));
        $this->assertSame('asked', $DB->get_field('local_oksigeniaayvurenda_wish', 'status', ['id' => $id]));
        wishes::decide($id, $entityid, true, (int) get_admin()->id, $now);
        $this->assertSame(0, wishes::waiting($entityid));
        $this->assertSame('El mar de noche', wishes::arrived($entityid, '3.º A', $now + DAYSECS));
        $this->assertNull(wishes::arrived($entityid, '3.º B', $now + DAYSECS));
        $this->assertNull(wishes::arrived($entityid, '3.º A', $now + wishes::TELL + 1));
    }
}
