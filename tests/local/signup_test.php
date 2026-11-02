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
 * A centre signed up for the hosted service, once and again.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(signup::class)]
final class signup_test extends \advanced_testcase {
    /**
     * The first time makes everything and mails the password; the second makes nothing, changes the plan; the manager
     * may manage that centre and no other.
     */
    public function test_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $data = ['name' => 'Biblioteca Municipal de Riosalto', 'library' => 'Sala central', 'plan' => 'small',
            'email' => 'Ana.Lopez@Example.org', 'firstname' => 'Ana', 'lastname' => 'López', 'lang' => 'es',
            'accepted' => '2026-11-03'];
        $this->assertSame(['centre' => 0, 'account' => 0], signup::look($data));
        $sink = $this->redirectEmails();
        $first = signup::centre($data);
        $this->assertSame(['centre' => true, 'account' => true, 'role' => true], $first['created']);
        $this->assertTrue($first['mailed']);
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame('ana.lopez@example.org', $sink->get_messages()[0]->to);
        $sink->clear();
        $user = $DB->get_record('user', ['id' => $first['userid']]);
        $this->assertSame('ana.lopez@example.org', $user->username);
        $this->assertSame('1', get_user_preferences('auth_forcepasswordchange', null, $user));
        $this->assertSame(
            'Sala central',
            $DB->get_field('local_oksigeniaayvurenda_library', 'name', ['slug' => $first['library']])
        );
        $this->assertSame('small', plans::of($first['entity'])['preset'] ?? $this->preset($first['entity']));
        $this->assertSame(strtotime('2026-11-03'), signup::terms($first['entity'])['time']);
        $this->assertSame('https://ayvurenda.com/privacidad/', signup::terms($first['entity'])['privacy']);

        $other = signup::centre(['name' => 'Fundación Monte Claro', 'plan' => 'free', 'email' => 'otra@example.org']);
        $sink->clear();
        $this->assertTrue(access::can('local/oksigeniaayvurenda:manage', $first['entity'], $first['userid']));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:manage', $other['entity'], $first['userid']));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:plans', $first['entity'], $first['userid']));

        $again = signup::centre(['plan' => 'medium', 'accepted' => '2027-01-01'] + $data);
        $this->assertSame(['centre' => false, 'account' => false, 'role' => false], $again['created']);
        $this->assertFalse($again['mailed']);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame($first['entity'], $again['entity']);
        $this->assertSame($first['userid'], $again['userid']);
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_entity', ['name' => $data['name']]));
        $this->assertSame(strtotime('2026-11-03'), signup::terms($first['entity'])['time'], 'the first acceptance counts');
        $this->assertSame(25000, plans::of($first['entity'])['copies']);
        $this->assertSame(['centre' => $first['entity'], 'account' => $first['userid']], signup::look($data));
    }

    /**
     * What it refuses: no plan, a bad address, a site not shared by centres, an unknown role.
     */
    public function test_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = ['name' => 'Biblioteca Zeta', 'plan' => 'small', 'email' => 'zeta@example.org'];
        foreach ([['entitycategories' => 0], ['plan' => 'gold'], ['email' => 'no'], ['role' => 'nobodyrole']] as $wrong) {
            set_config('entitycategories', $wrong['entitycategories'] ?? 1, 'local_oksigeniaayvurenda');
            try {
                signup::centre(array_diff_key($wrong, ['entitycategories' => 0]) + $data);
                $this->fail('Accepted: ' . json_encode($wrong));
            } catch (\invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->debuginfo);
            }
        }
    }

    /**
     * The preset a plan matches.
     *
     * @param int $entityid
     * @return string|null
     */
    private function preset(int $entityid): ?string {
        $plan = plans::of($entityid);
        foreach (array_keys(plans::PRESETS) as $key) {
            if (array_intersect_key($plan, plans::PRESETS[$key]) == plans::PRESETS[$key]) {
                return $key;
            }
        }
        return null;
    }
}
