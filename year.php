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
 * The school year as one sheet: its days, a day touched, what rules it, its groups, and last year in numbers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\year_change;
use local_oksigeniaayvurenda\local\yearsheet;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    if (optional_param('do', '', PARAM_ALPHA) === 'remindall') {
        // Everyone with a book of a reading library out is told, with the date to have it back, and each class's tutor.
        $classes = !\local_oksigeniaayvurenda\local\skins::no_classes($entityid);
        foreach (catalogue::libraries() as $one) {
            if (
                (int) $one->entityid !== $entityid || \local_oksigeniaayvurenda\local\bank::is($one)
                    || !\local_oksigeniaayvurenda\local\bankround::holders($one)
            ) {
                continue;
            }
            \local_oksigeniaayvurenda\local\bankround::queue_reminder($one, 'readers', time());
            if ($classes) {
                \local_oksigeniaayvurenda\local\bankround::queue_reminder($one, 'tutors', time());
            }
        }
        $notice = $str('bankroundremindqueued');
    } else {
        // Each gesture of the sheet sends only what it changes; back to where it was touched.
        [$anchor, $extra] = yearsheet::apply($library, time());
        redirect(new moodle_url(
            '/local/oksigeniaayvurenda/year.php',
            ['library' => $library->slug, 'at' => $anchor] + $extra,
            $anchor
        ));
    }
}
$touched = optional_param('dia', '', PARAM_RAW_TRIMMED);
$from = optional_param('desde', '', PARAM_RAW_TRIMMED);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
// Choosing the exact days of a report (from «Reports», «Another»): two touches on the sheet, and back to it.
$pickreport = optional_param('elegir', '', PARAM_ALPHA);
$pick = in_array($pickreport, \local_oksigeniaayvurenda\local\reports::ALL, true) ? ['report' => $pickreport, 'first' => $from]
    : null;
// Or the day the labels start from: one touch, and back to them with what came in since.
if ($pickreport === 'labels') {
    $pick = ['report' => 'labels', 'first' => '', 'back' => [
        'kind' => optional_param('kind', 'labels', PARAM_ALPHA) === 'spines' ? 'spines' : 'labels',
        'sheet' => optional_param('sheet', '', PARAM_ALPHANUMEXT),
    ]];
}
if ($pick) {
    $from = '';
    $touched = '';
}
$saved = optional_param('saved', 0, PARAM_BOOL) ? optional_param('at', '', PARAM_ALPHANUMEXT) : '';
if (optional_param('toolong', 0, PARAM_BOOL)) {
    $notice = $str('closuresbad', \local_oksigeniaayvurenda\local\closures::LONGEST);
} else if (optional_param('order', 0, PARAM_BOOL)) {
    $notice = $str('yearsheetorder');
} else if (($synced = json_decode(optional_param('synced', '', PARAM_RAW), true)) && is_array($synced)) {
    $notice = $str('yearsynced', (object) $synced) . plans::left_out($synced);
}
$status = year_change::status($entityid);
// The date to have everything back, while it is to come and six weeks away at most.
$soon = $status['returnby'] && time() <= $status['returnby'] && time() >= $status['returnby'] - 6 * WEEKSECS;
$year = $status['year'];
$url = fn(string $page, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $page,
    ['library' => $library->slug] + $params
))->out(false);
$date = fn(int $time) => zones::date($time, 'strftimedate', $library);

$cards = [
    ['number' => \local_oksigeniaayvurenda\local\screens::number($status['groups']), 'label' => $str(
        'yeargroups',
        \local_oksigeniaayvurenda\local\screens::number($status['members'])
    ), 'url' => $url('readers.php')],
    ['number' => $str('yeartutorsof', (object) ['done' => $status['tutors'], 'of' => $status['groups']]),
        'label' => $str('yeartutors'), 'url' => $url('tutors.php'),
        'late' => $status['notutor'] > 0],
    ['number' => \local_oksigeniaayvurenda\local\screens::number($status['out']), 'label' => $str('yearout'),
        'url' => $url('late.php', ['all' => 1])],
];
if (\local_oksigeniaayvurenda\local\skins::no_classes((int) $library->entityid)) {
    $cards = array_slice($cards, 2);
}
if ($status['left']) {
    $cards[] = ['number' => count($status['left']), 'label' => $str('yearleft'), 'url' => '#ayv-left', 'late' => true];
}
$last = null;
$lastnone = '';
if ($status['last'] && !(int) $status['last']['loans']) {
    // A year before the library lent: said in one line, not in zeros.
    $lastnone = $str('yearsheetlastnone', $status['last']['name']);
} else if ($status['last']) {
    $l = $status['last'];
    $last = [
        'heading' => $str('yearlast', $l['name']),
        'total' => $str('yearlasttotal', (object) ['loans' => $l['loans'], 'borrowers' => $l['borrowers'],
            'members' => $l['members']]),
        'groups' => array_map(fn($g) => ['group' => $g['group'], 'line' => $str('yearlastgroup', (object) $g),
            'share' => $g['members'] ? round(100 * $g['borrowers'] / $g['members']) : 0], $l['groups']),
    ];
}
$sheet = yearsheet::months($library, $year, time(), $touched, $from, '/local/oksigeniaayvurenda/year.php', $pick);
$step = characters::step($entityid);
$pose = $saved !== '' ? 'celebra' : 'lee';
$crier = characters::whole($entityid) ? characters::figure('herald', $pose, $step) : null;
$classes = !\local_oksigeniaayvurenda\local\skins::no_classes($entityid);
// What the sheet asks for while choosing: the day the labels start from, or the first and the last of a report.
if ($pick && $pick['report'] === 'labels') {
    $choosing = $str('yearsheetpicklabels');
} else if ($pick) {
    $choosing = $pick['first'] !== '' ? $str('yearsheetpicklast', $date((new DateTimeImmutable(
        $pick['first'] . ' 12:00',
        zones::of($library)
    ))->getTimestamp())) : $str('yearsheetpickfirst');
} else {
    $choosing = $from !== '' && ($touched === '' || $touched < $from) ? $str('yearsheetchoosing') : '';
}
$data = [
    'heading' => $str('yearheading', $year->name),
    'dates' => $str('yeardates', (object) ['start' => $date((int) $year->timestart),
        'end' => $date((int) $year->timeend - DAYSECS)])
        . ' ' . $str($classes ? 'yearsheethint' : 'yearsheethintlay'),
    'crier' => $crier ? ['figure' => $crier, 'puppet' => characters::puppet('herald', $pose, $step)] : null,
    'notice' => $notice,
    'life' => \local_oksigeniaayvurenda\local\life::year($library, time()),
    // Weeks before the date to have everything back, or the end of the year without one: what is out, and telling everyone.
    'ending' => $status['out'] && ($soon || $status['moment'] === 'end') ? ['text' => $soon
        ? $str('returnbyending', (object) ['date' => $date((int) $status['returnby']), 'out' => $status['out']])
        : $str('yearending', $status['out']), 'url' => $url('late.php', ['all' => 1]), 'label' => $str('lateall'),
        'remind' => $str('yearremindall'), 'remindsure' => $str('yearremindsure')] : null,
    'cards' => $cards,
    'card' => yearsheet::card($library, $year, $touched, $from, $str),
    'release' => (new moodle_url('/local/oksigeniaayvurenda/year.php', ['library' => $library->slug], 'ayv-cs-sheet'))->out(false),
    'releaselabel' => $str('yearsheetrelease'),
    'choosing' => $choosing,
    'sheettitle' => $str('yearsheettitle'),
    'legend' => array_values(array_filter([
        $classes ? ['class' => 'ayv-cs-t1', 'label' => $str('yearsheetlegendt1')] : null,
        $classes ? ['class' => 'ayv-cs-t2', 'label' => $str('yearsheetlegendt2')] : null,
        $classes ? ['class' => 'ayv-cs-t3', 'label' => $str('yearsheetlegendt3')] : null,
        ['class' => 'ayv-cs-lc', 'label' => $str('yearsheetlegendclosed')],
        ['class' => 'ayv-cs-lf', 'label' => $str('yearsheetlegendrest')],
        ['class' => 'ayv-cs-ll', 'label' => $str('yearsheetlegenddeadline')],
        ['class' => 'ayv-cs-lh', 'label' => $str('yearsheetlegendtoday')],
    ])),
    'months' => $sheet['months'],
    'letters' => $sheet['letters'],
    'rulestitle' => $str('yearsheetrulestitle'),
    'rulessub' => $str('yearsheetrulessub'),
    'phrases' => yearsheet::rules($library, $year, $str, $saved),
    'groupstitle' => $str('yearsheetgroupstitle'),
    'groupsintro' => $str('yearstartintro'),
    'sync' => $str('yearsync'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'change' => $str('hiwchange'),
    'close' => $str('hiwclose'),
    'lesslabel' => $str('hiwless'),
    'morelabel' => $str('hiwmore'),
    'savedword' => $str('hiwsaved'),
    'left' => $status['left'],
    'hasleft' => (bool) $status['left'],
    'leftheading' => $str('yearleftheading'),
    'leftintro' => $str('yearleftintro'),
    'last' => $last,
    'lastnone' => $lastnone,
];
foreach ($data['left'] as &$row) {
    $row['bookslabel'] = $str($row['books'] === 1 ? 'yearleftone' : 'yearleftmany', $row['books']);
}
unset($row);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/year', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $url('readers.php'), 'label' => $str('readers')],
    ['url' => $url('desk.php'), 'label' => $str('desktitle')]]]
);
