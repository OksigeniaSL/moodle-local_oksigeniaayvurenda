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
use local_oksigeniaayvurenda\core\marc\charset;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\import\abies_import;
use local_oksigeniaayvurenda\local\import\catalogue_import;
use local_oksigeniaayvurenda\local\import\files;
use local_oksigeniaayvurenda\local\import\preview;
use local_oksigeniaayvurenda\local\plan_full;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/import.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

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
$entityid = (int) $library->entityid;
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
// A count with the word that goes with it, one or many.
$figure = fn(int $count, string $key) => ['count' => screens::number($count), 'label' => $str($key . ($count === 1 ? 'one' : ''))];
$data = ['heading' => $str('importtitle'), 'intro' => $str('importintro'), 'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(), 'error' => '', 'upload' => true, 'review' => null, 'done' => null,
    'plansummary' => plans::summary($entityid), 'urladd' => $url('add.php'), 'urlcatalogue' => $url('index.php'),
    'urllabels' => $url('labels.php'), 'urlreaders' => $url('readers.php'), 'urldesk' => $url('desk.php'),
    'urlself' => $PAGE->url->out(false)];
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
    } else if ($step === 'remap' && $pending) {
        // The columns as they were chosen, to see the books again with them.
        $pending['map'] = array_filter(
            optional_param_array('column', [], PARAM_ALPHANUMEXT),
            fn($f) => in_array($f, catalogue_csv::FIELDS, true)
        );
        $set = optional_param('charset', 'auto', PARAM_ALPHANUM);
        $pending['charset'] = in_array($set, charset::SETS, true) ? $set : charset::AUTO;
        $SESSION->ayvurendaimport = $pending;
    } else if ($step === 'import' && $pending) {
        try {
            if ($pending['kind'] === 'abies') {
                $xml = files::abies_xml($pending['path']);
                $importer = new abies_import(
                    (int) $library->id,
                    optional_param('notes', 0, PARAM_BOOL),
                    optional_param('readers', 0, PARAM_BOOL)
                );
                $stats = plans::within($entityid, fn() => $importer->run(new export_reader($xml)));
                $figures = [$figure($stats['record']['created'] ?? 0, 'importnewrecords'),
                    $figure($stats['item']['created'] ?? 0, 'importnewitems')];
                if (optional_param('readers', 0, PARAM_BOOL)) {
                    $figures[] = $figure($stats['reader']['created'] ?? 0, 'importfigreaders');
                    $figures[] = $figure($stats['loan']['created'] ?? 0, 'importfigloans');
                }
                $here = 0;
                $made = 0;
                foreach ($stats as $counts) {
                    $here += ($counts['changedhere'] ?? 0) + ($counts['deletedhere'] ?? 0) + ($counts['erased'] ?? 0);
                    $made += $counts['madehere'] ?? 0;
                }
                $again = [$str('importabiesagain')];
                if ($here) {
                    $again[] = $str('importabieshere');
                }
                if ($made) {
                    $again[] = $str('importabiesmade');
                }
                $relabelled = array_map(fn($copy) => $copy[0] . ' → ' . $copy[1], $importer->relabelled());
                $data['done'] = ['figures' => $figures, 'again' => implode(' ', $again),
                    'newcodes' => $relabelled ? $str('importrelabelled', implode(', ', $relabelled)) : ''];
                catalogue::purge($entityid);
            } else {
                $map = array_filter(
                    optional_param_array('column', [], PARAM_ALPHANUMEXT),
                    fn($f) => in_array($f, catalogue_csv::FIELDS, true)
                );
                $records = $pending['kind'] === 'csv' ? files::csv_records($pending['path'], $map)
                    : files::marc($pending['path'], $pending['kind'], $pending['charset'] ?? charset::AUTO);
                $importer = new catalogue_import($library, $pending['kind'] === 'csv' ? 'csv' : 'marc');
                $stats = plans::within($entityid, fn() => $importer->run($records));
                $data['done'] = [
                    'figures' => [$figure($stats['records'], 'importnewrecords'), $figure($stats['items'], 'importnewitems')],
                    'again' => $stats['found'] || $stats['kept'] ? $str('importagain', (object) ['records' => $stats['found'],
                        'items' => $stats['kept']]) : '',
                    'newcodes' => $stats['newcodes'] ? $str('importnewcodes', $stats['newcodes']) : '',
                ];
            }
            $data['done']['said'] = characters::say($entityid, 'memory', 'importmemory', [], 'celebra');
            $data['upload'] = false;
        } catch (plan_full $e) {
            $data['error'] = $e->getMessage();
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
    try {
        $review = ['file' => $pending['name'], 'abies' => false, 'csv' => null, 'cannot' => '', 'tight' => ''];
        if ($pending['kind'] === 'abies') {
            $export = new export_reader(files::abies_xml($pending['path']));
            $report = survey::run($export);
            $look = ['records' => array_sum($report['records']), 'items' => $report['items'],
                'books' => preview::abies_books($export)];
            $review['kind'] = $str('importkindabies');
            $review['abies'] = true;
            $review['figures'] = [$figure($look['records'], 'importfigrecords'), $figure($look['items'], 'importfigitems'),
                $figure($report['readers'], 'importfigreaders'), $figure($report['loans'], 'importfigloans')];
        } else {
            if ($pending['kind'] === 'csv') {
                $csv = files::csv($pending['path']);
                $map = $pending['map'] ?? catalogue_csv::guess($csv['columns']);
                $columns = preview::columns($csv, $map);
                $look = preview::of(catalogue_csv::records($csv['rows'], $map));
                $review['kind'] = $str('importkindcsv');
                $review['csv'] = ['columns' => $columns['columns'], 'allknown' => $columns['hastitle']
                    && $columns['known'] === $columns['all'], 'notitle' => !$columns['hastitle'], 'line' => !$columns['hastitle']
                    ? $str('importcolumnsnotitle') : ($columns['known'] === $columns['all']
                    ? $str('importcolumnsall', screens::number($columns['all']))
                    : $str('importcolumnssome', (object) ['known' => $columns['known'], 'all' => $columns['all']]))];
                $review['cannot'] = $columns['hastitle'] ? '' : $str('importnotitle');
            } else {
                $set = $pending['charset'] ?? charset::AUTO;
                $look = preview::of(files::marc($pending['path'], $pending['kind'], $set));
                $review['kind'] = $str($pending['kind'] === 'onix' ? 'importkindonix' : 'importkindmarc');
                // Not in Unicode: the letters are guessed, and they can be read another way, seeing the books change.
                $seen = $pending['kind'] === 'marc' ? charset::guess((string) file_get_contents($pending['path'])) : 'utf8';
                if ($seen !== 'utf8' || $set !== charset::AUTO) {
                    $review['letters'] = ['options' => array_map(fn($code) => [
                        'value' => $code,
                        'selected' => $code === $set,
                        'label' => $code === charset::AUTO ? $str('importletterauto', $str('importletter' . $seen))
                            : $str('importletter' . $code),
                    ], charset::SETS)];
                }
            }
            $review['figures'] = [$figure($look['records'], 'importfigrecords'), $figure($look['items'], 'importfigitems')];
            if ($look['places']) {
                $review['figures'][] = $figure($look['places'], 'importfigplaces');
            }
            if ($look['nocode']) {
                $review['figures'][] = $figure($look['nocode'], 'importfignocode');
            }
        }
        if (!$look['records'] && !$review['cannot']) {
            $review['cannot'] = $str('importempty');
        }
        $review['books'] = array_map(fn($book) => $book + ['hascopies' => $book['copies'] > 1,
            'copieslabel' => $book['copies'] > 1 ? $str('importbookcopies', $book['copies']) : ''], $look['books']);
        $review['more'] = $look['records'] > count($look['books'])
            ? $str('importshelfmore', screens::number($look['records'] - count($look['books']))) : '';
        $review['safe'] = $str('importsafe') . (empty($look['nocode']) ? '' : ' ' . $str('importnocodehint'));
        $review['gowords'] = $look['records'] === 1 ? $str('importgoone') : $str('importgomany', screens::number($look['records']));
        $left = plans::left($entityid, 'copies');
        if ($left !== null && $look['items'] > $left) {
            $review['tight'] = $str('importtight', (object) ['left' => screens::number($left),
                'items' => screens::number($look['items'])]);
        }
        $data['review'] = $review;
        $data['upload'] = false;
    } catch (Throwable $e) {
        $data['error'] = $str('importfailed', $e->getMessage());
        @unlink($pending['path']);
        unset($SESSION->ayvurendaimport);
    }
}
foreach (
    ['importdrop', 'importdropor', 'importformats', 'importnext', 'importreading', 'importsources', 'importfromprogram',
        'importfromprogramhint', 'importfromsheet', 'importfromsheethint', 'importfromabies', 'importfromabieshint',
        'importfromnone', 'importfromnonehint', 'importfromnonego', 'importreviewtitle', 'importshelfheading',
        'importletters', 'importlettershint',
        'importcolumnsreview', 'readerslistcolumns', 'importcolumnsintro', 'importcolumnsapply', 'importcolumnssaysnothing',
        'importcancel', 'importbusy', 'importbusyhint', 'importuploadintro', 'importnotes', 'importreaders', 'importreadershint',
        'importdonetitle', 'importnextheading', 'importnextcatalogue', 'importnextcataloguehint', 'importnextlabels',
        'importnextlabelshint', 'importnextreaders', 'importnextreadershint', 'importnextdesk', 'importnextdeskhint',
        'importanother', 'offsiteclose'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/import', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => $url('index.php'), 'label' => $str('deskcatalogue')],
        ['url' => $url('add.php'), 'label' => $str('addnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'import']))
            ->out(false), 'label' => $str('helpnav')],
    ],
]);
