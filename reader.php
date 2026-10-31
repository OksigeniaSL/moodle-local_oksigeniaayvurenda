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
 * A reader's record, for the library staff: who they are, with their photo, what they have now, what they took home and
 * the staff's notes.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\profile;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
// Whoever keeps only this library sees the readers it serves, as at its desk.
$own = $library && access::keeps($library);
if (!$own) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/reader.php', ['library' => $ref, 'id' => $id]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library) {
    shell::missing($str('readernothere'));
}
$now = time();
if (!(new desk($library, (int) $USER->id, $now, $own))->allowed($id)) {
    shell::missing($str('readernothere'), $library);
}
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    profile::save_notes($library, $id, optional_param('notes', '', PARAM_TEXT));
    $notice = $str('profilenotessaved');
}
$record = profile::of($library, $id, $now);
if (!$record) {
    shell::missing($str('readernothere'), $library);
}
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$day = fn(int $time) => userdate($time, get_string('strftimedate', 'langconfig'));
$covers = covers::urls(array_merge(array_column($record['loans'], 'recordid'), array_column(
    $record['read']['books'],
    'recordid'
)));
$book = fn(array $b) => ['title' => $b['title'], 'url' => $link('record.php', ['id' => $b['recordid']]),
    'hascover' => isset($covers[$b['recordid']]), 'coverurl' => $covers[$b['recordid']] ?? '',
    'colour' => $b['recordid'] % 6];
$facts = array_values(array_filter([
    $record['group'] !== '' ? $record['group'] . ($record['stage'] !== '' ? ' · ' . $record['stage'] : '') : '',
    $record['tutor'] !== '' ? $str('profiletutor', $record['tutor']) : '',
    $record['email'],
]));
$looks = ['flower' => 'flower', 'sky' => 'sky', 'skysober' => 'sky'];
$drawing = '';
$stagename = '';
if (isset($looks[$record['look']])) {
    $stage = (int) $record['flower']['stage'];
    $drawing = $looks[$record['look']] === 'flower'
        ? \local_oksigeniaayvurenda\local\cards::flower_svg($stage, $record['flower']['levels'], $str('profileflower'), 120)
        : \local_oksigeniaayvurenda\local\cards::sky_svg(
            $stage,
            $record['flower']['levels'],
            $str('profilesky'),
            120,
            $record['look'] === 'skysober'
        );
    $stagename = $looks[$record['look']] === 'flower' ? $str('stage' . \local_oksigeniaayvurenda\core\flower::STAGES[$stage])
        : $str('stagesky' . $stage);
}
$notes = ['loved' => $str('noteloved'), 'unfinished' => $str('noteunfinished'), 'more' => $str('notemore')];
$data = [
    'name' => $record['name'],
    'photo' => $record['photo'],
    'hasphoto' => $record['photo'] !== '',
    'initial' => $record['initial'],
    'type' => get_string_manager()->string_exists('profiletype' . $record['type'], 'local_oksigeniaayvurenda')
        ? $str('profiletype' . $record['type']) : '',
    'facts' => array_map(fn($f) => ['fact' => $f], $facts),
    'cardcode' => $record['cardcode'],
    'barcode' => $record['barcode'],
    'since' => $str('profilesince', $day($record['since'])),
    'gone' => !$record['active'],
    'drawing' => $drawing,
    'stagename' => $stagename,
    'loans' => array_map(fn($l) => $book($l) + ['due' => $str('profiledue', $day($l['due'])), 'library' => $l['library'],
        'late' => $l['late'] ? $str($l['late'] === 1 ? 'profilelateone' : 'profilelate', $l['late']) : ''], $record['loans']),
    'hasloans' => (bool) $record['loans'],
    'holds' => array_map(fn($h) => ['title' => $h['title'], 'state' => $h['ready'] ? $str('profileholdready', $h['until'])
        : $str('profileholdwaiting')], $record['holds']),
    'hasholds' => (bool) $record['holds'],
    'readheading' => $str($record['read']['total'] === 1 ? 'profilereadone' : 'profileread', (object) ['year' => $record['year'],
        'count' => $record['read']['total']]),
    'read' => array_map(
        fn($b) => $book($b) + ['when' => $day($b['when']), 'note' => $notes[$b['note']] ?? ''],
        $record['read']['books']
    ),
    'hasread' => (bool) $record['read']['books'],
    'readmore' => $record['read']['total'] > count($record['read']['books'])
        ? $str('profilereadmore', $record['read']['total'] - count($record['read']['books'])) : '',
    'past' => array_map(fn($p) => ['year' => $p['year'], 'loans' => $p['loans'] === 1 ? $str('profilepastone')
        : $str('profilepast', $p['loans'])], $record['past']),
    'haspast' => (bool) $record['past'],
    'notes' => $record['notes'],
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'deskurl' => $link('desk.php', ['reader' => $id]),
    'cardurl' => $link('card.php', ['reader' => $id]),
];
foreach (
    ['profiledesk', 'profilecard', 'profilenow', 'profilenothing', 'profileholds', 'profilenoread', 'profilepastheading',
        'profilenotes', 'profilenoteshint', 'profilesave', 'profilegone', 'profilecode'] as $key
) {
    $data[$key] = $str($key);
}
$readers = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $record['name'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/reader', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $link('desk.php'), 'label' => $str('desktitle')],
        ['url' => $readers, 'label' => $str('readers')]]]
);
