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
 * Tests for «To open your library»: five steps from the centre's own data.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\opening
 */
#[\PHPUnit\Framework\Attributes\CoversClass(opening::class)]
final class opening_test extends \advanced_testcase {
    /**
     * A centre made from the screen starts closed and shows its list to its staff, never to a reader; each step is done
     * by the data, the rules and the team also by saying so; opening waits for books and readers; once open, no list.
     */
    public function test_steps(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        // Who did each step comes from Moodle's log.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->setAdminUser();
        $admin = (int) get_admin()->id;
        $library = libraries::create_entity(['name' => 'Riverside Library', 'closed' => true]);
        $entityid = (int) $library->entityid;
        $this->assertTrue(access::closed($entityid));
        $this->assertTrue(opening::shows($library, $admin));
        $reader = $this->getDataGenerator()->create_user();
        $this->assertFalse(opening::shows($library, (int) $reader->id));

        $done = fn() => array_keys(array_filter(opening::steps($library, $admin), fn($step) => $step['done']));
        $this->assertSame([], $done());
        $steps = opening::steps($library, $admin);
        $this->assertTrue($steps['open']['blocked']);
        $context = opening::context($library, $admin, 'ayv-open', true, false);
        $this->assertSame('0 de 5', str_replace(' of ', ' de ', $context['count']));
        $this->assertTrue($context['steps'][0]['now']);
        $this->assertNotNull($context['steps'][2]['confirm']);
        $this->assertNull($context['steps'][0]['confirm']);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $generator->create_item(['libraryid' => $library->id]);
        $this->assertSame(['catalogue'], $done());
        $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Ana', 'lastname' => 'Montes']);
        $this->assertSame(['catalogue', 'readers'], $done());
        $this->assertFalse(opening::steps($library, $admin)['open']['blocked']);
        // The rules that come with it will do, and that is kept with who said it.
        opening::say($entityid, 'rules');
        $this->assertSame(['catalogue', 'readers', 'rules'], $done());
        $this->assertStringContainsString(fullname(get_admin()), opening::steps($library, $admin)['rules']['meta']);
        // Someone given the librarian's role is a team.
        $colleague = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $colleague->id, access::context($entityid)->id);
        $this->assertSame(['catalogue', 'readers', 'rules', 'team'], $done());
        $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/open_steps', opening::context($library, $admin, 'x', true));
        $this->assertStringContainsString('4 of 5', $html);

        access::set_closed($entityid, false);
        $this->assertSame(opening::STEPS, $done());
        $this->assertFalse(opening::shows($library, $admin));
        $this->assertNull(opening::context($library, $admin, 'ayv-open', true));
    }

    /**
     * Whoever only keeps the desk sees the steps that are not theirs with who does them, and may not say anything.
     */
    public function test_not_theirs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = libraries::create_entity(['name' => 'Riverside Library', 'closed' => true]);
        $manager = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Gil']);
        role_assign(roles::ensure_manager(), $manager->id, access::context((int) $library->entityid)->id);
        $desk = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $desk->id, access::context((int) $library->entityid)->id);
        $this->setUser($desk);
        $context = opening::context($library, (int) $desk->id, 'ayv-open', false);
        $this->assertNotNull($context);
        $rules = $context['steps'][2];
        $this->assertTrue($rules['notyours']);
        $this->assertFalse($rules['can']);
        $this->assertNull($rules['confirm']);
        $this->assertStringContainsString('Ana Gil', $rules['who']);
    }

    /**
     * Another centre on a site already open keeps its doors as they were: only the screen and signing up start closed.
     */
    public function test_closed_only_when_asked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = libraries::create_entity(['name' => 'Old way']);
        $this->assertFalse(access::closed((int) $library->entityid));
        $this->assertNull(opening::context($library, (int) get_admin()->id, 'ayv-open', false));
    }
}
