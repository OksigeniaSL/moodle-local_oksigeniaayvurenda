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
 * The letters of old MARC files.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\marc\charset
 */
#[\PHPUnit\Framework\Attributes\CoversClass(charset::class)]
final class charset_test extends \basic_testcase {
    /**
     * Each set, read: a mark before its letter comes out as the letter with its accent.
     */
    public function test_to_utf8(): void {
        $text = "Garc\xE2ia M\xE2arquez, Pe\xE4na, M\xE8uller";
        $this->assertSame('García Márquez, Peña, Müller', charset::to_utf8($text, 'marc8'));
        $this->assertSame('Łódź ¿Æsir?', charset::to_utf8("\xA1\xE2od\xE2z \xC5\xA5sir?", 'marc8'));
        $this->assertSame('Élite façade', charset::to_utf8("\xC2Elite fa\xCBcade", 'iso5426'));
        $this->assertSame('Peña €', charset::to_utf8("Pe\xF1a \x80", 'cp1252'));
        $this->assertSame('Peña', charset::to_utf8('Peña', 'utf8'));
        // The way to another alphabet is taken out, and the words stay.
        $this->assertSame('Texto', charset::to_utf8("\x1B(BTexto", 'marc8'));
    }

    /**
     * Which set a file is in: what is valid UTF-8 is UTF-8, whatever the leader says; the rest, by how it writes its accents.
     */
    public function test_guess(): void {
        $this->assertSame('utf8', charset::guess('García Márquez'));
        $this->assertSame('utf8', charset::guess('Plain text'));
        $this->assertSame('marc8', charset::guess("Garc\xE2ia M\xE2arquez, Pe\xE4na"));
        $this->assertSame('cp1252', charset::guess("Garc\xEDa M\xE1rquez, Pe\xF1a"));
        $this->assertSame('iso5426', charset::guess("L'\xC2ecole d'\xC2et\xC2e, fa\xCBcade"));
        // German and Portuguese in Windows-1252, whose ä and ã look like MARC-8 marks.
        $this->assertSame('cp1252', charset::guess("M\xE4rchen aus den W\xE4ldern, B\xE4r, K\xE4the, M\xFCnchen"));
        $this->assertSame('cp1252', charset::guess("N\xE3o h\xE1 cora\xE7\xE3o sem irm\xE3o, S\xE3o Paulo"));
        $this->assertSame('marc8', charset::guess("Cien a\xE4nos de soledad, Garc\xE2ia M\xE2arquez, Espa\xE4na"));
    }

    /**
     * A whole record in MARC-8, with a blank position 9, and the same with it read by hand as something else.
     */
    public function test_a_record(): void {
        $record = new record();
        $record->data('245', '1', '0', [['a', "Cien a\xE4nos de soledad"]]);
        $raw = iso2709::write($record);
        $raw[9] = ' ';
        $this->assertSame('Cien años de soledad', iso2709::read($raw)[0]->first(['245'], 'a'));
        $this->assertSame('Cien años de soledad', iso2709::read($raw, 'marc8')[0]->first(['245'], 'a'));
        $this->assertStringNotContainsString('años', (string) iso2709::read($raw, 'cp1252')[0]->first(['245'], 'a'));
    }

    /**
     * UNIMARC says its character set in field 100, positions 26 to 29: «0103» after a real 100 is ISO 5426.
     */
    public function test_unimarc_says_it(): void {
        $this->assertSame('iso5426', charset::guess("\x1fa20051012d2005    u  y0spay0103    ba\x1e Caf\xC2e"));
    }
}
