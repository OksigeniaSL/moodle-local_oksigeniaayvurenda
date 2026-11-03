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
use local_oksigeniaayvurenda\core\isbn;
use local_oksigeniaayvurenda\core\readers\card_codes;
use local_oksigeniaayvurenda\core\text;
use local_oksigeniaayvurenda\local\years;

/**
 * Brings the catalogue of an Abies Web export into a library, faithfully: records, authors with their role,
 * classification with its hierarchy, subjects, publishers, places, copies with their own codes and register numbers,
 * copy and reader types, and loan rules. On request, also the readers, with their card codes and groups, and their
 * loans, out and returned; readers come without an account, to be matched with the site's accounts afterwards.
 *
 * It can run again with a newer export: each row keeps its Abies id and a fingerprint, so a second run adds what is
 * new, updates what changed in Abies, leaves the rest alone and never duplicates. What the library did here since wins:
 * a row changed here is not touched, one deleted here does not come back (nor a reader erased at their request), and a
 * copy made here with the code of its Abies label is recognised as that copy, with its record. A copy made here whose
 * new code Abies had printed on another copy gets a new code, to be relabelled: the printed label wins.
 */
final class abies_import {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Name of the source system. */
    private const SYSTEM = 'abies';

    /** @var array What the library can change in each kind of row: a row where any of it changed here is left alone. */
    private const OWN = [
        'authority' => ['kind', 'code', 'label', 'extra'],
        'record' => ['doctype', 'title', 'subtitle', 'responsibility', 'isbn', 'edition', 'pubplace', 'pubyear', 'lang',
            'extent', 'summary', 'notes'],
        'item' => ['recordid', 'barcode', 'regnumber', 'callnumber1', 'callnumber2', 'callnumber3', 'locationid', 'itemtype',
            'status', 'notes', 'timeacquired'],
        'reader' => ['cardcode', 'readertype', 'firstname', 'lastname', 'email'],
        'loan' => ['itemid', 'readerid', 'yearid', 'groupname', 'timeloaned', 'timedue', 'timereturned', 'renewals'],
    ];

    /** @var int */
    private int $entityid;

    /** @var int */
    private int $libraryid;

    /** @var bool Whether the free notes of the copies come in. */
    private bool $withnotes;

    /** @var bool Whether readers and loans come in. */
    private bool $withreaders;

    /** @var bool Whether the export is history: its copies come in on the shelf and its loans out as returned. */
    private bool $asreturned;

    /** @var int */
    private int $now;

    /** @var array Local id of each Abies id, by table: [table][externalid] => [localid, hash, hash as left here, source id]. */
    private array $map = [];

    /** @var array The rows there are now, by table: [table][id] => true. */
    private array $here = [];

    /** @var array The rows that came from Abies, by table: [table][localid] => true. */
    private array $fromabies = [];

    /** @var array Copy codes of the entity: code => item id. */
    private array $codes = [];

    /** @var array Records made here, by ISBN: isbn => record ids. */
    private array $madeisbns = [];

    /** @var array Title and ISBN of each record of the export, by Abies id. */
    private array $docs = [];

    /** @var array Copies made here that had a code Abies printed on another copy: [code, new code, title]. */
    private array $relabelled = [];

    /** @var string[] The codes of the copies of the export, which a new code made here must not take. */
    private array $exportcodes = [];

    /** @var array Authority id by kind and normalised label (or code, for classes). */
    private array $labels = [];

    /** @var array What happened. */
    private array $stats = [];

    /**
     * Prepares an import into a library.
     *
     * @param int $libraryid
     * @param bool $withnotes
     * @param bool $withreaders
     * @param bool $asreturned The export is old (the library has lent since, or will): the copies it had out come in on
     *     the shelf, the loans it had out come back on the day of the export, and the copies already here keep their status.
     */
    public function __construct(int $libraryid, bool $withnotes = true, bool $withreaders = false, bool $asreturned = false) {
        global $DB;
        $this->libraryid = $libraryid;
        $this->entityid = (int) $DB->get_field(self::T . 'library', 'entityid', ['id' => $libraryid], MUST_EXIST);
        $this->withnotes = $withnotes;
        $this->withreaders = $withreaders;
        $this->asreturned = $asreturned;
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
        $this->relabelled = [];
        $this->exportcodes = [];
        $this->load_maps();
        $transaction = $DB->start_delegated_transaction();
        $authorlinks = $this->authorities($export);
        $this->records($export, $authorlinks);
        $this->items($export);
        $this->types_and_rules($export);
        if ($this->withreaders) {
            $this->readers($export);
            $this->loans($export);
        }
        // Each copy in its place on the shelf, with the call numbers and titles that came.
        \local_oksigeniaayvurenda\local\stacks::refresh_keys($this->entityid);
        $transaction->allow_commit();
        \local_oksigeniaayvurenda\event\catalogue_imported::fire($this->entityid, null, ['how' => 'abies']);
        return $this->stats;
    }

    /**
     * Loads what earlier imports brought, and the authorities the entity already has.
     */
    private function load_maps(): void {
        global $DB;
        $this->map = ['authority' => [], 'record' => [], 'item' => [], 'reader' => [], 'loan' => []];
        $this->fromabies = ['authority' => [], 'record' => [], 'item' => [], 'reader' => [], 'loan' => []];
        $rows = $DB->get_recordset(
            self::T . 'source',
            ['entityid' => $this->entityid, 'system' => self::SYSTEM],
            '',
            'id, tablename, externalid, localid, hash, localhash'
        );
        foreach ($rows as $row) {
            $this->map[$row->tablename][$row->externalid] = [(int) $row->localid, $row->hash, $row->localhash, (int) $row->id];
            $this->fromabies[$row->tablename][(int) $row->localid] = true;
        }
        $rows->close();
        // What there is now, to tell what was deleted here.
        foreach (['authority', 'record', 'item', 'reader'] as $table) {
            $this->here[$table] = array_fill_keys(
                $DB->get_fieldset_select(self::T . $table, 'id', 'entityid = :entityid', ['entityid' => $this->entityid]),
                true
            );
        }
        $this->here['loan'] = array_fill_keys($DB->get_fieldset_sql(
            'SELECT l.id FROM {' . self::T . 'loan} l JOIN {' . self::T . 'library} b ON b.id = l.libraryid
              WHERE b.entityid = :entityid',
            ['entityid' => $this->entityid]
        ), true);
        $this->codes = [];
        $rows = $DB->get_recordset(self::T . 'item', ['entityid' => $this->entityid], '', 'id, barcode');
        foreach ($rows as $row) {
            $this->codes[strtoupper((string) $row->barcode)] = (int) $row->id;
        }
        $rows->close();
        $this->madeisbns = [];
        $rows = $DB->get_recordset_select(
            self::T . 'record',
            'entityid = :entityid AND isbn IS NOT NULL',
            ['entityid' => $this->entityid],
            '',
            'id, isbn'
        );
        foreach ($rows as $row) {
            if (!isset($this->fromabies['record'][(int) $row->id])) {
                $this->madeisbns[isbn::normalise((string) $row->isbn) ?? (string) $row->isbn][] = (int) $row->id;
            }
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
        // The codes of the copies of each record, to recognise a record made here by the label of one of its copies.
        $codesof = [];
        foreach ($export->items() as $item) {
            $code = strtoupper(trim($item['CodigoEjemplar'] ?? ''));
            if ($code !== '') {
                $codesof[$item['IdRegistro'] ?? ''][] = $code;
                $this->exportcodes[] = $code;
            }
        }
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
            $this->docs[$doc['id']] = ['title' => $data['title'], 'isbn' => $mapped['record']['isbn']];
            // Each author once in each role, in the order they came.
            $rows = [];
            $done = [];
            foreach ($links as $order => [$authorityid, $role]) {
                if (isset($done[$authorityid . '/' . $role])) {
                    continue;
                }
                $done[$authorityid . '/' . $role] = true;
                $rows[] = ['authorityid' => $authorityid, 'role' => $role, 'sortorder' => $order];
            }
            $own = array_map(fn($row) => [$row['authorityid'], $row['role']], $rows);
            if (!isset($this->map['record'][$doc['id']])) {
                $made = $this->made_here($doc['id'], $codesof[$doc['id']] ?? []);
                if ($made !== null) {
                    $this->adopt('record', $doc['id'], $made, sha1(json_encode([$data, $links])));
                    continue;
                }
            }
            [$recordid, $changed] = $this->upsert_tracked('record', $doc['id'], $data, $links, [], $own);
            if ($changed) {
                $DB->delete_records(self::T . 'recauth', ['recordid' => $recordid]);
                if ($rows) {
                    $DB->insert_records(self::T . 'recauth', array_map(fn($row) => ['recordid' => $recordid] + $row, $rows));
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
     * The copies, with their own codes. A code that another copy of the export already took gets the Abies id after
     * it, to be relabelled; the import reports it. A copy made here with the code of an Abies label is that copy, when
     * both are the same book; when they are not, the label Abies printed wins and the copy made here gets a new code.
     *
     * @param export_reader $export
     */
    private function items(export_reader $export): void {
        global $DB;
        $types = $export->item_types();
        $tz = \local_oksigeniaayvurenda\local\zones::of($this->entityid);
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $this->entityid]), true) ?: [];
        $seen = [];
        foreach ($export->items() as $item) {
            $abiesid = $item['Id'] ?? '';
            $docid = $item['IdRegistro'] ?? '';
            if (!isset($this->map['record'][$docid])) {
                $this->count('item', 'skipped');
                continue;
            }
            $seen[$abiesid] = true;
            $recordid = $this->live('record', $docid);
            if ($recordid === null) {
                // Its record was deleted here.
                $this->count('item', 'deletedhere');
                continue;
            }
            $known = $this->map['item'][$abiesid][0] ?? null;
            $barcode = strtoupper(trim($item['CodigoEjemplar'] ?? ''));
            $holder = $barcode !== '' ? ($this->codes[$barcode] ?? null) : null;
            if ($known === null && $holder !== null && !isset($this->fromabies['item'][$holder])) {
                $mine = $DB->get_record(self::T . 'item', ['id' => $holder], 'id, recordid', MUST_EXIST);
                if ((int) $mine->recordid === $recordid || $this->alike($docid, (int) $mine->recordid)) {
                    $data = $this->item_data($item, $recordid, $barcode, $types, $tz);
                    $this->adopt('item', $abiesid, $holder, sha1(json_encode([$data, []])));
                    continue;
                }
                $new = card_codes::next(
                    array_merge(
                        array_keys($this->codes),
                        $this->exportcodes,
                        \local_oksigeniaayvurenda\local\cataloguer::below_first_code($this->entityid)
                    ),
                    1,
                    !empty($settings['controlletter'])
                )[0];
                $DB->update_record(self::T . 'item', (object) ['id' => $holder, 'barcode' => $new, 'timemodified' => $this->now]);
                $this->codes[$new] = $holder;
                unset($this->codes[$barcode]);
                $this->relabelled[] = [$barcode, $new,
                    (string) $DB->get_field(self::T . 'record', 'title', ['id' => $mine->recordid])];
                $this->count('item', 'relabelled');
                $holder = null;
            }
            if ($barcode === '' || ($holder !== null && $holder !== $known)) {
                $barcode = ($barcode === '' ? 'ABIES' : $barcode) . '-' . $abiesid;
                $this->count('item', 'recoded');
            }
            $data = $this->item_data($item, $recordid, $barcode, $types, $tz);
            $oninsert = ['libraryid' => $this->libraryid];
            if ($this->asreturned) {
                // What an old export had out is on the shelf now, or the desk says otherwise.
                $oninsert['status'] = 'available';
            }
            [$itemid] = $this->upsert_tracked('item', $abiesid, $data, [], $oninsert);
            if ($itemid) {
                $this->codes[$barcode] = $itemid;
            }
        }
        $this->stats['item']['missing'] = count(array_diff_key($this->map['item'], $seen));
    }

    /**
     * A copy of the export as a row of the copies table. From an old export, without its status: a copy already here
     * keeps whatever the desk has done with it since.
     *
     * @param array $item
     * @param int $recordid
     * @param string $barcode
     * @param array $types
     * @param \DateTimeZone $tz
     * @return array
     */
    private function item_data(array $item, int $recordid, string $barcode, array $types, \DateTimeZone $tz): array {
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
        if ($this->asreturned) {
            unset($data['status']);
        }
        return $data;
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
        // Abies codes end with a control letter: the desk checks it before asking the server.
        $settings['controlletter'] = $settings['controlletter'] ?? true;
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
     * The readers, without an account, with their card codes and their group of the school year of the export. A card
     * code taken by a reader that came from somewhere else (a cohort) goes to the Abies reader, because that card is the
     * one printed; the other reader gets a new code. Two Abies readers with the same code: the second gets its id after.
     *
     * @param export_reader $export
     */
    private function readers(export_reader $export): void {
        global $DB;
        $types = $export->reader_types();
        $groups = $export->groups();
        $year = years::around($this->entityid, $this->export_moment($export));
        $codes = $DB->get_records_menu(self::T . 'reader', ['entityid' => $this->entityid], '', 'cardcode, id');
        $fromabies = array_flip(array_map(fn($row) => $row[0], $this->map['reader']));
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $this->entityid]), true) ?: [];
        foreach ($export->readers() as $row) {
            $abiesid = $row['Id'] ?? '';
            if (!isset($this->map['reader'][$abiesid]) && isset($this->map['reader'][self::erased($abiesid)])) {
                // Erased here at their request: they do not come back.
                $this->count('reader', 'erased');
                continue;
            }
            $known = $this->map['reader'][$abiesid][0] ?? null;
            $code = strtoupper(trim($row['CodLector'] ?? ''));
            $holder = $code !== '' ? (int) ($codes[$code] ?? 0) : 0;
            if ($code === '' || ($holder && $holder !== $known && isset($fromabies[$holder]))) {
                $code = ($code === '' ? 'ABIES' : $code) . '-' . $abiesid;
                $this->count('reader', 'recoded');
            } else if ($holder && $holder !== $known) {
                $new = \local_oksigeniaayvurenda\core\readers\card_codes::next(
                    array_keys($codes),
                    1,
                    !empty($settings['controlletter'])
                )[0];
                $DB->set_field(self::T . 'reader', 'cardcode', $new, ['id' => $holder]);
                $codes[$new] = $holder;
                $this->count('reader', 'gavecode');
            }
            $type = $types[$row['IdTipoLector'] ?? ''] ?? null;
            $data = [
                'cardcode' => $code,
                'readertype' => mapper::reader_type($type['name'] ?? 'student'),
                'firstname' => mapper::clean($row['Nombre'] ?? '') ?: null,
                'lastname' => mapper::clean(($row['Apellido1'] ?? '') . ' ' . ($row['Apellido2'] ?? '')) ?: null,
                'email' => ($row['Email'] ?? '') !== '' ? $row['Email'] : null,
            ];
            [$readerid] = $this->upsert_tracked('reader', $abiesid, $data, [], ['status' => 'active']);
            if (!$readerid) {
                continue;
            }
            $codes[$code] = $readerid;
            $fromabies[$readerid] = true;
            $group = $groups[$row['Curso'] ?? ''] ?? '';
            if ($group !== '' && !$DB->record_exists(self::T . 'member', ['readerid' => $readerid, 'yearid' => $year->id])) {
                $DB->insert_record(self::T . 'member', ['readerid' => $readerid, 'yearid' => $year->id,
                    'groupname' => $group, 'source' => 'abies', 'timecreated' => $this->now]);
            }
        }
    }

    /**
     * The loans, out and returned, each in its school year and, when the library knows it, with the reader's group.
     *
     * @param export_reader $export
     */
    private function loans(export_reader $export): void {
        global $DB;
        $tz = \local_oksigeniaayvurenda\local\zones::of($this->entityid);
        $date = function (?string $value, bool $endofday = false) use ($tz): ?int {
            if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return null;
            }
            $day = new \DateTimeImmutable(substr($value, 0, 10), $tz);
            return ($endofday ? $day->setTime(23, 59, 59) : $day->setTime(12, 0))->getTimestamp();
        };
        // An old export's loans still out come back on its day: they are history, not books a year late.
        $back = $this->asreturned ? $this->export_moment($export) : null;
        foreach ($export->loans() as $row) {
            $itemid = $this->live('item', $row['IdEjemplar'] ?? '');
            $readerid = $this->live('reader', $row['IdLector'] ?? '');
            $loaned = $date($row['Fecha'] ?? null);
            if ($itemid === null || $readerid === null || $loaned === null || ($row['Anulado'] ?? '0') === '1') {
                $this->count('loan', 'skipped');
                continue;
            }
            $year = years::around($this->entityid, $loaned);
            $group = $DB->get_field(
                self::T . 'member',
                'groupname',
                ['readerid' => $readerid, 'yearid' => $year->id],
                IGNORE_MULTIPLE
            );
            $data = [
                'itemid' => $itemid,
                'readerid' => $readerid,
                'yearid' => (int) $year->id,
                'groupname' => $group ?: null,
                'timeloaned' => $loaned,
                'timedue' => $date($row['FechaCalculada'] ?? null, true) ?? $loaned,
                'timereturned' => $date($row['FechaDevolucion'] ?? null) ?? ($back !== null ? max($back, $loaned) : null),
                'renewals' => (int) ($row['NumProrrogas'] ?? 0),
            ];
            $this->upsert_tracked('loan', $row['Id'] ?? '', $data, [], ['libraryid' => $this->libraryid]);
        }
    }

    /**
     * When the export was made, as far as its dates tell: the latest loan or reader registration.
     *
     * @param export_reader $export
     * @return int
     */
    private function export_moment(export_reader $export): int {
        $latest = '';
        foreach ($export->loans() as $row) {
            $latest = max(
                $latest,
                substr((string) ($row['FechaDevolucion'] ?? ''), 0, 10),
                substr((string) ($row['Fecha'] ?? ''), 0, 10)
            );
        }
        foreach ($export->readers() as $row) {
            $latest = max($latest, substr((string) ($row['FechaAlta'] ?? ''), 0, 10));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $latest)) {
            return $this->now;
        }
        return (new \DateTimeImmutable($latest, \local_oksigeniaayvurenda\local\zones::of($this->entityid)))
            ->setTime(12, 0)->getTimestamp();
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
     * Inserts or updates a row that came from Abies; a row whose fingerprint did not change is left alone, and so is
     * one changed here since it came. One deleted here stays deleted, except an authority, which the records need.
     *
     * @param string $table
     * @param string $externalid
     * @param array $data Fields from Abies.
     * @param array $alsohash More data that counts for the fingerprint (the links of a record).
     * @param array $oninsert Fields only set when the row is new.
     * @param array|null $links The authorities of a record, each once, as they are written: [authority id, role].
     * @return array [local id, or 0 when it was deleted here; whether it was written].
     */
    private function upsert_tracked(
        string $table,
        string $externalid,
        array $data,
        array $alsohash = [],
        array $oninsert = [],
        ?array $links = null
    ): array {
        global $DB;
        $hash = sha1(json_encode([$data, $alsohash]));
        $known = $this->map[$table][$externalid] ?? null;
        $exists = $known !== null && isset($this->here[$table][$known[0]]);
        if ($known !== null && !$exists && $table !== 'authority') {
            $this->count($table, 'deletedhere');
            return [0, false];
        }
        if ($exists && $known[1] === $hash) {
            $this->count($table, 'unchanged');
            return [$known[0], false];
        }
        if ($exists) {
            $row = $DB->get_record(self::T . $table, ['id' => $known[0]], '*', MUST_EXIST);
            if ($known[2] !== null && self::own_hash($table, $row, $this->links_here($table, $known[0])) !== $known[2]) {
                $this->count($table, 'changedhere');
                return [$known[0], false];
            }
            if ($table === 'record' && isset($data['extra'])) {
                // What was added here, as its cover, stays.
                $data['extra'] = json_encode(array_merge(
                    json_decode((string) $row->extra, true) ?: [],
                    json_decode($data['extra'], true) ?: []
                ));
            }
            $DB->update_record(self::T . $table, (object) (['id' => $known[0], 'timemodified' => $this->now] + $data));
            $own = self::own_hash($table, $data + (array) $row, $links);
            $DB->update_record(self::T . 'source', (object) ['id' => $known[3], 'hash' => $hash, 'localhash' => $own]);
            $this->map[$table][$externalid] = [$known[0], $hash, $own, $known[3]];
            $this->count($table, 'updated');
            return [$known[0], true];
        }
        if ($known !== null) {
            // An authority deleted here: forget the old link and bring it back.
            $DB->delete_records(self::T . 'source', ['id' => $known[3]]);
        }
        $row = ['entityid' => $this->entityid, 'timecreated' => $this->now, 'timemodified' => $this->now] + $oninsert + $data;
        $id = (int) $DB->insert_record(self::T . $table, (object) $row);
        $own = self::own_hash($table, $row, $links);
        $sourceid = (int) $DB->insert_record(self::T . 'source', [
            'entityid' => $this->entityid,
            'system' => self::SYSTEM,
            'tablename' => $table,
            'externalid' => $externalid,
            'localid' => $id,
            'hash' => $hash,
            'localhash' => $own,
            'timecreated' => $this->now,
        ]);
        $this->map[$table][$externalid] = [$id, $hash, $own, $sourceid];
        $this->here[$table][$id] = true;
        $this->fromabies[$table][$id] = true;
        $this->count($table, 'created');
        return [$id, true];
    }

    /**
     * Takes a row made here as the one of the export, as it is: from now on it updates like one that came from Abies.
     *
     * @param string $table record or item.
     * @param string $externalid
     * @param int $localid
     * @param string $hash The fingerprint of what the export has.
     */
    private function adopt(string $table, string $externalid, int $localid, string $hash): void {
        global $DB;
        $row = $DB->get_record(self::T . $table, ['id' => $localid], '*', MUST_EXIST);
        $own = self::own_hash($table, $row, $this->links_here($table, $localid));
        $sourceid = (int) $DB->insert_record(self::T . 'source', [
            'entityid' => $this->entityid,
            'system' => self::SYSTEM,
            'tablename' => $table,
            'externalid' => $externalid,
            'localid' => $localid,
            'hash' => $hash,
            'localhash' => $own,
            'timecreated' => $this->now,
        ]);
        $this->map[$table][$externalid] = [$localid, $hash, $own, $sourceid];
        $this->fromabies[$table][$localid] = true;
        $this->count($table, 'madehere');
    }

    /**
     * The record made here that is this one of the export: the one of a copy made here with the code of one of its
     * Abies labels, when it is the same book; else the only one made here with its ISBN.
     *
     * @param string $docid
     * @param string[] $codes The codes of its copies in the export.
     * @return int|null
     */
    private function made_here(string $docid, array $codes): ?int {
        global $DB;
        foreach ($codes as $code) {
            $holder = $this->codes[$code] ?? null;
            if ($holder === null || isset($this->fromabies['item'][$holder])) {
                continue;
            }
            $recordid = (int) $DB->get_field(self::T . 'item', 'recordid', ['id' => $holder]);
            if (!isset($this->fromabies['record'][$recordid]) && $this->alike($docid, $recordid)) {
                return $recordid;
            }
        }
        $isbn = $this->docs[$docid]['isbn'] ?? null;
        $made = array_values(array_filter(
            $this->madeisbns[$isbn ?? ''] ?? [],
            fn($id) => !isset($this->fromabies['record'][$id])
        ));
        return $isbn !== null && count($made) === 1 ? $made[0] : null;
    }

    /**
     * Whether a record here is the same book as one of the export: the same ISBN when both have one, else titles that
     * share most of their words, or all those of the shorter one.
     *
     * @param string $docid
     * @param int $recordid
     * @return bool
     */
    private function alike(string $docid, int $recordid): bool {
        global $DB;
        $doc = $this->docs[$docid] ?? null;
        $record = $DB->get_record(self::T . 'record', ['id' => $recordid], 'id, title, isbn');
        if ($doc === null || !$record) {
            return false;
        }
        $isbn = trim((string) $record->isbn) !== '' ? (isbn::normalise((string) $record->isbn) ?? trim($record->isbn)) : null;
        if ($doc['isbn'] !== null && $isbn !== null) {
            return $doc['isbn'] === $isbn;
        }
        [$a, $b] = [text::words((string) $doc['title']), text::words((string) $record->title)];
        if (!$a || !$b) {
            return false;
        }
        $shared = count(array_intersect($a, $b));
        return $shared === min(count($a), count($b)) || $shared / count(array_unique(array_merge($a, $b))) >= 0.5;
    }

    /**
     * The authorities of a record as they are here, in their order; null for any other table.
     *
     * @param string $table
     * @param int $id
     * @return array|null [authority id, role]
     */
    private function links_here(string $table, int $id): ?array {
        global $DB;
        if ($table !== 'record') {
            return null;
        }
        return array_map(
            fn($row) => [(int) $row->authorityid, $row->role],
            array_values($DB->get_records(self::T . 'recauth', ['recordid' => $id], 'sortorder, id', 'id, authorityid, role'))
        );
    }

    /**
     * The fingerprint of what the library can change in a row, as the database gives it back.
     *
     * @param string $table
     * @param array|\stdClass $row
     * @param array|null $links The authorities of a record: [authority id, role].
     * @return string
     */
    public static function own_hash(string $table, $row, ?array $links = null): string {
        $row = (array) $row;
        $values = [];
        foreach (self::OWN[$table] as $field) {
            $values[] = isset($row[$field]) ? (string) $row[$field] : null;
        }
        if ($links !== null) {
            $values[] = array_map(fn($link) => (int) $link[0] . '/' . (string) ($link[1] ?? ''), $links);
        }
        return sha1(json_encode($values));
    }

    /**
     * The mark an Abies reader erased at their request leaves, so that no later import brings them back: a fingerprint
     * of their Abies id, never the id itself.
     *
     * @param string $externalid
     * @return string
     */
    public static function erased(string $externalid): string {
        return 'erased:' . sha1(get_site_identifier() . ':' . $externalid);
    }

    /**
     * Takes what came from Abies into the entities as it is now as their starting point: from then on, what changes
     * here is the library's. For what came before that was kept.
     *
     * @param int|null $entityid One entity, or every one.
     */
    public static function settle(?int $entityid = null): void {
        global $DB;
        $where = 's.system = :system AND s.tablename = :tablename AND s.localhash IS NULL';
        $params = ['system' => self::SYSTEM];
        if ($entityid !== null) {
            $where .= ' AND s.entityid = :entityid';
            $params['entityid'] = $entityid;
        }
        foreach (array_keys(self::OWN) as $table) {
            $links = [];
            if ($table === 'record') {
                $rows = $DB->get_recordset_sql(
                    'SELECT ra.id, ra.recordid, ra.authorityid, ra.role
                       FROM {' . self::T . 'recauth} ra
                       JOIN {' . self::T . "source} s ON s.localid = ra.recordid
                      WHERE $where
                   ORDER BY ra.recordid, ra.sortorder, ra.id",
                    $params + ['tablename' => $table]
                );
                foreach ($rows as $row) {
                    $links[(int) $row->recordid][] = [(int) $row->authorityid, $row->role];
                }
                $rows->close();
            }
            $rows = $DB->get_recordset_sql(
                'SELECT s.id AS sourceid, t.*
                   FROM {' . self::T . 'source} s
                   JOIN {' . self::T . $table . "} t ON t.id = s.localid
                  WHERE $where",
                $params + ['tablename' => $table]
            );
            foreach ($rows as $row) {
                $own = self::own_hash($table, $row, $table === 'record' ? ($links[(int) $row->id] ?? []) : null);
                $DB->set_field(self::T . 'source', 'localhash', $own, ['id' => $row->sourceid]);
            }
            $rows->close();
        }
    }

    /**
     * Copies made here that had a code Abies printed on another copy, with the new code they got.
     *
     * @return array [code they had, new code, title]
     */
    public function relabelled(): array {
        return $this->relabelled;
    }

    /**
     * The local id of a row of the export, when it is still here.
     *
     * @param string $table
     * @param string $externalid
     * @return int|null
     */
    private function live(string $table, string $externalid): ?int {
        $id = $this->map[$table][$externalid][0] ?? null;
        return $id !== null && isset($this->here[$table][$id]) ? $id : null;
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
