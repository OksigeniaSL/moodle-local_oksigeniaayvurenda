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
 * The loan rules of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\cataloguer;
use local_oksigeniaayvurenda\core\desk\sanction_rules;
use local_oksigeniaayvurenda\core\loan_rules;
use local_oksigeniaayvurenda\core\notice_rules;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\rules;
use local_oksigeniaayvurenda\local\sanctions;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/rules.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$sm = get_string_manager();
$notice = '';
$entityid = (int) $library->entityid;
if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'sanctions') {
    // What a late book leaves, for the whole centre: its readers are the same in all its libraries. An amount left blank
    // keeps what it was: it must not turn into a fine of next to nothing, or a stop at the first one.
    $before = sanctions::settings($entityid);
    sanctions::save_settings($entityid, [
        // The notices of late books are saved apart, in their own sheet: they stay as they were.
        'noticeafter' => $before['noticeafter'], 'noticeevery' => $before['noticeevery'], 'noticedates' => $before['noticedates'],
        'noticereaders' => $before['noticereaders'], 'noticefamilies' => $before['noticefamilies'],
        'noticetutors' => $before['noticetutors'],
        'mode' => optional_param('mode', sanction_rules::NONE, PARAM_ALPHA),
        'grace' => optional_param('grace', 0, PARAM_INT),
        'perday' => optional_param('perday', 1, PARAM_INT),
        'maxdays' => optional_param('maxdays', 30, PARAM_INT),
        'cents' => sanction_rules::to_cents(optional_param('amount', '', PARAM_RAW_TRIMMED)) ?: $before['cents'],
        'maxcents' => sanction_rules::to_cents(optional_param('maxamount', '', PARAM_RAW_TRIMMED)) ?: $before['maxcents'],
        'block' => sanction_rules::to_cents(optional_param('block', '', PARAM_RAW_TRIMMED)) ?: $before['block'],
        'currency' => optional_param('currency', '', PARAM_ALPHA),
        // With all the stages marked, no stage is chosen: every one.
        'stages' => optional_param('allstages', 0, PARAM_BOOL) ? [] : optional_param_array('stages', [], PARAM_TEXT),
        'adults' => optional_param('adults', 0, PARAM_BOOL),
    ]);
    $notice = $str('sanctionssaved');
} else if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'notices') {
    // When late books are told about, and to whom, for the whole centre: a start of a touch (by kind of library, a
    // school's days from its year), or the sheet as it was filled in.
    $before = sanctions::settings($entityid);
    $preset = optional_param('preset', '', PARAM_ALPHA);
    if (isset(notice_rules::PRESETS[$preset])) {
        $chosen = notice_rules::PRESETS[$preset];
        if ($chosen['noticeevery'] === notice_rules::DATES) {
            // The thirds of the classes: up to the date to have everything back, or the year less its long holiday.
            $year = \local_oksigeniaayvurenda\local\years::around($entityid, time());
            $last = \local_oksigeniaayvurenda\local\closures::return_by($entityid);
            $end = $last !== null && $last > (int) $year->timestart && $last <= (int) $year->timeend ? $last
                : (int) $year->timeend - 70 * DAYSECS;
            $chosen['noticedates'] = notice_rules::thirds((int) $year->timestart, $end + 1, zones::of($entityid));
        }
        $notice = $str('noticespresetsaved');
    } else {
        $chosen = [
            'noticeafter' => optional_param('noticeafter', 1, PARAM_INT),
            'noticeevery' => optional_param('noticeevery', 'week', PARAM_ALPHA),
            'noticedates' => optional_param_array('noticedates', [], PARAM_RAW_TRIMMED),
            'noticereaders' => optional_param('noticereaders', 0, PARAM_BOOL),
            'noticefamilies' => optional_param('noticefamilies', 0, PARAM_BOOL),
            'noticetutors' => optional_param('noticetutors', 0, PARAM_BOOL),
        ];
        $notice = $str('noticessaved');
    }
    sanctions::save_settings($entityid, array_merge($before, $chosen));
} else if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'renewfrom') {
    // How the desk counts a renewal, for the whole centre: the other count and a day by hand stay a touch away there.
    rules::save_renew_from($entityid, optional_param('renewfrom', loan_rules::FROM_TODAY, PARAM_ALPHA));
    $notice = $str('renewfromsaved');
} else if (data_submitted() && confirm_sesskey()) {
    // One rule at a time: changed, added or taken away; the others stay as they were.
    $all = rules::of((int) $library->id);
    $index = optional_param('rule', 'new', PARAM_ALPHANUM);
    if (optional_param('do', '', PARAM_ALPHA) === 'remove') {
        unset($all[(int) $index]);
    } else {
        $lent = !optional_param('roomonly', 0, PARAM_BOOL);
        $rule = [
            // Any reader or copy is a star, which the parameter cleaning takes away: back to a star.
            'readertype' => optional_param('readertype', '', PARAM_ALPHANUMEXT) ?: '*',
            'itemtype' => optional_param('itemtype', '', PARAM_ALPHANUMEXT) ?: '*',
            'loandays' => $lent ? max(1, optional_param('loandays', 14, PARAM_INT)) : 0,
            'maxloans' => optional_param('nolimit', 0, PARAM_BOOL) ? 0 : max(1, optional_param('maxloans', 1, PARAM_INT)),
            'renewals' => $lent ? max(0, optional_param('renewals', 0, PARAM_INT)) : 0,
            'renewdays' => $lent ? max(1, optional_param('renewdays', 7, PARAM_INT)) : 7,
        ];
        // Two rules for the same readers and copies cannot be: a new one that repeats another says so, and changes nothing.
        $twin = null;
        foreach ($all as $at => $other) {
            $same = $other['readertype'] === $rule['readertype'] && $other['itemtype'] === $rule['itemtype'];
            if ((string) $at !== $index && $same) {
                $twin = $other;
            }
        }
        if ($twin) {
            $notice = $str('rulestwin', (object) ['who' => $rule['readertype'] === '*' ? $str('rulesanyreader')
                : ($sm->string_exists('readertype' . $rule['readertype'], 'local_oksigeniaayvurenda')
                ? $str(\local_oksigeniaayvurenda\local\skins::words((int) $library->entityid, 'readertype' . $rule['readertype']))
                : $rule['readertype']),
                'what' => $rule['itemtype'] === '*' ? $str('rulesanyitem')
                : ($sm->string_exists('itemtype' . $rule['itemtype'], 'local_oksigeniaayvurenda')
                ? $str('itemtype' . $rule['itemtype']) : $rule['itemtype'])]);
        } else if ($index === 'new') {
            $all[] = $rule;
        } else {
            $all[(int) $index] = $rule;
        }
    }
    if ($notice === '') {
        $notice = $str('rulessaved', rules::save((int) $library->id, array_values($all)));
    }
}
$readertypes = $DB->get_fieldset_sql(
    'SELECT DISTINCT readertype FROM {local_oksigeniaayvurenda_reader} WHERE entityid = ?',
    [$library->entityid]
);
$nocourses = \local_oksigeniaayvurenda\local\skins::no_courses((int) $library->entityid);
$readertypes = array_values(array_unique(array_merge(
    $nocourses ? ['*', 'student', 'staff'] : ['*', 'student', 'teacher', 'staff'],
    $readertypes
)));
$itemtypes = array_merge(['*'], cataloguer::itemtypes((int) $library->entityid));
$label = function (string $kind, string $value) use ($str, $sm): string {
    if ($value === '*') {
        return $str($kind === 'readertype' ? 'rulesanyreader' : 'rulesanyitem');
    }
    return $sm->string_exists($kind . $value, 'local_oksigeniaayvurenda') ? $str($kind . $value) : $value;
};
$options = fn(array $values, string $kind, string $chosen) => array_map(fn($v) => ['value' => $v,
    'label' => $label($kind, $v), 'selected' => $v === $chosen], $values);
// Each rule read as a sentence: who, what, how many, how long, how many times more.
$sentence = function (array $rule) use ($str): string {
    $days = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
    if ((int) $rule['loandays'] === 0) {
        return $str('rulesroomonly');
    }
    $max = match ((int) $rule['maxloans']) {
        0 => $str('rulesanynumber'),
        1 => $str('rulesuptoone'),
        default => $str('rulesupto', (int) $rule['maxloans']),
    };
    $renew = (int) $rule['renewals'] === 0 ? $str('rulesnorenew')
        : $str((int) $rule['renewals'] === 1 ? 'rulesrenewonce' : 'rulesrenewtimes', (object) ['times' => (int) $rule['renewals'],
            'days' => $days((int) $rule['renewdays'])]);
    return $str('rulessentence', (object) ['max' => $max, 'days' => $days((int) $rule['loandays']), 'renew' => $renew]);
};
$form = fn(string $index, array $rule) => [
    'index' => $index,
    'readertypes' => $options($readertypes, 'readertype', (string) $rule['readertype']),
    'itemtypes' => $options($itemtypes, 'itemtype', (string) $rule['itemtype']),
    'roomonly' => (int) $rule['loandays'] === 0 && $index !== 'new',
    'loandays' => (int) $rule['loandays'] ?: 14,
    'nolimit' => (int) $rule['maxloans'] === 0 && $index !== 'new',
    'maxloans' => (int) $rule['maxloans'] ?: 1,
    'renewals' => (int) $rule['renewals'],
    'renewdays' => (int) $rule['renewdays'] ?: 7,
    'removable' => $index !== 'new',
];
$cards = [];
foreach (rules::of((int) $library->id) as $index => $rule) {
    $cards[] = [
        'who' => $label('readertype', (string) $rule['readertype']),
        'what' => $label('itemtype', (string) $rule['itemtype']),
        'sentence' => $sentence($rule),
        'form' => $form((string) $index, $rule),
    ];
}
$parent = !empty($library->parentid) ? $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $library->parentid]) : null;
// What a late book leaves: read as a sentence, changed in a sheet.
$late = sanctions::settings($entityid);
$money = fn(int $cents) => sanctions::money($cents, $late['currency']);
$plain = fn(int $cents) => number_format($cents / 100, 2, '.', '');
$counted = fn(int $n) => $str($n === 1 ? 'rulesdayone' : 'rulesdaysn', $n);
$latesentence = $late['mode'] === sanction_rules::DAYS
    ? $str('sanctionssentencedays', (object) ['days' => $counted($late['perday']), 'max' => $counted($late['maxdays'])])
    : ($late['mode'] === sanction_rules::FINE ? $str('sanctionssentencefine', (object) ['amount' => $money($late['cents']),
    'max' => $money($late['maxcents']), 'block' => $money($late['block'])]) : $str('sanctionssentencenone'));
// What a late book leaves; when it is told about has its own card.
$latefacts = [];
if ($late['mode'] !== sanction_rules::NONE) {
    if ($late['grace']) {
        $latefacts[] = $late['grace'] === 1 ? $str('sanctionsgraceone') : $str('sanctionsgrace', $late['grace']);
    }
    $stagenames = implode(', ', $late['stages']);
    $latefacts[] = $late['stages'] ? $str($late['adults'] ? 'sanctionswhostages' : 'sanctionswhostagesonly', $stagenames)
        : $str($late['adults'] ? 'sanctionswhoall' : 'sanctionswhostudents');
}
$renewfrom = rules::renew_from($entityid);
// When late books are told about, as a sentence, and the sheet with the days of the year shown in this school year.
$first = $late['noticeafter'] === 1 ? $str('noticesfirstnext') : $str('noticesfirstafter', $late['noticeafter']);
$tz = zones::of($entityid);
$year = \local_oksigeniaayvurenda\local\years::around($entityid, time());
$inyear = function (string $date) use ($year, $tz): int {
    $start = (new \DateTimeImmutable('@' . (int) $year->timestart))->setTimezone($tz);
    $day = $start->setDate((int) $start->format('Y'), (int) substr($date, 0, 2), (int) substr($date, 3, 2))->setTime(12, 0);
    return ($day < $start ? $day->modify('+1 year') : $day)->getTimestamp();
};
$days = array_map($inyear, $late['noticedates']);
sort($days);
$named = array_map(fn($time) => userdate($time, get_string('strftimedateshort', 'langconfig'), $tz->getName()), $days);
$joined = count($named) > 1 ? implode(', ', array_slice($named, 0, -1)) . $str('addand') . end($named) : (string) reset($named);
$noticesentence = match (true) {
    $late['noticeevery'] === notice_rules::HAND => $str('noticeseveryhand'),
    $late['noticeevery'] === notice_rules::DATES && !$named => $str('noticeseverydatesnone'),
    $late['noticeevery'] === notice_rules::DATES => $str('noticeseverydates', (object) ['dates' => $joined,
        'days' => $counted($late['noticeafter'])]),
    default => $str('noticesevery' . $late['noticeevery'], $first),
};
$whom = array_values(array_filter([
    $late['noticereaders'] ? $str('noticeswhoreaders') : '',
    $late['noticefamilies'] ? $str('noticeswhofamilies') : '',
    $late['noticetutors'] ? $str('noticeswhotutors') : '',
]));
$noticewho = !$whom ? $str('noticeswhonone') : $str('noticeswho', count($whom) > 1
    ? implode(', ', array_slice($whom, 0, -1)) . $str('addand') . end($whom) : $whom[0]);
// As a date field wants them (userdate() takes the zero off the day).
$datefields = array_map(fn($time) => ['value' => (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->format('Y-m-d')], $days);
while (count($datefields) < 4) {
    $datefields[] = ['value' => ''];
}
$latestate = sanctions::now($entityid, time());
$stages = holds::stages($entityid, time());
$currencies = get_string_manager()->get_list_of_currencies();
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
$data = [
    'heading' => $str('rulestitle'),
    'intro' => $str('rulesintroshort'),
    'inherited' => $cards ? '' : ($parent ? $str('rulesparent', format_string($parent->name, true, ['escape' => false]))
        : $str('rulesdefault')),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'cards' => $cards,
    'hascards' => (bool) $cards,
    'newform' => $form('new', ['readertype' => '*', 'itemtype' => '*', 'loandays' => 14, 'maxloans' => 1, 'renewals' => 1,
        'renewdays' => 7]),
    'latesentence' => $latesentence,
    // Its own guided help, beside it: the screen's «How does it work?» shows the loan rules.
    'latehelp' => optional_param('help', 0, PARAM_BOOL) ? '' : (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'sanctions']
    ))->out(false),
    'latehelplabel' => $str('helpnav'),
    'latefacts' => array_map(fn($fact) => ['fact' => $fact], $latefacts),
    'latereport' => $late['mode'] !== sanction_rules::NONE || $latestate['pending']
        ? (new moodle_url('/local/oksigeniaayvurenda/sanctions.php', ['library' => $library->slug]))->out(false) : '',
    'lateleft' => $late['mode'] === sanction_rules::NONE && $latestate['pending'] ? $str('sanctionsopenleft') : '',
    'modes' => array_map(
        fn($mode) => ['value' => $mode, 'label' => $str('sanctionsmode' . $mode),
        'hint' => $str('sanctionsmodehint' . $mode), 'checked' => $late['mode'] === $mode],
        [sanction_rules::NONE, sanction_rules::DAYS, sanction_rules::FINE]
    ),
    'grace' => $late['grace'],
    'perday' => $late['perday'],
    'maxdays' => $late['maxdays'],
    'amount' => $plain($late['cents']),
    'maxamount' => $plain($late['maxcents']),
    'block' => $plain($late['block']),
    'currencies' => array_map(
        fn($code, $name) => ['code' => $code, 'name' => $name, 'chosen' => $code === $late['currency']],
        array_keys($currencies),
        array_values($currencies)
    ),
    'hasstages' => (bool) $stages,
    'allstages' => !$late['stages'],
    'stages' => array_map(fn($name) => ['name' => $name, 'chosen' => in_array($name, $late['stages'], true)], $stages),
    'adults' => $late['adults'],
    'noticeafter' => $late['noticeafter'],
    'renewsentence' => $str('renewfromsentence' . $renewfrom),
    'noticesentence' => $noticesentence,
    'noticewho' => $noticewho,
    'latelist' => $url('late.php'),
    'rhythms' => array_map(
        fn($rhythm) => ['value' => $rhythm, 'label' => $str('noticesrhythm' . $rhythm),
            'checked' => $late['noticeevery'] === $rhythm],
        notice_rules::rhythms()
    ),
    'datefields' => $datefields,
    'presets' => array_map(
        fn($preset) => ['value' => $preset, 'label' => $str('noticespreset' . $preset)],
        array_keys(notice_rules::PRESETS)
    ),
    'noticereaders' => $late['noticereaders'],
    'noticefamilies' => $late['noticefamilies'],
    'noticetutors' => $late['noticetutors'],
    'renewfroms' => array_map(
        fn($from) => ['value' => $from, 'label' => $str('renewfrom' . $from), 'hint' => $str('renewfrom' . $from . 'hint'),
        'checked' => $renewfrom === $from],
        [loan_rules::FROM_TODAY, loan_rules::FROM_DUE]
    ),
];
foreach (
    ['rulesreader', 'rulesitem', 'rulesdaysplain', 'rulesmaxplain', 'rulesrenewals', 'rulesrenewdays', 'rulesremoveone',
        'rulesnew', 'sourcessave', 'rulesroomonlyfield', 'rulesnolimit', 'ruleschange', 'rulesprecedence', 'offsiteclose',
        'sanctionsheading', 'menusanctions', 'sanctionsmodelegend', 'sanctionsfieldperday', 'sanctionsfieldmaxdays',
        'sanctionsdayshint', 'sanctionsfieldcents', 'sanctionsfieldmaxcents', 'sanctionsfieldblock', 'sanctionsfieldcurrency',
        'sanctionsfinehint', 'sanctionsfieldgrace', 'holdsstages', 'holdsallstages', 'sanctionsadults', 'sanctionscentre',
        'sanctionsfieldnotice', 'sanctionsnoticehint', 'renewfromheading', 'renewfromdesk', 'renewfromlegend',
        'renewfromcentre', 'noticesheading', 'noticespresets', 'noticespresetshint', 'noticesfieldafter', 'noticeseverylegend',
        'noticesdateslegend', 'noticesdateshint', 'noticeswholegend', 'noticestoreaders', 'noticestofamilies', 'noticestotutors',
        'menulate'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('rulestitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/rules', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => $url('readers.php'), 'label' => $str('readers')],
        ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'rules']))
            ->out(false), 'label' => $str('helpnav')],
    ],
]);
