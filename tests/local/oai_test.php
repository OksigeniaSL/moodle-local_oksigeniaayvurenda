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
 * Tests for the OAI-PMH repository of the public window.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\oai
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oai::class)]
final class oai_test extends \advanced_testcase {
    /**
     * The centre's own publications meant for the public are offered, the public-domain works it brought and other
     * people's are not; the verbs answer and the errors are the protocol's.
     */
    public function test_repository(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $own = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Revista del centro, número 1', 'lang' => 'spa']);
        $classic = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Un clásico']);
        $path = make_request_directory() . '/revista.pdf';
        file_put_contents($path, '%PDF-1.4 made-up');
        digital::add_file($library, (int) $own->id, $path, 'revista.pdf', ['title' => '', 'licence' => 'cc-by',
            'audience' => ['public']], 0);
        digital::add_file($library, (int) $classic->id, $path, 'clasico.pdf', ['title' => '', 'licence' => 'pd',
            'audience' => ['public']], 0);
        window::save($library, ['open' => 1, 'voices' => 1]);
        $ask = function (array $args) use ($library): \DOMXPath {
            $doc = new \DOMDocument();
            $this->assertTrue($doc->loadXML(oai::answer($library, $args)));
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('o', 'http://www.openarchives.org/OAI/2.0/');
            $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            return $xpath;
        };

        $this->assertSame('2.0', $ask(['verb' => 'Identify'])->evaluate('string(//o:protocolVersion)'));
        $this->assertSame(oai::base($library), $ask(['verb' => 'Identify'])->evaluate('string(//o:baseURL)'));
        $records = $ask(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc']);
        $this->assertSame(1, $records->query('//o:record')->length);
        $this->assertSame('Revista del centro, número 1', $records->evaluate('string(//dc:title)'));
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $records->evaluate('string(//dc:rights)'));
        $this->assertSame(1, $ask(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc'])->query('//o:header')->length);
        $id = oai::identifier($library, (int) $own->id);
        $this->assertSame($id, $ask(['verb' => 'GetRecord', 'identifier' => $id, 'metadataPrefix' => 'oai_dc'])
            ->evaluate('string(//o:header/o:identifier)'));

        $error = fn(array $args) => $ask($args)->evaluate('string(//o:error/@code)');
        $this->assertSame('badVerb', $error(['verb' => 'Nonsense']));
        $this->assertSame('badArgument', $error(['verb' => 'ListRecords']));
        $this->assertSame('badArgument', $error(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc', 'from' => 'yesterday']));
        $this->assertSame('cannotDisseminateFormat', $error(['verb' => 'ListRecords', 'metadataPrefix' => 'marc']));
        // MARC 21 for library systems: the record as the copy writes it, with where it is read.
        $formats = $ask(['verb' => 'ListMetadataFormats']);
        $this->assertSame(['oai_dc', 'marc21'], array_map(
            fn($n) => $n->textContent,
            iterator_to_array($formats->query('//o:metadataPrefix'))
        ));
        $marc = $ask(['verb' => 'GetRecord', 'identifier' => $id, 'metadataPrefix' => 'marc21']);
        $marc->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
        $title = '//m:datafield[@tag="245"]/m:subfield[@code="a"]';
        // With the punctuation that MARC 21 closes a title with.
        $this->assertSame('Revista del centro, número 1.', $marc->evaluate('string(' . $title . ')'));
        $this->assertStringContainsString('/read.php', $marc->evaluate('string(//m:datafield[@tag="856"]/m:subfield[@code="u"])'));
        $this->assertSame(1, $ask(['verb' => 'ListRecords', 'metadataPrefix' => 'marc21'])->query('//o:record')->length);
        $this->assertSame('noRecordsMatch', $error(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc',
            'from' => gmdate('Y-m-d', time() + 10 * DAYSECS)]));
        $this->assertSame('idDoesNotExist', $error(['verb' => 'GetRecord', 'identifier' => 'oai:nowhere:1',
            'metadataPrefix' => 'oai_dc']));
        // Only the digital works while the window does not show the catalogue.
        $this->assertSame(['digital'], array_map(
            fn($n) => $n->textContent,
            iterator_to_array($ask(['verb' => 'ListSets'])->query('//o:setSpec'))
        ));
        $this->assertSame('badResumptionToken', $error(['verb' => 'ListRecords', 'resumptionToken' => 'nonsense']));
    }

    /**
     * With the catalogue shown to anyone, every book with a copy in the library, as the set «catalogue», for a union
     * catalogue to gather it; in MARC 21 with its copies here (no codes); and in pages that go on by date and number,
     * whatever changes in between (IOB-M2).
     */
    public function test_catalogue(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $other = $gen->create_library(['entityid' => $library->entityid, 'slug' => 'aula']);
        $then = time() - DAYSECS;
        for ($i = 0; $i < 101; $i++) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro ' . $i,
                'isbn' => '978000000' . sprintf('%04d', $i), 'timemodified' => $then + $i]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => '82',
                'barcode' => 'B' . $i]);
        }
        $elsewhere = $gen->create_record(['entityid' => $library->entityid, 'title' => 'De otra biblioteca']);
        $gen->create_item(['libraryid' => $other->id, 'recordid' => $elsewhere->id]);
        $gone = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Dado de baja']);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $gone->id, 'status' => 'withdrawn']);
        $ask = function (array $args) use ($library): \DOMXPath {
            $doc = new \DOMDocument();
            $this->assertTrue($doc->loadXML(oai::answer($library, $args)));
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('o', 'http://www.openarchives.org/OAI/2.0/');
            $xpath->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
            return $xpath;
        };
        window::save($library, ['open' => 1]);
        $this->assertSame('noRecordsMatch', $ask(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc', 'set' => 'catalogue'])
            ->evaluate('string(//o:error/@code)'), 'a window that does not show its catalogue');
        window::save($library, ['open' => 1, 'catalogue' => 1]);
        $this->assertSame(['catalogue', 'digital'], array_map(
            fn($n) => $n->textContent,
            iterator_to_array($ask(['verb' => 'ListSets'])->query('//o:setSpec'))
        ));
        $first = $ask(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc', 'set' => 'catalogue']);
        $this->assertSame(oai::PAGE, $first->query('//o:header')->length);
        $this->assertSame('101', $first->evaluate('string(//o:resumptionToken/@completeListSize)'));
        $this->assertSame('catalogue', $first->evaluate('string(//o:header[1]/o:setSpec)'));
        $token = $first->evaluate('string(//o:resumptionToken)');
        // A change in between does not move the list: the next page goes on where the last one stopped.
        $DB->set_field('local_oksigeniaayvurenda_record', 'timemodified', time(), ['title' => 'Libro 0']);
        $next = $ask(['verb' => 'ListIdentifiers', 'resumptionToken' => $token]);
        $this->assertSame(2, $next->query('//o:header')->length, 'the last one, and Libro 0 changed now');
        $this->assertSame('', $next->evaluate('string(//o:resumptionToken)'));
        // The whole list is counted on the first page and carried in the token, not counted again on every page.
        $this->assertStringEndsWith('-101', $token);
        $this->assertSame('101', $next->evaluate('string(//o:resumptionToken/@completeListSize)'));
        // A record that gets a digital form, or a copy more, changes: a harvester asking since a date sees it.
        $five = (int) $DB->get_field('local_oksigeniaayvurenda_record', 'id', ['title' => 'Libro 5']);
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $DB->set_field('local_oksigeniaayvurenda_record', 'timemodified', $then, ['id' => $five]);
        digital::add_link($library, $five, 'https://example.org/libro-5', ['title' => '', 'licence' => 'cc-by',
            'audience' => ['public']], 0);
        $this->assertGreaterThan($then, (int) $DB->get_field('local_oksigeniaayvurenda_record', 'timemodified', ['id' => $five]));
        $changed = $ask(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc', 'from' => $since]);
        $this->assertContains(oai::identifier($library, $five), array_map(
            fn($n) => $n->textContent,
            iterator_to_array($changed->query('//o:header/o:identifier'))
        ));
        // A control character in a title (from a spreadsheet, an old MARC) does not make the answer unreadable.
        $odd = $gen->create_record(['entityid' => $library->entityid, 'title' => "Libro \x1Bcon \x0Bcontrol"]);
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $odd->id]);
        foreach (['oai_dc', 'marc21'] as $prefix) {
            $ask(['verb' => 'GetRecord', 'identifier' => oai::identifier($library, (int) $odd->id), 'metadataPrefix' => $prefix]);
        }
        // In MARC 21, with its copy here and no code.
        $id = oai::identifier($library, (int) $DB->get_field('local_oksigeniaayvurenda_record', 'id', ['title' => 'Libro 5']));
        $marc = $ask(['verb' => 'GetRecord', 'identifier' => $id, 'metadataPrefix' => 'marc21']);
        $this->assertSame(1, $marc->query('//m:datafield[@tag="852"]')->length);
        $this->assertStringNotContainsString('B5', $marc->evaluate('string(//m:datafield[@tag="852"])'));
        $dc = $ask(['verb' => 'GetRecord', 'identifier' => $id, 'metadataPrefix' => 'oai_dc']);
        $dc->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $this->assertSame('urn:isbn:9780000000005', $dc->evaluate('string(//dc:identifier[1])'));
        $this->assertStringContainsString('public.php', $dc->evaluate('string(//dc:identifier[2])'));
        // Neither another library's book nor one withdrawn.
        foreach ([$elsewhere, $gone] as $out) {
            $this->assertSame('idDoesNotExist', $ask(['verb' => 'GetRecord', 'identifier' => oai::identifier(
                $library,
                (int) $out->id
            ), 'metadataPrefix' => 'oai_dc'])->evaluate('string(//o:error/@code)'));
        }
    }
}
