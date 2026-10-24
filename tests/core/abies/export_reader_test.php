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
 * Tests for the reader of Abies Web exports.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\abies\export_reader
 */
#[\PHPUnit\Framework\Attributes\CoversClass(export_reader::class)]
final class export_reader_test extends \basic_testcase {
    /**
     * The made-up export of the fixtures.
     *
     * @return export_reader
     */
    private function export(): export_reader {
        return new export_reader(__DIR__ . '/../../fixtures/abies/small.xml');
    }

    /**
     * Records come with their type, their id and every repeated field.
     */
    public function test_documents(): void {
        $docs = iterator_to_array($this->export()->documents(), false);
        $this->assertCount(4, $docs);
        $this->assertSame('Libro', $docs[0]['name']);
        $this->assertSame('1001', $docs[0]['id']);
        $this->assertSame(['Montes, Ana', 'Prado, Leo'], $docs[0]['fields']['Autor']);
        $this->assertSame('Audio', $docs[3]['name']);
    }

    /**
     * The authority tables, by their short name, and the author links among them.
     */
    public function test_authorities(): void {
        $tables = [];
        foreach ($this->export()->authorities() as $row) {
            $tables[$row['table']] = ($tables[$row['table']] ?? 0) + 1;
        }
        $this->assertSame(3, $tables['Autor']);
        $this->assertSame(3, $tables['CDU']);
        $this->assertSame(4, $tables['FondoAutor']);
    }

    /**
     * Copies, readers and loans come as attributes; types and rules as tables.
     */
    public function test_sections(): void {
        $export = $this->export();
        $items = iterator_to_array($export->items(), false);
        $this->assertCount(6, $items);
        $this->assertSame('100000L', $items[0]['CodigoEjemplar']);
        $this->assertCount(3, iterator_to_array($export->readers(), false));
        $this->assertCount(3, iterator_to_array($export->loans(), false));
        $this->assertSame(['1723' => 'Normal', '1724' => 'No prestable', '1726' => 'Video'], $export->item_types());
        $this->assertSame('Alumno', $export->reader_types()['2785']['name']);
        $this->assertSame('6', $export->reader_types()['2785']['VecesRenovacion']);
        $this->assertCount(3, $export->rules());
        $this->assertSame('2º PRI A', $export->groups()['10002']);
    }

    /**
     * Another XML is not an Abies export.
     */
    public function test_not_abies(): void {
        $file = tempnam(sys_get_temp_dir(), 'ayv');
        file_put_contents($file, '<?xml version="1.0"?><catalogue/>');
        try {
            $this->expectException(\InvalidArgumentException::class);
            new export_reader($file);
        } finally {
            unlink($file);
        }
    }
}
