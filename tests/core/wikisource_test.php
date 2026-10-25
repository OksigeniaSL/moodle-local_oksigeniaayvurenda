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
 * Tests for reading Wikisource and Wikidata, with answers saved from them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\sources\wikisource
 */
#[\PHPUnit\Framework\Attributes\CoversClass(wikisource::class)]
final class wikisource_test extends \basic_testcase {
    /**
     * Chapters count as their work; the works come with their address and item; the author from Wikidata.
     */
    public function test_parse(): void {
        $dir = __DIR__ . '/../fixtures/';
        $this->assertSame('https://es.wikisource.org', wikisource::base('es'));
        $this->assertSame('https://wikisource.org', wikisource::base('xx'));
        $this->assertSame('spa', wikisource::lang3('es'));
        $this->assertStringContainsString('srsearch=Platero', wikisource::search_url('es', 'Platero'));

        $titles = wikisource::parse_search(file_get_contents($dir . 'wikisource-search.json'));
        $this->assertSame(['Platero y yo', 'El Lazarillo de Tormes'], $titles);
        $works = wikisource::parse_works(file_get_contents($dir . 'wikisource-roots.json'), $titles);
        $this->assertSame(['Platero y yo', 'El Lazarillo de Tormes'], array_column($works, 'title'));
        $this->assertSame('https://es.wikisource.org/wiki/Platero_y_yo', $works[0]['url']);
        $this->assertSame('Q99237826', $works[0]['item']);

        $platero = wikisource::parse_work_item(file_get_contents($dir . 'wikisource-works.json'), 'Q99237826');
        $this->assertSame(['Q131318'], $platero['authors']);
        $lazarillo = wikisource::parse_work_item(file_get_contents($dir . 'wikisource-works.json'), 'Q121742735');
        $this->assertSame([], $lazarillo['authors']);
        $this->assertSame('Q770895', $lazarillo['edition']);
        $names = wikisource::parse_labels(file_get_contents($dir . 'wikisource-authors.json'), 'es');
        $this->assertSame(['Q131318' => 'Juan Ramón Jiménez'], $names);
    }
}
