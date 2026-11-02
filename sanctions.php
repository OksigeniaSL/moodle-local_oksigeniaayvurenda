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

/**
 * What late books have left at a centre: who waits, who owes, and what has been closed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\desk\sanction_rules;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\sanctions;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$tab = optional_param('tab', 'now', PARAM_ALPHA) === 'closed' ? 'closed' : 'now';
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/sanctions.php', ['library' => $ref, 'tab' => $tab]));
$str = skins::speaker($library ?? null);
if (!$library) {
    shell::missing($str('nolibrary'));
}
$entityid = (int) $library->entityid;
$now = time();
$desk = new desk($library, (int) $USER->id, $now);

if (optional_param('format', '', PARAM_ALPHA) === 'csv') {
    \local_oksigeniaayvurenda\event\report_exported::fire($entityid, null, ['report' => 'sanctions']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . clean_filename('ayvurenda-sanctions-' . date('Ymd') . '.csv') . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, explode('|', $str('sanctionscolumns')), ',', '"', '\\');
    foreach (sanctions::export($entityid, $now) as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $readerid = optional_param('reader', 0, PARAM_INT);
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'reopen') {
        $notice = sanctions::reopen($library, optional_param('row', 0, PARAM_INT), $now) ? $str('sanctionreopened') : '';
    } else if ($readerid && in_array($do, ['lift', 'pay', 'waive'], true) && $desk->allowed($readerid)) {
        $notice = $desk->sanction($readerid, $do)['message'];
    }
}

$settings = sanctions::settings($entityid);
$state = sanctions::now($entityid, $now);
$money = fn(int $cents) => sanctions::money($cents, $settings['currency']);
$who = function (int $id) use ($desk): array {
    $reader = $desk->reader($id);
    return ['name' => $reader['name'], 'group' => $reader['group'], 'url' => $reader['url']];
};
$tiles = [];
if ($settings['mode'] === sanction_rules::DAYS) {
    $tiles[] = ['value' => count($state['suspended']), 'label' => $str('sanctionstilenow')];
    $tiles[] = ['value' => $state['daysthisyear'], 'label' => $str('sanctionstileyear')];
}
if ($settings['mode'] === sanction_rules::FINE || $state['pending'] || $state['paid'] || $state['waived']) {
    $tiles[] = ['value' => $money($state['pending']), 'label' => $str('sanctionstilepending')];
    $tiles[] = ['value' => $money($state['paid']), 'label' => $str('sanctionstilepaid')];
    $tiles[] = ['value' => $money($state['waived']), 'label' => $str('sanctionstilewaived')];
}
$link = fn(string $to) => (new moodle_url('/local/oksigeniaayvurenda/sanctions.php', ['library' => $ref,
    'tab' => $to]))->out(false);
$data = [
    'heading' => $str('sanctionstitle'),
    'intro' => $str('sanctionsintro'),
    'notice' => $notice,
    'off' => $settings['mode'] === sanction_rules::NONE ? $str('sanctionsoff') : '',
    'rulesurl' => (new moodle_url('/local/oksigeniaayvurenda/rules.php', ['library' => $ref]))->out(false),
    'tiles' => $tiles,
    'hastiles' => (bool) $tiles,
    'tabs' => [['url' => $link('now'), 'label' => $str('sanctionstabnow'), 'current' => $tab === 'now'],
        ['url' => $link('closed'), 'label' => $str('sanctionstabclosed'), 'current' => $tab === 'closed']],
    'isnow' => $tab === 'now',
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'csv' => (new moodle_url('/local/oksigeniaayvurenda/sanctions.php', ['library' => $ref, 'format' => 'csv']))->out(false),
];
if ($tab === 'now') {
    $data['suspended'] = array_map(fn($row) => $who($row['id']) + ['id' => $row['id'],
        'line' => $str('sanctionrowdays', $row['until'])], $state['suspended']);
    $data['owing'] = array_map(fn($row) => $who($row['id']) + ['id' => $row['id'], 'line' => $str(
        $row['rows'] === 1 ? 'sanctionsowesone' : 'sanctionsowes',
        (object) ['money' => $row['money'], 'rows' => $row['rows']]
    )], $state['owing']);
    $data['hassuspended'] = (bool) $state['suspended'];
    $data['hasowing'] = (bool) $state['owing'];
    $data['nothing'] = !$state['suspended'] && !$state['owing'];
} else {
    $closed = sanctions::closed($entityid, $now);
    $data['closed'] = array_map(fn($row) => $who($row['readerid']) + $row, $closed);
    $data['hasclosed'] = (bool) $closed;
}
foreach (
    ['sanctionssuspended', 'sanctionsowing', 'sanctionlift', 'sanctionpay', 'sanctionwaive', 'sanctionsnothing',
        'sanctionsnothingclosed', 'sanctionscsv', 'sanctionsoffgo', 'sanctionreopen'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('sanctionstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/sanctions', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $ref]))->out(false),
        'label' => $str('desktitle')]],
]);
