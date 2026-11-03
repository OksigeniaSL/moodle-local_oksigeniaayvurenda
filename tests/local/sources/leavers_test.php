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

use local_oksigeniaayvurenda\local\matching;

/**
 * Tests for sources that stop bringing readers.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\leavers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(leavers::class)]
final class leavers_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What is believable: few readers, a few leaving, half by hand; never everyone in a list of some size.
     */
    public function test_believable(): void {
        $this->assertTrue(leavers::believable(4, 4));
        $this->assertTrue(leavers::believable(100, 50));
        $this->assertFalse(leavers::believable(100, 51));
        $this->assertTrue(leavers::believable(100, 99, true));
        $this->assertFalse(leavers::believable(100, 100, true));
    }

    /**
     * Cohorts with six people, and whoever manages the centre.
     *
     * @return array Library, the two cohorts, the manager.
     */
    private function world(): array {
        $dg = $this->getDataGenerator();
        $library = $dg->get_plugin_generator('local_oksigeniaayvurenda')->create_library(['slug' => 'central']);
        $a = $dg->create_cohort(['name' => '1º A']);
        $b = $dg->create_cohort(['name' => '1º B']);
        foreach ([$a, $b] as $cohort) {
            foreach ([1, 2, 3] as $n) {
                cohort_add_member($cohort->id, $dg->create_user()->id);
            }
        }
        cohorts::save((int) $library->entityid, [
            $a->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true],
            $b->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true],
        ]);
        $boss = $dg->create_user();
        $role = create_role('Lleva el centro', 'ayvleva', '');
        assign_capability('local/oksigeniaayvurenda:manage', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $boss->id, \context_system::instance()->id);
        return [$library, $a, $b, $boss];
    }

    /**
     * A chosen cohort that is deleted leaves nobody as gone, says so on the page and tells the manager once a week;
     * when the cohort is chosen no longer, it goes as it should.
     */
    public function test_cohort_gone(): void {
        global $DB;
        $this->resetAfterTest();
        [$library, $a, $b] = $this->world();
        $entityid = (int) $library->entityid;
        $now = time();
        cohorts::sync($entityid, $now);
        cohort_delete_cohort($b);
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $stats = cohorts::sync($entityid, $now);
        $this->assertSame(0, $stats['left']);
        $this->assertSame(3, $stats['held']);
        $this->assertSame(6, $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']));
        $this->assertCount(1, leavers::held($entityid));
        $this->assertCount(1, $sink->get_messages());
        cohorts::sync($entityid, $now + DAYSECS);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();

        cohorts::save($entityid, [$a->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true]]);
        $stats = cohorts::sync($entityid, $now + 2 * DAYSECS, true);
        $this->assertSame(3, $stats['left']);
        $this->assertSame([], leavers::held($entityid));
    }

    /**
     * An empty answer from a program with a good status leaves nobody as gone, by hand either.
     */
    public function test_empty_answer(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        cifra::save($entityid, ['enabled' => 1, 'url' => 'http://cifra-puente.invalid:8080/', 'mode' => 'key',
            'example' => 1, 'key' => 'made-up-key']);
        $people = json_decode(file_get_contents(__DIR__ . '/../../fixtures/cifra/alumnos.json'), true);
        foreach ([1, 2] as $n) {
            $people[] = ['id' => 7900 + $n, 'uuid' => '00000000-0000-4000-8000-00000000790' . $n, 'nombre' => 'Otro' . $n,
                'email' => 'otro' . $n . '@example.invalid'] + $people[0];
        }
        cifra::$fetcher = fn(string $method, string $url) => json_encode(str_contains($url, 'page=1&') ? $people : []);
        cifra::sync($entityid);
        $active = $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']);
        $this->assertGreaterThanOrEqual(5, $active);
        cifra::$fetcher = fn() => json_encode([]);
        $stats = cifra::sync($entityid, null, true);
        cifra::$fetcher = null;
        $this->assertSame(0, $stats['left']);
        $this->assertSame($active, $stats['held']);
        $this->assertSame($active, $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']));
    }

    /**
     * A match made by hand stays when the list comes again and its row names no account, and nobody is made twice.
     */
    public function test_match_stays(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Bruno', 'lastname' => 'Olmo']);
        $row = ['firstname' => 'Bruno', 'lastname' => 'Olmo', 'group' => '2º A', 'number' => '77'];
        csvlist::load($entityid, [$row], 'student', time(), 'list');
        $reader = $DB->get_record(self::T . 'reader', ['entityid' => $entityid], '*', MUST_EXIST);
        $this->assertNull($reader->userid);
        matching::link($entityid, (int) $reader->id, (int) $user->id);
        csvlist::load($entityid, [$row], 'student', time(), 'list');
        $this->assertSame(1, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));
        $this->assertEquals($user->id, $DB->get_field(self::T . 'reader', 'userid', ['id' => $reader->id]));
        $this->assertNull($DB->get_field(self::T . 'reader', 'firstname', ['id' => $reader->id]));
    }
}
