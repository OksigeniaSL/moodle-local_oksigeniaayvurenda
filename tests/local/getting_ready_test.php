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
 * A site whose library is getting ready: open only to its staff, nobody else sees a way in or hears from it; and how a
 * centre is got ready from the console.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\access
 * @covers     \local_oksigeniaayvurenda\local\centre_setup
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(centre_setup::class)]
final class getting_ready_test extends \advanced_testcase {
    /**
     * A site with one centre, a reader of it, someone who is neither, and its librarian.
     *
     * @return \stdClass library, reader (user), someone (user), staff (user).
     */
    private function site(): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $reader = $this->getDataGenerator()->create_user();
        $generator->create_reader(['entityid' => $library->entityid, 'userid' => $reader->id]);
        $staff = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $staff->id, \context_system::instance()->id);
        return (object) ['library' => $library, 'reader' => $reader, 'someone' => $this->getDataGenerator()->create_user(),
            'staff' => $staff];
    }

    /**
     * Open, everyone with an account sees the way in; getting ready, only its staff.
     */
    public function test_way_in(): void {
        $this->resetAfterTest();
        $w = $this->site();
        foreach (['reader', 'someone', 'staff'] as $who) {
            $this->assertTrue(access::sees_any((int) $w->$who->id), $who);
        }

        access::set_closed((int) $w->library->entityid, true);
        access::forget();
        $this->assertFalse(access::sees_any((int) $w->reader->id));
        $this->assertFalse(access::sees_any((int) $w->someone->id));
        $this->assertTrue(access::sees_any((int) $w->staff->id));

        // The app's menu has no «Library» for them either.
        $this->setUser($w->someone);
        $this->assertTrue(\local_oksigeniaayvurenda\output\mobile::mobile_init([])['disabled']);
        $this->setUser($w->staff);
        $this->assertFalse(\local_oksigeniaayvurenda\output\mobile::mobile_init([])['disabled']);
    }

    /**
     * While it gets ready, the library sends nothing to its readers, and still tells its staff what waits for them.
     */
    public function test_nothing_sent(): void {
        $this->resetAfterTest();
        $w = $this->site();
        access::set_closed((int) $w->library->entityid, true);
        access::forget();
        $this->assertTrue(access::shut_for((int) $w->reader->id));
        $this->assertFalse(access::shut_for((int) $w->staff->id));

        $sink = $this->redirectMessages();
        $this->assertFalse(notices::send('overdue', $w->reader, 'Late', 'A book is late'));
        $this->assertTrue(notices::send('stafftodo', $w->staff, 'Waiting', 'Something waits'));
        $this->assertCount(1, $sink->get_messages());

        // Open, the reader hears from it again.
        access::set_closed((int) $w->library->entityid, false);
        access::forget();
        $this->assertFalse(access::shut_for((int) $w->reader->id));
        $this->assertTrue(notices::send('overdue', $w->reader, 'Late', 'A book is late'));
        $sink->close();
    }

    /**
     * From the console: names, closed, the characters and the cohorts, keeping what is not asked.
     */
    public function test_console(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->site();
        $entityid = (int) $w->library->entityid;
        $infantil = $this->getDataGenerator()->create_cohort(['name' => 'Infantil', 'idnumber' => 'INF']);
        $staffroom = $this->getDataGenerator()->create_cohort(['name' => 'Claustro', 'idnumber' => 'CLAUSTRO']);
        $class = $this->getDataGenerator()->create_cohort(['name' => '3.º A', 'idnumber' => '3A']);

        $done = centre_setup::apply($entityid, ['name' => 'Centro Zeta', 'library' => 'Biblioteca Zeta', 'closed' => true,
            'memory' => 'Abuela Zeta', 'herald' => 'Pregonera Zeta', 'heraldsex' => 'f',
            'cohorts' => 'INF=student:Infantil, CLAUSTRO=teacher, 3.º A=student:Primaria:group']);
        $this->assertCount(5, $done);
        $this->assertSame('Centro Zeta', $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $entityid]));
        $this->assertSame('Biblioteca Zeta', $DB->get_field('local_oksigeniaayvurenda_library', 'name', ['id' => $w->library->id]));
        $this->assertTrue(access::closed($entityid));
        $characters = characters::of($entityid);
        $this->assertSame('Abuela Zeta', $characters['memory']['name']);
        $this->assertSame('Pregonera Zeta', $characters['herald']['name']);
        $this->assertSame('f', $characters['herald']['sex']);

        $chosen = json_decode($DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)['cohorts'];
        $this->assertSame(['type' => 'student', 'stage' => 'Infantil', 'group' => false], $chosen[$infantil->id]);
        $this->assertSame('teacher', $chosen[$staffroom->id]['type']);
        $this->assertTrue($chosen[$class->id]['group']);

        // A name alone keeps the rest; opening says so.
        centre_setup::apply($entityid, ['herald' => 'Otra', 'closed' => false]);
        $characters = characters::of($entityid);
        $this->assertSame('Abuela Zeta', $characters['memory']['name']);
        $this->assertSame('f', $characters['herald']['sex']);
        $this->assertFalse(access::closed($entityid));
        $this->assertSame('to its readers', centre_setup::describe($entityid)['open']);

        // A cohort that is not there, or a kind of reader that is not one, stops it before changing anything.
        foreach (['NOPE=student', 'INF=pupil'] as $bad) {
            try {
                centre_setup::apply($entityid, ['cohorts' => $bad]);
                $this->fail($bad);
            } catch (\moodle_exception $e) {
                $this->assertStringContainsString(explode('=', $bad)[0] === 'NOPE' ? 'NOPE' : 'pupil', $e->debuginfo);
            }
        }
        $this->assertCount(3, json_decode(
            $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        )['cohorts']);
    }
}
