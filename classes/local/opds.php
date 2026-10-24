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
 * The public window as an OPDS 1.2 catalogue, for e-book readers and programs such as Calibre, KOReader or Thorium.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\digital_rules;

/**
 * One acquisition feed per window, a page at a time, with search. Each record is an entry: what can be downloaded goes
 * as an open-access acquisition link with its type; what is only read in the browser, as a link to the page.
 */
final class opds {
    /** @var string The type of an acquisition feed. */
    public const TYPE = 'application/atom+xml;profile=opds-catalog;kind=acquisition';

    /** @var string[] ISO 639-2 codes and their two-letter form, for the languages most likely in a library. */
    private const LANGS = ['spa' => 'es', 'eng' => 'en', 'cat' => 'ca', 'glg' => 'gl', 'eus' => 'eu', 'baq' => 'eu',
        'fra' => 'fr', 'fre' => 'fr', 'deu' => 'de', 'ger' => 'de', 'ita' => 'it', 'por' => 'pt', 'grn' => 'gn', 'ara' => 'ar',
        'zho' => 'zh', 'chi' => 'zh', 'rus' => 'ru', 'rum' => 'ro', 'ron' => 'ro', 'lat' => 'la'];

    /**
     * The feed of a window.
     *
     * @param \stdClass $library An open window's.
     * @param string $query
     * @param int $page
     * @return string Atom XML.
     */
    public static function feed(\stdClass $library, string $query = '', int $page = 0): string {
        $found = window::records($library, $query, $page);
        $url = fn(string $script, array $params = []) => (new \moodle_url(
            '/local/oksigeniaayvurenda/' . $script,
            ['library' => $library->slug] + $params
        ))->out(false);
        $self = $url('opds.php', array_filter(['q' => $query, 'page' => $page]));
        $updated = 0;
        foreach ($found['records'] as $record) {
            $updated = max($updated, $record['updated']);
        }
        $name = format_string($library->name);
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'feed', 'http://www.w3.org/2005/Atom');
        $xml->writeAttributeNs('xmlns', 'dc', null, 'http://purl.org/dc/terms/');
        $xml->writeAttributeNs('xmlns', 'opds', null, 'http://opds-spec.org/2010/catalog');
        $xml->writeElement('id', $self);
        $xml->writeElement('title', $query !== '' ? $name . ' · ' . $query : $name);
        $xml->writeElement('updated', date(DATE_ATOM, $updated ?: time()));
        $xml->startElement('author');
        $xml->writeElement('name', $name);
        $xml->writeElement('uri', $url('public.php'));
        $xml->endElement();
        self::link($xml, 'self', $self, self::TYPE);
        self::link($xml, 'start', $url('opds.php'), self::TYPE);
        self::link($xml, 'search', $url('opds.php', ['osd' => 1]), 'application/opensearchdescription+xml');
        self::link($xml, 'alternate', $url('public.php'), 'text/html');
        if ($found['more']) {
            self::link($xml, 'next', $url('opds.php', array_filter(['q' => $query, 'page' => $page + 1])), self::TYPE);
        }
        if ($page > 0) {
            self::link($xml, 'previous', $url('opds.php', array_filter(['q' => $query, 'page' => $page - 1])), self::TYPE);
        }
        foreach ($found['records'] as $record) {
            $xml->startElement('entry');
            $xml->writeElement('title', $record['title']);
            $xml->writeElement('id', $url('public.php') . '#record-' . $record['id']);
            $xml->writeElement('updated', date(DATE_ATOM, $record['updated'] ?: time()));
            foreach ($record['authors'] as $author) {
                $xml->startElement('author');
                $xml->writeElement('name', $author);
                $xml->endElement();
            }
            if ($record['lang'] !== '') {
                $lang = strtolower($record['lang']);
                $xml->writeElementNs('dc', 'language', null, self::LANGS[$lang] ?? $lang);
            }
            $summary = trim($record['subtitle'] . ($record['subtitle'] !== '' && $record['summary'] !== '' ? '. ' : '') .
                $record['summary']);
            if ($summary !== '') {
                $xml->startElement('summary');
                $xml->writeAttribute('type', 'text');
                $xml->text(\core_text::substr(strip_tags($summary), 0, 1000));
                $xml->endElement();
            }
            if ($record['cover']) {
                self::link($xml, 'http://opds-spec.org/image', $record['cover'], 'image/jpeg');
                self::link($xml, 'http://opds-spec.org/image/thumbnail', $record['cover'], 'image/jpeg');
            }
            foreach ($record['items'] as $item) {
                $title = $item->title !== '' && $item->title !== null ? $item->title
                    : get_string('format' . $item->format, 'local_oksigeniaayvurenda');
                $file = $item->kind === 'link' ? null : digital::file($item);
                if ($file && digital_rules::can_download($item->licence, (bool) $item->download)) {
                    self::link(
                        $xml,
                        'http://opds-spec.org/acquisition/open-access',
                        digital::file_url($item, true),
                        $file->get_mimetype(),
                        $title
                    );
                } else {
                    self::link($xml, 'alternate', $url('read.php', ['id' => $item->id]), 'text/html', $title);
                }
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();
        return $xml->outputMemory();
    }

    /**
     * The OpenSearch description of a window's catalogue, so readers can search it.
     *
     * @param \stdClass $library
     * @return string
     */
    public static function search(\stdClass $library): string {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'OpenSearchDescription', 'http://a9.com/-/spec/opensearch/1.1/');
        $xml->writeElement('ShortName', \core_text::substr(format_string($library->name), 0, 16));
        $xml->writeElement('Description', get_string('windowtitle', 'local_oksigeniaayvurenda', format_string($library->name)));
        $xml->startElement('Url');
        $xml->writeAttribute('type', self::TYPE);
        $xml->writeAttribute('template', (new \moodle_url('/local/oksigeniaayvurenda/opds.php', ['library' => $library->slug]))
            ->out(false) . '&q={searchTerms}');
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
        return $xml->outputMemory();
    }

    /**
     * Writes a link.
     *
     * @param \XMLWriter $xml
     * @param string $rel
     * @param string $href
     * @param string $type
     * @param string $title
     */
    private static function link(\XMLWriter $xml, string $rel, string $href, string $type, string $title = ''): void {
        $xml->startElement('link');
        $xml->writeAttribute('rel', $rel);
        $xml->writeAttribute('href', $href);
        $xml->writeAttribute('type', $type);
        if ($title !== '') {
            $xml->writeAttribute('title', $title);
        }
        $xml->endElement();
    }
}
