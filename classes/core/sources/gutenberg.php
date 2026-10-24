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
 * Project Gutenberg's own OPDS catalogue: searching it and reading one book's entry.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\sources;

/**
 * Plain PHP, without Moodle: addresses and the reading of Atom feeds. A search lists books by number, title and
 * author; a book's own feed gives its languages, its rights statement, its EPUB and its cover.
 */
final class gutenberg {
    /** @var string Where the catalogue is. */
    public const BASE = 'https://www.gutenberg.org';

    /** @var string[] Two-letter languages and their ISO 639-2 form, as the catalogue keeps them. */
    private const LANGS = ['es' => 'spa', 'en' => 'eng', 'fr' => 'fra', 'de' => 'deu', 'it' => 'ita', 'pt' => 'por',
        'ca' => 'cat', 'gl' => 'glg', 'eu' => 'eus', 'la' => 'lat', 'nl' => 'nld', 'ru' => 'rus', 'zh' => 'zho',
        'ar' => 'ara', 'el' => 'ell', 'grc' => 'grc'];

    /**
     * The address of a search.
     *
     * @param string $query
     * @return string
     */
    public static function search_url(string $query): string {
        return self::BASE . '/ebooks/search.opds/?query=' . rawurlencode(trim($query));
    }

    /**
     * The address of a book's own feed.
     *
     * @param int $id
     * @return string
     */
    public static function book_url(int $id): string {
        return self::BASE . '/ebooks/' . $id . '.opds';
    }

    /**
     * The books of a search: number, title and the author or what the catalogue says under the title.
     *
     * @param string $xml
     * @return array[] id, title, byline.
     */
    public static function parse_search(string $xml): array {
        $feed = self::load($xml);
        if (!$feed) {
            return [];
        }
        $books = [];
        foreach ($feed->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'entry') as $entry) {
            $id = 0;
            foreach ($entry->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link') as $link) {
                if (preg_match('~^(?:https?://www\.gutenberg\.org)?/ebooks/(\d+)\.opds$~', $link->getAttribute('href'), $m)) {
                    $id = (int) $m[1];
                }
            }
            if (!$id) {
                continue;
            }
            $byline = self::text($entry, 'content');
            $books[] = ['id' => $id, 'title' => self::text($entry, 'title'),
                'byline' => preg_match('/^\d+ downloads?$/', $byline) ? '' : $byline];
        }
        return $books;
    }

    /**
     * One book: title, authors, language, rights statement, EPUB and cover.
     *
     * @param string $xml
     * @return array|null id, title, authors, lang, langs, rights, epub, cover, summary.
     */
    public static function parse_book(string $xml): ?array {
        $feed = self::load($xml);
        if (!$feed) {
            return null;
        }
        $entry = $feed->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'entry')->item(0);
        if (!$entry) {
            return null;
        }
        $id = 0;
        // The entry says «urn:gutenberg:10814:2», or the address of the book.
        if (preg_match('~(?:/ebooks/|urn:gutenberg:)(\d+)~', self::text($entry, 'id'), $m)) {
            $id = (int) $m[1];
        }
        $authors = [];
        foreach ($entry->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'author') as $author) {
            $name = trim((string) ($author->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'name')->item(0)?->textContent));
            if ($name !== '') {
                $authors[] = $name;
            }
        }
        $epub = '';
        $cover = '';
        foreach ($entry->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link') as $link) {
            $rel = $link->getAttribute('rel');
            $href = $link->getAttribute('href');
            $href = str_starts_with($href, '/') ? self::BASE . $href : $href;
            if ($rel === 'http://opds-spec.org/acquisition' && $link->getAttribute('type') === 'application/epub+zip') {
                // The edition with pictures, when there is one, is the one to keep.
                if ($epub === '' || str_contains($href, 'images') && !str_contains($href, 'noimages')) {
                    $epub = $href;
                }
            } else if ($rel === 'http://opds-spec.org/image') {
                $cover = $href;
            }
        }
        // A book may say several languages (a text and its notes): the first is the record's, all are kept.
        $langs = [];
        foreach ($entry->getElementsByTagNameNS('http://purl.org/dc/terms/', 'language') as $node) {
            $two = strtolower(trim($node->textContent));
            $three = self::LANGS[$two] ?? (strlen($two) === 3 ? $two : '');
            if ($three !== '') {
                $langs[] = $three;
            }
        }
        $lang = $langs[0] ?? '';
        return [
            'id' => $id,
            'title' => self::text($entry, 'title'),
            'authors' => $authors,
            'lang' => $lang,
            'langs' => $langs,
            'rights' => self::text($entry, 'rights'),
            'epub' => $epub,
            'cover' => $cover,
            'summary' => self::text($entry, 'summary'),
        ];
    }

    /**
     * The name of an author as the catalogue gives it («Bécquer, Gustavo Adolfo»), kept that way for the authority.
     *
     * @param string $name
     * @return string
     */
    public static function author(string $name): string {
        // Dates the catalogue sometimes adds («Andersen, H. C. (Hans Christian), 1805-1875») stay out.
        return trim(preg_replace('/,?\s*\d{3,4}\??-\d{0,4}\??\s*$/', '', $name));
    }

    /**
     * Reads a feed, or nothing if it is not one.
     *
     * @param string $xml
     * @return \DOMDocument|null
     */
    private static function load(string $xml): ?\DOMDocument {
        if (trim($xml) === '') {
            return null;
        }
        $feed = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $feed->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $ok ? $feed : null;
    }

    /**
     * The text of the first child of an entry with that Atom name.
     *
     * @param \DOMElement $entry
     * @param string $name
     * @return string
     */
    private static function text(\DOMElement $entry, string $name): string {
        foreach ($entry->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return trim(preg_replace('/\s+/u', ' ', $child->textContent));
            }
        }
        return '';
    }
}
