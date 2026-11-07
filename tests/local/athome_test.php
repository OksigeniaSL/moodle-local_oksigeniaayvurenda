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
 * What the reader has at home, on the library's home page and card.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\screens
 */
#[\PHPUnit\Framework\Attributes\CoversClass(screens::class)]
final class athome_test extends \advanced_testcase {
    /**
     * A reader sees each book out with the day it comes back, the late one first and marked; nothing for whoever is not a
     * reader or has nothing out.
     */
    public function test_at_home(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertNull(screens::at_home($library));
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $this->assertNull(screens::at_home($library));
        $now = time();
        foreach (['El faro dormido' => $now + 5 * DAYSECS, 'La isla' => $now - 4 * DAYSECS] as $title => $due) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id, 'timedue' => $due]);
        }
        $home = screens::at_home($library, $now);
        $this->assertCount(2, $home['books']);
        $this->assertSame('La isla', $home['books'][0]['title']);
        $this->assertTrue($home['books'][0]['late']);
        $this->assertFalse($home['books'][1]['late']);
        $this->assertNull($home['books'][1]['renew']);
    }
}
