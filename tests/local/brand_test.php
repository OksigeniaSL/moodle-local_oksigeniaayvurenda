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
 * Tests for the look of a reader's library, for the app.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\brand
 */
#[\PHPUnit\Framework\Attributes\CoversClass(brand::class)]
final class brand_test extends \advanced_testcase {
    /**
     * A reader gets the look of their centre's main library (the one marked as the main seat); whoever is not a reader,
     * or reads at a centre open only to its staff, gets none.
     */
    public function test_brand(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $classroom = libraries::create_library($entityid, ['name' => 'Aula de 3.º', 'kind' => 'classroom']);
        libraries::save_look((int) $classroom->id, ['colour' => 1, 'motto' => 'Aquí se lee', 'featured' => true]);
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(local_oksigeniaayvurenda_oksigeniaaty_user_brand((int) $user->id));
        $gen->create_reader(['entityid' => $entityid, 'userid' => $user->id]);
        $brand = local_oksigeniaayvurenda_oksigeniaaty_user_brand((int) $user->id);
        $this->assertSame(['Aula de 3.º', '#e2654c', 'Aquí se lee'], [$brand['library'], $brand['colour'], $brand['tagline']]);
        $this->assertStringContainsString('index.php?library=', $brand['url']);
        access::set_closed($entityid, true);
        $this->assertNull(brand::of_user((int) $user->id));
    }
}
