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
 * ONIX for Books, the format of publishers and distributors.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

use local_oksigeniaayvurenda\core\marc\describer;

/**
 * The products of an ONIX message (3.0 or 2.1, with reference names or the short tags of 3.0) as the describers give a
 * record: what a publisher, a distributor or a service such as DILVE sends about its books. A product has no copies:
 * they are added at the desk. Plain PHP, without Moodle.
 */
final class onix {
    /** @var array<string, string> The short tags of ONIX 3.0 that are read, as their reference names (por verificar). */
    private const SHORT = ['product' => 'Product', 'a001' => 'RecordReference', 'productidentifier' => 'ProductIdentifier',
        'b221' => 'ProductIDType', 'b244' => 'IDValue', 'titledetail' => 'TitleDetail', 'b202' => 'TitleType',
        'titleelement' => 'TitleElement', 'b203' => 'TitleText', 'b030' => 'TitlePrefix', 'b031' => 'TitleWithoutPrefix',
        'b029' => 'Subtitle', 'contributor' => 'Contributor', 'b035' => 'ContributorRole', 'b036' => 'PersonName',
        'b037' => 'PersonNameInverted', 'b039' => 'NamesBeforeKey', 'b040' => 'KeyNames', 'b047' => 'CorporateName',
        'b057' => 'EditionNumber', 'language' => 'Language', 'b253' => 'LanguageRole', 'b252' => 'LanguageCode',
        'extent' => 'Extent', 'b218' => 'ExtentType', 'b219' => 'ExtentValue', 'subject' => 'Subject', 'b069' => 'SubjectCode',
        'b070' => 'SubjectHeadingText', 'collection' => 'Collection', 'textcontent' => 'TextContent', 'x426' => 'TextType',
        'd104' => 'Text', 'publisher' => 'Publisher', 'b081' => 'PublisherName', 'b209' => 'CityOfPublication',
        'publishingdate' => 'PublishingDate', 'b306' => 'Date', 'descriptivedetail' => 'DescriptiveDetail',
        'collateraldetail' => 'CollateralDetail', 'publishingdetail' => 'PublishingDetail'];

    /** @var string[] Contributor roles that are authors of the work: by, edited by, illustrated by, translated by. */
    private const AUTHORS = ['A01', 'A12', 'B01', 'B06'];

    /**
     * Whether a text is an ONIX message.
     *
     * @param string $head Its first bytes.
     * @return bool
     */
    public static function is(string $head): bool {
        return (bool) preg_match('/<(\w+:)?ONIX[Mm]essage\b/', $head);
    }

    /**
     * The products of a message.
     *
     * @param string $xml
     * @return array[] key, description and items (none), as the import of a catalogue takes them.
     */
    public static function records(string $xml): array {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // No network and no entities: what comes from outside is data.
        $ok = $xml !== '' && $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException('Not an XML document');
        }
        $records = [];
        foreach (self::all($doc->documentElement, 'Product') as $product) {
            $description = self::describe($product);
            if ($description['record']['title'] === '') {
                continue;
            }
            $key = $description['record']['isbn'] ?? ('onix-' . self::text($product, 'RecordReference'));
            $records[] = ['key' => $key, 'description' => $description, 'items' => []];
        }
        return $records;
    }

    /**
     * A product as a description.
     *
     * @param \DOMElement $product
     * @return array
     */
    private static function describe(\DOMElement $product): array {
        $isbn = null;
        foreach (self::all($product, 'ProductIdentifier') as $id) {
            // 15 is the ISBN-13; 02, the ISBN-10; 03, a GTIN that may be one.
            if (in_array(self::text($id, 'ProductIDType'), ['15', '02', '03'], true)) {
                $isbn = $isbn ?? isbn::normalise(self::text($id, 'IDValue'));
            }
        }
        // The distinctive title (type 01): in 3.0, in a title detail; in 2.1, in a title.
        [$title, $subtitle] = ['', ''];
        foreach (array_merge(self::all($product, 'TitleDetail'), self::all($product, 'Title')) as $detail) {
            if (self::text($detail, 'TitleType') !== '01' || self::ancestor($detail, 'Collection')) {
                continue;
            }
            $prefix = self::text($detail, 'TitlePrefix');
            $title = self::text($detail, 'TitleText') ?: trim($prefix . ' ' . self::text($detail, 'TitleWithoutPrefix'));
            $subtitle = self::text($detail, 'Subtitle');
            break;
        }
        $authors = [];
        foreach (self::all($product, 'Contributor') as $person) {
            if (!in_array(self::text($person, 'ContributorRole'), self::AUTHORS, true)) {
                continue;
            }
            $keys = self::text($person, 'KeyNames');
            $name = self::text($person, 'PersonNameInverted')
                ?: ($keys !== '' ? trim($keys . ', ' . self::text($person, 'NamesBeforeKey'), ', ')
                : (self::text($person, 'PersonName') ?: self::text($person, 'CorporateName')));
            if ($name !== '' && !in_array($name, $authors, true)) {
                $authors[] = $name;
            }
        }
        $lang = '';
        foreach (self::all($product, 'Language') as $language) {
            if (in_array(self::text($language, 'LanguageRole'), ['01', ''], true)) {
                $lang = strtolower(self::text($language, 'LanguageCode'));
                break;
            }
        }
        $pages = self::text($product, 'NumberOfPages');
        foreach (self::all($product, 'Extent') as $extent) {
            if (in_array(self::text($extent, 'ExtentType'), ['00', '07', '11'], true)) {
                $pages = $pages ?: self::text($extent, 'ExtentValue');
            }
        }
        $summary = '';
        foreach (array_merge(self::all($product, 'TextContent'), self::all($product, 'OtherText')) as $text) {
            $type = self::text($text, 'TextType') ?: self::text($text, 'TextTypeCode');
            if (in_array($type, ['03', '02', '01'], true)) {
                $summary = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
                    strip_tags(self::text($text, 'Text')),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )));
                break;
            }
        }
        $date = '';
        foreach (self::all($product, 'PublishingDate') as $when) {
            $date = $date ?: self::text($when, 'Date');
        }
        $date = $date ?: self::text($product, 'PublicationDate');
        $series = [];
        foreach (array_merge(self::all($product, 'Collection'), self::all($product, 'Series')) as $collection) {
            $name = self::text($collection, 'TitleText') ?: self::text($collection, 'TitleOfSeries');
            if ($name !== '') {
                $series[] = $name;
            }
        }
        $subjects = [];
        foreach (self::all($product, 'Subject') as $subject) {
            $heading = self::text($subject, 'SubjectHeadingText');
            if ($heading !== '' && !in_array($heading, $subjects, true)) {
                $subjects[] = $heading;
            }
        }
        $publishers = array_values(array_unique(array_filter(array_merge(
            array_map(fn($p) => self::text($p, 'PublisherName'), self::all($product, 'Publisher')),
            array_map(fn($i) => self::text($i, 'ImprintName'), self::all($product, 'Imprint'))
        ))));
        return [
            'record' => [
                'doctype' => 'book',
                'title' => (string) describer::clean($title),
                'subtitle' => describer::clean($subtitle),
                'responsibility' => null,
                'isbn' => $isbn,
                'edition' => self::text($product, 'EditionNumber') !== '' ? self::text($product, 'EditionNumber') . '.ª ed.' : null,
                'pubplace' => describer::clean(self::text($product, 'CityOfPublication')),
                'pubyear' => preg_match('/^(\d{4})/', $date, $m) ? $m[1] : null,
                'lang' => preg_match('/^[a-z]{3}$/', $lang) ? $lang : null,
                'extent' => ctype_digit($pages) && $pages !== '' ? $pages . ' p.' : null,
                'summary' => $summary !== '' ? $summary : null,
                'notes' => null,
            ],
            'authorities' => [
                'author' => $authors,
                'publisher' => array_slice($publishers, 0, 1),
                'class' => [],
                'subject' => array_slice($subjects, 0, 6),
                'collection' => array_slice(array_values(array_unique($series)), 0, 1),
                'audience' => [],
            ],
            'extra' => ['format' => 'onix'],
        ];
    }

    /**
     * The elements of a name inside an element, by reference name or short tag, in any namespace.
     *
     * @param \DOMElement $parent
     * @param string $name A reference name.
     * @return \DOMElement[]
     */
    private static function all(\DOMElement $parent, string $name): array {
        $found = [];
        foreach ($parent->getElementsByTagName('*') as $node) {
            $local = $node->localName;
            if ($local === $name || (self::SHORT[$local] ?? null) === $name) {
                $found[] = $node;
            }
        }
        return $found;
    }

    /**
     * The text of the first element of a name inside an element.
     *
     * @param \DOMElement $parent
     * @param string $name
     * @return string
     */
    private static function text(\DOMElement $parent, string $name): string {
        $first = self::all($parent, $name)[0] ?? null;
        return $first ? trim($first->textContent) : '';
    }

    /**
     * Whether an element is inside one of a name.
     *
     * @param \DOMElement $node
     * @param string $name
     * @return bool
     */
    private static function ancestor(\DOMElement $node, string $name): bool {
        for ($up = $node->parentNode; $up instanceof \DOMElement; $up = $up->parentNode) {
            if ($up->localName === $name || (self::SHORT[$up->localName] ?? null) === $name) {
                return true;
            }
        }
        return false;
    }
}
