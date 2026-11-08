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

namespace local_oksigeniaayvurenda\task;

/**
 * Tests for the night's tasks, run as the cron runs them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sync_readers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(send_notices::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(make_copies::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(forget_history::class)]
final class tasks_test extends \advanced_testcase {
    /**
     * Each scheduled task has its name and runs through a site with a library, a reader and a book out, without failing
     * (otr-codigo 7: what the classes do was tested, never what the cron runs).
     */
    public function test_each_task_runs(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $item = $gen->create_item(['libraryid' => $library->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => time() - 30 * DAYSECS, 'timedue' => time() - 10 * DAYSECS]);
        $names = [];
        foreach ([sync_readers::class, send_notices::class, make_copies::class, forget_history::class] as $class) {
            $task = new $class();
            $names[] = $task->get_name();
            ob_start();
            try {
                $task->execute();
            } finally {
                $said = ob_get_clean();
            }
            if ($class === forget_history::class) {
                $this->assertStringContainsString('no longer say who', $said);
            }
        }
        $this->assertCount(4, array_unique(array_filter($names)));
        // The copy of the night is there.
        $this->assertNotEmpty(get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniaayvurenda',
            'copies',
            (int) $library->entityid,
            'id',
            false
        ));
    }
}
