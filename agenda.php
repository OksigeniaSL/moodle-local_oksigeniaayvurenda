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
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/agenda.php', ['library' => $ref]));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$manage = access::can('local/oksigeniaayvurenda:manage', $library);
$now = time();
$notice = '';
$problem = '';
if ($manage && data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    if ($do === 'save') {
        $saved = agenda::save($library, [
            'title' => optional_param('title', '', PARAM_TEXT),
            'day' => optional_param('day', '', PARAM_TEXT),
            'time' => optional_param('time', '', PARAM_TEXT),
            'endday' => optional_param('endday', '', PARAM_TEXT),
            'endtime' => optional_param('endtime', '', PARAM_TEXT),
            'place' => optional_param('place', '', PARAM_TEXT),
            'audience' => optional_param('audience', '', PARAM_TEXT),
            'text' => optional_param('text', '', PARAM_TEXT),
            'seats' => optional_param('seats', 0, PARAM_INT),
        ], $now, optional_param('id', 0, PARAM_INT));
        if (is_int($saved)) {
            $notice = $str('agendasaved');
        } else {
            $problem = $str($saved);
        }
    } else if ($do === 'remove') {
        agenda::remove($library, required_param('id', PARAM_INT));
        $notice = $str('agendaremoved');
    }
}
$today = zones::today($library, $now);
$activities = agenda::between((int) $library->entityid, $today, $now + agenda::AHEAD * DAYSECS);
$several = count(array_unique(array_column($activities, 'libraryid'))) > 1;
$value = fn(?int $time, string $format) => $time ? zones::date($time, $format, $library) : '';
$cards = [];
foreach ($activities as $activity) {
    $mine = (int) $activity->libraryid === (int) $library->id;
    $timed = zones::date((int) $activity->timestart, '%H:%M', $library) !== '00:00';
    $cards[] = [
        'id' => (int) $activity->id,
        'title' => format_string($activity->title, true, ['escape' => false]),
        'when' => agenda::when($activity, $library),
        'day' => (int) zones::date((int) $activity->timestart, '%d', $library),
        'month' => core_text::strtoupper(zones::date((int) $activity->timestart, '%b', $library)),
        'place' => (string) $activity->place,
        'audience' => (string) $activity->audience,
        'text' => (string) $activity->text,
        'seats' => !$activity->seats ? '' : ((int) $activity->seats === 1 ? $str('agendaseatsone')
            : $str('agendaseats', (int) $activity->seats)),
        'library' => $several ? format_string($activity->libraryname) : '',
        'canedit' => $manage && $mine,
        'dayvalue' => $value((int) $activity->timestart, '%Y-%m-%d'),
        'timevalue' => $timed ? $value((int) $activity->timestart, '%H:%M') : '',
        'enddayvalue' => $activity->timeend ? $value((int) $activity->timeend, '%Y-%m-%d') : '',
        'endtimevalue' => $activity->timeend ? $value((int) $activity->timeend, '%H:%M') : '',
        'seatsvalue' => $activity->seats ? (int) $activity->seats : '',
        'removesure' => format_string($activity->title) . ': ' . $str('agendaremovesure'),
    ];
}
$data = [
    'heading' => $str('agendatitle'),
    'intro' => $manage ? $str('agendaintrostaff') : $str('agendaintro'),
    'notice' => $notice,
    'problem' => $problem,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'activities' => $cards,
    'hasactivities' => (bool) $cards,
    'manage' => $manage,
    'newday' => zones::date($now + DAYSECS, '%Y-%m-%d', $library),
];
foreach (
    ['agendanone', 'agendanew', 'agendaactivity', 'agendaday', 'agendatime', 'agendaendday', 'agendaendtime',
        'agendaendhint', 'agendaplace', 'agendaaudience', 'agendaaudiencehint', 'agendatext', 'agendaseatslabel',
        'agendaseatshint', 'agendasave', 'agendaclose', 'agendaedit', 'agendaremove'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('agendatitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/agenda', $data),
    $library,
    '',
    ['search' => false]
);
