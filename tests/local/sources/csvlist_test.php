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
        $this->assertSame(['created' => 3, 'updated' => 0, 'linked' => 1, 'total' => 3], $first);
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
        $this->assertSame(['created' => 0, 'updated' => 3, 'linked' => 1, 'total' => 3], $second);
        $this->assertSame('4º A', $DB->get_field(
            'local_oksigeniaayvurenda_member',
            'groupname',
            ['readerid' => $readers[1]->id]
        ));
        $this->assertSame(3, $DB->count_records('local_oksigeniaayvurenda_reader', ['entityid' => $entityid]));
    }
}
