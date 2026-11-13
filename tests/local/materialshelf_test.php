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
 * Tests for the material as tiles: its kind, its units and «−».
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\materialshelf
 */
#[\PHPUnit\Framework\Attributes\CoversClass(materialshelf::class)]
final class materialshelf_test extends \advanced_testcase {
    /**
     * The kind it was given, or what its name says in a few languages, or something else; a drawing for each.
     */
    public function test_kind(): void {
        $this->assertSame('tableta', materialshelf::kind('Lo que sea', ['kind' => 'tableta']));
        $this->assertSame('portatil', materialshelf::kind('Chromebook 314', []));
        $this->assertSame('portatil', materialshelf::kind('Portátiles del carro', []));
        $this->assertSame('calculadora', materialshelf::kind('Calculator', []));
        $this->assertSame('camara', materialshelf::kind('Cámara de fotos', []));
        $this->assertSame('juego', materialshelf::kind('Ajedrez', []));
        $this->assertSame('auriculares', materialshelf::kind('Headphones', []));
        $this->assertSame('otra', materialshelf::kind('Microscopio', ['kind' => 'nada']));
        foreach (materialshelf::KINDS as $kind) {
            $this->assertStringStartsWith('<svg', materialshelf::drawing($kind));
        }
    }

    /**
     * A thing with three units, one lent: «−» takes the last one in its place, not the lent one, and none is taken
     * when every unit left is lent.
     */
    public function test_units_and_less(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $added = cataloguer::add($library, ['record' => ['doctype' => 'device', 'title' => 'Tableta'], 'authorities' => [],
            'extra' => ['kind' => 'tableta']], ['count' => 3, 'itemtype' => 'device']);
        $ids = $DB->get_fieldset_select('local_oksigeniaayvurenda_item', 'id', 'recordid = ?', [$added['recordid']]);
        sort($ids);
        $DB->set_field('local_oksigeniaayvurenda_item', 'status', 'onloan', ['id' => $ids[2]]);
        $thing = materialshelf::things($library)[0];
        $this->assertSame([3, 1, 2, 'tableta'], [$thing['units'], $thing['lent'], $thing['inplace'], $thing['kind']]);
        $user = (int) get_admin()->id;
        $this->assertTrue(materialshelf::less($library, $added['recordid'], $user));
        $this->assertSame('withdrawn', $DB->get_field('local_oksigeniaayvurenda_item', 'status', ['id' => $ids[1]]));
        $this->assertTrue(materialshelf::less($library, $added['recordid'], $user));
        $this->assertFalse(materialshelf::less($library, $added['recordid'], $user));
        $thing = materialshelf::things($library)[0];
        $this->assertSame([1, 1, 0], [$thing['units'], $thing['lent'], $thing['inplace']]);
    }
}
