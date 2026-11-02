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
 * A site that is only a library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\onlylibrary
 */
#[\PHPUnit\Framework\Attributes\CoversClass(onlylibrary::class)]
final class onlylibrary_test extends \advanced_testcase {
    /**
     * Off, nothing changes; on, the platform's pages lead to the library, except for whoever runs the site.
     */
    public function test_where_the_pages_lead(): void {
        $this->resetAfterTest();
        $this->assertNull(onlylibrary::instead_of('site-index'));
        $this->assertNull(onlylibrary::way_out());

        set_config('libraryonly', 1, 'local_oksigeniaayvurenda');
        $this->assertStringEndsWith('/entrar.php', onlylibrary::instead_of('site-index')->get_path());
        $this->assertNull(onlylibrary::instead_of('user-profile'));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertStringEndsWith('/oksigeniaayvurenda/index.php', onlylibrary::instead_of('my-index')->get_path());
        $this->assertStringEndsWith('/logout.php', (new \moodle_url(onlylibrary::way_out()['url']))->get_path());

        $this->setAdminUser();
        $this->assertNull(onlylibrary::instead_of('my-index'));
        $this->assertNull(onlylibrary::way_out());
    }

    /**
     * The entrance names the app only once the site's setup link is Oksigenia Aty's in Google Play.
     */
    public function test_the_app_on_the_entrance(): void {
        $this->resetAfterTest();
        $play = 'https://play.google.com/store/apps/details?id=com.oksigenia.aty';
        foreach (['', 'https://download.moodle.org/mobile', 'https://example.org/?id=com.oksigenia.aty', $play . 'x'] as $other) {
            set_config('setuplink', $other, 'tool_mobile');
            $this->assertSame('', onlylibrary::app_link(), $other);
        }
        set_config('setuplink', $play, 'tool_mobile');
        $this->assertSame($play, onlylibrary::app_link());
    }

    /**
     * A library is named at the entry only if its window is open, and listed only if it also asks to be found.
     */
    public function test_what_the_entry_says(): void {
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertNull(onlylibrary::heading_for($library->slug, ''));
        $this->assertSame([], onlylibrary::open_libraries());

        window::save($library, ['open' => 1, 'text' => '', 'voices' => 0, 'index' => 0, 'catalogue' => 0]);
        $library = catalogue::library($library->slug);
        $this->assertSame((int) $library->id, (int) onlylibrary::heading_for($library->slug, '')->id);
        $this->assertSame(
            (int) $library->id,
            (int) onlylibrary::heading_for('', 'https://x.invalid/a.php?id=1&library=' . $library->slug)->id
        );
        $this->assertNull(onlylibrary::heading_for('nobody-here', ''));
        $this->assertNull(onlylibrary::heading_for(practice::library()->slug, ''));
        $this->assertSame([], onlylibrary::open_libraries());

        window::save($library, ['open' => 1, 'text' => '', 'voices' => 0, 'index' => 1, 'catalogue' => 0]);
        $this->assertCount(1, onlylibrary::open_libraries());
    }
}
