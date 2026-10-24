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
 * MARCXML, read and written.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

/**
 * MARCXML (http://www.loc.gov/MARC21/slim): the records in a document, wherever they are (a collection, one record, or
 * inside the answer of a catalogue), and records written back as a collection. Plain PHP, without Moodle.
 */
final class marcxml {
    /** @var string The MARCXML namespace. */
    public const NS = 'http://www.loc.gov/MARC21/slim';

    /**
     * The records of a document.
     *
     * @param string $xml
     * @return record[]
     * @throws \InvalidArgumentException When it is not XML.
     */
    public static function read(string $xml): array {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // No network and no entities: what comes from a catalogue is data.
        $ok = $xml !== '' && $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException('Not an XML document');
        }
        $records = [];
        foreach (self::elements($doc, 'record') as $node) {
            // A record of the catalogue's own answer (SRU) has no leader; a MARC one does.
            if (!self::children($node, 'leader') && !self::children($node, 'datafield')) {
                continue;
            }
            $records[] = self::record($node);
        }
        return $records;
    }

    /**
     * Records as a MARCXML collection.
     *
     * @param record[] $records
     * @return string
     */
    public static function write(array $records): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $collection = $doc->createElementNS(self::NS, 'collection');
        $doc->appendChild($collection);
        foreach ($records as $record) {
            $node = $doc->createElementNS(self::NS, 'record');
            $collection->appendChild($node);
            $node->appendChild($doc->createElementNS(self::NS, 'leader'))->appendChild($doc->createTextNode($record->leader));
            foreach ($record->fields as $field) {
                if (isset($field['value'])) {
                    $control = $doc->createElementNS(self::NS, 'controlfield');
                    $control->setAttribute('tag', $field['tag']);
                    $control->appendChild($doc->createTextNode($field['value']));
                    $node->appendChild($control);
                    continue;
                }
                $data = $doc->createElementNS(self::NS, 'datafield');
                $data->setAttribute('tag', $field['tag']);
                $data->setAttribute('ind1', $field['ind1']);
                $data->setAttribute('ind2', $field['ind2']);
                foreach ($field['subfields'] as [$code, $value]) {
                    $sub = $doc->createElementNS(self::NS, 'subfield');
                    $sub->setAttribute('code', (string) $code);
                    $sub->appendChild($doc->createTextNode(self::clean($value)));
                    $data->appendChild($sub);
                }
                $node->appendChild($data);
            }
        }
        return $doc->saveXML();
    }

    /**
     * One record from its element.
     *
     * @param \DOMElement $node
     * @return record
     */
    private static function record(\DOMElement $node): record {
        $leader = self::children($node, 'leader');
        $record = new record($leader ? $leader[0]->textContent : '');
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'controlfield') {
                $record->control($child->getAttribute('tag'), $child->textContent);
            } else if ($child->localName === 'datafield') {
                $subfields = [];
                foreach (self::children($child, 'subfield') as $sub) {
                    $subfields[] = [$sub->getAttribute('code'), trim($sub->textContent)];
                }
                $record->data(
                    $child->getAttribute('tag'),
                    $child->getAttribute('ind1'),
                    $child->getAttribute('ind2'),
                    $subfields
                );
            }
        }
        return $record;
    }

    /**
     * Elements with a local name in the MARCXML namespace, or without one, anywhere in the document.
     *
     * @param \DOMDocument $doc
     * @param string $name
     * @return \DOMElement[]
     */
    private static function elements(\DOMDocument $doc, string $name): array {
        $found = [];
        foreach ($doc->getElementsByTagNameNS('*', $name) as $node) {
            if (in_array($node->namespaceURI, [self::NS, null, ''], true)) {
                $found[] = $node;
            }
        }
        if (!$found) {
            foreach ($doc->getElementsByTagName($name) as $node) {
                $found[] = $node;
            }
        }
        return $found;
    }

    /**
     * The child elements with a local name.
     *
     * @param \DOMElement $node
     * @param string $name
     * @return \DOMElement[]
     */
    private static function children(\DOMElement $node, string $name): array {
        $found = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $found[] = $child;
            }
        }
        return $found;
    }

    /**
     * Text without the control characters XML does not allow.
     *
     * @param string $value
     * @return string
     */
    private static function clean(string $value): string {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }
}
