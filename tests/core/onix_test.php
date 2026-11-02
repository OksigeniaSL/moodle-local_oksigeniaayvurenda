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

namespace local_oksigeniaayvurenda\core;

/**
 * A publisher's ONIX as records of the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(onix::class)]
final class onix_test extends \basic_testcase {
    /**
     * Reference names: the distinctive title with its prefix (not the series'), the authors by their roles, the year of
     * publication, the pages, the description without its HTML; a product with no ISBN keeps its own reference.
     */
    public function test_reference(): void {
        $xml = file_get_contents(__DIR__ . '/../fixtures/onix/referencia.xml');
        $this->assertTrue(onix::is(substr($xml, 0, 4096)));
        $records = onix::records($xml);
        $this->assertCount(2, $records);
        $d = $records[0]['description'];
        $this->assertSame('9788490000014', $records[0]['key']);
        $this->assertSame('El faro dormido', $d['record']['title']);
        $this->assertSame('una historia de mar', $d['record']['subtitle']);
        $this->assertSame(['Laurel Prado, Irene', 'Olmo, Bruno'], $d['authorities']['author']);
        $this->assertSame('2024', $d['record']['pubyear']);
        $this->assertSame('96 p.', $d['record']['extent']);
        $this->assertSame('spa', $d['record']['lang']);
        $this->assertSame('Villanube', $d['record']['pubplace']);
        $this->assertSame('Un faro que duerme.', $d['record']['summary']);
        $this->assertSame(['Editorial Brújula'], $d['authorities']['publisher']);
        $this->assertSame(['Colección Linterna'], $d['authorities']['collection']);
        $this->assertSame(['Aventuras'], $d['authorities']['subject']);
        $this->assertSame([], $records[0]['items']);
        $this->assertSame('onix-brujula-0002', $records[1]['key']);
        $this->assertNull($records[1]['description']['record']['isbn']);
    }

    /**
     * The short tags of 3.0 read the same.
     */
    public function test_short_tags(): void {
        $records = onix::records(file_get_contents(__DIR__ . '/../fixtures/onix/cortas.xml'));
        $this->assertCount(1, $records);
        $d = $records[0]['description'];
        $this->assertSame('Mapas del sur', $d['record']['title']);
        $this->assertSame('9788490000021', $d['record']['isbn']);
        $this->assertSame(['Estrada Brisa, Olivia'], $d['authorities']['author']);
        $this->assertSame('2022', $d['record']['pubyear']);
        $this->assertFalse(onix::is('<collection xmlns="http://www.loc.gov/MARC21/slim">'));
    }
}
