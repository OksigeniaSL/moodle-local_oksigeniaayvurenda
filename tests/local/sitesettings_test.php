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
 * Tests for the first minutes after installing: no «New settings» of the library, the way in from the top bar before
 * there is a library, and naming the first one on the empty page.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sitesettings
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sitesettings::class)]
final class sitesettings_test extends \advanced_testcase {
    /**
     * Every setting without a value gets its default, what someone chose stays, the roles' LDAP contexts get an empty
     * one, and then Moodle has nothing of the library left to ask on «New settings».
     */
    public function test_settle(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        unset_config('z3950bridge', 'local_oksigeniaayvurenda');
        unset_config('planlti', 'local_oksigeniaayvurenda');
        unset_config('island3d', 'local_oksigeniaayvurenda');
        set_config('sitenotice', 'Open demo', 'local_oksigeniaayvurenda');
        unset_config(roles::LIBRARIAN . 'context', 'auth_ldap');
        $this->assertNotEmpty($this->unsettled());

        $done = sitesettings::settle();
        $this->assertContains('s_local_oksigeniaayvurenda_z3950bridge', $done);
        $this->assertSame('', get_config('local_oksigeniaayvurenda', 'z3950bridge'));
        $this->assertSame('1', get_config('local_oksigeniaayvurenda', 'planlti'));
        $this->assertSame('1', get_config('local_oksigeniaayvurenda', 'island3d'));
        $this->assertSame('Open demo', get_config('local_oksigeniaayvurenda', 'sitenotice'));
        $this->assertSame('', get_config('auth_ldap', roles::LIBRARIAN . 'context'));
        $this->assertSame([], $this->unsettled());
        // Again, nothing to do.
        $this->assertSame([], sitesettings::settle());
    }

    /**
     * Before the first library, whoever may set it up finds «start here» in the top bar, and nobody else sees anything;
     * with a library, the usual «Library».
     */
    public function test_top_bar(): void {
        $this->resetAfterTest();
        $text = fn() => $this->entry();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull($text());
        $this->setAdminUser();
        $this->assertSame(get_string('navlibrarystart', 'local_oksigeniaayvurenda'), $text());
        // A practice library of the guided help is not a library of the site.
        practice::library();
        $this->assertSame(get_string('navlibrarystart', 'local_oksigeniaayvurenda'), $text());
        $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $this->assertSame(get_string('navlibrary', 'local_oksigeniaayvurenda'), $text());
    }

    /**
     * The empty page names the first library for whoever may set it up; the form goes to the page that creates it and
     * takes the visitor in; once there is a centre, it does not show any more.
     */
    public function test_first_form(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(screens::choose()['first']);
        $this->setAdminUser();
        $first = screens::choose()['first'];
        $this->assertTrue($first['none']);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/libraries.php', $first['action']);
        $this->assertNull(screens::choose()['manage']);
        $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/choose', screens::choose());
        $this->assertStringContainsString('name="first" value="1"', $html);
        $this->assertStringNotContainsString('name="yearstart"', $html);
        practice::library();
        $this->assertNotNull(screens::choose()['first']);
        libraries::create_entity(['name' => 'Riverside Public Library']);
        $choose = screens::choose();
        $this->assertNull($choose['first']);
    }

    /**
     * The settings of the library Moodle would still show on «New settings».
     *
     * @return string[]
     */
    private function unsettled(): array {
        $names = [];
        $page = admin_get_root(true, true)->locate('local_oksigeniaayvurenda');
        foreach ($page->settings as $setting) {
            if ($setting->get_setting() === null) {
                $names[] = $setting->get_full_name();
            }
        }
        foreach ([roles::LIBRARIAN, roles::MANAGER] as $shortname) {
            $ldap = \core_component::get_component_directory('auth_ldap');
            if ($ldap && get_config('auth_ldap', $shortname . 'context') === false) {
                $names[] = 'auth_ldap/' . $shortname . 'context';
            }
        }
        return $names;
    }

    /**
     * The text of the library's entry in the top bar for the current user, or null without one.
     *
     * @return string|null
     */
    private function entry(): ?string {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url(new \moodle_url('/my/'));
        $primary = new \core\navigation\views\primary($page);
        \local_oksigeniaayvurenda\hook_callbacks::primary_extend(new \core\hook\navigation\primary_extend($primary));
        $node = $primary->get('local_oksigeniaayvurenda_library');
        return $node ? (string) $node->text : null;
    }
}
