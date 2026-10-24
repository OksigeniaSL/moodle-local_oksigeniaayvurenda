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
 * The six fixed reports of a library, on screen and as CSV.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\reports;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$report = optional_param('report', 'loans', PARAM_ALPHA);
$format = optional_param('format', '', PARAM_ALPHA);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:desk', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/reports.php', ['library' => $ref, 'report' => $report]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
if ($report === 'late') {
    redirect(new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug]));
}
$report = in_array($report, reports::ALL, true) ? $report : 'loans';
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$date = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'));
$day = function (string $text, int $default): int {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $p) ? (int) make_timestamp((int) $p[1], (int) $p[2], (int) $p[3])
        : $default;
};
$year = years::around((int) $library->entityid, time());
$from = $day(optional_param('from', '', PARAM_TEXT), (int) $year->timestart);
$to = $day(optional_param('to', '', PARAM_TEXT), time() + DAYSECS);
$unlent = max(0, optional_param('unlent', 3, PARAM_INT));
$older = max(0, optional_param('older', 5, PARAM_INT));
$rows = [];
$columns = [];
$summary = '';
$period = $report === 'loans' || $report === 'top';
switch ($report) {
    case 'loans':
        $found = reports::loans($library, $from, $to);
        $columns = [$str('reportgroup'), $str('reportloans'), $str('reportreaders')];
        $max = max(array_merge([1], array_column($found['rows'], 'value')));
        foreach ($found['rows'] as $row) {
            $rows[] = ['label' => $row['label'] !== '' ? $row['label'] : $str('readersnogroup'), 'value' => $row['value'],
                'note' => $row['members'] ? $str('reportreadersof', (object) $row) : $str('reportreadersonly', $row['readers']),
                'share' => round(100 * $row['value'] / $max),
                'csv' => [$row['label'], $row['value'], $row['readers'], $row['members']]];
        }
        $columns[] = $str('reportmembers');
        $summary = $str('reportloanstotal', $found['total']);
        break;
    case 'top':
        $found = reports::top($library, $from, $to);
        $columns = [$str('reporttitle'), $str('reportloans'), $str('reportcopies')];
        $max = max(array_merge([1], array_column($found, 'value')));
        foreach ($found as $row) {
            $rows[] = ['label' => $row['label'], 'value' => $row['value'], 'note' => $str('reportcopiesof', $row['copies']),
                'share' => round(100 * $row['value'] / $max), 'csv' => [$row['label'], $row['value'], $row['copies']]];
        }
        break;
    case 'never':
        // Zero years without a loan means never lent.
        $found = reports::never(
            $library,
            $unlent ? time() - $unlent * 365 * DAYSECS : 0,
            time() - $older * 365 * DAYSECS,
            $format === 'csv' ? 0 : 200
        );
        $columns = [$str('reporttitle'), $str('cardcode'), $str('addspine'), $str('reportacquired'), $str('reportlastloan')];
        foreach ($found['rows'] as $row) {
            $rows[] = ['label' => $row['label'], 'value' => $row['barcode'],
                'note' => trim($row['callnumber'] . ' · ' . ($row['lastloan'] ? $str('reportlastloanon', $date($row['lastloan']))
                    : $str('reportneverlent')), ' ·'),
                'csv' => [$row['label'], $row['barcode'], $row['callnumber'],
                    $row['acquired'] ? date('Y-m-d', $row['acquired']) : '',
                    $row['lastloan'] ? date('Y-m-d', $row['lastloan']) : '']];
        }
        $summary = $str('reportnevertotal', $found['total']);
        break;
    case 'holdings':
        $found = reports::holdings($library);
        $columns = [$str('reportsubject'), $str('reportcopies'), $str('reportlentthisyear')];
        foreach (['subjects', 'audiences'] as $part) {
            $max = max(array_merge([1], array_column($found[$part], 'value')));
            foreach ($found[$part] as $row) {
                $rows[] = ['label' => $row['label'], 'value' => $row['value'], 'part' => $str('report' . $part),
                    'note' => $str('reportlentof', $row['lent']), 'share' => round(100 * $row['value'] / $max),
                    'csv' => [$str('report' . $part), $row['label'], $row['value'], $row['lent']]];
            }
        }
        array_unshift($columns, $str('reportpart'));
        break;
    case 'year':
        $found = reports::year($library, $year);
        $summary = $str('reportyearsummary', (object) ['name' => $found['name'], 'loans' => $found['loans'],
            'borrowers' => $found['borrowers'], 'members' => $found['members'], 'acquired' => $found['acquired']]);
        $columns = [$str('reportgroup'), $str('reportloans'), $str('reportreaders'), $str('reportmembers')];
        $max = max(array_merge([1], array_column($found['groups'], 'loans')));
        foreach ($found['groups'] as $row) {
            $rows[] = ['label' => $row['group'], 'value' => $row['loans'], 'note' => $str(
                'reportreadersof',
                (object) ['readers' => $row['borrowers'], 'members' => $row['members']]
            ),
                'share' => round(100 * $row['loans'] / $max),
                'csv' => [$row['group'], $row['loans'], $row['borrowers'], $row['members']]];
        }
        break;
}
// Every line says what it has: in Mustache, a missing key is looked up in the page around it.
$rows = array_map(fn($row) => $row + ['part' => '', 'share' => 0, 'note' => ''], $rows);
if ($format === 'csv') {
    $filename = clean_filename('ayvurenda-' . $report . '-' . date('Ymd') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $columns, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, $row['csv'], ',', '"', '\\');
    }
    fclose($out);
    die();
}
$params = ['library' => $library->slug, 'report' => $report];
$extra = $period ? ['from' => date('Y-m-d', $from), 'to' => date('Y-m-d', $to - 1)] : ($report === 'never'
    ? ['unlent' => $unlent, 'older' => $older] : []);
$data = [
    'heading' => $str('report' . $report . 'title'),
    'intro' => $str('report' . $report . 'intro'),
    'tabs' => array_map(fn($r) => ['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/reports.php',
        ['library' => $library->slug, 'report' => $r]
    ))->out(false), 'label' => $str('report' . $r . 'title'),
        'current' => $r === $report], reports::ALL),
    'action' => (new moodle_url('/local/oksigeniaayvurenda/reports.php'))->out(false),
    'library' => $library->slug,
    'report' => $report,
    'period' => $period ? ['from' => date('Y-m-d', $from), 'to' => date('Y-m-d', $to - 1)] : null,
    'never' => $report === 'never' ? ['unlent' => $unlent, 'older' => $older] : null,
    'summary' => $summary,
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'empty' => $str('reportempty'),
    'csv' => (new moodle_url('/local/oksigeniaayvurenda/reports.php', $params + $extra + ['format' => 'csv']))->out(false),
];
foreach (['reportfrom', 'reportto', 'reportunlent', 'reportolder', 'labelsshow', 'reportcsv'] as $key) {
    $data[$key] = $str($key);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/reports', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]
);
