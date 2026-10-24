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
 * Tests for reading Project Gutenberg's catalogue, on made-up feeds with its shape.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\sources\gutenberg
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gutenberg::class)]
final class gutenberg_test extends \basic_testcase {
    /**
     * A search gives books by number, without the list of authors, and without «downloads» as a byline.
     */
    public function test_search(): void {
        $books = gutenberg::parse_search(file_get_contents(__DIR__ . '/../fixtures/gutenberg-search.xml'));
        $this->assertSame([
            ['id' => 99001, 'title' => 'El faro dormido (Spanish)', 'byline' => 'Autora Inventada'],
            ['id' => 99002, 'title' => 'Cuentos inventados', 'byline' => ''],
        ], $books);
        $this->assertSame([], gutenberg::parse_search('not a feed'));
        $this->assertSame('https://www.gutenberg.org/ebooks/search.opds/?query=el%20faro', gutenberg::search_url(' el faro '));
    }

    /**
     * A book gives its number, its languages, its rights, the EPUB with pictures and its cover.
     */
    public function test_book(): void {
        $book = gutenberg::parse_book(file_get_contents(__DIR__ . '/../fixtures/gutenberg-book.xml'));
        $this->assertSame(99001, $book['id']);
        $this->assertSame(['Inventada, Autora, 1801-1870'], $book['authors']);
        $this->assertSame(['spa', 'eng'], $book['langs']);
        $this->assertSame('spa', $book['lang']);
        $this->assertSame('Public domain in the USA.', $book['rights']);
        $this->assertSame('https://www.gutenberg.org/ebooks/99001.epub3.images', $book['epub']);
        $this->assertSame('https://www.gutenberg.org/cache/epub/99001/pg99001.cover.medium.jpg', $book['cover']);
        $this->assertSame('Inventada, Autora', gutenberg::author('Inventada, Autora, 1801-1870'));
        $this->assertNull(gutenberg::parse_book(''));
    }
}
