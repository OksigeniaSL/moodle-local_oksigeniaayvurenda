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
 * The school year: what its beginning asks, its end, last year in numbers and the day it starts.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\cohorts;
use local_oksigeniaayvurenda\local\year_change;
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
    $action = optional_param('do', '', PARAM_ALPHA);
    if ($action === 'sync') {
        $stats = cohorts::sync($entityid, null, true);
        $notice = $str('yearsynced', (object) $stats) . plans::left_out($stats);
    } else if ($action === 'closedadd') {
        $ok = \local_oksigeniaayvurenda\local\closures::add(
            $entityid,
            optional_param('from', '', PARAM_RAW_TRIMMED),
            optional_param('to', '', PARAM_RAW_TRIMMED),
            optional_param('label', '', PARAM_TEXT)
        );
        $notice = $str($ok ? 'closuresadded' : 'closuresbad', \local_oksigeniaayvurenda\local\closures::LONGEST);
    } else if ($action === 'closedremove') {
        \local_oksigeniaayvurenda\local\closures::remove(
            $entityid,
            optional_param('from', '', PARAM_RAW_TRIMMED),
            optional_param('to', '', PARAM_RAW_TRIMMED)
        );
        $notice = $str('closuresremoved');
    } else if ($action === 'week') {
        \local_oksigeniaayvurenda\local\libraries::update_entity($entityid, [
            'weekstart' => optional_param('weekstart', 0, PARAM_INT),
            'restdays' => optional_param_array('restdays', [], PARAM_INT),
        ]);
        $notice = $str('weeksaved');
    } else if ($action === 'returnby') {
        $day = optional_param('clear', 0, PARAM_BOOL) ? '' : optional_param('returnby', '', PARAM_RAW_TRIMMED);
        $ok = \local_oksigeniaayvurenda\local\closures::set_return_by($entityid, $day);
        $notice = $str(!$ok ? 'yearbadday' : ($day === '' ? 'returnbygone' : 'returnbysaved'));
    } else if ($action === 'terms') {
        $days = optional_param('clear', 0, PARAM_BOOL) ? [] : [optional_param('term1', '', PARAM_RAW_TRIMMED),
            optional_param('term2', '', PARAM_RAW_TRIMMED), optional_param('term3', '', PARAM_RAW_TRIMMED)];
        $ok = \local_oksigeniaayvurenda\local\challenges::set_ends($entityid, $days);
        $notice = $str(!$ok ? 'yearbadday' : 'termssaved');
    } else if ($action === 'remindall') {
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
    } else if ($action === 'start') {
        $ok = year_change::set_start($entityid, optional_param('month', 0, PARAM_INT), optional_param('day', 0, PARAM_INT));
        $notice = $str($ok ? 'sourcessaved' : 'yearbadday');
    }
}
$status = year_change::status($entityid);
// The date to have everything back, while it is to come and six weeks away at most.
// The ends of the terms of the year running, as the class challenges count them.
$termyear = \local_oksigeniaayvurenda\local\years::around($entityid, time());
$termends = $termyear ? \local_oksigeniaayvurenda\local\challenges::ends($termyear) : [];
$entitysettings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
    ?: [];
$soon = $status['returnby'] && time() <= $status['returnby'] && time() >= $status['returnby'] - 6 * WEEKSECS;
$year = $status['year'];
$url = fn(string $page, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $page,
    ['library' => $library->slug] + $params
))->out(false);
$date = fn(int $time) => zones::date($time, 'strftimedate', $library);

$cards = [
    ['number' => $status['groups'], 'label' => $str('yeargroups', $status['members']), 'url' => $url('readers.php')],
    ['number' => $status['tutors'] . ' / ' . $status['groups'], 'label' => $str('yeartutors'), 'url' => $url('tutors.php'),
        'late' => $status['notutor'] > 0],
    ['number' => $status['out'], 'label' => $str('yearout'), 'url' => $url('late.php', ['all' => 1])],
];
if (\local_oksigeniaayvurenda\local\skins::no_classes((int) $library->entityid)) {
    $cards = array_slice($cards, 2);
}
if ($status['left']) {
    $cards[] = ['number' => count($status['left']), 'label' => $str('yearleft'), 'url' => '#ayv-left', 'late' => true];
}
$last = null;
if ($status['last']) {
    $l = $status['last'];
    $last = [
        'heading' => $str('yearlast', $l['name']),
        'total' => $str('yearlasttotal', (object) ['loans' => $l['loans'], 'borrowers' => $l['borrowers'],
            'members' => $l['members']]),
        'groups' => array_map(fn($g) => ['group' => $g['group'], 'line' => $str('yearlastgroup', (object) $g),
            'share' => $g['members'] ? round(100 * $g['borrowers'] / $g['members']) : 0], $l['groups']),
    ];
}
[$month, $day] = array_map('intval', explode('-', (string) ($DB->get_field(
    'local_oksigeniaayvurenda_entity',
    'yearstart',
    ['id' => $entityid]
) ?: '09-01')));
$days = [];
for ($d = 1; $d <= 31; $d++) {
    $days[] = ['value' => $d, 'label' => $d, 'selected' => $d === $day];
}
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = ['value' => $m, 'label' => userdate(make_timestamp(2001, $m, 15, 12), '%B'), 'selected' => $m === $month];
}
// The days the centre is closed: what is to come first, and what passed, faded.
$today = zones::date(time(), '%Y-%m-%d', $library);
$closures = array_map(function ($range) use ($library, $str, $today) {
    $day = fn(string $d) => zones::date(
        (new DateTimeImmutable($d . ' 12:00', zones::of($library)))->getTimestamp(),
        'strftimedate',
        $library
    );
    return $range + [
        'name' => $range['label'] !== '' ? $range['label'] : $str('closuresnoname'),
        'when' => $range['from'] === $range['to'] ? $day($range['from'])
            : $str('closuresrange', (object) ['from' => $day($range['from']), 'to' => $day($range['to'])]),
        'past' => $range['to'] < $today,
    ];
}, \local_oksigeniaayvurenda\local\closures::of($entityid));
// The centre's week: the day it starts, and the days the libraries close every week (due dates, notices, «this week»),
// the days in the order of its week (Monday 1 to Sunday 7, as ISO numbers them).
$calendar = \core_calendar\type_factory::get_calendar_instance();
$weekdays = $calendar->get_weekdays();
$dayname = fn(int $iso) => (string) $weekdays[$iso % 7]['fullname'];
$moodlestart = (int) $calendar->get_starting_weekday() ?: 7;
$ownstart = (int) ((json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
    true
) ?: [])['weekstart'] ?? 0);
$rest = zones::rest_days($library);
$first = zones::first_weekday($library);
$week = ['heading' => $str('weekheading'), 'intro' => $str('weekintro'), 'startlabel' => $str('weekstart'),
    'restlabel' => $str('weekrest'), 'save' => $str('weeksave'),
    'starts' => array_merge(
        [['value' => 0, 'label' => $str('weekstartmoodle', $dayname($moodlestart)), 'selected' => $ownstart === 0]],
        array_map(fn($iso) => ['value' => $iso, 'label' => $dayname($iso), 'selected' => $ownstart === $iso], range(1, 7))
    ),
    'days' => array_map(
        fn($i) => ['value' => ($first - 1 + $i) % 7 + 1, 'label' => $dayname(($first - 1 + $i) % 7 + 1),
            'on' => in_array(($first - 1 + $i) % 7 + 1, $rest, true)],
        range(0, 6)
    )];
$data = [
    'heading' => $str('yearheading', $year->name),
    'life' => \local_oksigeniaayvurenda\local\life::year($library, time()),
    'closures' => $closures,
    'hasclosures' => (bool) $closures,
    'closuresheading' => $str('closuresheading'),
    'closuresintro' => $str('closuresintro'),
    'closuresnone' => $str('closuresnone'),
    'closuresadd' => $str('closuresadd'),
    'closuresremove' => $str('closuresremove'),
    'closuresname' => $str('closuresname'),
    'closuresnamehint' => $str('closuresnamehint'),
    'closuresfrom' => $str('closuresfrom'),
    'closuresto' => $str('closuresto'),
    'closuressave' => $str('closuressave'),
    'closuresclose' => $str('offsiteclose'),
    'closurestoday' => $today,
    'dates' => $str('yeardates', (object) ['start' => $date((int) $year->timestart),
        'end' => $date((int) $year->timeend - DAYSECS)]),
    'notice' => $notice,
    // Weeks before the date to have everything back, or the end of the year without one: what is out, and telling everyone.
    'ending' => $status['out'] && ($soon || $status['moment'] === 'end') ? ['text' => $soon
        ? $str('returnbyending', (object) ['date' => $date((int) $status['returnby']), 'out' => $status['out']])
        : $str('yearending', $status['out']), 'url' => $url('late.php', ['all' => 1]), 'label' => $str('lateall'),
        'remind' => $str('yearremindall'), 'remindsure' => $str('yearremindsure')] : null,
    // The centre's week: the day it starts, and the days the libraries close every week (due dates, notices, «this week»).
    'week' => $week,
    // The date by which everything lent has to be back: any library, any calendar.
    'returnby' => [
        'heading' => $str('returnbyheading'),
        'intro' => $str('returnbyintro'),
        'text' => $status['returnby'] ? $date((int) $status['returnby']) : $str('returnbynone'),
        'value' => $status['returnby'] ? zones::date((int) $status['returnby'], '%Y-%m-%d', $library) : '',
        'has' => (bool) $status['returnby'],
        'label' => $str('returnbylabel'),
        'save' => $str('returnbysave'),
        'clear' => $str('returnbyclear'),
    ],
    // When each term ends, for the class challenges: where there are classes, any country's calendar (D4).
    'terms' => \local_oksigeniaayvurenda\local\skins::no_classes($entityid) || !$termyear ? null : [
        'heading' => $str('termsheading'),
        'intro' => $str('termsintro'),
        'save' => $str('returnbysave'),
        'clear' => $str('termsclear'),
        'has' => !empty($entitysettings['termends']),
        'ends' => array_map(fn($end, $i) => ['name' => 'term' . ($i + 1), 'label' => $str('termsend', $i + 1),
            'value' => zones::date($end, '%Y-%m-%d', $library)], $termends, array_keys($termends)),
    ],
    'cards' => $cards,
    'startintro' => $str('yearstartintro'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'sync' => $str('yearsync'),
    'left' => $status['left'],
    'hasleft' => (bool) $status['left'],
    'leftheading' => $str('yearleftheading'),
    'leftintro' => $str('yearleftintro'),
    'last' => $last,
    'startheading' => $str('yearstartheading'),
    'startnote' => $str('yearstartnote'),
    'days' => $days,
    'months' => $months,
    'daylabel' => $str('yearday'),
    'monthlabel' => $str('yearmonth'),
    'save' => $str('sourcessave'),
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
