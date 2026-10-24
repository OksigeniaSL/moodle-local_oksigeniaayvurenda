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
 * Tests for boxes lent between libraries.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\lots
 */
#[\PHPUnit\Framework\Attributes\CoversClass(lots::class)]
final class lots_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Filling a box by codes, sending it to a classroom, lending one of its books there, giving the box back: what is
     * on the shelf goes home now, what a child has goes home when returned.
     */
    public function test_box(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library();
        $aula = libraries::create_library((int) $central->entityid, ['name' => 'Aula de 4º B', 'kind' => 'classroom',
            'group' => '4º B']);
        $now = time();
        $year = $gen->create_year(['entityid' => $central->entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $ana = $gen->create_member(['yearid' => $year->id, 'groupname' => '4º B', 'readerid' => $gen->create_reader([
            'entityid' => $central->entityid, 'firstname' => 'Ana', 'lastname' => 'Prueba'])->id])->readerid;
        $one = $gen->create_item(['libraryid' => $central->id]);
        $two = $gen->create_item(['libraryid' => $central->id]);
        $lent = $gen->create_item(['libraryid' => $central->id, 'status' => 'onloan']);
        $staff = $this->getDataGenerator()->create_user();

        $id = lots::create($central, 'Dinosaurios', (int) $aula->id, $now + 30 * DAYSECS, (int) $staff->id, $now);
        $lot = lots::get($id, $central);
        $this->assertSame('added', lots::add($lot, strtolower($one->barcode))['kind']);
        $this->assertSame('added', lots::add($lot, $two->barcode)['kind']);
        $this->assertSame('already', lots::add($lot, $one->barcode)['kind']);
        $this->assertSame('notfree', lots::add($lot, $lent->barcode)['kind']);
        $this->assertSame('notfound', lots::add($lot, '999999Z')['kind']);
        $this->assertFalse(libraries::delete_library((int) $aula->id), 'a library with boxes stays');

        $this->assertSame(2, lots::send($lot, $now));
        $this->assertEquals($aula->id, $DB->get_field(self::T . 'item', 'libraryid', ['id' => $one->id]));
        $this->assertCount(2, shelf::books($aula));
        $received = lots::of_library($aula)['received'];
        $this->assertSame([1, 2], [count($received), $received[0]['copies']]);

        $loanid = shelf::lend($aula, (int) $two->id, (int) $ana, (int) $staff->id, $now);
        $this->assertGreaterThan(0, $loanid);
        $back = lots::give_back(lots::get($id, $aula), $now + DAYSECS);
        $this->assertSame(1, $back['home']);
        $this->assertSame([['title' => $DB->get_field(self::T . 'record', 'title', ['id' => $two->recordid]),
            'who' => 'Ana Prueba']], $back['pending']);
        $this->assertEquals($central->id, $DB->get_field(self::T . 'item', 'libraryid', ['id' => $one->id]));
        $this->assertSame(1, lots::of_library($central)['sent'][0]['missing']);

        shelf::give_back($aula, (int) $two->id, (int) $staff->id, $now + 2 * DAYSECS);
        $this->assertEquals($central->id, $DB->get_field(self::T . 'item', 'libraryid', ['id' => $two->id]));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $two->id]));
        $this->assertSame(0, lots::of_library($central)['sent'][0]['missing']);
        $this->assertSame([], lots::pending(lots::get($id, $central)));
    }
}
