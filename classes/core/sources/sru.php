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
     * @param string $schema The name it gives to MARCXML («MARC21-xml» at the Deutsche Nationalbibliothek).
     */
    public function __construct(
        /** @var string Address of the service. */
        private string $base,
        /** @var string CQL index of the ISBN. */
        private string $index = 'bath.isbn',
        /** @var string SRU version. */
        private string $version = '1.1',
        /** @var string The name it gives to MARCXML. */
        private string $schema = 'marcxml'
    ) {
    }

    /**
     * The address that asks for an ISBN.
     *
     * @param string $isbn Digits.
     * @param string $relation «=» with the digits alone; another («all» at the BnF), with the digits quoted.
     * @return string
     */
    public function url(string $isbn, string $relation = '='): string {
        $digits = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        $query = http_build_query([
            'version' => $this->version,
            'operation' => 'searchRetrieve',
            'query' => $relation === '=' ? $this->index . '=' . $digits : $this->index . ' ' . $relation . ' "' . $digits . '"',
            'maximumRecords' => 5,
            'recordSchema' => $this->schema,
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
            'recordSchema' => $this->schema,
        ], '', '&', PHP_QUERY_RFC3986);
        return rtrim($this->base, '?&') . (str_contains($this->base, '?') ? '&' : '?') . $query;
    }

    /**
     * The address that asks for books by the words of their title or their authors, wherever they are.
     *
     * @param string $index The CQL index that looks everywhere; empty for the catalogue's own (the words alone, quoted).
     * @param string $relation «all» where the catalogue takes it; «=» where it reads all the words that way.
     * @param string $words
     * @param int $max
     * @return string
     */
    public function url_words(string $index, string $relation, string $words, int $max = 8): string {
        // What CQL would read as something else goes: quotes, masks and the backslash.
        $clean = trim(preg_replace('/\s+/u', ' ', preg_replace('/["\\\\*?^]/u', ' ', $words)));
        $query = http_build_query([
            'version' => $this->version,
            'operation' => 'searchRetrieve',
            'query' => ($index === '' ? '' : $index . ' ' . $relation . ' ') . '"' . $clean . '"',
            'maximumRecords' => $max,
            'recordSchema' => $this->schema,
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
        return array_map(fn($record) => self::composed(describer::describe($record)), marcxml::read($xml));
    }

    /**
     * A description with its accents as one character each (a catalogue in MARC-8 sends «o» and its accent apart), and
     * without the marks around a leading article.
     *
     * @param array $description
     * @return array
     */
    private static function composed(array $description): array {
        if (class_exists(\Normalizer::class)) {
            array_walk_recursive($description, function (&$value) {
                if (is_string($value) && !\Normalizer::isNormalized($value)) {
                    $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
                }
                // The marks around a leading article that sorting skips («\u{98}Die\u{9C} Verwandlung») are not text, and a
                // character the bridge could not map comes written as «<U+00BA>»: it is that character.
                if (is_string($value)) {
                    $value = preg_replace('/[\x{0098}\x{009C}]/u', '', $value);
                    $value = preg_replace_callback('/<U\+([0-9A-F]{4,6})>/', fn($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $value);
                }
            });
        }
        return $description;
    }
}
