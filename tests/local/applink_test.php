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
 * Tests for the way into the app for a reader.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\applink
 */
#[\PHPUnit\Framework\Attributes\CoversClass(applink::class)]
final class applink_test extends \advanced_testcase {
    /**
     * Without the app's plugin there is nothing to send; with it, a reader with an account gets the link, one without
     * gets an account first from the library's e-mail (if whoever asks may make accounts), never one that another
     * account already has, and one without an e-mail is told what is missing.
     */
    public function test_state_and_account(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $withaccount = $gen->create_reader(['entityid' => $library->entityid,
            'userid' => $this->getDataGenerator()->create_user()->id]);
        $noaccount = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Nico', 'lastname' => 'Zeta',
            'email' => 'nico.zeta@example.com']);
        $noemail = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Mario']);
        $sibling = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Lucía',
            'email' => 'familia@example.com']);
        $this->getDataGenerator()->create_user(['email' => 'familia@example.com']);
        if (!applink::available()) {
            $this->assertSame('none', applink::state($withaccount));
            $this->assertSame('appnone', applink::send($withaccount));
        } else {
            $this->setAdminUser();
            $this->assertSame('send', applink::state($withaccount));
            $this->assertSame('create', applink::state($noaccount));
            $this->assertSame('noemail', applink::state($noemail));
            // Sent by e-mail; the account made on the way, for one who had none.
            $sink = $this->redirectEmails();
            $this->assertSame('appsent', applink::send($withaccount));
            $this->assertSame('appcreated', applink::send($noaccount));
            $this->assertSame('apptaken', applink::send($sibling));
            $this->assertSame(['nico.zeta@example.com'], array_slice(array_column($sink->get_messages(), 'to'), 1));
            $sink->close();
            $noaccount = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $noaccount->id]);
            $DB->set_field('local_oksigeniaayvurenda_reader', 'userid', null, ['id' => $noaccount->id]);
            delete_user(\core_user::get_user((int) $noaccount->userid));
            $noaccount->userid = null;
            $this->setUser($this->getDataGenerator()->create_user());
            $this->assertSame('none', applink::state($noaccount));
        }
        // The account, from what the library has.
        $userid = applink::account($noaccount);
        $user = \core_user::get_user($userid);
        $this->assertSame(
            ['Nico', 'Zeta', 'nico.zeta@example.com', 'manual'],
            [$user->firstname, $user->lastname, $user->email, $user->auth]
        );
        $this->assertSame($userid, (int) $DB->get_field('local_oksigeniaayvurenda_reader', 'userid', ['id' => $noaccount->id]));
        $this->assertSame(0, applink::account($noemail));
        $this->assertSame(0, applink::account($sibling));
        $this->assertNull($DB->get_field('local_oksigeniaayvurenda_reader', 'userid', ['id' => $sibling->id]));
    }
}
