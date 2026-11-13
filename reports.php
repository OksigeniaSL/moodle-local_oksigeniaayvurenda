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

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\reports;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\years;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$report = optional_param('report', '', PARAM_ALPHA);
$format = optional_param('format', '', PARAM_ALPHA);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/reports.php', ['library' => $ref, 'report' => $report]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
if ($report === 'late') {
    redirect(new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug]));
}
// Loans by group first where there are classes; elsewhere (a public library, a company) the most read first, and the
// groups (a club, a workshop) last.
$order = \local_oksigeniaayvurenda\local\skins::no_classes((int) $library->entityid)
    ? ['top', 'late', 'never', 'holdings', 'year', 'loans'] : reports::ALL;
$report = in_array($report, reports::ALL, true) ? $report : $order[0];
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$date = fn(int $time) => zones::date($time, 'strftimedate', $library);
$year = years::around((int) $library->entityid, time());
// The period: a sheet touched (this week, a term, the year…), months from «Another», or exact days from the sheet of the
// year; the days of before (from, to) still say the same.
$periodkey = optional_param('periodo', '', PARAM_ALPHANUM);
$desde = optional_param('desde', '', PARAM_RAW_TRIMMED);
$hasta = optional_param('hasta', '', PARAM_RAW_TRIMMED);
$oldfrom = optional_param('from', '', PARAM_TEXT);
if ($desde === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $oldfrom)) {
    $desde = $oldfrom;
    $hasta = optional_param('to', '', PARAM_TEXT);
}
[$from, $to, $chosen] = \local_oksigeniaayvurenda\local\periods::resolve($library, time(), $periodkey, $desde, $hasta);
$other = optional_param('otro', 0, PARAM_BOOL) || $chosen === 'otro';
$unlent = max(0, optional_param('unlent', 3, PARAM_INT));
$older = max(0, optional_param('older', 5, PARAM_INT));
$rows = [];
$columns = [];
$summary = '';
$figure = null;
$period = $report === 'loans' || $report === 'top';
switch ($report) {
    case 'loans':
        $found = reports::loans($library, $from, $to);
        $columns = [$str('reportgroup'), $str('reportloans'), $str('reportreaders')];
        $max = max(array_merge([1], array_column($found['rows'], 'value')));
        foreach ($found['rows'] as $row) {
            $rows[] = ['label' => $row['label'] !== '' ? $row['label'] : $str('readersnogroup'), 'value' => $row['value'],
                'url' => $row['label'] !== '' ? (new moodle_url(
                    '/local/oksigeniaayvurenda/readers.php',
                    ['library' => $library->slug, 'group' => $row['label']]
                ))->out(false) : '',
                // One reader is «a reader», not «1 readers».
                'note' => $row['members']
                    ? $str((int) $row['readers'] === 1 ? 'reportreadersofone' : 'reportreadersof', (object) $row)
                    : $str((int) $row['readers'] === 1 ? 'reportreadersonlyone' : 'reportreadersonly', $row['readers']),
                'share' => round(100 * $row['value'] / $max),
                'csv' => [$row['label'], $row['value'], $row['readers'], $row['members']]];
        }
        $columns[] = $str('reportmembers');
        // It says which days it counts, and each group opens its readers: the figure first, in the letter of titles; a
        // whole month by its name; the year only when it is not the same at both ends.
        $total = (int) $found['total'];
        $lead = $str(
            $total === 1 ? 'reportloansone' : 'reportloanscount',
            \local_oksigeniaayvurenda\local\screens::number($total)
        );
        $zone = zones::of($library);
        $at = fn(int $time) => (new DateTimeImmutable('@' . $time))->setTimezone($zone);
        $first = $at($from);
        $last = $at(min($to - 1, time()));
        $month = fn(DateTimeImmutable $day) => userdate($day->getTimestamp(), '%B', zones::name($library));
        $wholemonth = $first->format('j H:i') === '1 00:00'
            && $at($to)->format('Y-m-d H:i') === $first->modify('first day of next month')->format('Y-m-d H:i');
        if ($wholemonth) {
            $rest = $str('reportloansinmonth', $first->format('Y') === $at(time())->format('Y') ? $month($first)
                : $str('reportmonthyear', (object) ['month' => $month($first), 'year' => $first->format('Y')]));
        } else if ($first->format('Y') === $last->format('Y')) {
            $short = fn(DateTimeImmutable $day) => $str('reportdaymonth', (object) ['day' => $day->format('j'),
                'month' => $month($day)]);
            $rest = $str('reportloansrange', (object) ['from' => $short($first), 'to' => $short($last)]);
        } else {
            $rest = $str('reportloansrange', (object) ['from' => $date($from), 'to' => $date($last->getTimestamp())]);
        }
        $figure = ['lead' => $lead, 'rest' => $rest];
        break;
    case 'top':
        $found = reports::top($library, $from, $to);
        $columns = [$str('reporttitle'), $str('reportloans'), $str('reportcopies')];
        $max = max(array_merge([1], array_column($found, 'value')));
        foreach ($found as $row) {
            $copies = (int) $row['copies'] === 1 ? $str('reportcopiesofone') : $str('reportcopiesof', $row['copies']);
            $rows[] = ['label' => $row['label'], 'value' => $row['value'], 'note' => $copies,
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
                    $row['acquired'] ? zones::date((int) $row['acquired'], '%Y-%m-%d', $library) : '',
                    $row['lastloan'] ? zones::date((int) $row['lastloan'], '%Y-%m-%d', $library) : '']];
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
            'borrowers' => $found['borrowers'], 'members' => $found['members'], 'acquired' => $found['acquired']]) .
            ($found['room'] ? ' ' . $str('reportyearroom', $found['room']) : '');
        $columns = [$str('reportgroup'), $str('reportloans'), $str('reportreaders'), $str('reportmembers')];
        $max = max(array_merge([1], array_column($found['groups'], 'loans')));
        foreach ($found['groups'] as $row) {
            $rows[] = ['label' => $row['group'], 'value' => $row['loans'], 'note' => $str(
                (int) $row['borrowers'] === 1 ? 'reportreadersofone' : 'reportreadersof',
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
    \local_oksigeniaayvurenda\event\report_exported::fire((int) $library->entityid, null, ['report' => $report]);
    $filename = clean_filename('ayvurenda-' . $report . '-' . date('Ymd') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $columns, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, \local_oksigeniaayvurenda\core\spreadsheet::row($row['csv']), ',', '"', '');
    }
    fclose($out);
    die();
}
$params = ['library' => $library->slug, 'report' => $report];
$shown = fn(int $time) => zones::date($time, '%Y-%m-%d', $library);
$extra = $period ? ['desde' => $shown($from), 'hasta' => $shown($to - 1)] : ($report === 'never'
    ? ['unlent' => $unlent, 'older' => $older] : []);
// The row of sheets of the periods, and the strip of months of «Another».
$here = fn(array $more) => (new moodle_url('/local/oksigeniaayvurenda/reports.php', $params + $more))->out(false);
$sheets = [];
foreach (\local_oksigeniaayvurenda\local\periods::all($library, time()) as $one) {
    $sheets[] = ['name' => $one['name'], 'dates' => $one['dates'], 'url' => $here(['periodo' => $one['key']]),
        'current' => !$other && $one['key'] === $chosen, 'other' => false];
}
$sheets[] = ['name' => $str('periodother'), 'dates' => $str('periodotherdates'), 'url' => $here(['otro' => 1]) . '#ayv-in-meses',
    'current' => $chosen === 'otro' || $other, 'other' => true];
$first = preg_match('/^\d{4}-\d{2}$/', $desde) && $hasta === '' ? $desde : '';
$strip = $other && $hasta === '' ? [
    'prompt' => $first !== '' ? $str('periodfrom', '<b>' . s(userdate((new DateTimeImmutable($first . '-15 12:00'))
        ->getTimestamp(), '%B')) . '</b>') : $str('periodfirst'),
    'months' => \local_oksigeniaayvurenda\local\periods::months($library, time(), $first, fn(array $more) => $here($more)),
    'days' => (new moodle_url(
        '/local/oksigeniaayvurenda/year.php',
        ['library' => $library->slug, 'elegir' => $report],
        'ayv-cs-sheet'
    ))->out(false),
    'dayslabel' => $str('perioddays'),
] : null;
$data = [
    'heading' => $str('report' . $report . 'title'),
    // A little life above the numbers: this week, the year ahead of the last, what there is to celebrate.
    'life' => \local_oksigeniaayvurenda\local\life::reports($library, time()),
    'intro' => $str('report' . $report . 'intro'),
    'tabs' => array_map(fn($r) => ['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/reports.php',
        ['library' => $library->slug, 'report' => $r] + ($chosen !== 'curso' ? $extra : [])
    ))->out(false), 'label' => $str('report' . $r . 'title'),
        'current' => $r === $report], $order),
    'action' => (new moodle_url('/local/oksigeniaayvurenda/reports.php'))->out(false),
    'library' => $library->slug,
    'report' => $report,
    'period' => $period ? ['label' => $str('periodlabel'), 'sheets' => $sheets, 'strip' => $strip] : null,
    'reportsnav' => $str('reportsnav'),
    'never' => $report === 'never' ? ['unlent' => $unlent, 'older' => $older] : null,
    'summary' => $summary,
    'figure' => $figure,
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
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/memoir.php', ['library' => $library->slug]))->out(false),
            'label' => $str('memoirnav')],
        ...(\local_oksigeniaayvurenda\local\skins::no_courses((int) $library->entityid) ? [] : [['url' =>
            (new moodle_url('/local/oksigeniaayvurenda/readings.php', ['library' => $library->slug]))->out(false),
            'label' => $str('readingsdemandnav')]]),
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'reports']))
            ->out(false), 'label' => $str('helpnav')]]]
);
