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
 * Tests for the entities and their libraries.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\libraries
 */
#[\PHPUnit\Framework\Attributes\CoversClass(libraries::class)]
final class libraries_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A centre from nothing; a classroom library with its group; addresses made from names and never repeated;
     * renaming; removing only what has no copies; the practice library left out.
     */
    public function test_libraries(): void {
        global $DB;
        $this->resetAfterTest();
        $central = libraries::create_entity(['name' => 'Colegio Inventado', 'library' => 'Biblioteca Central',
            'yearstart' => '13-45', 'classification' => 'dewey', 'controlletter' => 1]);
        $entity = $DB->get_record(self::T . 'entity', ['id' => $central->entityid], '*', MUST_EXIST);
        $this->assertSame('09-01', $entity->yearstart, 'a date that is not one becomes September the first');
        $this->assertSame(['classification' => 'dewey', 'controlletter' => true], json_decode($entity->settings, true));
        $this->assertSame('biblioteca-central', $central->slug);
        $this->assertSame('central', $central->kind);

        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $year = $gen->create_year(['entityid' => $central->entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            time(),
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $reader = $gen->create_reader(['entityid' => $central->entityid]);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '4º B']);
        $this->assertSame(['4º B'], libraries::groups((int) $central->entityid));

        $aula = libraries::create_library((int) $central->entityid, ['name' => 'Biblioteca Central', 'kind' => 'classroom',
            'parentid' => $central->id, 'group' => '4º B']);
        $this->assertSame('biblioteca-central-2', $aula->slug);
        $this->assertEquals($central->id, $aula->parentid);
        $this->assertSame(['groups' => ['4º B']], json_decode($aula->settings, true));
        $this->assertSame('mi-aula', libraries::slug('  Mi   aula! '));
        $this->assertSame('n-a-o', libraries::slug('Ñ á ó'));

        libraries::update_library((int) $aula->id, ['name' => 'Estantería de 4º', 'kind' => 'classroom',
            'parentid' => $aula->id, 'groups' => ['4º B', ' ', '4º A', '4º B']]);
        $aula = $DB->get_record(self::T . 'library', ['id' => $aula->id]);
        $this->assertSame('Estantería de 4º', $aula->name);
        $this->assertSame(['4º A', '4º B'], shelf::groups($aula), 'several groups, each once, in order');
        $old = (object) ['kind' => 'classroom', 'settings' => json_encode(['group' => '3º C'])];
        $this->assertSame(['3º C'], shelf::groups($old), 'a library from before 0.18.0 keeps its group');
        $this->assertNull($aula->parentid, 'a library does not hang from itself');

        $gen->create_item(['libraryid' => $central->id]);
        $this->assertFalse(libraries::delete_library((int) $central->id));
        $this->assertTrue(libraries::delete_library((int) $aula->id));

        practice::reset();
        $all = libraries::all();
        $this->assertCount(1, $all);
        $this->assertSame(['Biblioteca Central'], array_map(fn($l) => $l->name, $all[0]['libraries']));
    }
}
