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
 * The desk of a library: lend and take back with a scanner, the keyboard or a phone.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
// Whoever keeps only this library works at its desk too, for this library alone.
$own = access::keeps($library);
if (!$own) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$today = (new desk($library, (int) $USER->id, null, $own))->today();
$settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]), true);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$strings = [];
foreach (
    ['deskundo', 'deskrenew', 'desklendanyway', 'deskdismiss', 'deskclose', 'noteloved', 'noteunfinished', 'notemore',
        'deskbadletter', 'desknoloans', 'deskloans', 'deskerror', 'desklate', 'deskreaderopen', 'deskchoosehint',
        'deskconditionout', 'deskconditionin', 'deskholds', 'deskholdready', 'deskholdwaiting', 'deskkeepheld',
        'deskbankset', 'deskbankmissing', 'deskbankdone', 'deskbankcontract',
        'desklendheld', 'deskroom'] as $key
) {
    $strings[$key] = $str($key);
}
// What waits for the staff besides the desk: reviews to read and books the classes asked for.
$today['reviews'] = $own ? 0 : \local_oksigeniaayvurenda\local\reviews::waiting((int) $library->entityid);
$today['wishes'] = $own ? 0 : \local_oksigeniaayvurenda\local\wishes::waiting((int) $library->entityid);
$cards = '';
foreach (
    ['today' => 'todaytoday', 'held' => 'todayheld', 'late' => 'todaylate', 'out' => 'todayout', 'reviews' => 'todayreviews',
        'wishes' => 'todaywishes'] as $key => $label
) {
    if ($today[$key] > 0) {
        $inner = '<span class="ayv-today-number">' . $today[$key] . '</span><span class="ayv-today-label">' .
            s($str($label)) . '</span>';
        if ($key === 'today' || $own) {
            $cards .= '<li class="ayv-today-card ayv-today-' . $key . '">' . $inner . '</li>';
        } else {
            $page = ['held' => 'holds.php', 'reviews' => 'reviews.php', 'wishes' => 'wishes.php'][$key] ?? null;
            $url = $page ? new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug])
                : new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug,
                    'all' => (int) ($key === 'out')]);
            $cards .= '<li><a class="ayv-today-card ayv-today-' . $key . '" href="' . $url->out() . '">' . $inner . '</a></li>';
        }
    }
}
// Inside the guided help, the page shows only the field and what it does.
$help = optional_param('help', 0, PARAM_BOOL);
$yearlink = '';
if (!$help && access::can('local/oksigeniaayvurenda:manage', $library)) {
    $status = \local_oksigeniaayvurenda\local\year_change::status((int) $library->entityid);
    $label = '';
    if ($status['moment'] === 'start' && ($status['notutor'] || $status['left'])) {
        $label = $str('yearbegin', $status['year']->name);
    } else if ($status['moment'] === 'end' && $status['out']) {
        $label = $str('yearending', $status['out']);
    }
    if ($label !== '') {
        $yearlink = '<p class="ayv-more"><a class="ayv-button ayv-button-quiet" href="' .
            (new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug]))->out() . '">' .
            s($label) . '</a></p>';
    }
}
$tools = shell::readers_tools($library);
if ($help) {
    $tools['html'] = '';
} else if ($own) {
    // The camera, yes; the phone as a reader is for the centre's staff.
    $tools['html'] = '<div class="ayv-tools"><button type="button" class="ayv-toggle" id="ayv-camera-open" hidden>' .
        s($str('camerabutton')) . '</button></div>';
}
$main = '<section class="ayv-desk" aria-labelledby="ayv-desk-title">
<h1 id="ayv-desk-title" class="ayv-sr">' . s($str('desktitle')) . '</h1>
<form id="ayv-scan" class="ayv-scan" autocomplete="off">
    <label for="ayv-code" class="ayv-scan-label">' . s($str('deskprompt')) . '</label>
    <input id="ayv-code" name="code" type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false"
        autofocus maxlength="200">
</form>
' . $tools['html'] . '
<div id="ayv-reader" class="ayv-reader" hidden></div>
<ul id="ayv-today" class="ayv-today">' . $cards . '</ul>
<ol id="ayv-log" class="ayv-log" aria-live="polite"></ol>
' . $yearlink . '
</section>';
shell::output($str('desktitle'), $main, $library, '', [
    'search' => false,
    'scripts' => ['camera', 'desk'],
    'nav' => $help ? [] : ($own ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'desk']))
            ->out(false), 'label' => $str('helpnav')],
    ] : array_merge([
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false),
            'label' => $str('readers')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/holds.php', ['library' => $library->slug]))->out(false),
            'label' => $str('holdsnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/lots.php', ['library' => $library->slug]))->out(false),
            'label' => $str('lotsnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $library->slug]))->out(false),
            'label' => $str('copiesnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/reports.php', ['library' => $library->slug]))->out(false),
            'label' => $str('reportsnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'desk']))
            ->out(false), 'label' => $str('helpnav')],
    ], access::can('local/oksigeniaayvurenda:catalogue', $library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
            'label' => $str('addnav')],
    ] : [], \local_oksigeniaayvurenda\local\bank::is($library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $library->slug]))->out(false),
            'label' => $str('bankrollnav')],
    ] : [], access::can('local/oksigeniaayvurenda:catalogue', $library) && \local_oksigeniaayvurenda\local\bank::is($library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/bank.php', ['library' => $library->slug]))->out(false),
            'label' => $str('banknav')],
    ] : [], access::can('local/oksigeniaayvurenda:manage', $library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false), 'label' => $str('librariesnav')],
    ] : [])),
    'config' => [
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/desk_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'controlletter' => !empty($settings['controlletter']),
        'strings' => $strings + $tools['strings'],
    ] + $tools['config'],
]);
