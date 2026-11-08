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

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * A library's catalogue for other catalogues to search, by SRU 1.2 (IOB-M2): another library's program (Koha, a union
 * catalogue, a VuFind) asks for a book by its ISBN, its title, its author or any word, and gets it in MARCXML, with its
 * copies here, or in Dublin Core. Only where the window shows the catalogue to anyone. The query language is a small
 * part of CQL: an index, «=» (or any, all, adj, exact) and a term, joined with «and».
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sru {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The namespace of SRU 1.2. */
    private const SRW = 'http://www.loc.gov/zing/srw/';

    /** @var string The namespace of its diagnostics. */
    private const DIAG = 'http://www.loc.gov/zing/srw/diagnostic/';

    /** @var int Records at most in one answer. */
    public const MOST = 50;

    /** @var array<string, string> The indexes it answers to and what they look in. */
    public const INDEXES = ['cql.anywhere' => 'words', 'cql.serverchoice' => 'words', 'serverchoice' => 'words',
        'dc.title' => 'words', 'bath.title' => 'words', 'dc.creator' => 'words', 'bath.author' => 'words',
        'dc.subject' => 'words', 'bath.isbn' => 'isbn', 'dc.identifier' => 'isbn'];

    /** @var array<string, string> The schemas a record is given in, by the names harvesters use for them. */
    public const SCHEMAS = ['marcxml' => 'marcxml', 'info:srw/schema/1/marcxml-v1.1' => 'marcxml', 'marc21' => 'marcxml',
        'dc' => 'dc', 'info:srw/schema/1/dc-v1.1' => 'dc'];

    /**
     * The address of a library's SRU server.
     *
     * @param \stdClass $library
     * @return string
     */
    public static function base(\stdClass $library): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/sru.php/' . $library->slug))->out(false);
    }

    /**
     * Answers a request.
     *
     * @param \stdClass $library An open window's that shows its catalogue.
     * @param array $args The arguments of the request.
     * @return string The XML.
     */
    public static function answer(\stdClass $library, array $args): string {
        $operation = (string) ($args['operation'] ?? '');
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        if ($operation === 'searchRetrieve') {
            self::search($xml, $library, $args);
        } else {
            self::explain($xml, $library, $operation !== '' && $operation !== 'explain');
        }
        $xml->endDocument();
        return $xml->outputMemory();
    }

    /**
     * What the server is and what it answers to.
     *
     * @param \XMLWriter $xml
     * @param \stdClass $library
     * @param bool $unsupported Whether it was asked for an operation it does not have.
     */
    private static function explain(\XMLWriter $xml, \stdClass $library, bool $unsupported): void {
        $xml->startElementNs('srw', 'explainResponse', self::SRW);
        $xml->writeElementNs('srw', 'version', null, '1.2');
        $xml->startElementNs('srw', 'record', null);
        $xml->writeElementNs('srw', 'recordSchema', null, 'http://explain.z3950.org/dtd/2.0/');
        $xml->writeElementNs('srw', 'recordPacking', null, 'xml');
        $xml->startElementNs('srw', 'recordData', null);
        $xml->startElementNs(null, 'explain', 'http://explain.z3950.org/dtd/2.0/');
        $url = parse_url(self::base($library));
        $xml->startElement('serverInfo');
        $xml->writeAttribute('protocol', 'SRU');
        $xml->writeAttribute('version', '1.2');
        $xml->writeElement('host', (string) ($url['host'] ?? ''));
        $xml->writeElement('port', (string) ($url['port'] ?? (($url['scheme'] ?? '') === 'https' ? 443 : 80)));
        $xml->writeElement('database', ltrim((string) ($url['path'] ?? ''), '/'));
        $xml->endElement();
        $xml->startElement('databaseInfo');
        $xml->writeElement('title', format_string($library->name, true, ['escape' => false]));
        $xml->writeElement('contact', \core_user::get_support_user()->email);
        $xml->endElement();
        $xml->startElement('indexInfo');
        foreach (
            ['cql' => 'info:srw/cql-context-set/1/cql-v1.2', 'dc' => 'info:srw/cql-context-set/1/dc-v1.1',
            'bath' => 'http://zing.z3950.org/cql/bath/2.0/'] as $name => $identifier
        ) {
            $xml->startElement('set');
            $xml->writeAttribute('name', $name);
            $xml->writeAttribute('identifier', $identifier);
            $xml->endElement();
        }
        foreach (array_keys(self::INDEXES) as $index) {
            if (!str_contains($index, '.')) {
                continue;
            }
            [$set, $name] = explode('.', $index);
            $xml->startElement('index');
            $xml->startElement('map');
            $xml->startElement('name');
            $xml->writeAttribute('set', $set);
            $xml->text($name);
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('schemaInfo');
        foreach (['marcxml' => 'info:srw/schema/1/marcxml-v1.1', 'dc' => 'info:srw/schema/1/dc-v1.1'] as $name => $id) {
            $xml->startElement('schema');
            $xml->writeAttribute('name', $name);
            $xml->writeAttribute('identifier', $id);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('configInfo');
        $xml->startElement('default');
        $xml->writeAttribute('type', 'numberOfRecords');
        $xml->text('10');
        $xml->endElement();
        $xml->startElement('setting');
        $xml->writeAttribute('type', 'maximumRecords');
        $xml->text((string) self::MOST);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        if ($unsupported) {
            self::diagnostics($xml, [[4, 'Unsupported operation']]);
        }
        $xml->endElement();
    }

    /**
     * A search: the records of the catalogue that match, in the schema asked, from where asked.
     *
     * @param \XMLWriter $xml
     * @param \stdClass $library
     * @param array $args
     */
    private static function search(\XMLWriter $xml, \stdClass $library, array $args): void {
        $xml->startElementNs('srw', 'searchRetrieveResponse', self::SRW);
        $xml->writeElementNs('srw', 'version', null, '1.2');
        $schema = self::SCHEMAS[strtolower((string) ($args['recordSchema'] ?? 'marcxml'))] ?? null;
        $start = (int) ($args['startRecord'] ?? 1);
        $most = (int) ($args['maximumRecords'] ?? 10);
        $problem = null;
        $clauses = self::parse((string) ($args['query'] ?? ''), $problem);
        if (!$problem && !$schema) {
            $problem = [66, 'Unknown schema for retrieval'];
        }
        if (!$problem && ($start < 1 || $most < 0)) {
            $problem = [6, 'Unsupported parameter value'];
        }
        if ($problem) {
            $xml->writeElementNs('srw', 'numberOfRecords', null, '0');
            self::diagnostics($xml, [$problem]);
            $xml->endElement();
            return;
        }
        $most = min($most, self::MOST);
        [$sql, $params] = self::where($library, $clauses);
        global $DB;
        $total = (int) $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'record} r WHERE ' . $sql, $params);
        $xml->writeElementNs('srw', 'numberOfRecords', null, (string) $total);
        $ids = $most ? array_keys($DB->get_records_sql(
            'SELECT r.id FROM {' . self::T . 'record} r WHERE ' . $sql . ' ORDER BY r.id',
            $params,
            $start - 1,
            $most
        )) : [];
        if ($ids) {
            $digital = oai::digital($library);
            $xml->startElementNs('srw', 'records', null);
            foreach ($ids as $position => $id) {
                $item = ['id' => (int) $id, 'datestamp' => 0, 'sets' => ['catalogue'], 'items' => $digital[$id]['items'] ?? []];
                $xml->startElementNs('srw', 'record', null);
                $xml->writeElementNs('srw', 'recordSchema', null, $schema === 'dc' ? 'info:srw/schema/1/dc-v1.1'
                    : 'info:srw/schema/1/marcxml-v1.1');
                $xml->writeElementNs('srw', 'recordPacking', null, 'xml');
                $xml->startElementNs('srw', 'recordData', null);
                $xml->writeRaw($schema === 'dc' ? self::dc($library, (int) $id) : oai::marc($library, $item));
                $xml->endElement();
                $xml->writeElementNs('srw', 'recordPosition', null, (string) ($start + $position));
                $xml->endElement();
            }
            $xml->endElement();
            if ($start - 1 + count($ids) < $total) {
                $xml->writeElementNs('srw', 'nextRecordPosition', null, (string) ($start + count($ids)));
            }
        }
        $xml->endElement();
    }

    /**
     * Reads a query of the small CQL it understands.
     *
     * @param string $query
     * @param array|null $problem A diagnostic (its number and words) when it cannot.
     * @return array[] Each clause: what it looks in (words or isbn) and its term.
     */
    public static function parse(string $query, ?array &$problem): array {
        $problem = null;
        $query = trim($query);
        if ($query === '') {
            $problem = [7, 'Mandatory parameter not supplied: query'];
            return [];
        }
        // Terms, quoted or bare, relations, and the boolean words.
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|(==|<>|<=|>=|=|<|>)|([^\s"=<>()]+)|(\(|\))/u', $query, $tokens, PREG_SET_ORDER);
        $clauses = [];
        $i = 0;
        $count = count($tokens);
        $term = fn(array $token) => isset($token[1]) && $token[1] !== '' ? stripslashes($token[1])
            : ($token[3] ?? '');
        while ($i < $count) {
            if (($tokens[$i][4] ?? '') !== '') {
                $problem = [10, 'Query syntax error: parentheses are not supported'];
                return [];
            }
            $first = $term($tokens[$i]);
            $relation = $tokens[$i + 1] ?? null;
            $isrelation = $relation && (($relation[2] ?? '') !== ''
                || in_array(strtolower($relation[3] ?? ''), ['any', 'all', 'adj', 'exact'], true));
            if ($isrelation && isset($tokens[$i + 2])) {
                $index = strtolower($first);
                if (!isset(self::INDEXES[$index])) {
                    $problem = [16, 'Unsupported index: ' . $first];
                    return [];
                }
                if (!in_array($relation[2] ?? '', ['', '=', '=='], true)) {
                    $problem = [19, 'Unsupported relation: ' . $relation[2]];
                    return [];
                }
                $clauses[] = [self::INDEXES[$index], $term($tokens[$i + 2])];
                $i += 3;
            } else {
                $clauses[] = ['words', $first];
                $i++;
            }
            if ($i < $count) {
                $word = strtolower($term($tokens[$i]));
                if ($word === 'or' || $word === 'not' || $word === 'prox') {
                    $problem = [37, 'Unsupported boolean operator: ' . $word];
                    return [];
                }
                if ($word !== 'and') {
                    $problem = [10, 'Query syntax error'];
                    return [];
                }
                $i++;
                if ($i >= $count) {
                    $problem = [10, 'Query syntax error'];
                    return [];
                }
            }
        }
        return $clauses;
    }

    /**
     * The condition of the records that match: books with a copy in this library, and every clause.
     *
     * @param \stdClass $library
     * @param array[] $clauses
     * @return array The SQL and its parameters.
     */
    private static function where(\stdClass $library, array $clauses): array {
        global $DB;
        $where = ["r.entityid = :entityid AND r.doctype <> 'device' AND EXISTS (SELECT 1 FROM {" . self::T . "item} i
            WHERE i.recordid = r.id AND i.libraryid = :libraryid AND i.status <> 'withdrawn' AND i.status <> 'lost')"];
        $params = ['entityid' => $library->entityid, 'libraryid' => $library->id];
        $n = 0;
        foreach ($clauses as [$kind, $value]) {
            if ($kind === 'isbn') {
                $isbn = purchases::isbn($value);
                $where[] = 'r.isbn = :isbn' . $n;
                $params['isbn' . $n] = $isbn !== '' ? $isbn : '-';
                $n++;
                continue;
            }
            foreach (preg_split('/\s+/u', text::normalise($value), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $where[] = $DB->sql_like('r.searchtext', ':word' . $n, false);
                $params['word' . $n] = '%' . $DB->sql_like_escape($word) . '%';
                $n++;
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * A record in Dublin Core, as SRU gives it.
     *
     * @param \stdClass $library
     * @param int $id
     * @return string
     */
    private static function dc(\stdClass $library, int $id): string {
        global $DB;
        $record = $DB->get_record(self::T . 'record', ['id' => $id], '*', MUST_EXIST);
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startElementNs('srw_dc', 'dc', 'info:srw/schema/1/dc-schema');
        $xml->writeAttributeNs('xmlns', 'dc', null, 'http://purl.org/dc/elements/1.1/');
        $title = trim((string) $record->title . ((string) $record->subtitle !== '' ? ': ' . $record->subtitle : ''));
        $xml->writeElementNs('dc', 'title', null, $title);
        $authors = $DB->get_fieldset_sql(
            "SELECT a.label FROM {" . self::T . "recauth} ra JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind = 'author' ORDER BY ra.sortorder, ra.id",
            [$id]
        );
        foreach ($authors as $author) {
            $xml->writeElementNs('dc', 'creator', null, $author);
        }
        if (preg_match('/(\d{4})/', (string) $record->pubyear, $m)) {
            $xml->writeElementNs('dc', 'date', null, $m[1]);
        }
        if ((string) $record->isbn !== '') {
            $xml->writeElementNs('dc', 'identifier', null, 'urn:isbn:' . $record->isbn);
        }
        $xml->writeElementNs('dc', 'identifier', null, (new \moodle_url(
            '/local/oksigeniaayvurenda/public.php',
            ['library' => $library->slug, 'record' => $id]
        ))->out(false));
        if ((string) $record->lang !== '') {
            $xml->writeElementNs('dc', 'language', null, $record->lang);
        }
        $xml->endElement();
        return $xml->outputMemory();
    }

    /**
     * The diagnostics of an answer.
     *
     * @param \XMLWriter $xml
     * @param array[] $problems Each one, its number and its words.
     */
    private static function diagnostics(\XMLWriter $xml, array $problems): void {
        $xml->startElementNs('srw', 'diagnostics', null);
        foreach ($problems as [$number, $words]) {
            $xml->startElementNs('diag', 'diagnostic', self::DIAG);
            $xml->writeElementNs('diag', 'uri', null, 'info:srw/diagnostic/1/' . $number);
            $xml->writeElementNs('diag', 'message', null, $words);
            $xml->endElement();
        }
        $xml->endElement();
    }
}
