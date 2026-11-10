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

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
$desk = new desk($library, (int) $USER->id, null, $own);
$today = $desk->today();
// From a reader's record: the desk opens with them.
$preload = optional_param('reader', 0, PARAM_INT);
$preload = $preload && $DB->record_exists('local_oksigeniaayvurenda_reader', ['id' => $preload, 'entityid' => $library->entityid])
    && $desk->allowed($preload) ? $desk->reader_panel($preload) : null;
// From a book's page: the desk opens with the book, asking who takes it.
$book = optional_param('book', 0, PARAM_INT);
$book = $book && !$preload ? $desk->shelf_book($book) : null;
$settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]), true);
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$strings = [];
foreach (
    ['deskundo', 'deskrenew', 'deskrenewother', 'deskrenewbyhand', 'deskrenewbyhandgo', 'desklendanyway', 'deskdismiss',
        'deskclose', 'profileview', 'deskoffline', 'deskofflineone',
        'deskofflinemany', 'deskbackonline', 'noteloved', 'noteunfinished',
        'notemore',
        'deskbadletter', 'desknoloans', 'deskloans', 'deskerror', 'desklate', 'deskreaderopen', 'deskchoosehint', 'deskstampback',
        'deskconditionout', 'deskconditionin', 'deskholds', 'deskholdready', 'deskholdwaiting', 'deskkeepheld',
        'deskbankset', 'deskbankmissing', 'deskbankdone', 'deskbankcontract',
        'desklendheld', 'deskroom', 'deskgiveback', 'deskbookfree', 'deskbookfreeone',
        'deskshelfhint', 'deskmorebooks', 'deskforwhom', 'deskmorereaders', 'desklost', 'desklostsure', 'deskfor', 'deskbooksalso',
        'deskbadletteragain'] as $key
) {
    $strings[$key] = $str($key);
}
// What waits for the staff besides the desk: reviews to read and books the classes asked for.
$today['reviews'] = $own ? 0 : \local_oksigeniaayvurenda\local\reviews::waiting((int) $library->entityid);
$today['wishes'] = $own ? 0 : \local_oksigeniaayvurenda\local\wishes::waiting((int) $library->entityid);
$today['selfposts'] = $own ? 0 : \local_oksigeniaayvurenda\local\selfloan::stopped($library);
$today['ill'] = $own ? 0 : \local_oksigeniaayvurenda\local\ill::overdue($library);
$today['formats'] = $own ? 0 : \local_oksigeniaayvurenda\local\formats::waiting($library);
$cards = '';
foreach (
    ['today' => 'todaytoday', 'held' => 'todayheld', 'late' => 'todaylate', 'out' => 'todayout', 'reviews' => 'todayreviews',
        'wishes' => 'todaywishes', 'selfposts' => 'todayselfposts', 'ill' => 'todayill',
        'formats' => 'todayformats'] as $key => $label
) {
    if ($today[$key] > 0) {
        $inner = '<span class="ayv-today-number">' . $today[$key] . '</span><span class="ayv-today-label">' .
            s($str($label)) . '</span>';
        if ($own && !in_array($key, ['today', 'late', 'out'], true)) {
            $cards .= '<li class="ayv-today-card ayv-today-' . $key . '">' . $inner . '</li>';
        } else {
            $page = ['held' => 'holds.php', 'reviews' => 'reviews.php', 'wishes' => 'wishes.php',
                'selfposts' => 'selfloan.php', 'ill' => 'ill.php', 'formats' => 'formats.php'][$key] ?? null;
            // What comes back today opens what is out, filtered to today.
            $url = $page ? new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug])
                : new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug,
                    'all' => (int) ($key === 'out' || $key === 'today')] + ($key === 'today' ? ['span' => 'today'] : []));
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
    } else if (
        $status['out'] && $status['returnby'] && time() <= $status['returnby']
        && time() >= $status['returnby'] - 6 * WEEKSECS
    ) {
        // The date to have everything back is weeks away.
        $label = $str('returnbyending', (object) ['out' => $status['out'],
            'date' => \local_oksigeniaayvurenda\local\zones::date((int) $status['returnby'], 'strftimedate', $library)]);
    } else if ($status['moment'] === 'end' && $status['out']) {
        $label = $str('yearending', $status['out']);
    }
    if ($label !== '') {
        $yearlink = '<p class="ayv-more"><a class="ayv-button ayv-button-quiet" href="' .
            (new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug]))->out() . '">' .
            s($label) . '</a></p>';
    }
}
// A centre that has just started: where to begin, in three steps, until it has books and readers.
$start = '';
if (!$help && !$own) {
    $books = $DB->record_exists('local_oksigeniaayvurenda_item', ['entityid' => $library->entityid]);
    $people = $DB->record_exists_select(
        'local_oksigeniaayvurenda_reader',
        "entityid = ? AND status = 'active' AND readertype <> 'library'",
        [$library->entityid]
    );
    if (!$books || !$people) {
        $link = fn(string $page, string $label, string $cap) => access::can($cap, $library)
            ? '<a class="ayv-button ayv-button-quiet" href="' . (new moodle_url(
                '/local/oksigeniaayvurenda/' . $page,
                ['library' => $library->slug]
            ))->out() . '">' . s($str($label)) . '</a>' : '';
        $step = fn(bool $done, string $text, string $buttons) => '<li class="ayv-start-step' . ($done ? ' ayv-start-done' : '')
            . '"><span class="ayv-start-state">' . s($str($done ? 'deskstartdone' : 'deskstartpending')) . '</span> <span>'
            . s($str($text)) . '</span>' . ($done ? '' : '<span class="ayv-start-go">' . $buttons . '</span>') . '</li>';
        $start = '<section class="ayv-start" aria-labelledby="ayv-start-title"><h2 id="ayv-start-title" class="ayv-section-title">'
            . s($str('deskstartheading')) . '</h2><ol class="ayv-start-steps">'
            . $step($books, 'deskstartbooks', $link('import.php', 'menuimport', 'local/oksigeniaayvurenda:catalogue')
                . $link('add.php', 'menuadd', 'local/oksigeniaayvurenda:catalogue'))
            . $step($people, 'deskstartreaders', $link('sources.php', 'menusources', 'local/oksigeniaayvurenda:manage'))
            . $step(false, 'deskstartlabels', $link('labels.php', 'menulabels', 'local/oksigeniaayvurenda:catalogue')
                . $link('cardprint.php', 'menucards', 'local/oksigeniaayvurenda:desk'))
            . '</ol></section>';
    }
}
// What there is to celebrate this week, quietly under the desk's cards: the desk is work, and it also has its days.
$moment = '';
if (!$help) {
    $found = \local_oksigeniaayvurenda\local\moments::of($library, time(), true);
    if ($found) {
        // Only its title is a link, as on the other staff screens.
        $part = \local_oksigeniaayvurenda\local\life::split($found[0]['text'], $found[0]['url']);
        $moment = '<p class="ayv-desk-moment"><span class="ayv-desk-moment-mark" aria-hidden="true"></span><span><b>'
            . s($str('momentsheading')) . '</b> ' . s($part['lifebefore'])
            . ($part['lifehref'] !== '' ? '<a href="' . s($part['lifehref']) . '">' . s($part['lifelinked']) . '</a>' : '')
            . s($part['lifeafter']) . '</span></p>';
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
' . $tools['html'] . $start . '
<div id="ayv-reader" class="ayv-reader" hidden></div>
<ul id="ayv-today" class="ayv-today">' . $cards . '</ul>
<ol id="ayv-log" class="ayv-log" aria-live="polite" data-empty="' . s($str('desklogempty')) . '"></ol>
' . $yearlink . $moment . '
</section>';
shell::output($str('desktitle'), $main, $library, '', [
    'search' => false,
    'scripts' => ['camera', 'desk'],
    'nav' => $help ? [] : ($own ? array_merge([
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
    ], \local_oksigeniaayvurenda\local\bank::is($library) ? [
        // Whoever keeps a bank runs its classes, its round and its sets too.
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $library->slug]))->out(false),
            'label' => $str('bankrollnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/bankround.php', ['library' => $library->slug]))->out(false),
            'label' => $str('bankroundnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/bank.php', ['library' => $library->slug]))->out(false),
            'label' => $str('banknav')],
    ] : [], [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug,
            'piece' => \local_oksigeniaayvurenda\local\bank::is($library) ? 'bank' : 'desk']))
            ->out(false), 'label' => $str('helpnav')],
    ]) : array_merge([
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
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug,
            'piece' => \local_oksigeniaayvurenda\local\bank::is($library) ? 'bank' : 'desk']))
            ->out(false), 'label' => $str('helpnav')],
    ], access::can('local/oksigeniaayvurenda:catalogue', $library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/add.php', ['library' => $library->slug]))->out(false),
            'label' => $str('addnav')],
    ] : [], \local_oksigeniaayvurenda\local\bank::is($library) ? [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $library->slug]))->out(false),
            'label' => $str('bankrollnav')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/bankround.php', ['library' => $library->slug]))->out(false),
            'label' => $str('bankroundnav')],
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
        'preload' => $preload,
        'book' => $book,
        'strings' => $strings + $tools['strings'],
    ] + $tools['config'],
]);
