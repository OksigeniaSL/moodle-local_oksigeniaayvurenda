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
 * The collecting and the handing out of a textbook bank.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\bank;
use local_oksigeniaayvurenda\local\bankround;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
// Whoever keeps a bank (a teacher who coordinates it) runs it all: its desk, its classes, its round and its sets.
if (!($library && bank::is($library) && access::keeps($library))) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/bankround.php', ['library' => $ref]));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library || !bank::is($library)) {
    shell::missing($str('banknotbank'), $library);
}
$now = time();
$mode = optional_param('mode', '', PARAM_ALPHA);
$mode = in_array($mode, ['collect', 'hand'], true) ? $mode : bankround::moment($library, $now);
$page = fn(string $file, array $params = []) => new moodle_url(
    '/local/oksigeniaayvurenda/' . $file,
    ['library' => $library->slug] + $params
);
$here = fn(array $params = []) => $page('bankround.php', $params);
$roll = fn(array $params) => $page('roll.php', $params)->out(false);

$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'deadline') {
        $date = optional_param('deadline', '', PARAM_TEXT);
        $when = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? (new DateTimeImmutable($date . ' 23:59:59', zones::of($library)))->getTimestamp() : 0;
        bankround::save_state($library, ['deadline' => $when]);
        $notice = $str($when ? 'bankrounddeadlinesaved' : 'bankrounddeadlinegone');
    } else if ($do === 'remind') {
        $who = optional_param('who', '', PARAM_ALPHA);
        $notice = $str(bankround::queue_reminder($library, $who, $now) ? 'bankroundremindqueued' : 'bankroundremindtoday');
    } else if ($do === 'found') {
        bankround::found($library, required_param('loan', PARAM_INT), $now);
        $notice = $str('bankroundfoundnow');
    }
}

$number = fn(int $n) => screens::number($n);
$short = get_string('strftimedateshort', 'langconfig');
$state = bankround::state($library);
// The last reminder: when, and how many went; while the task is still sending it, that it is on its way.
$last = function (string $who) use ($state, $str, $number, $short, $library, $now): string {
    if (!$state[$who]) {
        return '';
    }
    $when = zones::date((int) $state[$who], $short, $library);
    $going = !(int) $state[$who . 'sent'] && (int) $state[$who] > $now - HOURSECS && \core\task\manager::get_adhoc_tasks(
        '\\local_oksigeniaayvurenda\\task\\bank_reminder'
    );
    return $going ? $str('bankroundremindgoing', $when)
        : $str('bankroundremindlast', (object) ['when' => $when, 'sent' => $number((int) $state[$who . 'sent'])]);
};
$data = [
    'heading' => $str('bankroundheading', format_string($library->name, true, ['escape' => false])),
    'notice' => $notice,
    'action' => $here(['mode' => $mode])->out(false),
    'sesskey' => sesskey(),
    'tabs' => [
        ['label' => $str('bankroundcollect'), 'url' => $here(['mode' => 'collect'])->out(false), 'current' => $mode === 'collect'],
        ['label' => $str('bankroundhand'), 'url' => $here(['mode' => 'hand'])->out(false), 'current' => $mode === 'hand'],
    ],
    'iscollect' => $mode === 'collect',
];
if ($mode === 'collect') {
    $c = bankround::collection($library, $now);
    $losses = bankround::losses($library, $now);
    $data += [
        'outnumber' => $number($c['out']),
        'outlabel' => $str($c['out'] === 1 ? 'bankroundoutone' : 'bankroundout'),
        'progress' => $c['total']
            ? $str('bankroundback', (object) ['back' => $number($c['back']), 'total' => $number($c['total'])]) : '',
        'percent' => $c['percent'],
        'hasprogress' => $c['total'] > 0,
        'allback' => $c['total'] > 0 && !$c['out'],
        'allbacktext' => $str('bankroundallback'),
        'notyet' => !$c['total'],
        'notyettext' => $str('bankroundnotyet'),
        'old' => $c['old'] ? $str('bankroundold', $number($c['old'])) : '',
        'deadlinelabel' => $str('bankrounddeadline'),
        'deadlinetext' => $state['deadline'] ? zones::date((int) $state['deadline'], $short, $library)
            : $str('bankrounddeadlinenone'),
        'deadlinevalue' => $state['deadline'] ? zones::date((int) $state['deadline'], '%Y-%m-%d', $library) : '',
        'deadlinechange' => $str($state['deadline'] ? 'bankrounddeadlinechange' : 'bankrounddeadlineset'),
        'deadlinehint' => $str('bankrounddeadlinehint'),
        'save' => $str('bankroundsave'),
        'close' => $str('bankroundclose'),
        'hasout' => $c['out'] > 0,
        'remindheading' => $str('bankroundremind'),
        'remindreaders' => $str('bankroundremindreaders'),
        'remindreadershint' => $str('bankroundremindreadershint', (object) ['with' => $number($c['holders'] - $c['noaccount']),
            'without' => $number($c['noaccount'])]),
        'remindreaderslast' => $last('readers'),
        'remindtutors' => $str('bankroundremindtutors'),
        'remindtutorshint' => $str('bankroundremindtutorshint'),
        'remindtutorslast' => $last('tutors'),
        'canreaders' => (int) $state['readers'] <= $now - DAYSECS,
        'cantutors' => (int) $state['tutors'] <= $now - DAYSECS,
        'classesheading' => $str('bankroundclasses'),
        'classes' => array_map(fn($k) => [
            'group' => $k['group'],
            'url' => $roll(['group' => $k['group'], 'show' => 'holding']),
            'line' => $str($k['out'] === 1 ? 'bankroundclassone' : 'bankroundclass', (object) ['out' => $number($k['out']),
                'holding' => $number($k['holding'])]),
        ], $c['classes']),
        'hasclasses' => (bool) $c['classes'],
        'lostheading' => $str('bankroundlost'),
        'losthint' => $str('bankroundlosthint'),
        'losses' => array_map(fn($l) => $l + ['date' => zones::date(
            $l['when'],
            $short,
            $library
        )], $losses),
        'haslosses' => (bool) $losses,
        'foundlabel' => $str('bankroundfound'),
    ];
} else {
    $h = bankround::handout($library, $now);
    $data += [
        'waitingnumber' => $number($h['waiting']),
        'waitinglabel' => $str($h['waiting'] === 1 ? 'bankroundwaitingone' : 'bankroundwaiting'),
        'shortnumber' => $number($h['short']),
        'shortlabel' => $str($h['short'] === 1 ? 'bankroundshortone' : 'bankroundshort'),
        'nosets' => implode(', ', $h['nosets']),
        'nosetslabel' => $str('bankroundnosetslabel', $number(count($h['nosets']))),
        'emptygroups' => $h['empty'] ? $str('bankroundempty', implode(', ', $h['empty'])) : '',
        'setsheading' => $str('bankroundsets'),
        'sets' => array_map(fn($s) => [
            'name' => $s['name'],
            'pupils' => $str('bankrollpupils', $number($s['pupils'])),
            'titles' => array_map(fn($t) => [
                'title' => $t['title'],
                'free' => $str('bankroundfree', $number($t['free'])),
                'short' => $t['short'] ? $str('bankroundshorttitle', $number($t['short'])) : '',
                'mend' => $t['broken'] + $t['lost'] ? $str('bankroundmend', $number($t['broken'] + $t['lost'])) : '',
            ], $s['titles']),
            'hastitles' => (bool) $s['titles'],
        ], $h['sets']),
        'hassets' => (bool) $h['sets'],
        'nosetstext' => $str('bankroundnosetsyet'),
        'setsurl' => (new moodle_url('/local/oksigeniaayvurenda/bank.php', ['library' => $library->slug]))->out(false),
        'setslink' => $str('banknav'),
        'handclassesheading' => $str('bankroundhandclasses'),
        'handclasses' => array_map(fn($k) => [
            'group' => $k['group'],
            'url' => $roll(['group' => $k['group'], 'show' => 'waiting']),
            'line' => $k['waiting'] ? $str('bankroundhandclass', (object) ['waiting' => $number($k['waiting']),
                'pupils' => $number($k['pupils'])]) : $str('bankroundhandclassdone'),
            'done' => !$k['waiting'],
            'contracts' => (new moodle_url('/local/oksigeniaayvurenda/contract.php', ['library' => $library->slug,
                'group' => $k['group']]))->out(false),
        ], $h['classes']),
        'hashandclasses' => (bool) $h['classes'],
        'contractslabel' => $str('bankroundcontracts'),
        'listlabel' => $str('bankroundlist'),
    ];
}
$nav = fn(string $file, string $label, array $params = []) => ['url' => $page($file, $params)->out(false), 'label' => $label];
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/bankround', $data),
    $library,
    '',
    ['search' => false, 'nav' => [
        $nav('desk.php', $str('desktitle')),
        $nav('roll.php', $str('bankrollnav')),
        $nav('bank.php', $str('banknav')),
        $nav('help.php', $str('helpnav'), ['piece' => 'bankround']),
    ]]
);
