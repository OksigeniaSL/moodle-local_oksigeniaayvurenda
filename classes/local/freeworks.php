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
use local_oksigeniaayvurenda\core\sources\wikisource;

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
     * The language of the site's Wikisource.
     *
     * @return string
     */
    public static function wikisource_lang(): string {
        return substr(current_language(), 0, 2);
    }

    /**
     * Searches Wikisource: the works whose pages say those words.
     *
     * @param string $query
     * @param string $lang
     * @return array[]|null title and url; null if it did not answer.
     */
    public static function wikisource_search(string $query, string $lang): ?array {
        if (trim($query) === '') {
            return [];
        }
        $json = lookup::fetch(wikisource::search_url($lang, $query));
        if ($json === null) {
            return null;
        }
        $titles = wikisource::parse_search($json);
        if (!$titles) {
            return [];
        }
        $json = lookup::fetch(wikisource::works_url($lang, $titles));
        return $json === null ? null : wikisource::parse_works($json, $titles);
    }

    /**
     * One work of Wikisource, with its authors from Wikidata (or those of the work it is an edition of).
     *
     * @param string $title
     * @param string $lang
     * @return array|null title, url, item, authors.
     */
    public static function wikisource_work(string $title, string $lang): ?array {
        $json = lookup::fetch(wikisource::works_url($lang, [$title]));
        $work = $json === null ? null : (wikisource::parse_works($json, [$title])[0] ?? null);
        if (!$work) {
            return null;
        }
        $work['authors'] = [];
        if ($work['item'] !== '') {
            $json = lookup::fetch(wikisource::wikidata_url([$work['item']], $lang));
            $found = $json === null ? ['authors' => [], 'edition' => ''] : wikisource::parse_work_item($json, $work['item']);
            if (!$found['authors'] && $found['edition'] !== '') {
                $json = lookup::fetch(wikisource::wikidata_url([$found['edition']], $lang));
                $found = $json === null ? $found : wikisource::parse_work_item($json, $found['edition']);
            }
            if ($found['authors']) {
                $json = lookup::fetch(wikisource::wikidata_url($found['authors'], $lang));
                $names = $json === null ? [] : wikisource::parse_labels($json, $lang);
                $work['authors'] = array_values(array_filter(array_map(fn($a) => $names[$a] ?? '', $found['authors'])));
            }
        }
        return $work;
    }

    /**
     * The record already brought from that work of Wikisource, if any.
     *
     * @param int $entityid
     * @param string $url
     * @return int
     */
    public static function wikisource_brought(int $entityid, string $url): int {
        global $DB;
        return (int) $DB->get_field_select(
            self::T . 'record',
            'id',
            'entityid = :entityid AND ' .
            $DB->sql_like('extra', ':mark'),
            ['entityid' => $entityid, 'mark' => '%"wikisourceid":"' . sha1($url) . '"%'],
            IGNORE_MULTIPLE
        );
    }

    /**
     * Brings a work of Wikisource: its record, and its EPUB to read it here, for whom the library says. When its export
     * does not answer, a link to read it there; brought again later, the EPUB takes the link's place.
     *
     * @param \stdClass $library
     * @param string $title
     * @param string $lang
     * @param array $audience
     * @param int $userid
     * @return int The record.
     */
    public static function bring_wikisource(\stdClass $library, string $title, string $lang, array $audience, int $userid): int {
        $work = self::wikisource_work($title, $lang);
        if (!$work) {
            throw new \moodle_exception('freeworksnoanswerws', 'local_oksigeniaayvurenda');
        }
        global $DB;
        $recordid = self::wikisource_brought((int) $library->entityid, $work['url']);
        if (!$recordid) {
            $recordid = cataloguer::create_record((int) $library->entityid, [
                'record' => ['doctype' => 'electronic', 'title' => $work['title'], 'lang' => wikisource::lang3($lang),
                    'notes' => 'Wikisource'],
                'authorities' => ['author' => $work['authors']],
                'extra' => ['wikisource' => $work['url'], 'wikisourceid' => sha1($work['url'])],
            ], time());
        }
        $items = $DB->get_records(self::T . 'digital', ['recordid' => $recordid]);
        if (array_filter($items, fn($item) => $item->kind === 'file')) {
            return $recordid;
        }
        // A long work takes Wikisource a while to put together.
        \core_php_time_limit::raise(180);
        $epub = lookup::fetch(wikisource::epub_url($lang, $title), 120);
        if ($epub !== null && str_starts_with($epub, 'PK')) {
            $path = make_request_directory() . '/obra.epub';
            file_put_contents($path, $epub);
            digital::add_file(
                $library,
                $recordid,
                $path,
                'wikisource-' . substr(sha1($work['url']), 0, 12) . '.epub',
                ['title' => '', 'licence' => 'cc-by-sa', 'audience' => $audience, 'download' => 1],
                $userid
            );
            foreach ($items as $item) {
                if ($item->kind === 'link' && $item->url === $work['url']) {
                    digital::delete((int) $item->id, $library);
                }
            }
        } else if (!$items) {
            digital::add_link($library, $recordid, $work['url'], ['title' => '', 'licence' => 'cc-by-sa',
                'audience' => $audience, 'download' => 0], $userid);
        }
        return $recordid;
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
