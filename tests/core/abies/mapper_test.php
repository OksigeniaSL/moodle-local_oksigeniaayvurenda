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

namespace local_oksigeniaayvurenda\core\abies;

/**
 * Tests for the mapping from Abies to Ayvurenda.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\abies\mapper
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mapper::class)]
final class mapper_test extends \basic_testcase {
    /**
     * Languages as catalogues write them become MARC codes.
     */
    public function test_language(): void {
        $this->assertSame('spa', mapper::language('E'));
        $this->assertSame('spa', mapper::language('Español'));
        $this->assertSame('spa', mapper::language('esp'));
        $this->assertSame('eng', mapper::language('eng'));
        $this->assertSame('grn', mapper::language('Guaraní'));
        $this->assertNull(mapper::language('klingon'));
        $this->assertSame('slv', mapper::language('slv'));
        $this->assertSame('cat', mapper::language('val'));
        $this->assertNull(mapper::language('Uru'));
        $this->assertNull(mapper::language(null));
    }

    /**
     * Author functions, with or without dots and capitals.
     */
    public function test_role(): void {
        $this->assertSame('author', mapper::role('aut.'));
        $this->assertSame('author', mapper::role('aut'));
        $this->assertSame('illustrator', mapper::role('Il'));
        $this->assertSame('editor', mapper::role('ed. lit.'));
        $this->assertSame('prefacer', mapper::role('int. y not.'));
        $this->assertSame('contributor', mapper::role('something'));
    }

    /**
     * Copy and reader types by name; unknown names become a code of their own.
     */
    public function test_types(): void {
        $this->assertSame('reference', mapper::item_type('No prestable'));
        $this->assertSame('av', mapper::item_type('Vídeo'));
        $this->assertSame('mapas-murales', mapper::item_type('Mapas murales'));
        $this->assertSame('staff', mapper::reader_type('No Docente'));
        $this->assertSame('classroom', mapper::reader_type('Aula'));
        $this->assertSame('onloan', mapper::item_status('1'));
        $this->assertSame('available', mapper::item_status('4'));
        $this->assertFalse(mapper::known_status('4'));
    }

    /**
     * A record: its fields, its ISBN-13, its year, and the texts that become authorities, without repeats.
     */
    public function test_record(): void {
        $export = new export_reader(__DIR__ . '/../../fixtures/abies/small.xml');
        $docs = iterator_to_array($export->documents(), false);
        $first = mapper::record($docs[0]);
        $this->assertSame('book', $first['record']['doctype']);
        $this->assertSame('El faro dormido', $first['record']['title']);
        $this->assertSame('9780306406157', $first['record']['isbn']);
        $this->assertSame('2019', $first['record']['pubyear']);
        $this->assertSame('spa', $first['record']['lang']);
        $this->assertSame(['Editorial Faro'], $first['authorities']['publisher']);
        $this->assertSame(['Aventuras', 'Faros'], $first['authorities']['subject']);
        $this->assertSame('12', $first['extra']['seriesnumber']);
        $second = mapper::record($docs[1]);
        $this->assertSame('2008', $second['record']['pubyear']);
        $this->assertNull($second['record']['isbn']);
        $this->assertSame('audio', mapper::record($docs[3])['record']['doctype']);
    }
}
