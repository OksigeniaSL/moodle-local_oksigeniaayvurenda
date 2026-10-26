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
 * A record of the catalogue in simple Dublin Core.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\export;

/**
 * Simple Dublin Core, the fifteen elements every catalogue and repository reads, as the oai_dc record of OAI-PMH, so
 * that each one validates against its schema. The ISBN goes as a URN. Plain PHP, without Moodle.
 */
final class dublincore {
    /** @var string The namespace of the oai_dc record. */
    public const OAI_DC = 'http://www.openarchives.org/OAI/2.0/oai_dc/';

    /** @var string The namespace of the elements. */
    public const DC = 'http://purl.org/dc/elements/1.1/';

    /** @var array<string, string> The DCMI type of each kind of document. */
    private const TYPES = ['book' => 'Text', 'periodical' => 'Text', 'audio' => 'Sound', 'video' => 'MovingImage',
        'electronic' => 'Text', 'device' => 'PhysicalObject', 'other' => 'Text'];

    /**
     * The oai_dc record of a record, as XML without a declaration, to go inside a collection.
     *
     * @param array $record The fields of the record (doctype, title, subtitle, isbn, edition, pubyear, lang, extent,
     *     summary).
     * @param array $authorities Kind => labels, in order (author, publisher, class, subject, collection).
     * @return string
     */
    public static function element(array $record, array $authorities): string {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startElementNs('oai_dc', 'dc', self::OAI_DC);
        $xml->writeAttributeNs('xmlns', 'dc', null, self::DC);
        $write = function (string $name, string $value) use ($xml): void {
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            if ($value !== '') {
                $xml->writeElementNs('dc', $name, null, $value);
            }
        };
        $subtitle = trim((string) ($record['subtitle'] ?? ''));
        $write('title', trim((string) ($record['title'] ?? '')) . ($subtitle !== '' ? ': ' . $subtitle : ''));
        foreach ($authorities['author'] ?? [] as $author) {
            $write('creator', $author);
        }
        foreach (array_merge($authorities['subject'] ?? [], $authorities['class'] ?? []) as $subject) {
            $write('subject', $subject);
        }
        $write('description', strip_tags((string) ($record['summary'] ?? '')));
        foreach ($authorities['publisher'] ?? [] as $publisher) {
            $write('publisher', $publisher);
        }
        $write('date', (string) ($record['pubyear'] ?? ''));
        $write('type', self::TYPES[(string) ($record['doctype'] ?? 'book')] ?? 'Text');
        $write('format', (string) ($record['extent'] ?? ''));
        if (($record['isbn'] ?? '') !== '') {
            $write('identifier', 'urn:isbn:' . $record['isbn']);
        }
        $write('language', (string) ($record['lang'] ?? ''));
        foreach ($authorities['collection'] ?? [] as $collection) {
            $write('relation', $collection);
        }
        $xml->endElement();
        return $xml->outputMemory();
    }
}
