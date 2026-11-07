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
 * What waits for the staff, as a number in the menu.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\menu
 */
#[\PHPUnit\Framework\Attributes\CoversClass(menu::class)]
final class menu_waiting_test extends \advanced_testcase {
    /**
     * A review waiting shows on its link and on «More»; nothing waits, no number; a practice library never has one.
     */
    public function test_waiting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $admin = (int) get_admin()->id;
        $this->assertSame([], menu::waiting($library, $admin));
        $item = $gen->create_item(['libraryid' => $library->id]);
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        reviews::write((int) $library->entityid, (int) $item->recordid, (int) $reader->id, 'Me gustó.');
        $this->assertSame(['reviews.php' => 1], menu::waiting($library, $admin));
        $more = array_values(array_filter(menu::doors($library, $admin), fn($door) => $door['key'] === 'more'))[0];
        $this->assertSame(1, $more['count']);
        $links = array_merge(...array_column($more['groups'], 'links'));
        $reviews = array_values(array_filter($links, fn($link) => str_contains($link['url'], 'reviews.php')))[0];
        $this->assertSame(1, $reviews['count']);

        $practice = practice::library();
        $pitem = $gen->create_item(['libraryid' => $practice->id]);
        $preader = $gen->create_reader(['entityid' => $practice->entityid]);
        $gen->create_loan(['libraryid' => $practice->id, 'itemid' => $pitem->id, 'readerid' => $preader->id]);
        reviews::write((int) $practice->entityid, (int) $pitem->recordid, (int) $preader->id, 'De práctica.');
        $this->assertSame([], menu::waiting($practice, $admin));
    }
}
