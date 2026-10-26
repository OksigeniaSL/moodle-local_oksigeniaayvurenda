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

use local_oksigeniaayvurenda\core\export\dublincore;
use local_oksigeniaayvurenda\core\export\ris;

/**
 * Tests for the records of the catalogue in RIS and Dublin Core.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\export\ris
 * @covers     \local_oksigeniaayvurenda\core\export\dublincore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ris::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dublincore::class)]
final class export_test extends \basic_testcase {
    /** @var array A made-up book. */
    private const RECORD = ['doctype' => 'book', 'title' => 'El faro dormido', 'subtitle' => 'una historia del sur',
        'isbn' => '9788490000014', 'edition' => '2.ª ed.', 'pubplace' => 'Madrid', 'pubyear' => '2007', 'lang' => 'spa',
        'extent' => '120 p.', 'summary' => "Un faro que\n<b>duerme</b>."];

    /** @var array Its authorities. */
    private const AUTHORITIES = ['author' => ['Lago Brisa, Hugo', 'Montes, Ana'], 'publisher' => ['Editorial Inventada'],
        'subject' => ['Faros', 'Mar'], 'class' => ['821.134.2-31'], 'collection' => ['Los misterios del faro']];

    /**
     * A book in RIS: one line a field, authors one each, the summary without marks, the shelf where it is.
     */
    public function test_ris(): void {
        $this->assertSame("TY  - BOOK\r\nTI  - El faro dormido: una historia del sur\r\nAU  - Lago Brisa, Hugo\r\n" .
            "AU  - Montes, Ana\r\nPY  - 2007\r\nET  - 2.ª ed.\r\nCY  - Madrid\r\nSN  - 9788490000014\r\nLA  - spa\r\n" .
            "PB  - Editorial Inventada\r\nT2  - Los misterios del faro\r\nKW  - Faros\r\nKW  - Mar\r\n" .
            "AB  - Un faro que duerme.\r\nCN  - N LAG far\r\nER  - \r\n", ris::entry(self::RECORD, self::AUTHORITIES, 'N LAG far'));
        $this->assertSame('', ris::entry(['doctype' => 'device', 'title' => 'Tableta'] + self::RECORD, []));
        $this->assertStringStartsWith("TY  - VIDEO\r\n", ris::entry(['doctype' => 'video'] + self::RECORD, []));
    }

    /**
     * A book in Dublin Core, as the oai_dc record, which validates against its schema.
     */
    public function test_dublincore(): void {
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML(dublincore::element(self::RECORD, self::AUTHORITIES)));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('dc', dublincore::DC);
        $value = fn(string $q) => array_map(fn($n) => $n->textContent, iterator_to_array($xpath->query($q)));
        $this->assertSame(['El faro dormido: una historia del sur'], $value('//dc:title'));
        $this->assertSame(['Lago Brisa, Hugo', 'Montes, Ana'], $value('//dc:creator'));
        $this->assertSame(['Faros', 'Mar', '821.134.2-31'], $value('//dc:subject'));
        $this->assertSame(['urn:isbn:9788490000014'], $value('//dc:identifier'));
        $this->assertSame(['Text'], $value('//dc:type'));
        $this->assertSame(['Un faro que duerme.'], $value('//dc:description'));
    }
}
