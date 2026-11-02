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
 * Readers from a list: a CSV file from the school's program or a spreadsheet.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\readers\csv_list;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plan_full;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\csvlist;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$keep = optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [];
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readerslist.php', ['library' => $ref] + $keep));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$page = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/' . $file, ['library' => $library->slug]))->out(false);
$text = '';
$error = '';
$step = 'paste';
$done = null;
$filename = '';
$list = ['columns' => [], 'rows' => []];
$map = [];
if (data_submitted() && confirm_sesskey()) {
    $text = optional_param('csv', '', PARAM_RAW);
    $filename = optional_param('filename', '', PARAM_FILE);
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $text = (string) file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 5 * 1024 * 1024);
        $filename = clean_filename((string) ($_FILES['file']['name'] ?? ''));
    }
    $list = csv_list::read($text);
    $map = csv_list::guess($list['columns']);
    $chosen = optional_param_array('column', null, PARAM_ALPHANUMEXT);
    if ($chosen !== null) {
        $map = array_filter($chosen, fn($field) => in_array($field, csv_list::FIELDS, true));
    }
    if (optional_param('load', 0, PARAM_BOOL)) {
        $type = optional_param('type', 'student', PARAM_ALPHA);
        try {
            $done = plans::within($entityid, fn() => csvlist::load(
                $entityid,
                csv_list::readers($list['rows'], $map),
                $type,
                null,
                'csv',
                false
            ));
            \local_oksigeniaayvurenda\event\readers_synced::tell($entityid, 'list', $done);
            $text = '';
        } catch (plan_full $e) {
            $error = $e->getMessage();
        }
    } else if ($list['columns']) {
        $step = 'check';
    } else {
        $error = $str('readerslistnothing');
    }
}
$data = ['heading' => $str('readerslisttitle'), 'intro' => $str('readerslistintro'), 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'paste' => $step === 'paste' && !$done, 'check' => null,
    'done' => null, 'plansummary' => plans::summary($entityid), 'said' => null];
if ($done) {
    $figure = fn(int $count, string $key) => $count ? ['count' => $number($count), 'label' => $str($key)] : null;
    $data['done'] = [
        'figures' => array_values(array_filter([
            $figure((int) $done['created'], 'readerslistfigcreated'),
            $figure((int) $done['updated'], 'readerslistfigupdated'),
            $figure((int) $done['linked'], 'readerslistfiglinked'),
        ])),
        'urlreaders' => $page('readers.php'),
        'urldesk' => $page('desk.php'),
    ];
    $data['said'] = \local_oksigeniaayvurenda\local\characters::say($entityid, 'memory', 'readerslistmemory', [], 'celebra');
}
if ($step === 'check') {
    $readers = csv_list::readers($list['rows'], $map);
    $columns = [];
    foreach ($list['columns'] as $index => $heading) {
        $options = [['value' => '', 'label' => $str('readerslistignore'), 'selected' => !isset($map[$index])]];
        foreach (csv_list::FIELDS as $field) {
            $options[] = ['value' => $field, 'label' => $str('readerslistfield' . $field),
                'selected' => ($map[$index] ?? '') === $field];
        }
        $sample = '';
        foreach (array_slice($list['rows'], 0, 25) as $row) {
            if (trim((string) ($row[$index] ?? '')) !== '') {
                $sample = trim((string) $row[$index]);
                break;
            }
        }
        $columns[] = ['index' => $index, 'heading' => $heading, 'options' => $options, 'sample' => $sample,
            'isknown' => isset($map[$index])];
    }
    $groups = array_unique(array_filter(array_column($readers, 'group')));
    $emails = count(array_filter(array_column($readers, 'email')));
    $people = array_map(function ($reader) {
        $name = trim(($reader['firstname'] ?? '') . ' ' . ($reader['lastname'] ?? ''));
        return ['name' => $name, 'group' => (string) ($reader['group'] ?? ''),
            'initial' => \local_oksigeniaayvurenda\local\profile::initial($name)];
    }, array_slice($readers, 0, 6));
    $more = count($readers) - count($people);
    $data['check'] = [
        'csv' => $text,
        'filename' => $filename,
        'kind' => $str('importkindcsv'),
        'figures' => array_values(array_filter([
            ['count' => $number(count($readers)), 'label' => $str('readerslistfigreaders')],
            $groups ? ['count' => $number(count($groups)), 'label' => $str('readerslistfiggroups')] : null,
            $emails ? ['count' => $number($emails), 'label' => $str('readerslistfigemail')] : null,
        ])),
        'people' => $people,
        'more' => $more > 0 ? $str('readerslistmore', $number($more)) : '',
        'columns' => $columns,
        'allknown' => count($map) === count($columns),
        'line' => count($map) === count($columns) ? $str('importcolumnsall', count($columns))
            : $str('importcolumnssome', (object) ['known' => count($map), 'all' => count($columns)]),
        'cannot' => $readers ? '' : $str('readerslistnoname'),
        'noname' => !$readers,
        'gowords' => $str('readerslistload', $number(count($readers))),
        'types' => array_map(
            fn($t) => ['value' => $t, 'label' => $str('readertype' . $t), 'selected' => $t === 'student'],
            ['student', 'teacher', 'staff', 'family']
        ),
    ];
}
foreach (
    ['readerslistdrop', 'readerslistformats', 'readerslistpastetoggle', 'readerslistpastego', 'readerslistsample',
        'readerslistsamplehint', 'readerslistcolumns', 'readerslisttype', 'readerslistchecktitle', 'readerslistchecklead',
        'readerslistpeople', 'readerslistsafe', 'readerslistdonetitle', 'readerslistgoreaders', 'readerslistgoreadershint',
        'importdropor', 'importnextheading', 'importnextdesk', 'importnextdeskhint', 'importcolumnsreview',
        'importcolumnsintro', 'importcolumnsapply', 'importcolumnssaysnothing', 'importcancel', 'importreading',
        'importbusy', 'importbusyhint', 'offsiteclose'] as $key
) {
    $data[$key] = $str($key);
}
$data['urlself'] = $PAGE->url->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/readerslist', $data),
    $library,
    '',
    ['search' => false, 'nav' => $keep ? [] : [['url' => $page('sources.php'), 'label' => $str('readerssources')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'readerslist']))
            ->out(false), 'label' => $str('helpnav')]]]
);
