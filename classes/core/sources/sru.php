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
 * A catalogue that answers by SRU.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\sources;

use local_oksigeniaayvurenda\core\marc\describer;
use local_oksigeniaayvurenda\core\marc\marcxml;

/**
 * SRU (Search/Retrieve via URL): a catalogue searched over HTTP with a CQL query, answering MARCXML. The Library of
 * Congress serves it without a key; any other catalogue that speaks it is configured with its address, version and
 * index of ISBN. Builds the address and reads the answer; the request itself is the caller's. Plain PHP, without
 * Moodle.
 */
final class sru {
    /**
     * A catalogue.
     *
     * @param string $base Address of the service, up to the question mark.
     * @param string $index CQL index of the ISBN.
     * @param string $version SRU version.
     */
    public function __construct(
        /** @var string Address of the service. */
        private string $base,
        /** @var string CQL index of the ISBN. */
        private string $index = 'bath.isbn',
        /** @var string SRU version. */
        private string $version = '1.1'
    ) {
    }

    /**
     * The address that asks for an ISBN.
     *
     * @param string $isbn Digits.
     * @return string
     */
    public function url(string $isbn): string {
        $query = http_build_query([
            'version' => $this->version,
            'operation' => 'searchRetrieve',
            'query' => $this->index . '=' . preg_replace('/[^0-9X]/', '', strtoupper($isbn)),
            'maximumRecords' => 5,
            'recordSchema' => 'marcxml',
        ], '', '&', PHP_QUERY_RFC3986);
        return rtrim($this->base, '?&') . (str_contains($this->base, '?') ? '&' : '?') . $query;
    }

    /**
     * The address that asks for any of several ways of writing one number, in a single question.
     *
     * @param string[] $terms
     * @return string
     */
    public function url_any(array $terms): string {
        $query = http_build_query([
            'version' => $this->version,
            'operation' => 'searchRetrieve',
            'query' => implode(' or ', array_map(fn($t) => $this->index . '="' . preg_replace('/[^0-9X-]/', '', $t) . '"', $terms)),
            'maximumRecords' => 5,
            'recordSchema' => 'marcxml',
        ], '', '&', PHP_QUERY_RFC3986);
        return rtrim($this->base, '?&') . (str_contains($this->base, '?') ? '&' : '?') . $query;
    }

    /**
     * The descriptions in an answer.
     *
     * @param string $xml
     * @return array[] Descriptions, as describer gives them.
     * @throws \RuntimeException When the catalogue answers with a diagnostic.
     */
    public static function descriptions(string $xml): array {
        if (preg_match('/<(?:\w+:)?diagnostic\b.*?<(?:\w+:)?message>([^<]*)</s', $xml, $matches)) {
            throw new \RuntimeException('SRU: ' . trim($matches[1]));
        }
        return array_map([describer::class, 'describe'], marcxml::read($xml));
    }
}
