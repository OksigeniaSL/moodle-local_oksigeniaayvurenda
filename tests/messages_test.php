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

namespace local_oksigeniaayvurenda;

/**
 * What the library says, and by what ways.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class messages_test extends \advanced_testcase {
    /**
     * What a reader waits for goes to the app too, on a new site and on one brought up to date.
     */
    public function test_the_phone_rings(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/messages.php');
        foreach (['overdue', 'holdready', 'newbooks', 'overduetutor'] as $name) {
            $this->assertArrayHasKey('airnotifier', $messageproviders[$name]['defaults'], $name);
            $key = 'message_provider_local_oksigeniaayvurenda_' . $name . '_enabled';
            $this->assertContains('airnotifier', explode(',', (string) get_config('message', $key)), $name);
        }

        // A site from before: its other choices stay as they were.
        $key = 'message_provider_local_oksigeniaayvurenda_overdue_enabled';
        set_config($key, 'email', 'message');
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/upgrade.php');
        $this->setAdminUser();
        xmldb_local_oksigeniaayvurenda_upgrade(2026110115);
        $this->assertSame('email,airnotifier', get_config('message', $key));
        $this->assertTrue($DB->record_exists('config_plugins', ['plugin' => 'message', 'name' => $key]));
    }
}
