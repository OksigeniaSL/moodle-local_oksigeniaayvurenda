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
 * Links that open from home, and from the places a teacher works.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * What a subscribed resource needs to open from outside the building: its permanent address, through the prefix of the
 * institution's proxy, or a question to its link resolver. Part of the core: plain PHP, with no call to Moodle.
 */
final class links {
    /**
     * A DOI, in any of the ways people paste it, as its permanent address.
     *
     * @param string $text «10.1234/abc», «doi:10.1234/abc» or «https://doi.org/10.1234/abc».
     * @return string|null
     */
    public static function doi(string $text): ?string {
        if (!preg_match('~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)?(10\.\d{4,9}/\S+)$~i', trim($text), $found)) {
            return null;
        }
        return 'https://doi.org/' . $found[1];
    }

    /**
     * Whether an address of the institution's (a proxy's prefix, a resolver) can be used: with https, and nothing odd.
     *
     * @param string $address
     * @return bool
     */
    public static function usable(string $address): bool {
        $parts = parse_url(trim($address));
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !empty($parts['host'])
            && !preg_match('/[\s<>"\']/', $address);
    }

    /**
     * An address through a proxy's prefix. One that ends in «qurl=» takes the address encoded; «url=», as it is.
     *
     * @param string $url
     * @param string $prefix Empty for none.
     * @return string
     */
    public static function through(string $url, string $prefix): string {
        if ($prefix === '' || strpos($url, $prefix) === 0) {
            return $url;
        }
        return $prefix . (preg_match('/qurl=$/i', $prefix) ? rawurlencode($url) : $url);
    }

    /**
     * A question to a link resolver about a book (OpenURL 1.0).
     *
     * @param string $resolver Empty for none.
     * @param string $isbn
     * @param string $title
     * @param string $author
     * @return string|null
     */
    public static function openurl(string $resolver, string $isbn, string $title, string $author = ''): ?string {
        if ($resolver === '' || ($isbn === '' && $title === '')) {
            return null;
        }
        $query = array_filter(['ctx_ver' => 'Z39.88-2004', 'rft_val_fmt' => 'info:ofi/fmt:kev:mtx:book', 'rft.isbn' => $isbn,
            'rft.btitle' => $title, 'rft.au' => $author], fn($value) => $value !== '');
        return $resolver . (strpos($resolver, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Where to share an address with a class in Google Classroom. Google documents only the address for this link.
     *
     * @param string $url
     * @return string
     */
    public static function classroom(string $url): string {
        return 'https://classroom.google.com/share?' . http_build_query(['url' => $url], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Where to share an address with a team in Microsoft Teams, as Microsoft's own button builds it, without its script.
     *
     * @param string $url
     * @param string $title
     * @return string
     */
    public static function teams(string $url, string $title): string {
        $query = ['href' => $url, 'msgText' => mb_substr($title, 0, 200)];
        return 'https://teams.microsoft.com/share?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
