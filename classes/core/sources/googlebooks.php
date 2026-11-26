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
 * Google Books: the covers of the books it knows, by ISBN or by title and author.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\sources;

/**
 * Google Books, for covers: it knows many editions that Open Library does not. Without a key of its own a site shares
 * Google's common allowance, which is often spent; with one (free), it has its own. Builds the addresses and reads the
 * answer; the request itself is the caller's. Plain PHP, without Moodle.
 */
final class googlebooks {
    /** @var string Where it is asked. */
    private const API = 'https://www.googleapis.com/books/v1/volumes?';

    /**
     * The address that asks for the covers of an ISBN.
     *
     * @param string $isbn Digits.
     * @param string $key The site's key, or ''.
     * @return string
     */
    public static function isbn_url(string $isbn, string $key = ''): string {
        return self::address('isbn:' . $isbn, 2, $key);
    }

    /**
     * The address that asks for the covers of a title, and of an author if there is one.
     *
     * @param string $title
     * @param string $author
     * @param int $max
     * @param string $key
     * @return string
     */
    public static function search_url(string $title, string $author = '', int $max = 10, string $key = ''): string {
        $quoted = fn(string $words) => '"' . trim(str_replace('"', ' ', $words)) . '"';
        return self::address(
            'intitle:' . $quoted($title) . (trim($author) !== '' ? ' inauthor:' . $quoted($author) : ''),
            $max,
            $key
        );
    }

    /**
     * An address of the API.
     *
     * @param string $query
     * @param int $max
     * @param string $key
     * @return string
     */
    private static function address(string $query, int $max, string $key): string {
        $params = ['q' => $query, 'maxResults' => $max, 'printType' => 'books',
            'fields' => 'items(volumeInfo(title,authors,publisher,publishedDate,imageLinks))'];
        if ($key !== '') {
            $params['key'] = $key;
        }
        return self::API . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The covers in an answer, with what tells one edition from another.
     *
     * @param string $json
     * @return array[] url (of the picture, as big as it gives it), title, authors, publisher, year.
     */
    public static function covers(string $json): array {
        $data = json_decode($json, true);
        $found = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            $info = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : [];
            $links = is_array($info['imageLinks'] ?? null) ? $info['imageLinks'] : [];
            $url = (string) ($links['thumbnail'] ?? $links['smallThumbnail'] ?? '');
            if (!preg_match('#^https?://books\.google(usercontent)?\.[a-z.]+/#', $url)) {
                continue;
            }
            // Over https, without the curl of the page drawn on it.
            $url = preg_replace('#^http://#', 'https://', str_replace('&edge=curl', '', $url));
            $year = preg_match('/\d{4}/', (string) ($info['publishedDate'] ?? ''), $m) ? $m[0] : '';
            $found[] = ['url' => $url, 'title' => trim((string) ($info['title'] ?? '')),
                'authors' => implode(', ', array_filter(array_map('strval', (array) ($info['authors'] ?? [])))),
                'publisher' => trim((string) ($info['publisher'] ?? '')), 'year' => $year];
        }
        return $found;
    }

    /**
     * Whether an address is one of its pictures: what is chosen among the candidates is only fetched from here.
     *
     * @param string $url
     * @return bool
     */
    public static function is_picture(string $url): bool {
        return (bool) preg_match('#^https://books\.google(usercontent)?\.[a-z.]+/#', $url);
    }
}
