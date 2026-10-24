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
        $this->assertSame('noRecordsMatch', $error(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc',
            'from' => gmdate('Y-m-d', time() + 10 * DAYSECS)]));
        $this->assertSame('idDoesNotExist', $error(['verb' => 'GetRecord', 'identifier' => 'oai:nowhere:1',
            'metadataPrefix' => 'oai_dc']));
        $this->assertSame('noSetHierarchy', $error(['verb' => 'ListSets']));
        $this->assertSame('badResumptionToken', $error(['verb' => 'ListRecords', 'resumptionToken' => 'nonsense']));
    }
}
