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
 * A library's open window for harvesters, by OAI-PMH 2.0: its catalogue and its own digital works, in Dublin Core and MARC 21.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Two sets. «catalogue»: every book with a copy in this library, when its window shows the catalogue to anyone, so a
 * union catalogue, a VuFind or a neighbour's Koha can gather it (IOB-M2). «digital»: what the centre publishes as its
 * own (its own licence or a Creative Commons one), meant for the public: not the public-domain works it brought, which
 * have their own repositories, nor the voices. Each record is one item, in one or both; its datestamp is the last change
 * of the record or of what it publishes. Lists go on by date and number, so they hold while the catalogue changes. No
 * deletions kept: a harvester gathers it all again now and then.
 */
final class oai {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Records in a list before a resumption token. */
    public const PAGE = 100;

    /** @var string[] The sets, as the protocol names them. */
    public const SETS = ['catalogue', 'digital'];

    /** @var string[] Licences of what the centre publishes. */
    public const OWN = ['own', 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa', 'cc-by-nd', 'cc-by-nc-nd'];

    /** @var array<string, string[]> What a record can be given in: its schema and its namespace. Dublin Core for anyone; MARC
     * 21 (MARCXML) for library systems that harvest it. */
    public const FORMATS = [
        'oai_dc' => ['http://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'http://www.openarchives.org/OAI/2.0/oai_dc/'],
        'marc21' => ['http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd', 'http://www.loc.gov/MARC21/slim'],
    ];

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
            $earliest = self::earliest($library);
            $xml->startElement('Identify');
            $xml->writeElement('repositoryName', format_string($library->name, true, ['escape' => false]));
            $xml->writeElement('baseURL', self::base($library));
            $xml->writeElement('protocolVersion', '2.0');
            $xml->writeElement('adminEmail', \core_user::get_support_user()->email);
            $xml->writeElement('earliestDatestamp', gmdate('Y-m-d\TH:i:s\Z', $earliest ?? time()));
            $xml->writeElement('deletedRecord', 'no');
            $xml->writeElement('granularity', 'YYYY-MM-DDThh:mm:ssZ');
            $xml->endElement();
            return null;
        }
        if ($verb === 'ListSets') {
            $xml->startElement('ListSets');
            foreach (self::sets($library) as $set) {
                $xml->startElement('set');
                $xml->writeElement('setSpec', $set);
                $xml->writeElement('setName', get_string('oaiset' . $set, 'local_oksigeniaayvurenda'));
                $xml->endElement();
            }
            $xml->endElement();
            return null;
        }
        if (isset($args['metadataPrefix']) && !isset(self::FORMATS[$args['metadataPrefix']])) {
            return ['cannotDisseminateFormat', 'Only ' . implode(' and ', array_keys(self::FORMATS))];
        }
        $prefix = $args['metadataPrefix'] ?? 'oai_dc';
        if ($verb === 'ListMetadataFormats') {
            if (isset($args['identifier']) && !self::find($library, $args['identifier'])) {
                return ['idDoesNotExist', 'No such item'];
            }
            $xml->startElement('ListMetadataFormats');
            foreach (self::FORMATS as $name => [$schema, $namespace]) {
                $xml->startElement('metadataFormat');
                $xml->writeElement('metadataPrefix', $name);
                $xml->writeElement('schema', $schema);
                $xml->writeElement('metadataNamespace', $namespace);
                $xml->endElement();
            }
            $xml->endElement();
            return null;
        }
        if ($verb === 'GetRecord') {
            $item = self::find($library, $args['identifier']);
            if (!$item) {
                return ['idDoesNotExist', 'No such item'];
            }
            $xml->startElement('GetRecord');
            self::record($xml, $library, $item, true, $prefix);
            $xml->endElement();
            return null;
        }
        // ListIdentifiers and ListRecords: from a token, or from the start with the dates and the set asked.
        $after = null;
        if (isset($args['resumptionToken'])) {
            $token = self::untoken($args['resumptionToken']);
            if (!$token) {
                return ['badResumptionToken', 'Bad or expired token'];
            }
            [$after, $from, $until, $prefix, $set] = $token;
        } else {
            $from = isset($args['from']) ? self::time($args['from'], false) : null;
            $until = isset($args['until']) ? self::time($args['until'], true) : null;
            $set = $args['set'] ?? '';
        }
        if ($set !== '' && !in_array($set, self::sets($library), true)) {
            return ['noRecordsMatch', 'No such set here'];
        }
        $items = self::items($library, $set, $from, $until, $after, self::PAGE + 1);
        if (!$items) {
            return $after ? ['badResumptionToken', 'Bad or expired token'] : ['noRecordsMatch', 'Nothing matches'];
        }
        $more = count($items) > self::PAGE;
        $items = array_slice($items, 0, self::PAGE);
        $xml->startElement($verb);
        foreach ($items as $item) {
            self::record($xml, $library, $item, $verb === 'ListRecords', $prefix);
        }
        if ($after || $more) {
            $xml->startElement('resumptionToken');
            $xml->writeAttribute('completeListSize', (string) self::count($library, $set, $from, $until));
            if ($more) {
                $last = end($items);
                $xml->text(self::token([$last['datestamp'], $last['id']], $from, $until, $prefix, $set));
            }
            $xml->endElement();
        }
        $xml->endElement();
        return null;
    }

    /**
     * The sets this library's repository has: the catalogue when its window shows it, and its digital works.
     *
     * @param \stdClass $library
     * @return string[]
     */
    public static function sets(\stdClass $library): array {
        return window::catalogue_open($library) ? self::SETS : ['digital'];
    }

    /**
     * The library's own digital works meant for the public, by record: when each changed last and its items.
     *
     * @param \stdClass $library
     * @return array[] Record id => datestamp and items.
     */
    public static function digital(\stdClass $library): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::OWN, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT d.*
               FROM {" . self::T . "digital} d
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
            $records[$id] = $records[$id] ?? ['datestamp' => 0, 'items' => []];
            $records[$id]['items'][] = $row;
            $records[$id]['datestamp'] = max($records[$id]['datestamp'], (int) $row->timemodified);
        }
        return $records;
    }

    /**
     * The query of the repository's items: each record with its datestamp, in the set asked (both, without one).
     *
     * @param \stdClass $library
     * @param string $set catalogue, digital, or '' for both.
     * @param array $digital As digital() gives it.
     * @return array|null The SQL (of id and datestamp, as ds) and its parameters; null when nothing can be in it.
     */
    private static function query(\stdClass $library, string $set, array $digital): ?array {
        global $DB;
        $catalogue = window::catalogue_open($library) && $set !== 'digital';
        $ids = $set === 'catalogue' ? [] : array_keys($digital);
        if (!$catalogue && !$ids) {
            return null;
        }
        $params = ['entityid' => $library->entityid, 'libraryid' => $library->id];
        $where = [];
        if ($catalogue) {
            $where[] = "(r.doctype <> 'device' AND EXISTS (SELECT 1 FROM {" . self::T . "item} i WHERE i.recordid = r.id
                AND i.libraryid = :libraryid AND i.status <> 'withdrawn' AND i.status <> 'lost'))";
        }
        if ($ids) {
            [$in, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'dig');
            $where[] = "r.id $in";
            $params += $inparams;
        }
        // A record changes when a digital form of it changes too.
        $sql = "SELECT r.id, CASE WHEN d.ts IS NOT NULL AND d.ts > r.timemodified THEN d.ts ELSE r.timemodified END AS ds
                  FROM {" . self::T . "record} r
             LEFT JOIN (SELECT recordid, MAX(timemodified) AS ts FROM {" . self::T . "digital}
                         WHERE entityid = :dentity AND status = 'published' AND kind <> 'voice'
                      GROUP BY recordid) d ON d.recordid = r.id
                 WHERE r.entityid = :entityid AND (" . implode(' OR ', $where) . ")";
        return [$sql, $params + ['dentity' => $library->entityid]];
    }

    /**
     * A page of the repository's items, oldest change first, after one already given.
     *
     * @param \stdClass $library
     * @param string $set
     * @param int|null $from
     * @param int|null $until
     * @param int[]|null $after The datestamp and id of the last one given.
     * @param int $limit
     * @return array[] id, datestamp and sets of each.
     */
    private static function items(\stdClass $library, string $set, ?int $from, ?int $until, ?array $after, int $limit): array {
        global $DB;
        $digital = self::digital($library);
        $query = self::query($library, $set, $digital);
        if (!$query) {
            return [];
        }
        [$sql, $params] = $query;
        [$filter, $more] = self::window($from, $until, $after);
        $rows = $DB->get_records_sql(
            "SELECT x.id, x.ds FROM ($sql) x" . $filter . " ORDER BY x.ds, x.id",
            $params + $more,
            0,
            $limit
        );
        return array_values(array_map(fn($row) => self::item($library, (int) $row->id, (int) $row->ds, $digital), $rows));
    }

    /**
     * How many items a list has, whole.
     *
     * @param \stdClass $library
     * @param string $set
     * @param int|null $from
     * @param int|null $until
     * @return int
     */
    private static function count(\stdClass $library, string $set, ?int $from, ?int $until): int {
        global $DB;
        $query = self::query($library, $set, self::digital($library));
        if (!$query) {
            return 0;
        }
        [$filter, $more] = self::window($from, $until, null);
        return (int) $DB->count_records_sql("SELECT COUNT(1) FROM ({$query[0]}) x" . $filter, $query[1] + $more);
    }

    /**
     * The first change the repository has, if it has any.
     *
     * @param \stdClass $library
     * @return int|null
     */
    private static function earliest(\stdClass $library): ?int {
        global $DB;
        $query = self::query($library, '', self::digital($library));
        if (!$query) {
            return null;
        }
        $first = $DB->get_field_sql("SELECT MIN(x.ds) FROM ({$query[0]}) x", $query[1]);
        return $first ? (int) $first : null;
    }

    /**
     * The conditions of dates and of where a list goes on.
     *
     * @param int|null $from
     * @param int|null $until
     * @param int[]|null $after
     * @return array The WHERE (with its word, or '') and its parameters.
     */
    private static function window(?int $from, ?int $until, ?array $after): array {
        $where = [];
        $params = [];
        if ($from !== null) {
            $where[] = 'x.ds >= :fromds';
            $params['fromds'] = $from;
        }
        if ($until !== null) {
            $where[] = 'x.ds <= :untilds';
            $params['untilds'] = $until;
        }
        if ($after) {
            $where[] = '(x.ds > :afterds OR (x.ds = :afterds2 AND x.id > :afterid))';
            $params += ['afterds' => $after[0], 'afterds2' => $after[0], 'afterid' => $after[1]];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * An item of the repository: a record, its datestamp and the sets it is in.
     *
     * @param \stdClass $library
     * @param int $id
     * @param int $datestamp
     * @param array $digital As digital() gives it.
     * @return array id, datestamp, sets and items (its digital forms).
     */
    private static function item(\stdClass $library, int $id, int $datestamp, array $digital): array {
        global $DB;
        $sets = [];
        if (
            window::catalogue_open($library) && $DB->record_exists_select(
                self::T . 'item',
                "recordid = ? AND libraryid = ? AND status <> 'withdrawn' AND status <> 'lost'",
                [$id, $library->id]
            )
        ) {
            $sets[] = 'catalogue';
        }
        if (isset($digital[$id])) {
            $sets[] = 'digital';
        }
        return ['id' => $id, 'datestamp' => $datestamp, 'sets' => $sets, 'items' => $digital[$id]['items'] ?? []];
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
     * The item of an identifier, if the repository offers it.
     *
     * @param \stdClass $library
     * @param string $identifier
     * @return array|null
     */
    private static function find(\stdClass $library, string $identifier): ?array {
        global $DB;
        $prefix = self::identifier($library, 0);
        $prefix = substr($prefix, 0, -1);
        if (!str_starts_with($identifier, $prefix) || !ctype_digit(substr($identifier, strlen($prefix)))) {
            return null;
        }
        $id = (int) substr($identifier, strlen($prefix));
        $digital = self::digital($library);
        $query = self::query($library, '', $digital);
        if (!$query) {
            return null;
        }
        $row = $DB->get_record_sql("SELECT x.id, x.ds FROM ({$query[0]}) x WHERE x.id = :wanted", $query[1] + ['wanted' => $id]);
        return $row ? self::item($library, $id, (int) $row->ds, $digital) : null;
    }

    /**
     * Writes a record: its header and, for ListRecords and GetRecord, its metadata.
     *
     * @param \XMLWriter $xml
     * @param \stdClass $library
     * @param array $item As item() gives it.
     * @param bool $metadata
     * @param string $prefix oai_dc or marc21.
     */
    private static function record(
        \XMLWriter $xml,
        \stdClass $library,
        array $item,
        bool $metadata,
        string $prefix = 'oai_dc'
    ): void {
        global $DB;
        if ($metadata) {
            $xml->startElement('record');
        }
        $xml->startElement('header');
        $xml->writeElement('identifier', self::identifier($library, $item['id']));
        $xml->writeElement('datestamp', gmdate('Y-m-d\TH:i:s\Z', $item['datestamp']));
        foreach ($item['sets'] as $set) {
            $xml->writeElement('setSpec', $set);
        }
        $xml->endElement();
        if (!$metadata) {
            return;
        }
        $xml->startElement('metadata');
        if ($prefix === 'marc21') {
            $xml->writeRaw(self::marc($library, $item));
            $xml->endElement();
            $xml->endElement();
            return;
        }
        $record = $DB->get_record(self::T . 'record', ['id' => $item['id']], '*', MUST_EXIST);
        $by = self::authorities($item['id']);
        $xml->startElementNs('oai_dc', 'dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $xml->writeAttributeNs('xmlns', 'dc', null, 'http://purl.org/dc/elements/1.1/');
        $xml->writeAttributeNs('xmlns', 'xsi', null, 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttributeNs(
            'xsi',
            'schemaLocation',
            null,
            'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
        );
        $title = trim((string) $record->title . ((string) $record->subtitle !== '' ? ': ' . $record->subtitle : ''));
        $xml->writeElementNs('dc', 'title', null, $title);
        foreach ($by['author'] ?? [] as $author) {
            $xml->writeElementNs('dc', 'creator', null, $author);
        }
        foreach ($by['subject'] ?? [] as $subject) {
            $xml->writeElementNs('dc', 'subject', null, $subject);
        }
        if ((string) $record->summary !== '') {
            $xml->writeElementNs('dc', 'description', null, \core_text::substr(strip_tags((string) $record->summary), 0, 4000));
        }
        // What the centre publishes, it publishes; a book of the catalogue, its publisher.
        $publishers = in_array('catalogue', $item['sets'], true) ? ($by['publisher'] ?? [])
            : [format_string($library->name, true, ['escape' => false])];
        foreach ($publishers as $publisher) {
            $xml->writeElementNs('dc', 'publisher', null, $publisher);
        }
        $year = preg_match('/(\d{4})/', (string) $record->pubyear, $m) ? $m[1] : '';
        $xml->writeElementNs('dc', 'date', null, $year !== '' ? $year : gmdate('Y-m-d', $item['datestamp']));
        $xml->writeElementNs('dc', 'type', null, in_array($record->doctype, ['audio', 'video'], true)
            ? ($record->doctype === 'audio' ? 'Sound' : 'MovingImage') : 'Text');
        if ((string) $record->lang !== '') {
            $xml->writeElementNs('dc', 'language', null, $record->lang);
        }
        if ((string) $record->isbn !== '') {
            $xml->writeElementNs('dc', 'identifier', null, 'urn:isbn:' . $record->isbn);
        }
        if (in_array('catalogue', $item['sets'], true)) {
            $xml->writeElementNs('dc', 'identifier', null, (new \moodle_url(
                '/local/oksigeniaayvurenda/public.php',
                ['library' => $library->slug, 'record' => $item['id']]
            ))->out(false));
        }
        foreach ($item['items'] as $digital) {
            $xml->writeElementNs('dc', 'identifier', null, (new \moodle_url(
                '/local/oksigeniaayvurenda/read.php',
                ['library' => $library->slug, 'id' => $digital->id]
            ))->out(false));
            $file = $digital->kind === 'link' ? null : digital::file($digital);
            if ($file) {
                $xml->writeElementNs('dc', 'format', null, $file->get_mimetype());
            }
            $xml->writeElementNs('dc', 'rights', null, self::LICENCES[$digital->licence] ??
                get_string('licence' . str_replace('-', '', $digital->licence), 'local_oksigeniaayvurenda'));
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
    }

    /**
     * The authorities of a record, by kind, in their order: a class by its number, as 080 and 082 ask.
     *
     * @param int $recordid
     * @return array Kind => labels.
     */
    private static function authorities(int $recordid): array {
        global $DB;
        $by = [];
        foreach (
            $DB->get_records_sql(
                "SELECT ra.id, a.kind, a.label, a.code FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid
                  WHERE ra.recordid = ? ORDER BY ra.sortorder, ra.id",
                [$recordid]
            ) as $one
        ) {
            $by[$one->kind][] = $one->kind === 'class' && trim((string) $one->code) !== '' ? (string) $one->code : $one->label;
        }
        return $by;
    }

    /**
     * A record in MARCXML, as the daily copy writes it: its copies in this library (852, without their codes) and where
     * each of its digital forms is read (856).
     *
     * @param \stdClass $library
     * @param array $item
     * @return string
     */
    public static function marc(\stdClass $library, array $item): string {
        global $DB;
        $row = (array) $DB->get_record(self::T . 'record', ['id' => $item['id']], '*', MUST_EXIST);
        $copies = [];
        if (in_array('catalogue', $item['sets'], true)) {
            $rows = $DB->get_records_sql(
                "SELECT i.id, i.callnumber1, i.callnumber2, i.callnumber3, l.label AS location
                   FROM {" . self::T . "item} i
              LEFT JOIN {" . self::T . "authority} l ON l.id = i.locationid
                  WHERE i.recordid = ? AND i.libraryid = ? AND i.status <> 'withdrawn' AND i.status <> 'lost'
               ORDER BY i.id",
                [$item['id'], $library->id]
            );
            foreach ($rows as $copy) {
                $copies[] = ['barcode' => '', 'serial' => '', 'library' => format_string(
                    $library->name,
                    true,
                    ['escape' => false]
                ), 'location' => $copy->location, 'callnumber' => [$copy->callnumber1,
                    $copy->callnumber2, $copy->callnumber3], 'regnumber' => '', 'itemtype' => '', 'acquired' => ''];
            }
        }
        $entity = (string) $DB->get_field(self::T . 'entity', 'name', ['id' => $library->entityid]);
        $marc = \local_oksigeniaayvurenda\core\marc\encoder::encode($row, self::authorities($item['id']), $copies, format_string(
            $entity,
            true,
            ['escape' => false]
        ));
        foreach ($item['items'] as $digital) {
            $marc->data('856', '4', '0', [['u', (new \moodle_url(
                '/local/oksigeniaayvurenda/read.php',
                ['library' => $library->slug, 'id' => $digital->id]
            ))->out(false)], ['y', $digital->title !== '' && $digital->title !== null ? $digital->title : (string) $row['title']]]);
        }
        $element = \local_oksigeniaayvurenda\core\marc\marcxml::element($marc);
        return str_contains(substr($element, 0, 80), 'xmlns') ? $element
            : preg_replace('/^<record/', '<record xmlns="' . self::FORMATS['marc21'][1] . '"', $element);
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
     * A resumption token: where the list goes on (the datestamp and id of the last one given), the dates, the format and
     * the set asked.
     *
     * @param int[] $after
     * @param int|null $from
     * @param int|null $until
     * @param string $prefix
     * @param string $set
     * @return string
     */
    private static function token(array $after, ?int $from, ?int $until, string $prefix, string $set): string {
        return $after[0] . '_' . $after[1] . '-' . ($from ?? '') . '-' . ($until ?? '') . '-' . $prefix . '-' . $set;
    }

    /**
     * Reads a resumption token.
     *
     * @param string $token
     * @return array|null after, from, until, prefix and set.
     */
    private static function untoken(string $token): ?array {
        if (
            !preg_match('/^(\d+)_(\d+)-(\d*)-(\d*)-(\w+)-(\w*)$/', $token, $m) || !isset(self::FORMATS[$m[5]])
                || ($m[6] !== '' && !in_array($m[6], self::SETS, true))
        ) {
            return null;
        }
        return [[(int) $m[1], (int) $m[2]], $m[3] === '' ? null : (int) $m[3], $m[4] === '' ? null : (int) $m[4], $m[5], $m[6]];
    }
}
