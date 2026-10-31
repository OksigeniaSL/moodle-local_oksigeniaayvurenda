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
 * Tests for bringing free works, with a made-up Project Gutenberg that answers without the network.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\freeworks
 */
#[\PHPUnit\Framework\Attributes\CoversClass(freeworks::class)]
final class freeworks_test extends \advanced_testcase {
    /**
     * Forget the made-up catalogue after each test.
     */
    protected function tearDown(): void {
        lookup::$fetcher = null;
        parent::tearDown();
    }

    /**
     * A book brought is a record with its author and language and an EPUB in the public domain for whom was chosen;
     * brought again, it is the same record; when the catalogue does not answer, nothing is made.
     */
    public function test_bring(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $fixtures = __DIR__ . '/../fixtures/';
        lookup::$fetcher = function (string $url) use ($fixtures) {
            if (str_contains($url, 'search.opds')) {
                return file_get_contents($fixtures . 'gutenberg-search.xml');
            }
            if (str_ends_with($url, '/99001.opds')) {
                return file_get_contents($fixtures . 'gutenberg-book.xml');
            }
            if (str_contains($url, '.epub')) {
                return "PK\x03\x04 made-up book";
            }
            return null;
        };
        $this->assertCount(2, freeworks::search('faro'));
        $this->assertSame(0, freeworks::brought((int) $library->entityid, 99001));

        $recordid = freeworks::bring($library, 99001, ['students', 'public'], 0);
        $record = $DB->get_record('local_oksigeniaayvurenda_record', ['id' => $recordid]);
        $this->assertSame(['El faro dormido', 'spa', 'electronic'], [$record->title, $record->lang, $record->doctype]);
        $this->assertSame(['Inventada, Autora'], array_values($DB->get_fieldset_sql(
            'SELECT a.label FROM {local_oksigeniaayvurenda_authority} a
               JOIN {local_oksigeniaayvurenda_recauth} ra ON ra.authorityid = a.id
              WHERE ra.recordid = ?',
            [$recordid]
        )));
        $items = digital::of_record($recordid, $library, ['who' => 'desk', 'stage' => null, 'userid' => 0, 'readerid' => 0]);
        $this->assertCount(1, $items);
        $item = reset($items);
        $this->assertSame(['pd', 'students,public', 'epub'], [$item->licence, $item->audience, $item->format]);
        $this->assertSame($recordid, freeworks::brought((int) $library->entityid, 99001));

        $this->assertSame($recordid, freeworks::bring($library, 99001, ['students'], 0));
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_record', ['entityid' => $library->entityid,
            'title' => 'El faro dormido']));

        lookup::$fetcher = fn(string $url) => null;
        $this->assertNull(freeworks::search('faro'));
        $this->expectException(\moodle_exception::class);
        freeworks::bring($library, 99001, ['students'], 0);
    }

    /**
     * Bringing a work of Wikisource: the record with its author from Wikidata and a link to read it, once.
     */
    public function test_bring_wikisource(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $fixtures = __DIR__ . '/../fixtures/';
        $asked = [];
        lookup::$fetcher = function (string $url) use ($fixtures, &$asked) {
            $asked[] = $url;
            if (str_contains($url, 'list=search')) {
                return file_get_contents($fixtures . 'wikisource-search.json');
            }
            if (str_contains($url, 'wikisource.org/w/api.php')) {
                return file_get_contents($fixtures . 'wikisource-roots.json');
            }
            if (str_contains($url, 'ids=Q131318')) {
                return file_get_contents($fixtures . 'wikisource-authors.json');
            }
            return file_get_contents($fixtures . 'wikisource-works.json');
        };
        $found = freeworks::wikisource_search('Platero', 'es');
        $this->assertSame('Platero y yo', $found[0]['title']);
        $recordid = freeworks::bring_wikisource($library, 'Platero y yo', 'es', ['students'], 2);
        $record = $DB->get_record('local_oksigeniaayvurenda_record', ['id' => $recordid], '*', MUST_EXIST);
        $this->assertSame('Platero y yo', $record->title);
        $this->assertSame('spa', $record->lang);
        $this->assertSame(1, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_oksigeniaayvurenda_recauth} ra
            JOIN {local_oksigeniaayvurenda_authority} a ON a.id = ra.authorityid WHERE ra.recordid = ? AND a.label = ?",
            [$recordid, 'Juan Ramón Jiménez']
        ));
        $link = $DB->get_record('local_oksigeniaayvurenda_digital', ['recordid' => $recordid], '*', MUST_EXIST);
        $this->assertSame('https://es.wikisource.org/wiki/Platero_y_yo', $link->url);
        $this->assertSame('cc-by-sa', $link->licence);
        $this->assertSame($recordid, freeworks::bring_wikisource($library, 'Platero y yo', 'es', ['students'], 2));
        $this->assertSame(1, $DB->count_records('local_oksigeniaayvurenda_digital', ['recordid' => $recordid]));
    }

    /**
     * A work of Wikisource comes with its EPUB, to read it in the library; one that came as a link when the export did
     * not answer gets its EPUB when brought again, in the link's place.
     */
    public function test_bring_wikisource_epub(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $fixtures = __DIR__ . '/../fixtures/';
        $zip = make_request_directory() . '/obra.epub';
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('mimetype', 'application/epub+zip');
        $archive->close();
        $exported = false;
        $asked = [];
        lookup::$fetcher = function (string $url) use ($fixtures, $zip, &$exported, &$asked) {
            $asked[] = $url;
            if (str_contains($url, 'ws-export.wmcloud.org')) {
                return $exported ? file_get_contents($zip) : null;
            }
            if (str_contains($url, 'wikisource.org/w/api.php')) {
                return file_get_contents($fixtures . 'wikisource-roots.json');
            }
            if (str_contains($url, 'ids=Q131318')) {
                return file_get_contents($fixtures . 'wikisource-authors.json');
            }
            return file_get_contents($fixtures . 'wikisource-works.json');
        };
        $recordid = freeworks::bring_wikisource($library, 'Platero y yo', 'es', ['students'], 2);
        $this->assertContains('https://ws-export.wmcloud.org/?format=epub-3&lang=es&page=Platero_y_yo', $asked);
        $this->assertSame(['link'], array_values($DB->get_fieldset_select(
            'local_oksigeniaayvurenda_digital',
            'kind',
            'recordid = ?',
            [$recordid]
        )));
        $exported = true;
        $this->assertSame($recordid, freeworks::bring_wikisource($library, 'Platero y yo', 'es', ['students'], 2));
        $items = array_values($DB->get_records('local_oksigeniaayvurenda_digital', ['recordid' => $recordid]));
        $this->assertCount(1, $items);
        $this->assertSame(['file', 'epub', 'cc-by-sa'], [$items[0]->kind, $items[0]->format, $items[0]->licence]);
        $this->assertNotNull(digital::file($items[0]));
        // Once it has its EPUB, it is not asked for again.
        $asked = [];
        freeworks::bring_wikisource($library, 'Platero y yo', 'es', ['students'], 2);
        $this->assertEmpty(array_filter($asked, fn($url) => str_contains($url, 'ws-export')));
    }
}
