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
 * Tests for the shelf of a classroom library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\shelf
 */
#[\PHPUnit\Framework\Attributes\CoversClass(shelf::class)]
final class shelf_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The tutor of the group uses the shelf, nobody else; a child of the group borrows with two taps until the end of
     * the year; a child of another group does not; bringing it back and undoing; what each one read.
     */
    public function test_shelf(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library();
        $aula = libraries::create_library((int) $central->entityid, ['name' => 'Aula de 4º B', 'kind' => 'classroom',
            'parentid' => $central->id, 'group' => '4º B']);
        $now = time();
        $year = $gen->create_year(['entityid' => $central->entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $tutor = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $DB->insert_record(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => '4º B', 'userid' => $tutor->id,
            'timecreated' => $now, 'timemodified' => $now]);
        $this->assertTrue(shelf::can_use($aula, (int) $tutor->id));
        $this->assertFalse(shelf::can_use($aula, (int) $other->id));
        $this->assertFalse(shelf::can_use($central, (int) $tutor->id));

        $child = fn(string $name, string $group) => $gen->create_member(['yearid' => $year->id, 'groupname' => $group,
            'readerid' => $gen->create_reader(['entityid' => $central->entityid, 'firstname' => $name,
                'lastname' => 'Prueba'])->id])->readerid;
        $bruno = $child('Bruno', '4º B');
        $ana = $child('Ana', '4º B');
        $leo = $child('Leo', '5º A');
        $this->assertSame(['Ana Prueba', 'Bruno Prueba'], array_column(shelf::children($aula), 'name'));

        $faro = $gen->create_item(['libraryid' => $aula->id, 'recordid' => $gen->create_record([
            'entityid' => $central->entityid, 'title' => 'El faro dormido'])->id]);
        $this->assertSame(0, shelf::lend($aula, (int) $faro->id, (int) $leo, (int) $tutor->id), 'not of the group');
        $loanid = shelf::lend($aula, (int) $faro->id, (int) $ana, (int) $tutor->id);
        $this->assertGreaterThan(0, $loanid);
        $this->assertEquals((int) $year->timeend - 1, $DB->get_field(self::T . 'loan', 'timedue', ['id' => $loanid]));
        $this->assertSame('Ana Prueba', shelf::books($aula)[0]['lent']);
        $this->assertSame(0, shelf::lend($aula, (int) $faro->id, (int) $bruno, (int) $tutor->id), 'Ana has it');

        $this->assertSame($loanid, shelf::give_back($aula, (int) $faro->id, (int) $tutor->id));
        (new desk($aula, (int) $tutor->id))->undo($loanid, 'returned');
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $faro->id]));
        shelf::give_back($aula, (int) $faro->id, (int) $tutor->id);

        $read = shelf::read($aula);
        $this->assertSame(
            [['name' => 'Ana Prueba', 'books' => ['El faro dormido']], ['name' => 'Bruno Prueba', 'books' => []]],
            $read
        );
    }
}
