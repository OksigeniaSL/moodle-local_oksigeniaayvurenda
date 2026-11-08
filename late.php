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
 * The books that are late, or all those out, by group.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\late;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$all = optional_param('all', false, PARAM_BOOL);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
// Whoever keeps only this library (a classroom's, a department's) sees what it has out, and who has it.
$own = $library && access::keeps($library) && !access::can('local/oksigeniaayvurenda:desk', $library);
if (!$own) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $ref, 'all' => (int) $all] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/late.php', ['library' => $library->slug]
    + $params + (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])))->out(false);
$now = time();
$notice = '';
$year = \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, $now);
// Where there are no classes nor tutors (a university, a public library, a company, a bookmobile), each reader is told.
$adults = \local_oksigeniaayvurenda\local\skins::no_classes((int) $library->entityid);
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'tell') {
        // Tell a group's tutor now, without waiting for the week's notice.
        $groupname = optional_param('group', '', PARAM_TEXT);
        $told = [];
        foreach ($DB->get_records('local_oksigeniaayvurenda_tutor', ['yearid' => $year->id, 'groupname' => $groupname]) as $tutor) {
            $tutor->entityid = (int) $library->entityid;
            $user = core_user::get_user((int) $tutor->userid);
            if ($user && \local_oksigeniaayvurenda\local\notices::tell_tutor($tutor, $now)) {
                $told[] = fullname($user);
            }
        }
        $notice = $told ? $str('latetutortoldname', (object) ['names' => implode(' · ', $told), 'group' => $groupname])
            : $str('latenotutor', $groupname);
    } else if ($do === 'lost') {
        // A book its reader says is lost, from the list: as at the desk (it may leave something to pay).
        $notice = (new \local_oksigeniaayvurenda\local\desk($library, (int) $USER->id, $now, $own))
            ->lose(required_param('loan', PARAM_INT))['message'];
    } else if ($do === 'remind') {
        // Remind a reader now.
        $notice = $str(\local_oksigeniaayvurenda\local\notices::remind(
            required_param('reader', PARAM_INT),
            $now,
            (int) $library->entityid
        )
            ? 'latereadertold' : 'latereadernoaccount');
    }
}
$groups = late::by_group($library, !$all, $now);
// The spans of lateness, as cards that filter: more than 30 days, 15 to 30, under 15.
$span = optional_param('span', '', PARAM_ALPHA);
$spanof = fn(int $days) => $days > 30 ? 'old' : ($days >= 15 ? 'mid' : 'new');
// Red past 30 days, orange from 15: what is close to it shows too.
$toneof = fn(int $days) => $days > 30 ? 'late' : ($days >= 15 ? 'near' : ($days > 0 ? 'soon' : 'ok'));
$counts = ['old' => 0, 'mid' => 0, 'new' => 0];
$order = optional_param('order', 'date', PARAM_ALPHA);
$lines = [];
foreach ($groups as $group) {
    foreach ($group['lines'] as $line) {
        if ($line['days'] > 0) {
            $counts[$spanof($line['days'])]++;
        }
        $lines[] = $line + ['group' => $group['group']];
    }
}
$only = optional_param('group', '', PARAM_TEXT);
$covers = \local_oksigeniaayvurenda\local\covers::urls(array_column($lines, 'recordid'));
$readerurl = fn(int $id) => (new moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $library->slug, 'id' => $id]))
    ->out(false);
// Their faces: the staff know the child, not the cover of the book.
$people = $DB->get_records_list(
    'local_oksigeniaayvurenda_reader',
    'id',
    array_unique(array_column($lines, 'readerid')),
    '',
    'id, entityid, userid'
);
$accounts = $DB->get_records_list('user', 'id', array_filter(array_column($people, 'userid')));
$faces = [];
foreach ($people as $person) {
    $faces[(int) $person->id] = \local_oksigeniaayvurenda\local\profile::photo($person, $accounts[$person->userid] ?? null);
}
// The tutors of each group this year, by their names: the button says whom it tells.
$tutornames = [];
foreach ($DB->get_records('local_oksigeniaayvurenda_tutor', ['yearid' => $year->id]) as $tutor) {
    $user = core_user::get_user((int) $tutor->userid);
    if ($user && !$user->deleted) {
        $tutornames[(string) $tutor->groupname][] = fullname($user);
    }
}
// What is out says how long it has, when that is little.
$leftwords = function (array $line) use ($str) {
    if ($line['left'] > 7) {
        return $str('lateuntil', $line['due']);
    }
    return !$line['left'] ? $str('latelefttoday') : ($line['left'] === 1 ? $str('lateleftone') : $str('lateleft', $line['left']));
};
// The boxes of the page: a group each (a reader each, with adults), the most late first, and in each the most late
// first; also when a span filters.
$boxes = [];
foreach ($lines as $line) {
    if (!$all && $span !== '' && isset($counts[$span]) && $spanof($line['days']) !== $span) {
        continue;
    }
    if ($all && isset(late::WINDOWS[$span]) && !late::in_window($line, $span)) {
        continue;
    }
    $key = $adults ? 'r' . $line['readerid'] : 'g' . $line['group'];
    if ($only !== '' && ($adults || $line['group'] !== $only)) {
        continue;
    }
    $boxes[$key]['group'] = $line['group'];
    $boxes[$key]['readerid'] = $line['readerid'];
    $boxes[$key]['name'] = $line['name'];
    $boxes[$key]['readers'][$line['readerid']] = true;
    $boxes[$key]['oldest'] = max($boxes[$key]['oldest'] ?? 0, $line['days']);
    $boxes[$key]['lines'][] = $line + [
        'url' => $readerurl($line['readerid']),
        'cover' => $covers[$line['recordid']] ?? null,
        'colour' => $line['recordid'] % 6,
        'photo' => $faces[(int) $line['readerid']] ?? '',
        'initial' => \local_oksigeniaayvurenda\local\profile::initial((string) $line['name']),
        'when' => $line['days'] > 0 ? $str($line['days'] === 1 ? 'desklateoneday' : 'desklatedays', $line['days'])
            : $leftwords($line),
        // Among what is out, anything late is what shows most.
        'tone' => $line['days'] > 0 ? ($all ? 'late' : $toneof($line['days']))
            : ($line['left'] <= 3 ? 'near' : ($line['left'] <= 7 ? 'week' : 'ok')),
        // In a box of one reader, the row is the book: its name is already the box's.
        'bybook' => $adults,
        'bookurl' => $adults ? (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
            'id' => $line['recordid']]))->out(false) : '',
        'wasdue' => $adults ? $str('latewasdue', $line['due']) : '',
        // Only what is late can be given up as lost from here.
        'canlose' => $line['days'] > 0 && !$all,
    ];
}
uasort($boxes, fn($a, $b) => [$b['oldest'], $a['group'], $a['name']] <=> [$a['oldest'], $b['group'], $b['name']]);
$shown = [];
// One day is «1 día», never «1 días».
$days = fn(int $n) => $str($n === 1 ? 'latedayone' : 'latedaysof', \local_oksigeniaayvurenda\local\screens::number($n));
foreach ($boxes as $box) {
    usort($box['lines'], fn($a, $b) => [$b['days'], $a['name']] <=> [$a['days'], $b['name']]);
    $readers = count($box['readers']);
    $books = count($box['lines']);
    if ($adults) {
        $heading = $box['name'];
        $summary = $box['oldest'] > 0 ? $str($books === 1 ? 'latereaderone' : 'latereader', (object) ['books' => $books,
            'days' => $days($box['oldest'])]) : $str($books === 1 ? 'latebooksone' : 'latebooks', $books);
    } else {
        $heading = $box['group'] !== '' ? $box['group'] : $str('readersnogroup');
        $summary = $box['oldest'] > 0
            ? $str($readers === 1 ? 'lategroupone' : 'lategroup', (object) ['readers' => $readers, 'days' => $days($box['oldest'])])
            : $str($readers === 1 ? 'lategroupcountone' : 'lategroupcount', $readers);
    }
    $tutors = $tutornames[$box['group']] ?? [];
    $shown[] = [
        'heading' => $heading,
        'group' => $box['group'],
        'summary' => $summary,
        'tone' => $toneof($box['oldest']),
        'lines' => $box['lines'],
        'open' => $only !== '',
        // To whom it goes, by name: the group's tutors; at a university, the reader.
        'tell' => !$all && !$adults && $box['group'] !== '' && $tutors
            ? $str('latetellname', implode(' · ', $tutors)) : '',
        'settutor' => !$all && !$adults && $box['group'] !== '' && !$tutors
            ? (new moodle_url('/local/oksigeniaayvurenda/tutors.php', ['library' => $library->slug]))->out(false) : '',
        'remind' => !$all && $adults && $box['oldest'] > 0 ? $str('latetellname', $box['name']) : '',
        'readerid' => $box['readerid'],
        'readerurl' => $adults ? $readerurl($box['readerid']) : '',
        'printurl' => $adults ? '' : $url(['group' => $box['group'], 'all' => (int) $all]),
    ];
}
// With few readers out, they are seen at once, each with their face: no group to open.
$cards = [];
$showing = [];
foreach ($boxes as $box) {
    $showing += $box['readers'];
}
if ($all && $only === '' && !$adults && count($showing) <= 24) {
    foreach ($boxes as $box) {
        foreach ($box['lines'] as $line) {
            $id = (int) $line['readerid'];
            $cards[$id] ??= ['name' => $line['name'], 'url' => $line['url'], 'photo' => $line['photo'],
                'initial' => $line['initial'], 'group' => $box['group'], 'books' => [], 'first' => $line['timedue']];
            $cards[$id]['first'] = min($cards[$id]['first'], $line['timedue']);
            $cards[$id]['books'][] = ['title' => $line['title'], 'when' => $line['when'], 'tone' => $line['tone'],
                'url' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
                    'id' => $line['recordid']]))->out(false)];
        }
    }
    $sortname = fn($card) => \core_text::strtolower($card['name']);
    $sortgroup = fn($card) => \core_text::strtolower($card['group']);
    usort($cards, fn($a, $b) => match ($order) {
        'name' => $sortname($a) <=> $sortname($b),
        'group' => [$sortgroup($a), $sortname($a)] <=> [$sortgroup($b), $sortname($b)],
        default => [$a['first'], $sortname($a)] <=> [$b['first'], $sortname($b)],
    });
    $shown = [];
}
$data = [
    'heading' => $str($all ? 'lateallheading' : 'lateheading'),
    'notice' => $notice,
    'tabs' => [
        ['label' => $str('latetablate'), 'url' => $url(), 'current' => !$all],
        ['label' => $str('latetaball'), 'url' => $url(['all' => 1]), 'current' => $all],
    ],
    'spans' => $all ? array_map(
        function ($key) use ($lines, $str, $url, $span) {
            $count = count(array_filter($lines, fn($line) => late::in_window($line, $key)));
            return ['key' => $key, 'count' => \local_oksigeniaayvurenda\local\screens::number($count),
                'label' => $str('latewindow' . $key), 'alarm' => $key === 'overdue' && $count > 0,
                'url' => $url($span === $key ? ['all' => 1] : ['all' => 1, 'span' => $key]), 'current' => $span === $key];
        },
        array_keys(late::WINDOWS)
    ) : array_map(fn($key) => ['key' => $key, 'count' => \local_oksigeniaayvurenda\local\screens::number(
        $counts[$key]
    ), 'label' => $str('latespan' . $key), 'url' => $url($span === $key ? [] : ['span' => $key]),
        'current' => $span === $key], ['old', 'mid', 'new']),
        'groups' => $shown,
        'hasgroups' => (bool) $shown,
        'cards' => $cards,
        'hascards' => (bool) $cards,
        'orderlabel' => $str('lateorder'),
        'orders' => $cards ? array_map(fn($key) => ['label' => $str('lateorder' . $key), 'current' => $order === $key,
        'url' => $url(['all' => 1, 'order' => $key] + ($span !== '' ? ['span' => $span] : []))], ['date', 'group', 'name']) : [],
        'only' => $only !== '',
        'allurl' => $url(['all' => (int) $all]),
        'empty' => $shown || $cards ? '' : $str($all ? 'lateallempty' : 'lateempty'),
        'action' => $url(['all' => (int) $all]),
        'sesskey' => sesskey(),
        'lostlabel' => $str('desklost'),
        'lostsure' => $str('desklostsure'),
];
foreach (['lateprint', 'lateallgroups', 'printnow', 'latesettutor', 'lateseerecord', 'latefind'] as $key) {
    $data[$key] = $str($key);
}
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/late', $data),
    $library,
    '',
    ['search' => false, 'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => $deskurl, 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'late']))
            ->out(false), 'label' => $str('helpnav')],
    ]]
);
