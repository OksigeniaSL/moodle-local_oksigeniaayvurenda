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
 * Tests for the SRU server of a library's catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sru
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sru::class)]
final class sru_test extends \advanced_testcase {
    /**
     * The small CQL it reads, and the diagnostics of what it does not.
     */
    public function test_parse(): void {
        $problem = null;
        $this->assertSame([['isbn', '9788478884452']], sru::parse('bath.isbn = 9788478884452', $problem));
        $this->assertSame(
            [['words', 'el faro'], ['words', 'Montes']],
            sru::parse('dc.title = "el faro" and dc.creator=Montes', $problem)
        );
        $this->assertSame([['words', 'faro']], sru::parse('faro', $problem));
        $this->assertSame([['words', 'faro dormido']], sru::parse('cql.anywhere all "faro dormido"', $problem));
        $this->assertNull($problem);
        sru::parse('faro or isla', $problem);
        $this->assertSame(37, $problem[0]);
        sru::parse('dc.publisher = Anaya', $problem);
        $this->assertSame(16, $problem[0]);
        sru::parse('dc.title < faro', $problem);
        $this->assertSame(19, $problem[0]);
        sru::parse('', $problem);
        $this->assertSame(7, $problem[0]);
        sru::parse('(faro)', $problem);
        $this->assertSame(10, $problem[0]);
    }

    /**
     * A search finds the books of this library by their ISBN or their words, in MARCXML or Dublin Core, a page at a
     * time; never another library's.
     */
    public function test_search(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $other = $gen->create_library(['entityid' => $library->entityid, 'slug' => 'aula']);
        $faro = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido', 'isbn' => '9788478884452',
            'searchtext' => 'el faro dormido montes ana']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $faro->id, 'callnumber1' => '82']);
        for ($i = 0; $i < 3; $i++) {
            $isla = $gen->create_record(['entityid' => $library->entityid, 'title' => 'La isla ' . $i,
                'searchtext' => 'la isla ' . $i]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $isla->id]);
        }
        $theirs = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro de otro',
            'searchtext' => 'el faro de otro']);
        $gen->create_item(['libraryid' => $other->id, 'recordid' => $theirs->id]);
        $ask = function (array $args) use ($library): \DOMXPath {
            $doc = new \DOMDocument();
            $this->assertTrue($doc->loadXML(sru::answer($library, $args)));
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('s', 'http://www.loc.gov/zing/srw/');
            $xpath->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
            $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $xpath->registerNamespace('d', 'http://www.loc.gov/zing/srw/diagnostic/');
            return $xpath;
        };

        $this->assertSame('1.2', $ask([])->evaluate('string(//s:explainResponse/s:version)'));
        $isbn = $ask(['operation' => 'searchRetrieve', 'version' => '1.2', 'query' => 'bath.isbn=978-84-7888-445-2']);
        $this->assertSame('1', $isbn->evaluate('string(//s:numberOfRecords)'));
        $title = $isbn->evaluate('string(//m:datafield[@tag="245"]/m:subfield[@code="a"])');
        $this->assertStringStartsWith('El faro dormido', $title);
        $this->assertSame(1, $isbn->query('//m:datafield[@tag="852"]')->length);
        // Words, another library's book left out.
        $this->assertSame('1', $ask(['operation' => 'searchRetrieve', 'query' => 'faro'])->evaluate('string(//s:numberOfRecords)'));
        $dc = $ask(['operation' => 'searchRetrieve', 'query' => 'dc.title=faro', 'recordSchema' => 'dc']);
        $this->assertSame('El faro dormido', $dc->evaluate('string(//dc:title)'));
        // A page at a time.
        $page = $ask(['operation' => 'searchRetrieve', 'query' => 'isla', 'maximumRecords' => '2']);
        $this->assertSame('3', $page->evaluate('string(//s:numberOfRecords)'));
        $this->assertSame(2, $page->query('//s:record')->length);
        $this->assertSame('3', $page->evaluate('string(//s:nextRecordPosition)'));
        $last = $ask(['operation' => 'searchRetrieve', 'query' => 'isla', 'maximumRecords' => '2', 'startRecord' => '3']);
        $this->assertSame(1, $last->query('//s:record')->length);
        $this->assertSame('3', $last->evaluate('string(//s:recordPosition)'));
        // What it cannot do, it says the protocol's way.
        $this->assertSame('info:srw/diagnostic/1/66', $ask(['operation' => 'searchRetrieve', 'query' => 'faro',
            'recordSchema' => 'mods'])->evaluate('string(//d:uri)'));
        $this->assertSame('info:srw/diagnostic/1/37', $ask(['operation' => 'searchRetrieve', 'query' => 'faro or isla'])
            ->evaluate('string(//d:uri)'));
        $this->assertSame('info:srw/diagnostic/1/4', $ask(['operation' => 'scan'])->evaluate('string(//d:uri)'));
        unset($DB);
    }
}
