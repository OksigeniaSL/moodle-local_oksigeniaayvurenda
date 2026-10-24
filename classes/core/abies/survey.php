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
 * What an Abies export brings, before importing it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\abies;

use local_oksigeniaayvurenda\core\control_letter;

/**
 * Counts what an export brings and lists what the librarian should know before importing it: repeated codes, copies of
 * records that do not exist, records without copies, languages that cannot be told, copies without a place. It reads
 * no names of people.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class survey {
    /**
     * The report of an export.
     *
     * @param export_reader $export
     * @return array
     */
    public static function run(export_reader $export): array {
        $report = [
            'records' => [],
            'authorities' => [],
            'items' => 0,
            'readers' => 0,
            'loans' => 0,
            'openloans' => 0,
            'issues' => [
                'duplicatebarcodes' => [],
                'badletters' => [],
                'orphanitems' => [],
                'recordswithoutitems' => 0,
                'orphanauthorlinks' => 0,
                'unknownlanguages' => [],
                'itemswithoutlocation' => 0,
                'unknownstatuses' => [],
                'recordswithisbn' => 0,
            ],
        ];
        $records = [];
        foreach ($export->documents() as $doc) {
            $records[$doc['id']] = true;
            $report['records'][$doc['name']] = ($report['records'][$doc['name']] ?? 0) + 1;
            $mapped = mapper::record($doc);
            if ($mapped['record']['isbn'] !== null) {
                $report['issues']['recordswithisbn']++;
            }
            $raw = $mapped['rawlanguage'];
            if ($raw !== null && $mapped['record']['lang'] === null) {
                $report['issues']['unknownlanguages'][$raw] = ($report['issues']['unknownlanguages'][$raw] ?? 0) + 1;
            }
        }
        foreach ($export->authorities() as $authority) {
            $report['authorities'][$authority['table']] = ($report['authorities'][$authority['table']] ?? 0) + 1;
            if ($authority['table'] === 'FondoAutor' && !isset($records[$authority['fields']['IdDocumentoPrincipal'][0] ?? ''])) {
                $report['issues']['orphanauthorlinks']++;
            }
        }
        $seen = [];
        $withitems = [];
        foreach ($export->items() as $item) {
            $report['items']++;
            $code = strtoupper(trim($item['CodigoEjemplar'] ?? ''));
            if (isset($seen[$code])) {
                $report['issues']['duplicatebarcodes'][$code] = ($report['issues']['duplicatebarcodes'][$code] ?? 1) + 1;
            }
            $seen[$code] = true;
            if ($code !== '' && preg_match('/^\d+[A-Z]$/', $code) && !control_letter::is_valid($code)) {
                $report['issues']['badletters'][] = $code;
            }
            if (!isset($records[$item['IdRegistro'] ?? ''])) {
                $report['issues']['orphanitems'][] = $code;
            } else {
                $withitems[$item['IdRegistro']] = true;
            }
            if (($item['Ubicacion'] ?? '-1') === '-1' || ($item['Ubicacion'] ?? '') === '') {
                $report['issues']['itemswithoutlocation']++;
            }
            if (!mapper::known_status($item['Estado'] ?? null)) {
                $status = $item['Estado'] ?? '';
                $report['issues']['unknownstatuses'][$status] = ($report['issues']['unknownstatuses'][$status] ?? 0) + 1;
            }
        }
        $report['issues']['recordswithoutitems'] = count(array_diff_key($records, $withitems));
        foreach ($export->readers() as $unused) {
            $report['readers']++;
        }
        foreach ($export->loans() as $loan) {
            $report['loans']++;
            if (($loan['FechaDevolucion'] ?? '') === '') {
                $report['openloans']++;
            }
        }
        return $report;
    }
}
