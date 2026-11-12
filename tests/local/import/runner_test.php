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

namespace local_oksigeniaayvurenda\local\import;

/**
 * Tests for importing a reviewed file, in the page or in the background.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\import\runner
 * @covers     \local_oksigeniaayvurenda\task\run_import
 */
#[\PHPUnit\Framework\Attributes\CoversClass(runner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_oksigeniaayvurenda\task\run_import::class)]
final class runner_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The made-up MARC of another program, where an upload leaves it.
     *
     * @return array As the import page keeps it.
     */
    private function upload(): array {
        $path = make_request_directory() . '/subido.upload';
        copy(__DIR__ . '/../../fixtures/marc/programa.xml', $path);
        return ['path' => $path, 'name' => 'programa.xml', 'kind' => 'marcxml'];
    }

    /**
     * In the page: what came in, as figures.
     */
    public function test_run(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $done = runner::run($library, $this->upload(), []);
        $records = $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]);
        $this->assertGreaterThan(0, $records);
        $this->assertSame((string) $records, $done['figures'][0]['count']);
        $this->assertSame('', $done['again']);
    }

    /**
     * In the background: the file kept apart, the library marked (a mark older than six hours holds nothing); the task
     * imports as the person who started it, tells them, takes the mark away and leaves no file behind.
     */
    public function test_background(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $user = $this->getDataGenerator()->create_user();
        $upload = $this->upload();
        runner::queue($library, $upload, [], (int) $user->id);
        $this->assertFileDoesNotExist($upload['path']);
        $this->assertSame('programa.xml', runner::running($library)['name']);
        $this->assertNull(runner::running($library, time() + 7 * HOURSECS));
        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(\local_oksigeniaayvurenda\task\run_import::class);
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertStringContainsString('programa.xml', $messages[0]->subject);
        $this->assertNull(runner::running($library));
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertSame([], glob(make_upload_directory('local_oksigeniaayvurenda/imports') . '/*'));
    }

    /**
     * A file that is not there when the task comes: the message says it could not, and the library can import again.
     */
    public function test_background_fails(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $user = $this->getDataGenerator()->create_user();
        runner::queue($library, $this->upload(), [], (int) $user->id);
        foreach (glob(make_upload_directory('local_oksigeniaayvurenda/imports') . '/*') as $kept) {
            unlink($kept);
        }
        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(\local_oksigeniaayvurenda\task\run_import::class);
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString(
            get_string('importbgfailedsubject', 'local_oksigeniaayvurenda', 'programa.xml'),
            $messages[0]->subject
        );
        $this->assertNull(runner::running($library));
        $this->assertSame(0, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
    }

    /**
     * A background import alive is not taken as dead however long it lasts (it beats at each batch); one that died twice
     * on the way (the process killed, so Moodle runs it again) says so and lets the library import again.
     */
    public function test_alive_and_dead_twice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        runner::queue($library, $this->upload(), [], (int) $user->id, $now);
        $this->assertSame(1, runner::started((int) $library->id, $now + 5 * HOURSECS));
        runner::beat((int) $library->id, $now + 10 * HOURSECS);
        $this->assertNotNull(runner::running($library, $now + 11 * HOURSECS));
        $this->assertNull(runner::running($library, $now + 17 * HOURSECS));
        // Two starts already, as if it had died twice: the third one does not import.
        $this->assertSame(2, runner::started((int) $library->id));
        $sink = $this->redirectMessages();
        ob_start();
        $this->runAdhocTasks(\local_oksigeniaayvurenda\task\run_import::class);
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString(
            get_string('importbgfailedsubject', 'local_oksigeniaayvurenda', 'programa.xml'),
            $messages[0]->subject
        );
        $this->assertNull(runner::running($library));
        $this->assertSame(0, $DB->count_records(self::T . 'record', ['entityid' => $library->entityid]));
        $this->assertSame([], glob(make_upload_directory('local_oksigeniaayvurenda/imports') . '/*'));
    }

    /**
     * A file goes apart when it is big, or when the review counted many records in it, however small (a zip).
     */
    public function test_too_big(): void {
        $this->resetAfterTest();
        $upload = $this->upload();
        $this->assertFalse(runner::too_big($upload));
        $this->assertTrue(runner::too_big($upload + ['records' => runner::RECORDS + 1]));
        $this->assertFalse(runner::too_big($upload + ['records' => 30]));
        @unlink($upload['path']);
    }
}
