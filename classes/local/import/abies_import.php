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
 * Importing an Abies Web export into a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\import;

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\core\abies\mapper;
use local_oksigeniaayvurenda\core\text;

/**
 * Brings the catalogue of an Abies Web export into a library, faithfully: records, authors with their role,
 * classification with its hierarchy, subjects, publishers, places, copies with their own codes and register numbers,
 * copy and reader types, and loan rules. Readers and loans come later.
 *
 * It can run again with a newer export: each row keeps its Abies id and a fingerprint, so a second run adds what is
 * new, updates what changed, leaves the rest alone and never duplicates. While a library still works in Abies, Abies
 * wins on what comes from it; what only exists in Ayvurenda is not touched.
 */
final class abies_import {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Name of the source system. */
    private const SYSTEM = 'abies';

    /** @var int */
    private int $entityid;

    /** @var int */
    private int $libraryid;

    /** @var bool Whether the free notes of the copies come in. */
    private bool $withnotes;

    /** @var int */
    private int $now;

    /** @var array Local id of each Abies id, by table: [table][externalid] => [localid, hash]. */
    private array $map = [];

    /** @var array Authority id by kind and normalised label (or code, for classes). */
    private array $labels = [];

    /** @var array What happened. */
    private array $stats = [];

    /**
     * Prepares an import into a library.
     *
     * @param int $libraryid
     * @param bool $withnotes
     */
    public function __construct(int $libraryid, bool $withnotes = true) {
        global $DB;
        $this->libraryid = $libraryid;
        $this->entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $libraryid], MUST_EXIST);
        $this->withnotes = $withnotes;
        $this->now = time();
    }

    /**
     * Imports the catalogue.
     *
     * @param export_reader $export
     * @return array What was created, updated, left alone and set aside, by table.
     */
    public function run(export_reader $export): array {
        global $DB;
        $this->stats = [];
        $this->load_maps();
        $transaction = $DB->start_delegated_transaction();
        $authorlinks = $this->authorities($export);
        $this->records($export, $authorlinks);
        $this->items($export);
        $this->types_and_rules($export);
        $transaction->allow_commit();
        return $this->stats;
    }

    /**
     * Loads what earlier imports brought, and the authorities the entity already has.
     */
    private function load_maps(): void {
        global $DB;
        $this->map = ['authority' => [], 'record' => [], 'item' => []];
        $rows = $DB->get_recordset(
            self::T . 'source',
            ['entityid' => $this->entityid, 'system' => self::SYSTEM],
            '',
            'id, tablename, externalid, localid, hash'
        );
        foreach ($rows as $row) {
            $this->map[$row->tablename][$row->externalid] = [(int) $row->localid, $row->hash];
        }
        $rows->close();
        $this->labels = [];
        $rows = $DB->get_recordset(self::T . 'authority', ['entityid' => $this->entityid], '', 'id, kind, code, label');
        foreach ($rows as $row) {
            $this->labels[$row->kind][self::label_key($row->kind, $row->code ?? $row->label)] = (int) $row->id;
        }
        $rows->close();
    }

    /**
     * The authority tables. Returns the author links of each record, which come in a table of their own.
     *
     * @param export_reader $export
     * @return array Abies record id => list of [authority id, role].
     */
    private function authorities(export_reader $export): array {
        global $DB;
        $parents = [];
        foreach ($export->authorities() as $row) {
            $f = $row['fields'];
            $kind = mapper::AUTHORITY_KINDS[$row['table']] ?? null;
            if ($kind === null) {
                continue;
            }
            $code = null;
            $extra = [];
            switch ($row['table']) {
                case 'Autor':
                    $label = $f['Nombre'][0] ?? '';
                    foreach (
                        ['TipoAutor' => 'type', 'FechaAsociada' => 'dates', 'Lugar' => 'place',
                            'Tratamiento' => 'title'] as $field => $key
                    ) {
                        if (isset($f[$field][0])) {
                            $extra[$key] = $field === 'TipoAutor' ? mb_strtolower($f[$field][0]) : $f[$field][0];
                        }
                    }
                    break;
                case 'CDU':
                    $code = $f['Codigo'][0] ?? '';
                    $label = $f['Literal'][0] ?? $code;
                    if (!empty($f['CodigoPadre'][0])) {
                        $parents[$code] = $f['CodigoPadre'][0];
                    }
                    break;
                case 'Descriptor':
                    $label = $f['descripcion'][0] ?? '';
                    break;
                case 'Editorial':
                    $label = $f['Editorial'][0] ?? '';
                    break;
                case 'Ubicacion':
                    $label = $f['Ubicacion'][0] ?? '';
                    break;
                default:
                    $label = $f['nombre'][0] ?? '';
            }
            $label = mapper::clean($label);
            if ($label === '') {
                continue;
            }
            $data = ['kind' => $kind, 'code' => $code, 'label' => $label, 'extra' => $extra ? json_encode($extra) : null];
            $id = $this->upsert('authority', $row['table'] . ':' . $row['id'], $data);
            $this->labels[$kind][self::label_key($kind, $code ?? $label)] = $id;
        }
        foreach ($parents as $code => $parentcode) {
            $id = $this->labels['class'][self::label_key('class', $code)] ?? null;
            $parentid = $this->labels['class'][self::label_key('class', $parentcode)] ?? null;
            if ($id !== null && $parentid !== null) {
                $DB->set_field(self::T . 'authority', 'parentid', $parentid, ['id' => $id]);
            }
        }
        // The author links, in a second pass: in the file they may come before the authors.
        $links = [];
        foreach ($export->authorities() as $row) {
            if ($row['table'] !== 'FondoAutor') {
                continue;
            }
            $f = $row['fields'];
            $authorid = $this->map['authority']['Autor:' . ($f['IdDocumentoRelacionado'][0] ?? '')][0] ?? null;
            if ($authorid === null) {
                $this->count('authorlink', 'skipped');
                continue;
            }
            $links[$f['IdDocumentoPrincipal'][0] ?? ''][] = [$authorid, mapper::role($f['ValorRelacionado'][0] ?? null)];
        }
        return $links;
    }

    /**
     * The records, with their authors, publishers, classification, subjects, collection and audience.
     *
     * @param export_reader $export
     * @param array $authorlinks
     */
    private function records(export_reader $export, array $authorlinks): void {
        global $DB;
        $seen = [];
        foreach ($export->documents() as $doc) {
            $seen[$doc['id']] = true;
            $mapped = mapper::record($doc);
            $links = [];
            $words = [$mapped['record']['title'], $mapped['record']['subtitle']];
            if (!empty($authorlinks[$doc['id']])) {
                foreach ($authorlinks[$doc['id']] as [$authorid, $role]) {
                    $links[] = [$authorid, $role];
                }
                $words = array_merge($words, $mapped['authorities']['author']);
            } else {
                foreach ($mapped['authorities']['author'] as $name) {
                    $links[] = [$this->authority('author', $name), 'author'];
                    $words[] = $name;
                }
            }
            foreach (['publisher', 'class', 'subject', 'collection', 'audience'] as $kind) {
                foreach ($mapped['authorities'][$kind] as $label) {
                    $links[] = [$this->authority($kind, $label), null];
                    if ($kind === 'subject' || $kind === 'collection') {
                        $words[] = $label;
                    }
                }
            }
            $extra = $mapped['extra'] + ['abiestype' => $doc['name']];
            $data = $mapped['record'] + [
                'extra' => json_encode($extra),
                'searchtext' => text::normalise(implode(' ', array_filter($words))),
            ];
            if ($data['title'] === '') {
                $data['title'] = '?';
            }
            [$recordid, $changed] = $this->upsert_tracked('record', $doc['id'], $data, $links);
            if ($changed) {
                $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
                $rows = [];
                $done = [];
                foreach ($links as $order => [$authorityid, $role]) {
                    if (isset($done[$authorityid . '/' . $role])) {
                        continue;
                    }
                    $done[$authorityid . '/' . $role] = true;
                    $rows[] = ['recordid' => $recordid, 'authorityid' => $authorityid, 'role' => $role, 'sortorder' => $order];
                }
                if ($rows) {
                    $DB->insert_records(self::T . 'recauth', $rows);
                }
            }
        }
        // Author links of records that are not in the export.
        foreach (array_diff_key($authorlinks, $seen) as $orphans) {
            foreach ($orphans as $unused) {
                $this->count('authorlink', 'skipped');
            }
        }
    }

    /**
     * The copies, with their own codes. A code already taken by another copy gets the Abies id after it, to be
     * relabelled; the import reports it.
     *
     * @param export_reader $export
     */
    private function items(export_reader $export): void {
        global $DB;
        $types = $export->item_types();
        $barcodes = $DB->get_records_menu(self::T . 'item', ['entityid' => $this->entityid], '', 'barcode, id');
        $tz = \core_date::get_server_timezone_object();
        $seen = [];
        foreach ($export->items() as $item) {
            $abiesid = $item['Id'] ?? '';
            $recordid = $this->map['record'][$item['IdRegistro'] ?? ''][0] ?? null;
            if ($recordid === null) {
                $this->count('item', 'skipped');
                continue;
            }
            $seen[$abiesid] = true;
            $known = $this->map['item'][$abiesid][0] ?? null;
            $barcode = strtoupper(trim($item['CodigoEjemplar'] ?? ''));
            if ($barcode === '' || (isset($barcodes[$barcode]) && (int) $barcodes[$barcode] !== $known)) {
                $barcode = ($barcode === '' ? 'ABIES' : $barcode) . '-' . $abiesid;
                $this->count('item', 'recoded');
            }
            $location = null;
            if (($item['Ubicacion'] ?? '-1') !== '-1') {
                $location = $this->map['authority']['Ubicacion:' . $item['Ubicacion']][0] ?? null;
            }
            $acquired = null;
            if (!empty($item['Fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $item['Fecha'])) {
                $acquired = (new \DateTimeImmutable(substr($item['Fecha'], 0, 10), $tz))->getTimestamp();
            }
            $data = [
                'recordid' => $recordid,
                'barcode' => $barcode,
                'regnumber' => ($item['NumRegistro'] ?? '') !== '' ? $item['NumRegistro'] : null,
                'callnumber1' => ($item['Signatura1'] ?? '') !== '' ? $item['Signatura1'] : null,
                'callnumber2' => ($item['Signatura2'] ?? '') !== '' ? $item['Signatura2'] : null,
                'callnumber3' => ($item['Signatura3'] ?? '') !== '' ? $item['Signatura3'] : null,
                'locationid' => $location,
                'itemtype' => mapper::item_type($types[$item['IdTipoEjemplar'] ?? ''] ?? 'normal'),
                'status' => mapper::item_status($item['Estado'] ?? null),
                'notes' => $this->withnotes && ($item['Observaciones'] ?? '') !== '' ? $item['Observaciones'] : null,
                'timeacquired' => $acquired,
            ];
            [$itemid] = $this->upsert_tracked('item', $abiesid, $data, [], ['libraryid' => $this->libraryid]);
            $barcodes[$barcode] = $itemid;
        }
        $this->stats['item']['missing'] = count(array_diff_key($this->map['item'], $seen));
    }

    /**
     * The copy and reader types, kept in the entity settings, and the loan rules, when the library has none yet.
     *
     * @param export_reader $export
     */
    private function types_and_rules(export_reader $export): void {
        global $DB;
        $itemtypes = [];
        foreach ($export->item_types() as $name) {
            $itemtypes[mapper::item_type($name)] = $name;
        }
        $readertypes = [];
        $readerinfo = $export->reader_types();
        foreach ($readerinfo as $info) {
            $readertypes[mapper::reader_type($info['name'])] = $info['name'];
        }
        $entity = $DB->get_record(self::T . 'entity', ['id' => $this->entityid], 'id, settings', MUST_EXIST);
        $settings = json_decode($entity->settings ?? '', true) ?: [];
        $settings['itemtypes'] = ($settings['itemtypes'] ?? []) + $itemtypes;
        $settings['readertypes'] = ($settings['readertypes'] ?? []) + $readertypes;
        $settings['classification'] = $settings['classification'] ?? 'udc';
        $DB->update_record(self::T . 'entity', (object) ['id' => $entity->id, 'settings' => json_encode($settings),
            'timemodified' => $this->now]);

        if ($DB->record_exists(self::T . 'rule', ['libraryid' => $this->libraryid])) {
            $this->count('rule', 'kept');
            return;
        }
        $types = $export->item_types();
        $done = [];
        foreach ($export->rules() as $rule) {
            $reader = $readerinfo[$rule['IdTipoLector'] ?? ''] ?? null;
            if ($reader === null || !isset($types[$rule['IdTipoEjemplar'] ?? ''])) {
                continue;
            }
            $readertype = mapper::reader_type($reader['name']);
            $itemtype = mapper::item_type($types[$rule['IdTipoEjemplar']]);
            if (isset($done[$readertype . '/' . $itemtype])) {
                continue;
            }
            $done[$readertype . '/' . $itemtype] = true;
            $DB->insert_record(self::T . 'rule', [
                'libraryid' => $this->libraryid,
                'readertype' => $readertype,
                'itemtype' => $itemtype,
                'loandays' => (int) ($rule['DiasPrestamo'] ?? 0),
                'maxloans' => (int) ($rule['NumPrestamos'] ?? 0),
                'renewals' => (int) ($reader['VecesRenovacion'] ?? 0),
                'renewdays' => (int) ($reader['DiasRenovacion'] ?? 0),
                'timecreated' => $this->now,
                'timemodified' => $this->now,
            ]);
            $this->count('rule', 'created');
        }
    }

    /**
     * The authority of a kind with a label, created when the entity does not have it yet.
     *
     * @param string $kind
     * @param string $label
     * @return int
     */
    private function authority(string $kind, string $label): int {
        $key = self::label_key($kind, $label);
        if (isset($this->labels[$kind][$key])) {
            return $this->labels[$kind][$key];
        }
        $data = ['kind' => $kind, 'code' => $kind === 'class' ? $label : null, 'label' => $label, 'extra' => null];
        $id = $this->upsert('authority', 'label:' . $kind . ':' . $key, $data);
        $this->labels[$kind][$key] = $id;
        return $id;
    }

    /**
     * Inserts or updates a row that came from Abies, without a fingerprint.
     *
     * @param string $table
     * @param string $externalid
     * @param array $data
     * @return int The local id.
     */
    private function upsert(string $table, string $externalid, array $data): int {
        return $this->upsert_tracked($table, $externalid, $data)[0];
    }

    /**
     * Inserts or updates a row that came from Abies; a row whose fingerprint did not change is left alone.
     *
     * @param string $table
     * @param string $externalid
     * @param array $data Fields from Abies.
     * @param array $alsohash More data that counts for the fingerprint (the links of a record).
     * @param array $oninsert Fields only set when the row is new.
     * @return array [local id, whether it was written].
     */
    private function upsert_tracked(
        string $table,
        string $externalid,
        array $data,
        array $alsohash = [],
        array $oninsert = []
    ): array {
        global $DB;
        $hash = sha1(json_encode([$data, $alsohash]));
        $known = $this->map[$table][$externalid] ?? null;
        if ($known !== null && $known[1] === $hash) {
            $this->count($table, 'unchanged');
            return [$known[0], false];
        }
        if ($known !== null && $DB->record_exists(self::T . $table, ['id' => $known[0]])) {
            $DB->update_record(self::T . $table, (object) (['id' => $known[0], 'timemodified' => $this->now] + $data));
            $DB->set_field(self::T . 'source', 'hash', $hash, ['entityid' => $this->entityid, 'system' => self::SYSTEM,
                'tablename' => $table, 'externalid' => $externalid]);
            $this->map[$table][$externalid] = [$known[0], $hash];
            $this->count($table, 'updated');
            return [$known[0], true];
        }
        if ($known !== null) {
            // The row was deleted here: forget the old link and bring it back.
            $DB->delete_records(self::T . 'source', ['entityid' => $this->entityid, 'system' => self::SYSTEM,
                'tablename' => $table, 'externalid' => $externalid]);
        }
        $id = (int) $DB->insert_record(self::T . $table, (object) (['entityid' => $this->entityid,
            'timecreated' => $this->now, 'timemodified' => $this->now] + $oninsert + $data));
        $DB->insert_record(self::T . 'source', [
            'entityid' => $this->entityid,
            'system' => self::SYSTEM,
            'tablename' => $table,
            'externalid' => $externalid,
            'localid' => $id,
            'hash' => $hash,
            'timecreated' => $this->now,
        ]);
        $this->map[$table][$externalid] = [$id, $hash];
        $this->count($table, 'created');
        return [$id, true];
    }

    /**
     * Counts something that happened.
     *
     * @param string $table
     * @param string $what
     */
    private function count(string $table, string $what): void {
        $this->stats[$table][$what] = ($this->stats[$table][$what] ?? 0) + 1;
    }

    /**
     * The key to find an authority again: classes by their code, the rest by their label without accents or case.
     *
     * @param string $kind
     * @param string $value
     * @return string
     */
    private static function label_key(string $kind, string $value): string {
        return $kind === 'class' ? trim($value) : text::normalise($value);
    }
}
