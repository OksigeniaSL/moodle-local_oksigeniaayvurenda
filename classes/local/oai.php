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
 * The centre's own publications in the public window, for harvesters, by OAI-PMH 2.0 with Dublin Core.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Only what the centre publishes as its own (its own licence or a Creative Commons one), meant for the public, in an
 * open window: not the public-domain works it brought, which have their own repositories, nor the voices. Each
 * record is an item of the repository, its datestamp the last change of what it publishes. No sets, no deletions kept.
 */
final class oai {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Records in a list before a resumption token. */
    public const PAGE = 100;

    /** @var string[] Licences of what the centre publishes. */
    public const OWN = ['own', 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa', 'cc-by-nd', 'cc-by-nc-nd'];

    /** @var string[] Where each licence is described. */
    private const LICENCES = ['cc-by' => 'https://creativecommons.org/licenses/by/4.0/',
        'cc-by-sa' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        'cc-by-nc' => 'https://creativecommons.org/licenses/by-nc/4.0/',
        'cc-by-nc-sa' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
        'cc-by-nd' => 'https://creativecommons.org/licenses/by-nd/4.0/',
        'cc-by-nc-nd' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/'];

    /**
     * The base address of a library's repository: the arguments go after it, so it carries none of its own.
     *
     * @param \stdClass $library
     * @return string
     */
    public static function base(\stdClass $library): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/oai.php/' . $library->slug))->out(false);
    }

    /**
     * Answers a request.
     *
     * @param \stdClass $library An open window's.
     * @param array $args The arguments of the request.
     * @param int|null $now
     * @return string The XML.
     */
    public static function answer(\stdClass $library, array $args, ?int $now = null): string {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'OAI-PMH', 'http://www.openarchives.org/OAI/2.0/');
        $xml->writeAttributeNs('xmlns', 'xsi', null, 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttributeNs(
            'xsi',
            'schemaLocation',
            null,
            'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd'
        );
        $xml->writeElement('responseDate', gmdate('Y-m-d\TH:i:s\Z', $now ?? time()));
        $error = self::check($args);
        $xml->startElement('request');
        if (!$error || !in_array($error[0], ['badVerb', 'badArgument'], true)) {
            foreach ($args as $name => $value) {
                $xml->writeAttribute($name, (string) $value);
            }
        }
        $xml->text(self::base($library));
        $xml->endElement();
        if (!$error) {
            $error = self::verb($xml, $library, $args);
        }
        if ($error) {
            $xml->startElement('error');
            $xml->writeAttribute('code', $error[0]);
            $xml->text($error[1]);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();
        return $xml->outputMemory();
    }

    /**
     * Checks the verb and its arguments.
     *
     * @param array $args
     * @return array|null An error code and its words.
     */
    private static function check(array $args): ?array {
        $allowed = [
            'Identify' => [[], []],
            'ListMetadataFormats' => [[], ['identifier']],
            'ListSets' => [[], ['resumptionToken']],
            'GetRecord' => [['identifier', 'metadataPrefix'], []],
            'ListIdentifiers' => [['metadataPrefix'], ['from', 'until', 'set', 'resumptionToken']],
            'ListRecords' => [['metadataPrefix'], ['from', 'until', 'set', 'resumptionToken']],
        ];
        $verb = $args['verb'] ?? '';
        if (!isset($allowed[$verb])) {
            return ['badVerb', 'Illegal OAI verb'];
        }
        [$required, $optional] = $allowed[$verb];
        $given = array_diff(array_keys($args), ['verb']);
        if (isset($args['resumptionToken']) && in_array($verb, ['ListIdentifiers', 'ListRecords'], true)) {
            return count($given) === 1 ? null : ['badArgument', 'resumptionToken is exclusive'];
        }
        if (array_diff($given, $required, $optional) || array_diff($required, $given)) {
            return ['badArgument', 'Missing or illegal arguments'];
        }
        foreach (['from', 'until'] as $date) {
            if (isset($args[$date]) && self::time($args[$date], $date === 'until') === null) {
                return ['badArgument', 'Bad ' . $date];
            }
        }
        if (isset($args['from'], $args['until']) && strlen($args['from']) !== strlen($args['until'])) {
            return ['badArgument', 'from and until with different granularities'];
        }
        return null;
    }

    /**
     * Writes the answer to a correct request.
     *
     * @param \XMLWriter $xml
     * @param \stdClass $library
     * @param array $args
     * @return array|null An error code and its words.
     */
    private static function verb(\XMLWriter $xml, \stdClass $library, array $args): ?array {
        $verb = $args['verb'];
        if ($verb === 'Identify') {
            $records = self::records($library);
            $earliest = $records ? min(array_column($records, 'datestamp')) : time();
            $xml->startElement('Identify');
            $xml->writeElement('repositoryName', format_string($library->name));
            $xml->writeElement('baseURL', self::base($library));
            $xml->writeElement('protocolVersion', '2.0');
            $xml->writeElement('adminEmail', \core_user::get_support_user()->email);
            $xml->writeElement('earliestDatestamp', gmdate('Y-m-d\TH:i:s\Z', $earliest));
            $xml->writeElement('deletedRecord', 'no');
            $xml->writeElement('granularity', 'YYYY-MM-DDThh:mm:ssZ');
            $xml->endElement();
            return null;
        }
        if ($verb === 'ListSets') {
            return ['noSetHierarchy', 'This repository has no sets'];
        }
        if (isset($args['metadataPrefix']) && $args['metadataPrefix'] !== 'oai_dc') {
            return ['cannotDisseminateFormat', 'Only oai_dc'];
        }
        if ($verb === 'ListMetadataFormats') {
            if (isset($args['identifier']) && !self::find($library, $args['identifier'])) {
                return ['idDoesNotExist', 'No such item'];
            }
            $xml->startElement('ListMetadataFormats');
            $xml->startElement('metadataFormat');
            $xml->writeElement('metadataPrefix', 'oai_dc');
            $xml->writeElement('schema', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd');
            $xml->writeElement('metadataNamespace', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
            $xml->endElement();
            $xml->endElement();
            return null;
        }
        if ($verb === 'GetRecord') {
            $record = self::find($library, $args['identifier']);
            if (!$record) {
                return ['idDoesNotExist', 'No such item'];
            }
            $xml->startElement('GetRecord');
            self::record($xml, $library, $record, true);
            $xml->endElement();
            return null;
        }
        // ListIdentifiers and ListRecords: from a token, or from the start with the dates asked.
        if (isset($args['set'])) {
            return ['noSetHierarchy', 'This repository has no sets'];
        }
        $offset = 0;
        if (isset($args['resumptionToken'])) {
            $token = self::untoken($args['resumptionToken']);
            if (!$token) {
                return ['badResumptionToken', 'Bad or expired token'];
            }
            [$offset, $from, $until] = $token;
        } else {
            $from = isset($args['from']) ? self::time($args['from'], false) : null;
            $until = isset($args['until']) ? self::time($args['until'], true) : null;
        }
        $records = array_values(array_filter(self::records($library), fn($r) => ($from === null || $r['datestamp'] >= $from)
            && ($until === null || $r['datestamp'] <= $until)));
        if (!$records || $offset >= count($records)) {
            return $offset ? ['badResumptionToken', 'Bad or expired token'] : ['noRecordsMatch', 'Nothing matches'];
        }
        $xml->startElement($verb);
        foreach (array_slice($records, $offset, self::PAGE) as $record) {
            self::record($xml, $library, $record, $verb === 'ListRecords');
        }
        if ($offset || count($records) > self::PAGE) {
            $xml->startElement('resumptionToken');
            $xml->writeAttribute('completeListSize', (string) count($records));
            $xml->writeAttribute('cursor', (string) $offset);
            if ($offset + self::PAGE < count($records)) {
                $xml->text(self::token($offset + self::PAGE, $from, $until));
            }
            $xml->endElement();
        }
        $xml->endElement();
        return null;
    }

    /**
     * The records the repository offers, oldest change first.
     *
     * @param \stdClass $library
     * @return array[] id, title, lang, summary, pubyear, datestamp, items.
     */
    public static function records(\stdClass $library): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::OWN, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT d.*, r.title AS rtitle, r.lang AS rlang, r.summary AS rsummary, r.pubyear AS rpubyear
               FROM {" . self::T . "digital} d
               JOIN {" . self::T . "record} r ON r.id = d.recordid
              WHERE d.entityid = :entityid AND d.status = 'published' AND d.kind <> 'voice' AND d.licence $in
                AND " . $DB->sql_like($DB->sql_concat("','", 'd.audience', "','"), ':public') . "
           ORDER BY d.id",
            $params + ['entityid' => $library->entityid, 'public' => '%,public,%']
        );
        $records = [];
        foreach ($rows as $row) {
            if (!window::shows($row)) {
                continue;
            }
            $id = (int) $row->recordid;
            $records[$id] = $records[$id] ?? ['id' => $id, 'title' => $row->rtitle, 'lang' => (string) $row->rlang,
                'summary' => (string) $row->rsummary, 'pubyear' => (string) $row->rpubyear, 'datestamp' => 0, 'items' => []];
            $records[$id]['items'][] = $row;
            $records[$id]['datestamp'] = max($records[$id]['datestamp'], (int) $row->timemodified);
        }
        uasort($records, fn($a, $b) => [$a['datestamp'], $a['id']] <=> [$b['datestamp'], $b['id']]);
        return array_values($records);
    }

    /**
     * The identifier of a record in the repository.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return string
     */
    public static function identifier(\stdClass $library, int $recordid): string {
        global $CFG;
        return 'oai:' . parse_url($CFG->wwwroot, PHP_URL_HOST) . ':ayvurenda/' . $library->slug . '/' . $recordid;
    }

    /**
     * The record of an identifier, if the repository offers it.
     *
     * @param \stdClass $library
     * @param string $identifier
     * @return array|null
     */
    private static function find(\stdClass $library, string $identifier): ?array {
        foreach (self::records($library) as $record) {
            if (self::identifier($library, $record['id']) === $identifier) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Writes a record: its header and, for ListRecords and GetRecord, its Dublin Core.
     *
     * @param \XMLWriter $xml
     * @param \stdClass $library
     * @param array $record
     * @param bool $metadata
     */
    private static function record(\XMLWriter $xml, \stdClass $library, array $record, bool $metadata): void {
        global $DB;
        if ($metadata) {
            $xml->startElement('record');
        }
        $xml->startElement('header');
        $xml->writeElement('identifier', self::identifier($library, $record['id']));
        $xml->writeElement('datestamp', gmdate('Y-m-d\TH:i:s\Z', $record['datestamp']));
        $xml->endElement();
        if (!$metadata) {
            return;
        }
        $xml->startElement('metadata');
        $xml->startElementNs('oai_dc', 'dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $xml->writeAttributeNs('xmlns', 'dc', null, 'http://purl.org/dc/elements/1.1/');
        $xml->writeAttributeNs('xmlns', 'xsi', null, 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttributeNs(
            'xsi',
            'schemaLocation',
            null,
            'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
        );
        $xml->writeElementNs('dc', 'title', null, $record['title']);
        $authors = $DB->get_fieldset_sql(
            "SELECT a.label FROM {" . self::T . "recauth} ra JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
              WHERE ra.recordid = ? AND a.kind = 'author' ORDER BY ra.sortorder, ra.id",
            [$record['id']]
        );
        foreach ($authors as $author) {
            $xml->writeElementNs('dc', 'creator', null, $author);
        }
        if ($record['summary'] !== '') {
            $xml->writeElementNs('dc', 'description', null, \core_text::substr(strip_tags($record['summary']), 0, 4000));
        }
        $xml->writeElementNs('dc', 'publisher', null, format_string($library->name));
        $xml->writeElementNs('dc', 'date', null, preg_match('/^\d{4}$/', $record['pubyear']) ? $record['pubyear']
            : gmdate('Y-m-d', $record['datestamp']));
        $xml->writeElementNs('dc', 'type', null, 'Text');
        if ($record['lang'] !== '') {
            $xml->writeElementNs('dc', 'language', null, $record['lang']);
        }
        foreach ($record['items'] as $item) {
            $xml->writeElementNs('dc', 'identifier', null, (new \moodle_url(
                '/local/oksigeniaayvurenda/read.php',
                ['library' => $library->slug, 'id' => $item->id]
            ))->out(false));
            $file = $item->kind === 'link' ? null : digital::file($item);
            if ($file) {
                $xml->writeElementNs('dc', 'format', null, $file->get_mimetype());
            }
            $xml->writeElementNs('dc', 'rights', null, self::LICENCES[$item->licence] ??
                get_string('licence' . str_replace('-', '', $item->licence), 'local_oksigeniaayvurenda'));
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
    }

    /**
     * A date of a request as a time, or null if it is not one; «until» a day means the end of that day.
     *
     * @param string $value
     * @param bool $end
     * @return int|null
     */
    private static function time(string $value, bool $end): ?int {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $time = strtotime($value . 'T00:00:00Z');
            return $time === false ? null : ($end ? $time + DAYSECS - 1 : $time);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            $time = strtotime($value);
            return $time === false ? null : $time;
        }
        return null;
    }

    /**
     * A resumption token: where to go on, and the dates asked.
     *
     * @param int $offset
     * @param int|null $from
     * @param int|null $until
     * @return string
     */
    private static function token(int $offset, ?int $from, ?int $until): string {
        return $offset . '-' . ($from ?? '') . '-' . ($until ?? '');
    }

    /**
     * Reads a resumption token.
     *
     * @param string $token
     * @return array|null offset, from, until.
     */
    private static function untoken(string $token): ?array {
        if (!preg_match('/^(\d+)-(\d*)-(\d*)$/', $token, $m)) {
            return null;
        }
        return [(int) $m[1], $m[2] === '' ? null : (int) $m[2], $m[3] === '' ? null : (int) $m[3]];
    }
}
