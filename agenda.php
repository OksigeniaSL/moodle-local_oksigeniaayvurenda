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
 * The library's agenda: what is coming, for everyone; whoever manages the centre adds, changes and takes away.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\agenda;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
// The month shown and the day touched, as in the course sheet: links, so that a day can be shared and comes back.
$monthparam = optional_param('month', '', PARAM_RAW_TRIMMED);
$dayparam = optional_param('day', '', PARAM_RAW_TRIMMED);
$zone = zones::of((int) $library->entityid);
$now = time();
$today = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d');
$touched = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayparam) ? $dayparam : '';
$month = preg_match('/^\d{4}-\d{2}$/', $monthparam) ? $monthparam : substr($touched !== '' ? $touched : $today, 0, 7);
$pageurl = fn(array $params = []) => new moodle_url('/local/oksigeniaayvurenda/agenda.php', $params + ['library' => $ref]);
$PAGE->set_url($pageurl(array_filter(['month' => $month, 'day' => $touched])));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$manage = access::can('local/oksigeniaayvurenda:manage', $library);
$notice = '';
$problem = '';
$open = 0;
if ($manage && data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    $id = optional_param('id', 0, PARAM_INT);
    if ($do === 'save') {
        // A new one is written on the day touched: its name, and the rest on its card.
        $saved = agenda::save($library, ['title' => optional_param('title', '', PARAM_TEXT),
            'day' => optional_param('day', '', PARAM_TEXT)], $now);
        if (is_int($saved)) {
            $notice = $str('agendasaved');
            $open = $saved;
        } else {
            $problem = $str($saved);
        }
    } else if ($do === 'set' && $id) {
        // Each gesture sends one thing: the first that comes is the one changed.
        foreach (['time', 'lasts', 'placeother', 'place', 'audience', 'seats', 'title', 'text'] as $field) {
            $value = optional_param($field, null, PARAM_TEXT);
            if ($value !== null && ($field !== 'placeother' || trim($value) !== '')) {
                agenda::set($library, $id, $field === 'placeother' ? 'place' : $field, (string) $value, $now);
                break;
            }
        }
        $open = $id;
    } else if ($do === 'remove' && $id) {
        agenda::remove($library, $id);
        $notice = $str('agendaremoved');
    }
}
$colours = ['green', 'coral', 'sun', 'night'];
$local = fn(int $time) => (new DateTimeImmutable('@' . $time))->setTimezone($zone);
$first = new DateTimeImmutable($month . '-01 12:00', $zone);
$last = $first->modify('last day of this month');
$inmonth = agenda::between(
    (int) $library->entityid,
    $first->setTime(0, 0)->getTimestamp(),
    $last->setTime(23, 59, 59)->getTimestamp(),
    (int) $library->id
);
$byday = [];
foreach ($inmonth as $activity) {
    $byday[$local((int) $activity->timestart)->format('Y-m-d')][] = $activity;
}
// The sheet of the month: a cell a day, the activities as marks of colour.
$rest = zones::rest_days($library);
$firstday = zones::first_weekday($library);
$letters = explode(',', $str('yearsheetletters'));
$cells = [];
for ($i = 0; $i < ((int) $first->format('N') - $firstday + 7) % 7; $i++) {
    $cells[] = ['blank' => true];
}
for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
    $date = $day->format('Y-m-d');
    $acts = $byday[$date] ?? [];
    $cells[] = ['blank' => false, 'n' => (int) $day->format('j'),
        'url' => $pageurl(['month' => $month, 'day' => $date])->out(false) . '#ayv-ag-dia',
        'aria' => core_text::strtolower(userdate($day->getTimestamp(), '%A')) . ' ' . $str('hiwdaymonth', (object) [
            'day' => (int) $day->format('j'), 'month' => userdate($day->getTimestamp(), '%B')])
            . ($acts ? ', ' . (count($acts) === 1 ? $str('agendaariaone') : $str('agendaaria', count($acts))) : ''),
        'rest' => in_array((int) $day->format('N'), $rest, true), 'past' => $date < $today, 'today' => $date === $today,
        'touched' => $date === $touched,
        'acts' => array_map(fn($a) => ['short' => core_text::substr(format_string($a->title, true, ['escape' => false]), 0, 14),
            'colour' => $colours[(int) $a->id % 4]], $acts)];
}
// The day touched: its activities, each with its gestures, and a new one written on it.
$dayview = null;
if ($touched !== '') {
    $at = new DateTimeImmutable($touched . ' 12:00', $zone);
    $places = agenda::used($library, 'place');
    $audiences = agenda::used($library, 'audience');
    $acts = [];
    foreach ($byday[$touched] ?? [] as $activity) {
        $start = $local((int) $activity->timestart);
        $timed = $start->format('H:i') !== '00:00';
        $minutes = $activity->timeend ? intdiv((int) $activity->timeend - (int) $activity->timestart, MINSECS) : 0;
        $shift = fn(int $by) => $timed ? $start->modify(($by > 0 ? '+' : '') . $by . ' minutes')->format('H:i') : '10:00';
        $chips = fn(array $known, string $now) => array_map(
            fn($v) => ['label' => $v, 'pressed' => $v === $now],
            array_values(array_unique(array_filter(array_merge($now !== '' ? [$now] : [], $known))))
        );
        $seats = (int) $activity->seats;
        $acts[] = [
            'id' => (int) $activity->id,
            'colour' => $colours[(int) $activity->id % 4],
            'open' => (int) $activity->id === $open,
            'title' => format_string($activity->title, true, ['escape' => false]),
            'when' => agenda::when($activity, $library),
            'place' => (string) $activity->place,
            'audience' => (string) $activity->audience,
            'text' => (string) $activity->text,
            'seats' => !$seats ? '' : ($seats === 1 ? $str('agendaseatsone') : $str('agendaseats', $seats)),
            'canedit' => $manage,
            'timevalue' => $timed ? $start->format('H:i') : '—',
            'timeless' => $shift(-15),
            'timemore' => $shift(15),
            'allday' => !$timed,
            'lasts' => array_map(
                fn($m) => ['value' => $m, 'label' => $str('agendalasts' . $m), 'pressed' => $m === $minutes],
                [30, 60, 90, 120, 0]
            ),
            'places' => $chips($places, (string) $activity->place),
            'audiences' => $chips($audiences, (string) $activity->audience),
            'seatsvalue' => $seats ?: '—',
            'seatsless' => max(0, $seats - 5),
            'seatsmore' => $seats + 5,
            'unlimited' => !$seats,
        ];
    }
    $dayview = ['long' => userdate($at->getTimestamp(), $str('agendadayformat')), 'monthshort' => core_text::strtoupper(
        userdate($at->getTimestamp(), '%b')
    ), 'n' => (int) $at->format('j'), 'dayvalue' => $touched, 'acts' => $acts,
        'agendanewon' => $str('agendanewon', userdate($at->getTimestamp(), '%A %e')),
        'release' => $pageurl(['month' => $month])->out(false)];
}
// What is coming, under the sheet: each with its day's leaf, leading to its day.
$activities = agenda::between((int) $library->entityid, zones::today($library, $now), $now + agenda::AHEAD * DAYSECS);
$several = count(array_unique(array_column($activities, 'libraryid'))) > 1;
$cards = [];
foreach ($activities as $activity) {
    $date = $local((int) $activity->timestart);
    $cards[] = [
        'title' => format_string($activity->title, true, ['escape' => false]),
        'when' => agenda::when($activity, $library) . ($several ? ' · ' . format_string($activity->libraryname) : ''),
        'day' => (int) $date->format('j'),
        'month' => core_text::strtoupper(userdate($date->getTimestamp(), '%b')),
        'place' => (string) $activity->place,
        'audience' => (string) $activity->audience,
        'seats' => !$activity->seats ? '' : ((int) $activity->seats === 1 ? $str('agendaseatsone')
            : $str('agendaseats', (int) $activity->seats)),
        'dayurl' => $pageurl(['month' => $date->format('Y-m'), 'day' => $date->format('Y-m-d')])->out(false) . '#ayv-ag-dia',
    ];
}
$data = [
    'heading' => $str('agendatitle'),
    'intro' => $manage ? $str('agendaintrostaff') : $str('agendaintro'),
    'notice' => $notice,
    'problem' => $problem,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'manage' => $manage,
    'monthname' => core_text::strtoupper(core_text::substr(userdate($first->getTimestamp(), '%B'), 0, 1))
        . core_text::substr(userdate($first->getTimestamp(), '%B'), 1),
    'monthyear' => $first->format('Y'),
    'prevurl' => $pageurl(['month' => $first->modify('first day of previous month')->format('Y-m')])->out(false),
    'nexturl' => $pageurl(['month' => $first->modify('first day of next month')->format('Y-m')])->out(false),
    'letters' => array_map(fn($i) => ['letter' => trim($letters[($firstday - 1 + $i) % 7] ?? ''),
        'rest' => in_array(($firstday - 1 + $i) % 7 + 1, $rest, true)], range(0, 6)),
    'cells' => $cells,
    'day' => $dayview,
    'activities' => $cards,
    'hasactivities' => (bool) $cards,
];
foreach (
    ['agendanone', 'agendaactivity', 'agendatime', 'agendaplace', 'agendaaudience', 'agendatext', 'agendatexthint',
        'agendaseatslabel', 'agendasave', 'agendaedit', 'agendaremove', 'agendaremovesure', 'agendaallday', 'agendalasts',
        'agendanoseats', 'agendaotherplace', 'agendaput', 'comingheading', 'savedword'] as $key
) {
    $data[$key] = $str($key === 'comingheading' ? 'agendacoming' : ($key === 'savedword' ? 'profilesaved' : $key));
}
$data += ['earlier' => $str('agendaearlier'), 'later' => $str('agendalater'), 'fewer' => $str('agendafewer'),
    'more' => $str('agendamore'), 'nothing' => $str('agendanothingday'), 'prevlabel' => $str('agendaprev'),
    'nextlabel' => $str('agendanext'), 'releaselabel' => $str('agendarelease')];
shell::output(
    $str('agendatitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/agenda', $data),
    $library,
    '',
    ['search' => false]
);
