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

namespace local_oksigeniaayvurenda\core\marc;

/**
 * Tests for MARCXML and the descriptions made from MARC21.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\marc\marcxml
 * @covers     \local_oksigeniaayvurenda\core\marc\record
 * @covers     \local_oksigeniaayvurenda\core\marc\describer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(marcxml::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(record::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(describer::class)]
final class marc_test extends \basic_testcase {
    /**
     * The made-up record of the fixtures.
     *
     * @return record
     */
    private function record(): record {
        $records = marcxml::read(file_get_contents(__DIR__ . '/../../fixtures/marc/record.xml'));
        $this->assertCount(1, $records);
        return $records[0];
    }

    /**
     * A record is read with its fields in order, and written and read back the same.
     */
    public function test_read_write(): void {
        $record = $this->record();
        $this->assertSame('01234cam a2200301 i 4500', $record->leader);
        $this->assertSame('ayv-0001', $record->control_value('001'));
        $this->assertSame('El faro dormido /', $record->first(['245'], 'a'));
        $this->assertSame(['Faros', 'Mar.', 'Faros'], $record->values(['650'], 'a'));
        $again = marcxml::read(marcxml::write([$record, $record]));
        $this->assertCount(2, $again);
        $this->assertEquals($record, $again[1]);
        $this->expectException(\InvalidArgumentException::class);
        marcxml::read('not xml');
    }

    /**
     * The description: the punctuation that closes a value goes, the ISBN loses its qualifier, the year comes from the
     * imprint, the language from the fixed field, authorities by kind without repeats.
     */
    public function test_describe(): void {
        $d = describer::describe($this->record());
        $this->assertSame('book', $d['record']['doctype']);
        $this->assertSame('El faro dormido', $d['record']['title']);
        $this->assertSame('una historia de mar', $d['record']['subtitle']);
        $this->assertSame('Irene Laurel ; ilustraciones de Bruno Olmo', $d['record']['responsibility']);
        $this->assertSame('9788490000014', $d['record']['isbn']);
        $this->assertSame('Primera edición', $d['record']['edition']);
        $this->assertSame('Villanube', $d['record']['pubplace']);
        $this->assertSame('2024', $d['record']['pubyear']);
        $this->assertSame('spa', $d['record']['lang']);
        $this->assertSame('96 p.', $d['record']['extent']);
        $this->assertSame(['Laurel Prado, Irene', 'Olmo, Bruno'], $d['authorities']['author']);
        $this->assertSame(['Editorial Brújula'], $d['authorities']['publisher']);
        $this->assertSame(['821.134.2-31'], $d['authorities']['class']);
        $this->assertSame(['Faros', 'Mar'], $d['authorities']['subject']);
        $this->assertSame(['Colección Linterna'], $d['authorities']['collection']);
        $this->assertSame(['A partir de 8 años'], $d['authorities']['audience']);
        $this->assertSame('udc', $d['extra']['classscheme']);
        $this->assertSame('12', $d['extra']['seriesnumber']);
        $this->assertSame(['9788490000014', '9788490000021'], $d['extra']['isbns']);
    }

    /**
     * Kinds of document from the leader.
     */
    public function test_doctype(): void {
        $this->assertSame('book', describer::doctype('00000nam a2200000 a 4500'));
        $this->assertSame('periodical', describer::doctype('00000nas a2200000 a 4500'));
        $this->assertSame('audio', describer::doctype('00000njm a2200000 a 4500'));
        $this->assertSame('video', describer::doctype('00000ngm a2200000 a 4500'));
        $this->assertSame('device', describer::doctype('00000nrm a2200000 a 4500'));
    }

    /**
     * Cleaning keeps the full stop of initials and abbreviations.
     */
    public function test_clean(): void {
        $this->assertSame('Tolkien, J. R. R.', describer::clean('Tolkien, J. R. R.'));
        $this->assertSame('120 p.', describer::clean('120 p. ;'));
        $this->assertSame('Madrid', describer::clean('Madrid :'));
        $this->assertSame('2015?', describer::clean('[2015?]'));
        $this->assertNull(describer::clean(' / '));
    }
}
