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

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\libraries;

/**
 * Tests for readers from a list.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\csvlist
 */
#[\PHPUnit\Framework\Attributes\CoversClass(csvlist::class)]
final class csvlist_test extends \advanced_testcase {
    /**
     * New readers get cards and their group; whoever has an account with the same email is linked to it; loading
     * again finds each one by its key, changes what changed and repeats nobody.
     */
    public function test_load(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $DB->set_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            json_encode(['controlletter' => true]),
            ['id' => $entityid]
        );
        $ana = $this->getDataGenerator()->create_user(['email' => 'ana@example.invalid']);
        $list = [
            ['idnumber' => '1001', 'firstname' => 'Ana', 'lastname' => 'Montes', 'group' => '3º A',
                'email' => 'ana@example.invalid'],
            ['firstname' => 'Leo', 'lastname' => 'Faro', 'group' => '3º A', 'type' => 'Profesor'],
            ['firstname' => 'Noa', 'lastname' => 'Ríos', 'cardcode' => '20000c'],
        ];
        $first = csvlist::load($entityid, $list);
        $this->assertCount(3, $first['ids']);
        unset($first['ids']);
        $this->assertSame(['created' => 3, 'updated' => 0, 'linked' => 1, 'total' => 3, 'overlimit' => 0], $first);
        $readers = array_values($DB->get_records('local_oksigeniaayvurenda_reader', ['entityid' => $entityid], 'id'));
        $this->assertSame(
            [control_letter::append('00001'), control_letter::append('00002'), '20000C'],
            array_column($readers, 'cardcode')
        );
        $this->assertEquals($ana->id, $readers[0]->userid);
        $this->assertNull($readers[0]->firstname);
        $this->assertSame('teacher', $readers[1]->readertype);
        $this->assertSame('Leo', $readers[1]->firstname);
        $this->assertSame(2, $DB->count_records('local_oksigeniaayvurenda_member', ['groupname' => '3º A']));

        $list[1]['group'] = '4º A';
        $second = csvlist::load($entityid, $list);
        unset($second['ids']);
        $this->assertSame(['created' => 0, 'updated' => 3, 'linked' => 1, 'total' => 3, 'overlimit' => 0], $second);
        $this->assertSame('4º A', $DB->get_field(
            'local_oksigeniaayvurenda_member',
            'groupname',
            ['readerid' => $readers[1]->id]
        ));
        $this->assertSame(3, $DB->count_records('local_oksigeniaayvurenda_reader', ['entityid' => $entityid]));
    }

    /**
     * With centres apart, a list does not take the account of someone of another centre, and does not say whether there is one.
     */
    public function test_an_account_of_another_centre(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('local_oksigeniaayvurenda');
        $mine = libraries::create_entity(['name' => 'Centro uno', 'library' => 'Uno']);
        $other = libraries::create_entity(['name' => 'Centro dos', 'library' => 'Dos']);
        $theirs = $generator->create_user(['username' => 'deotrocentro', 'email' => 'deotro@example.org']);
        $plugin->create_reader(['entityid' => $other->entityid, 'userid' => $theirs->id]);
        $staff = $generator->create_user(['username' => 'delcentro']);
        $role = create_role('Inside', 'ayvinside', 'Made up for the test');
        role_assign($role, $staff->id, access::context((int) $mine->entityid)->id);

        $list = [['firstname' => 'Eva', 'lastname' => 'Uno', 'username' => 'deotrocentro'],
            ['firstname' => 'Hugo', 'lastname' => 'Dos', 'email' => 'deotro@example.org'],
            ['firstname' => 'Teo', 'lastname' => 'Tres', 'username' => 'delcentro']];
        $stats = csvlist::load((int) $mine->entityid, $list);
        $this->assertSame(3, $stats['created']);
        $this->assertSame(1, $stats['linked']);
        $linked = $DB->get_fieldset_select(
            'local_oksigeniaayvurenda_reader',
            'userid',
            'entityid = ? AND userid IS NOT NULL',
            [$mine->entityid]
        );
        $this->assertSame([(int) $staff->id], array_map('intval', $linked));
    }

    /**
     * A school office's list: «Curso» is the group, and a group named for teachers or staff says their kind.
     */
    public function test_curso_and_kind_of_group(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = (int) $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library()->entityid;
        $read = \local_oksigeniaayvurenda\core\readers\csv_list::read("Nombre,Apellidos,Curso\nAna,Prado,3º Primaria Z\n"
            . "Fermín,Lago,Profesores Z\nRosa,Valle,Personal de cocina\n");
        $map = \local_oksigeniaayvurenda\core\readers\csv_list::guess($read['columns']);
        $this->assertSame('group', $map[2]);
        $people = \local_oksigeniaayvurenda\core\readers\csv_list::readers($read['rows'], $map);
        csvlist::load($entityid, $people);
        $kind = fn(string $first) => $DB->get_field('local_oksigeniaayvurenda_reader', 'readertype', ['entityid' => $entityid,
            'firstname' => $first]);
        $this->assertSame('student', $kind('Ana'));
        $this->assertSame('teacher', $kind('Fermín'));
        $this->assertSame('staff', $kind('Rosa'));
    }

    /**
     * A name alone the first time and twice the next (another reader of the same name came): the one of before is kept,
     * by their group, and only the new one is made.
     */
    public function test_a_name_that_comes_twice(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = (int) $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library()->entityid;
        csvlist::load($entityid, [['firstname' => 'Elena', 'lastname' => 'Zeta', 'group' => '2º A']]);
        $first = (int) $DB->get_field('local_oksigeniaayvurenda_reader', 'id', ['entityid' => $entityid, 'firstname' => 'Elena']);
        csvlist::load($entityid, [['firstname' => 'Elena', 'lastname' => 'Zeta', 'group' => '2º A'],
            ['firstname' => 'Elena', 'lastname' => 'Zeta', 'group' => '3º B']]);
        $elenas = $DB->get_records('local_oksigeniaayvurenda_reader', ['entityid' => $entityid, 'firstname' => 'Elena',
            'status' => 'active']);
        $this->assertCount(2, $elenas);
        $this->assertArrayHasKey($first, $elenas);
    }
}
