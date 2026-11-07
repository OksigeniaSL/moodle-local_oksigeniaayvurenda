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
 * Readers given their way in on the web.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\entry
 */
#[\PHPUnit\Framework\Attributes\CoversClass(entry::class)]
final class entry_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * With an e-mail, the account and a message with the first password; without one, the password to print, an address
     * Moodle never writes to, and a name Moodle does not ask to fill in; the first password is changed the first time.
     * A reader with an account, or one whose e-mail another account has, gets nothing new.
     */
    public function test_give(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $withmail = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Eva', 'lastname' => 'Ríos',
            'email' => 'eva.rios@example.org', 'cardcode' => '10001']);
        $without = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Leo', 'lastname' => '',
            'email' => '', 'cardcode' => '10002']);
        $sink = $this->redirectEmails();
        $given = entry::give($withmail);
        $this->assertSame('mailed', $given['state']);
        $this->assertSame('', $given['password']);
        $this->assertCount(1, $sink->get_messages());
        $userid = (int) $DB->get_field(self::T . 'reader', 'userid', ['id' => $withmail->id]);
        $this->assertNotEmpty($userid);
        $this->assertSame('1', get_user_preferences('auth_forcepasswordchange', null, $userid));
        $this->assertStringContainsString('Eva', $sink->get_messages()[0]->body);

        $sink->clear();
        $given = entry::give($without);
        $this->assertSame('sheet', $given['state']);
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+-\d{4}$/', $given['password']);
        $this->assertCount(0, $sink->get_messages());
        $user = \core_user::get_user((int) $DB->get_field(self::T . 'reader', 'userid', ['id' => $without->id]));
        $this->assertStringEndsWith('@' . applink::NOMAIL, $user->email);
        $this->assertSame('10002', $user->lastname);
        $this->assertFalse(user_not_fully_set_up($user, false));
        $this->assertTrue(validate_internal_user_password($user, $given['password']));
        $this->assertSame('none', entry::give($DB->get_record(self::T . 'reader', ['id' => $without->id]))['state']);

        $twin = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Eva', 'lastname' => 'Ríos',
            'email' => 'eva.rios@example.org', 'cardcode' => '10003']);
        $this->assertSame('taken', entry::give($twin)['state']);
        $sink->close();
    }

    /**
     * A whole class: the readers of the group this year without an account, and nobody else; the staff of another
     * centre's desk, on a shared site, may not.
     */
    public function test_group(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, time());
        $ids = [];
        foreach (['20001', '20002', '20003'] as $card) {
            $reader = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Ana', 'lastname' => 'Prado ' . $card,
                'email' => '', 'cardcode' => $card]);
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '2º A']);
            $ids[] = (int) $reader->id;
        }
        $DB->set_field(self::T . 'reader', 'userid', $this->getDataGenerator()->create_user()->id, ['id' => $ids[2]]);
        $this->assertCount(2, entry::waiting($library, '2º A', time()));
        $this->assertSame([], entry::waiting($library, '', time()));
        $done = entry::give_group($library, '2º A', time());
        $this->assertCount(2, $done['sheets']);
        $this->assertSame([], entry::waiting($library, '2º A', time()));

        $this->setUser($this->getDataGenerator()->create_user());
        $other = $gen->create_reader(['entityid' => $entityid, 'email' => '', 'cardcode' => '20004']);
        $this->assertFalse(entry::may($other));
    }
}
