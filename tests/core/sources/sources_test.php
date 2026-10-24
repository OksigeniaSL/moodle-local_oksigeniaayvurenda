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

namespace local_oksigeniaayvurenda\core\sources;

/**
 * Tests for the catalogues asked by ISBN.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\sources\sru
 * @covers     \local_oksigeniaayvurenda\core\sources\openlibrary
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sru::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(openlibrary::class)]
final class sources_test extends \basic_testcase {
    /**
     * The SRU address and the editions of its answer; a diagnostic is an error.
     */
    public function test_sru(): void {
        $this->assertSame('https://lx2.loc.gov/sru/lcdb?version=1.1&operation=searchRetrieve&query=bath.isbn%3D9788490000014' .
            '&maximumRecords=5&recordSchema=marcxml', (new sru('https://lx2.loc.gov/sru/lcdb'))->url('978-84-90000-01-4'));
        $this->assertStringStartsWith(
            'https://example.org/sru?x-db=books&version=2.0',
            (new sru('https://example.org/sru?x-db=books', 'dc.identifier', '2.0'))->url('9788490000014')
        );
        $descriptions = sru::descriptions(file_get_contents(__DIR__ . '/../../fixtures/marc/sru.xml'));
        $this->assertCount(2, $descriptions);
        $this->assertSame('The sleeping lighthouse', $descriptions[0]['record']['title']);
        $this->assertSame(['863.7'], $descriptions[0]['authorities']['class']);
        $this->assertSame('2020', $descriptions[0]['record']['pubyear']);
        $this->assertSame('9788490000014', $descriptions[1]['record']['isbn']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query syntax error');
        sru::descriptions(file_get_contents(__DIR__ . '/../../fixtures/marc/sru-diagnostic.xml'));
    }

    /**
     * Open Library's answer, with its loose subjects trimmed; nothing when it does not know the book.
     */
    public function test_openlibrary(): void {
        $this->assertSame(
            'https://openlibrary.org/api/books?bibkeys=ISBN:9791040000075&jscmd=data&format=json',
            openlibrary::url('9791040000075')
        );
        $d = openlibrary::description(file_get_contents(__DIR__ . '/../../fixtures/openlibrary/book.json'), '9791040000075');
        $this->assertSame('La nube de tinta', $d['record']['title']);
        $this->assertSame('cuentos para leer a oscuras', $d['record']['subtitle']);
        $this->assertSame('2023', $d['record']['pubyear']);
        $this->assertSame('120 p.', $d['record']['extent']);
        $this->assertSame('Villanube', $d['record']['pubplace']);
        $this->assertSame(['Marco Quintana Cardo'], $d['authorities']['author']);
        $this->assertSame(['Editorial Brújula'], $d['authorities']['publisher']);
        $this->assertSame(['863'], $d['authorities']['class']);
        $this->assertSame(['Cuentos', 'Noche'], $d['authorities']['subject']);
        $this->assertSame('https://covers.openlibrary.org/b/id/0-M.jpg', $d['extra']['cover']);
        $this->assertNull(openlibrary::description('{}', '9788490000014'));
        $this->assertNull(openlibrary::description('not json', '9788490000014'));
    }
}
