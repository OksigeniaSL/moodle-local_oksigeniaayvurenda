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

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\local\readers;

/**
 * Tests for readers from cohorts.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\cohorts
 * @covers     \local_oksigeniaayvurenda\local\readers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cohorts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(readers::class)]
final class cohorts_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Two group cohorts and a staff cohort, with three students and a teacher.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->library = $dg->get_plugin_generator('local_oksigeniaayvurenda')->create_library(['slug' => 'central']);
        $DB->set_field(self::T . 'entity', 'settings', json_encode(['controlletter' => true]), ['id' => $w->library->entityid]);
        $w->a = $dg->create_cohort(['name' => '3º Primaria A']);
        $w->b = $dg->create_cohort(['name' => '4º Primaria B']);
        $w->staff = $dg->create_cohort(['name' => 'Claustro']);
        $w->ana = $dg->create_user(['firstname' => 'Ana', 'lastname' => 'Montes']);
        $w->leo = $dg->create_user(['firstname' => 'Leo', 'lastname' => 'Prado']);
        $w->noa = $dg->create_user(['firstname' => 'Noa', 'lastname' => 'Valle']);
        $w->teacher = $dg->create_user(['firstname' => 'Irene', 'lastname' => 'Laurel']);
        cohort_add_member($w->a->id, $w->ana->id);
        cohort_add_member($w->a->id, $w->leo->id);
        cohort_add_member($w->b->id, $w->noa->id);
        cohort_add_member($w->staff->id, $w->teacher->id);
        cohorts::save((int) $w->library->entityid, [
            $w->a->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true],
            $w->b->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true],
            $w->staff->id => ['type' => 'teacher', 'stage' => '', 'group' => false],
        ]);
        return $w;
    }

    /**
     * The first sync makes a reader with a card for each person, and the group of the students this year.
     */
    public function test_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $stats = cohorts::sync((int) $w->library->entityid);
        $this->assertSame(4, $stats['created']);
        $ana = $DB->get_record(self::T . 'reader', ['userid' => $w->ana->id], '*', MUST_EXIST);
        $this->assertSame('student', $ana->readertype);
        $this->assertTrue(\local_oksigeniaayvurenda\core\control_letter::is_valid($ana->cardcode));
        $this->assertSame('teacher', $DB->get_field(self::T . 'reader', 'readertype', ['userid' => $w->teacher->id]));
        $this->assertSame('3º Primaria A', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $ana->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'year', ['entityid' => $w->library->entityid]));

        $groups = readers::groups($w->library);
        $this->assertSame(['3º Primaria A', '4º Primaria B'], array_column($groups, 'name'));
        $this->assertSame(2, $groups[0]['count']);
        $this->assertSame(['Ana Montes', 'Leo Prado'], array_column(readers::in_group($w->library, '3º Primaria A'), 'name'));
        $this->assertSame(['Irene Laurel'], array_column(readers::in_group($w->library, ''), 'name'));

        $again = cohorts::sync((int) $w->library->entityid);
        $this->assertSame(0, $again['created']);
        $this->assertSame(4, $again['total']);
        $this->assertSame(4, $DB->count_records(self::T . 'reader'));
    }

    /**
     * Moving group changes this year's group; leaving marks the reader, who keeps the card; coming back reopens it.
     */
    public function test_move_leave_back(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        cohorts::sync((int) $w->library->entityid);
        $leo = $DB->get_record(self::T . 'reader', ['userid' => $w->leo->id], '*', MUST_EXIST);

        cohort_remove_member($w->a->id, $w->leo->id);
        cohort_add_member($w->b->id, $w->leo->id);
        cohorts::sync((int) $w->library->entityid);
        $this->assertSame('4º Primaria B', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $leo->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'member', ['readerid' => $leo->id]));

        cohort_remove_member($w->b->id, $w->leo->id);
        $stats = cohorts::sync((int) $w->library->entityid);
        $this->assertSame(1, $stats['left']);
        $this->assertSame('left', $DB->get_field(self::T . 'reader', 'status', ['id' => $leo->id]));
        $this->assertSame($leo->cardcode, $DB->get_field(self::T . 'reader', 'cardcode', ['id' => $leo->id]));

        cohort_add_member($w->a->id, $w->leo->id);
        $this->assertSame(1, cohorts::sync((int) $w->library->entityid)['back']);
    }

    /**
     * The options list every cohort, the chosen ones as they were saved and a guessed stage for the others.
     */
    public function test_options(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $other = $this->getDataGenerator()->create_cohort(['name' => 'ESO 1º C']);
        $options = [];
        foreach (cohorts::options((int) $w->library->entityid) as $option) {
            $options[$option['name']] = $option;
        }
        $this->assertTrue($options['3º Primaria A']['chosen']);
        $this->assertSame(2, $options['3º Primaria A']['members']);
        $this->assertSame('teacher', $options['Claustro']['type']);
        $this->assertFalse($options['ESO 1º C']['chosen']);
        $this->assertSame('ESO', $options['ESO 1º C']['stage']);
    }

    /**
     * Courses with groups bring their students with their group (or the course, without one) and their teachers as
     * teachers; a course no longer chosen lets its readers go.
     */
    public function test_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $library = $dg->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $course = $dg->create_course(['fullname' => 'Lengua inventada']);
        $group = $dg->create_group(['courseid' => $course->id, 'name' => '3º A']);
        $ana = $dg->create_user();
        $leo = $dg->create_user();
        $profe = $dg->create_user();
        $dg->enrol_user($ana->id, $course->id, 'student');
        $dg->enrol_user($leo->id, $course->id, 'student');
        $dg->enrol_user($profe->id, $course->id, 'editingteacher');
        $dg->create_group_member(['groupid' => $group->id, 'userid' => $ana->id]);
        courses::save($entityid, [$course->id => ['stage' => 'Primaria', 'group' => true]]);
        $stats = cohorts::sync($entityid);
        $this->assertSame(3, $stats['created']);
        $reader = fn($user) => $DB->get_record('local_oksigeniaayvurenda_reader', ['userid' => $user->id], '*', MUST_EXIST);
        $group = fn($user) => $DB->get_field(
            'local_oksigeniaayvurenda_member',
            'groupname',
            ['readerid' => $reader($user)->id]
        );
        $this->assertSame('3º A', $group($ana));
        $this->assertSame('Lengua inventada', $group($leo));
        $this->assertSame('teacher', $reader($profe)->readertype);
        $this->assertFalse($group($profe));

        courses::save($entityid, []);
        $this->assertSame(0, cohorts::sync($entityid)['total']);
        courses::save($entityid, [$course->id => ['stage' => '', 'group' => false]]);
        $this->assertSame(
            ['created' => 0, 'updated' => 0, 'left' => 0, 'back' => 0, 'total' => 3, 'overlimit' => 0],
            cohorts::sync($entityid)
        );
    }
}
