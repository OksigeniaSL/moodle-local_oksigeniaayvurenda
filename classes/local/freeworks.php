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

/**
 * Free works: books in the public domain brought from Project Gutenberg into the catalogue, with their EPUB.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\sources\gutenberg;

/**
 * One question to the catalogue per search and one book at a time, when someone of the library asks: never in bulk.
 * A book brought becomes a record with its author, language and cover, and its EPUB in the public domain, for whom
 * the library chooses. Brought again, the same record is found by its number.
 */
final class freeworks {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Searches the catalogue.
     *
     * @param string $query
     * @return array[]|null id, title, byline; null if it did not answer.
     */
    public static function search(string $query): ?array {
        if (trim($query) === '') {
            return [];
        }
        $xml = lookup::fetch(gutenberg::search_url($query));
        return $xml === null ? null : gutenberg::parse_search($xml);
    }

    /**
     * Looks at one book before bringing it: what it is and what its rights say.
     *
     * @param int $id
     * @return array|null As gutenberg::parse_book() gives it.
     */
    public static function book(int $id): ?array {
        $xml = lookup::fetch(gutenberg::book_url($id));
        $book = $xml === null ? null : gutenberg::parse_book($xml);
        return $book && $book['epub'] !== '' ? $book : null;
    }

    /**
     * The record already brought from that number, if any.
     *
     * @param int $entityid
     * @param int $id
     * @return int
     */
    public static function brought(int $entityid, int $id): int {
        global $DB;
        return (int) $DB->get_field_select(self::T . 'record', 'id', 'entityid = :entityid AND ' .
            $DB->sql_like('extra', ':mark'), ['entityid' => $entityid, 'mark' => '%"gutenberg":' . $id . '}%'], IGNORE_MULTIPLE);
    }

    /**
     * Brings a book: its record, its EPUB in the public domain for whom the library chooses, and its cover.
     *
     * @param \stdClass $library
     * @param int $id The book's number in the catalogue.
     * @param array $audience
     * @param int $userid
     * @return int The record.
     */
    public static function bring(\stdClass $library, int $id, array $audience, int $userid): int {
        $book = self::book($id);
        if (!$book) {
            throw new \moodle_exception('freeworksnoanswer', 'local_oksigeniaayvurenda');
        }
        $epub = lookup::fetch($book['epub']);
        if ($epub === null || !str_starts_with($epub, "PK")) {
            throw new \moodle_exception('freeworksnoanswer', 'local_oksigeniaayvurenda');
        }
        $recordid = self::brought((int) $library->entityid, $id);
        if (!$recordid) {
            $recordid = cataloguer::create_record((int) $library->entityid, [
                'record' => ['doctype' => 'electronic', 'title' => $book['title'], 'lang' => $book['lang'],
                    'summary' => $book['summary'] !== '' ? $book['summary'] : null,
                    'notes' => 'Project Gutenberg · ' . $book['rights']],
                'authorities' => ['author' => array_map([gutenberg::class, 'author'], $book['authors'])],
                'extra' => ['languages' => $book['langs'], 'gutenberg' => $id],
            ], time());
            if ($book['cover'] !== '') {
                covers::fetch($recordid, $book['cover'], 'gutenberg');
            }
        }
        $path = make_request_directory() . '/libro.epub';
        file_put_contents($path, $epub);
        digital::add_file($library, $recordid, $path, 'gutenberg-' . $id . '.epub', ['title' => '', 'licence' => 'pd',
            'audience' => $audience, 'download' => 1], $userid);
        return $recordid;
    }
}
