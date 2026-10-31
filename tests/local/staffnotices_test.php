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
 * Tests for the notice that tells the staff what waits for them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\staffnotices
 */
#[\PHPUnit\Framework\Attributes\CoversClass(staffnotices::class)]
final class staffnotices_test extends \advanced_testcase {
    /**
     * The staff of a centre hear of a review waiting, once; again only when something new arrives.
     */
    public function test_once_and_when_new(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $librarian = $this->getDataGenerator()->create_user();
        $roleid = create_role('Biblioteca de prueba', 'ayvtestdesk', '');
        assign_capability('local/oksigeniaayvurenda:desk', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $librarian->id, \context_system::instance()->id);
        $generator->create_reader(['entityid' => $entityid, 'userid' => $librarian->id, 'readertype' => 'staff']);
        $item = $generator->create_item(['libraryid' => $library->id]);
        $reader = $generator->create_reader(['entityid' => $entityid]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        $now = time();
        $sink = $this->redirectMessages();

        $this->assertSame(0, staffnotices::run($now)['sent']);
        reviews::write($entityid, (int) $item->recordid, (int) $reader->id, 'Me gustó.', $now - 60);
        $this->assertSame(1, staffnotices::run($now)['sent']);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($librarian->id, $messages[0]->useridto);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/desk.php?library=', $messages[0]->contexturl);
        $this->assertStringContainsString(get_string('stafftodoreviewsone', 'local_oksigeniaayvurenda'), $messages[0]->subject);
        $this->assertSame(0, staffnotices::run($now + HOURSECS)['sent']);

        $other = $generator->create_reader(['entityid' => $entityid]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $other->id]);
        reviews::write($entityid, (int) $item->recordid, (int) $other->id, 'A mí también.', $now + DAYSECS);
        $this->assertSame(1, staffnotices::run($now + DAYSECS + 60)['sent']);
    }
}
