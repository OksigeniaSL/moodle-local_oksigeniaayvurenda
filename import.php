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
 * Importing a catalogue: an Abies Web export, MARC from any library program, or a spreadsheet as CSV.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\core\abies\survey;
use local_oksigeniaayvurenda\core\import\catalogue_csv;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\import\abies_import;
use local_oksigeniaayvurenda\local\import\catalogue_import;
use local_oksigeniaayvurenda\local\import\files;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/import.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
core_php_time_limit::raise(600);
raise_memory_limit(MEMORY_HUGE);
$pending = $SESSION->ayvurendaimport ?? null;
if ($pending && (!is_file($pending['path']) || $pending['library'] !== $library->slug)) {
    $pending = null;
}
$data = ['heading' => $str('importtitle'), 'intro' => $str('importintro'), 'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(), 'notice' => '', 'error' => '', 'upload' => true, 'abies' => null, 'marc' => null, 'csv' => null];
if (data_submitted() && confirm_sesskey()) {
    $step = optional_param('step', '', PARAM_ALPHA);
    if ($step === 'upload' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $dir = make_temp_directory('local_oksigeniaayvurenda');
        $path = $dir . '/' . random_string(20) . '.upload';
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
        $kind = files::kind($path, (string) $_FILES['file']['name']);
        if ($kind === '') {
            @unlink($path);
            $data['error'] = $str('importunknown');
            $pending = null;
        } else {
            $pending = ['path' => $path, 'name' => clean_filename($_FILES['file']['name']), 'kind' => $kind,
                'library' => $library->slug];
        }
        $SESSION->ayvurendaimport = $pending;
    } else if ($step === 'import' && $pending) {
        try {
            if ($pending['kind'] === 'abies') {
                $xml = files::abies_xml($pending['path']);
                $stats = (new abies_import(
                    (int) $library->id,
                    optional_param('notes', 0, PARAM_BOOL),
                    optional_param('readers', 0, PARAM_BOOL)
                ))->run(new export_reader($xml));
                $data['notice'] = $str('importdoneabies', (object) [
                    'records' => $stats['record']['created'] ?? 0,
                    'items' => $stats['item']['created'] ?? 0,
                    'readers' => $stats['reader']['created'] ?? 0,
                    'loans' => $stats['loan']['created'] ?? 0,
                ]);
                catalogue::purge((int) $library->entityid);
            } else {
                $map = array_filter(
                    optional_param_array('column', [], PARAM_ALPHANUMEXT),
                    fn($f) => in_array($f, catalogue_csv::FIELDS, true)
                );
                $records = $pending['kind'] === 'csv' ? files::csv_records($pending['path'], $map)
                    : files::marc($pending['path'], $pending['kind']);
                $stats = (new catalogue_import($library, $pending['kind'] === 'csv' ? 'csv' : 'marc'))->run($records);
                $data['notice'] = $str('importdone', (object) $stats);
            }
        } catch (Throwable $e) {
            $data['error'] = $str('importfailed', $e->getMessage());
        }
        @unlink($pending['path']);
        $pending = null;
        unset($SESSION->ayvurendaimport);
    } else if ($step === 'cancel' && $pending) {
        @unlink($pending['path']);
        $pending = null;
        unset($SESSION->ayvurendaimport);
    }
}
if ($pending) {
    $data['upload'] = false;
    $data['file'] = $pending['name'];
    try {
        if ($pending['kind'] === 'abies') {
            $report = survey::run(new export_reader(files::abies_xml($pending['path'])));
            $data['abies'] = ['summary' => $str('importabiessummary', (object) ['records' => array_sum($report['records']),
                'items' => $report['items'], 'readers' => $report['readers'], 'loans' => $report['loans']])];
        } else if ($pending['kind'] === 'csv') {
            $csv = files::csv($pending['path']);
            $map = catalogue_csv::guess($csv['columns']);
            $columns = [];
            foreach ($csv['columns'] as $index => $heading) {
                $options = [['value' => '', 'label' => $str('readerslistignore'), 'selected' => !isset($map[$index])]];
                foreach (catalogue_csv::FIELDS as $field) {
                    $options[] = ['value' => $field, 'label' => $str('importfield' . $field),
                        'selected' => ($map[$index] ?? '') === $field];
                }
                $columns[] = ['index' => $index, 'heading' => $heading, 'options' => $options];
            }
            $records = catalogue_csv::records($csv['rows'], $map);
            $data['csv'] = ['columns' => $columns, 'summary' => $str('importcsvsummary', (object) ['records' => count($records),
                'items' => array_sum(array_map(fn($r) => count($r['items']), $records))]),
                'preview' => array_map(
                    fn($row) => ['cells' => array_map(fn($c) => ['value' => $c], $row)],
                    array_slice($csv['rows'], 0, 5)
                )];
        } else {
            $count = 0;
            $items = 0;
            foreach (files::marc($pending['path'], $pending['kind']) as $record) {
                $count++;
                $items += count($record['items']);
            }
            $data['marc'] = ['summary' => $str('importmarcsummary', (object) ['records' => $count, 'items' => $items])];
        }
    } catch (Throwable $e) {
        $data['error'] = $str('importfailed', $e->getMessage());
        $data['upload'] = true;
        @unlink($pending['path']);
        unset($SESSION->ayvurendaimport);
    }
}
foreach (
    ['importfile', 'importnext', 'importgo', 'importcancel', 'importnotes', 'importreaders', 'importreadershint',
        'readerslistcolumns', 'readerslistpreview'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/import', $data), $library, '', [
    'search' => false,
    'nav' => [
        ['url' => $url('index.php'), 'label' => $str('deskcatalogue')],
        ['url' => $url('add.php'), 'label' => $str('addnav')],
    ],
]);
