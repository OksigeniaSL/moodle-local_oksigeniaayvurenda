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
 * Tests for the guide of tasks («How do I…?»).
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\howto
 */
#[\PHPUnit\Framework\Attributes\CoversClass(howto::class)]
final class howto_test extends \advanced_testcase {
    /**
     * Questions in the staff's own words find their task first; nobody sees a task they cannot do.
     */
    public function test_find(): void {
        $this->resetAfterTest();
        // The test sites speak English only.
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $admin = (int) get_admin()->id;
        $first = fn(string $q, int $userid) => howto::find($q, $library, $userid)[0]['key'] ?? null;
        $this->assertSame('lend', $first('How do I lend a book?', $admin));
        $this->assertSame('weed', $first('withdraw a broken book', $admin));
        $this->assertSame('labels', $first('print stickers for the spines', $admin));
        $this->assertSame('reviews', $first('approve the reviews', $admin));
        $this->assertSame('room', $first('books read at the tables', $admin));
        $this->assertSame([], howto::find('xyzzy', $library, $admin));
        $this->assertCount(count(howto::TASKS), howto::find('', $library, $admin));
        $this->assertNotNull(howto::find('labels', $library, $admin)[0]['help']);

        // Without the site's AI, nothing changes: no answer in words, and the guide is there as text.
        $this->assertFalse(howto::ai_ready());
        $this->assertNull(howto::ask('How do I lend a book?', $library, $admin));
        $this->assertStringContainsString('## Lend a book', howto::guide($library, $admin));

        // A teacher who only sees the library: only what is theirs.
        $teacher = $this->getDataGenerator()->create_user();
        $keys = array_column(howto::find('', $library, (int) $teacher->id), 'key');
        $this->assertSame(['myclass', 'stacks'], $keys);
    }

    /**
     * Each guided help has its script and its words, step by step, in both languages; each task's help exists.
     */
    public function test_each_help_is_whole(): void {
        global $CFG;
        $manager = get_string_manager();
        $script = file_get_contents($CFG->dirroot . '/local/oksigeniaayvurenda/app/help-pieces.js');
        foreach (howto::PIECES as $piece => $help) {
            $this->assertStringContainsString("\n    {$piece}: function(h)", $script, $piece);
            $this->assertFileExists($CFG->dirroot . '/local/oksigeniaayvurenda/' . $help['page']);
            $keys = ['helptitle' . $piece, 'helpintro' . $piece, 'helpdone' . $piece, 'helpback' . $piece];
            for ($step = 1; $step <= $help['steps']; $step++) {
                $keys[] = sprintf('help%s%02d', $piece, $step);
            }
            foreach ($keys as $key) {
                foreach (['en', 'es'] as $lang) {
                    $this->assertNotSame('', trim($manager->get_string($key, 'local_oksigeniaayvurenda', null, $lang)), $key);
                }
            }
            $next = sprintf('help%s%02d', $piece, $help['steps'] + 1);
            $this->assertFalse($manager->string_exists($next, 'local_oksigeniaayvurenda'), $next);
        }
        foreach (howto::TASKS as [, , $piece]) {
            if ($piece !== '') {
                $this->assertArrayHasKey($piece, howto::PIECES);
            }
        }
    }
}
