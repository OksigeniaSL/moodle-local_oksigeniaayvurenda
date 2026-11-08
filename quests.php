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
 * The library's challenges with their own dates: each in a card with how it goes, made and changed in its window.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\quests;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/quests.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$now = time();
$notice = '';
$problem = '';
if (data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'save') {
        $saved = quests::save($library, [
            'name' => optional_param('name', '', PARAM_TEXT),
            'text' => optional_param('text', '', PARAM_TEXT),
            'start' => optional_param('start', '', PARAM_TEXT),
            'end' => optional_param('end', '', PARAM_TEXT),
            'goal' => optional_param('goal', 0, PARAM_INT),
            'stages' => optional_param_array('stages', [], PARAM_TEXT),
        ], $now, optional_param('id', 0, PARAM_INT));
        if (is_int($saved)) {
            $notice = $str('questsaved');
        } else {
            $problem = $str($saved);
        }
    } else if ($do === 'remove') {
        quests::remove($library, required_param('id', PARAM_INT));
        $notice = $str('questremoved');
    }
}
$stages = quests::stages($library, $now);
$date = fn(int $time, string $format) => zones::date($time, $format, $library);
$long = 'strftimedate';
$cards = [];
foreach (quests::of($library) as $quest) {
    $totals = quests::totals($quest, $now);
    $for = json_decode((string) $quest->stages, true) ?: [];
    $state = $quest->timestart > $now ? 'soon' : ($quest->timeend < $now ? 'over' : 'on');
    $cards[] = [
        'id' => (int) $quest->id,
        'name' => format_string($quest->name, true, ['escape' => false]),
        'text' => (string) $quest->text,
        'when' => $str('questwhen', (object) ['from' => $date((int) $quest->timestart, $long),
            'to' => $date((int) $quest->timeend, $long)]),
        'for' => $for ? implode(', ', $for) : $str('questforall'),
        'goal' => (int) $quest->goal,
        'goalline' => (int) $quest->goal === 1 ? $str('questgoallineone') : $str('questgoalline', (int) $quest->goal),
        'state' => $str('queststate' . $state), 'stateclass' => $state,
        'readers' => screens::number($totals['readers']),
        'done' => screens::number($totals['done']),
        'books' => screens::number($totals['books']),
        'startvalue' => $date((int) $quest->timestart, '%Y-%m-%d'),
        'endvalue' => $date((int) $quest->timeend, '%Y-%m-%d'),
        'stages' => array_map(fn($s) => ['name' => $s, 'on' => in_array($s, $for, true)], $stages),
        'removesure' => format_string($quest->name) . ': ' . $str('questremovesure'),
    ];
}
$data = [
    'heading' => $str('queststitle'),
    'intro' => $str('questsintro'),
    'notice' => $notice,
    'problem' => $problem,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'quests' => $cards,
    'hasquests' => (bool) $cards,
    'stages' => array_map(fn($s) => ['name' => $s, 'on' => false], $stages),
    'hasstages' => (bool) $stages,
    'maxgoal' => quests::MAXGOAL,
    'newstart' => $date($now, '%Y-%m-%d'),
    'newend' => $date($now + 30 * DAYSECS, '%Y-%m-%d'),
];
foreach (
    ['questsnone', 'questsnew', 'questname', 'questnamehint', 'questtext', 'questtexthint', 'queststart', 'questend',
        'questgoal', 'questfor', 'questforhint', 'questsave', 'questclose', 'questedit', 'questremove', 'questreaders',
        'questdone', 'questbooks'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('queststitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/quests', $data),
    $library,
    '',
    ['search' => false]
);
