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
 * «Do you want to give this book a voice?»: recording a reading of a record, and the recorder's own voices of it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$recordid = required_param('record', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/voice.php', ['library' => $ref, 'record' => $recordid]));

$found = $library ? catalogue::record($recordid, $library) : null;
if (!$found) {
    throw new moodle_exception('recordnotfound', 'local_oksigeniaayvurenda');
}
$viewer = digital::viewer((int) $library->entityid, (int) $USER->id);
// Readers of the centre and the library staff record; anyone else is no one here.
if ($viewer['who'] === 'nobody') {
    shell::login_first();
    shell::refuse(get_string('voicenotreader', 'local_oksigeniaayvurenda'), $library);
}
if (!plans::allows((int) $library->entityid, 'voices')) {
    shell::refuse(
        plans::without((int) $library->entityid, 'voices'),
        $library,
        get_string('planofftitle', 'local_oksigeniaayvurenda')
    );
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
// Teachers and the library invite families who have no account, with a one-time link.
$caninvite = in_array($viewer['who'], ['desk', 'staff'], true);
// A tutor says whose family it is, among the students of their groups: then the family can keep it to that class.
$children = [];
if ($caninvite) {
    $year = \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, time());
    $rows = $DB->get_records_sql(
        "SELECT DISTINCT r.id, m.groupname
           FROM {local_oksigeniaayvurenda_member} m
           JOIN {local_oksigeniaayvurenda_reader} r ON r.id = m.readerid
           JOIN {local_oksigeniaayvurenda_tutor} t ON t.yearid = m.yearid AND t.groupname = m.groupname
          WHERE t.userid = :userid AND m.yearid = :yearid AND r.readertype = 'student' AND r.status = 'active'",
        ['userid' => $USER->id, 'yearid' => $year->id]
    );
    $desk = new \local_oksigeniaayvurenda\local\desk($library, 0);
    foreach ($rows as $row) {
        $children[(int) $row->id] = $desk->reader((int) $row->id)['name'] . ' (' . $row->groupname . ')';
    }
    asort($children);
}
if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'invite' && $caninvite) {
    $child = optional_param('child', 0, PARAM_INT);
    $child = isset($children[$child]) ? $child : 0;
    $name = trim(optional_param('name', '', PARAM_TEXT));
    if ($name === '' && $child) {
        $name = $str('voiceinvitefamilyof', $desk->reader($child)['name']);
    }
    voices::invite($library, $recordid, (int) $USER->id, $name, null, $child);
    $notice = $str('voiceinvitemade');
}
if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'delete') {
    $item = digital::get(required_param('item', PARAM_INT), $library);
    if ($item->kind === 'voice' && (int) $item->createdby === (int) $USER->id) {
        voices::remove($item);
        $notice = $str('voicedeleted');
    }
}
$mine = array_map(fn($v) => [
    'id' => (int) $v->id,
    'title' => (string) $v->title . ((string) $v->signature !== '' ? ' · ' . $v->signature : ''),
    'url' => digital::file_url($v),
    'state' => $str($v->status === 'pending' ? 'voicepending' : 'voiceapproved'),
], voices::mine($recordid, $library, (int) $USER->id));
$data = [
    'heading' => $str('voicetitle', $found['record']->title),
    'how' => $str('voicehow'),
    'infourl' => (new moodle_url('/local/oksigeniaayvurenda/voicesinfo.php', ['library' => $library->slug]))->out(false),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'mine' => $mine,
    'hasmine' => (bool) $mine,
    'consent' => false,
    'askemail' => false,
    'caninvite' => $caninvite,
    'children' => array_map(fn($id, $label) => ['id' => $id, 'label' => $label], array_keys($children), $children),
    'haschildren' => (bool) $children,
    'invites' => [],
];
// The text to read from, if the book has one this person may open; if not, the paper book.
$text = voices::text_of($recordid, $library, $viewer);
$data += [
    'hastext' => (bool) $text,
    'epub' => $text && $text->format === 'epub',
    'pdf' => $text && $text->format === 'pdf',
    'fileurl' => $text ? digital::file_url($text) : '',
    'marks' => false,
    'spread' => false,
];
foreach (
    ['readprev', 'readnext', 'readsmaller', 'readbigger', 'readmark', 'readprogress', 'readloading', 'readfullscreen',
        'readfullscreenexit'] as $key
) {
    $data[$key] = $str($key);
}
$place = $text ? digital::place((int) $text->id, (int) $USER->id) : ['position' => null, 'bookmarks' => []];
if ($caninvite) {
    foreach (voices::invites($recordid, (int) $USER->id) as $invite) {
        $open = !$invite->timeused && $invite->timeexpires > time();
        $state = $invite->timeused ? ($invite->digitalid ? 'voiceinviterecorded' : 'voiceinvitedeleted')
            : ($open ? 'voiceinviteopen' : 'voiceinviteexpired');
        $data['invites'][] = [
            'name' => (string) $invite->name,
            'state' => $str($state, userdate($invite->timeexpires, get_string('strftimedate', 'langconfig'))),
            'link' => $open ? (new moodle_url('/local/oksigeniaayvurenda/family.php', ['t' => $invite->token]))->out(false) : '',
            'linklabel' => $str('voiceinvitelinkfor', (string) $invite->name),
            'copylabel' => $str('voiceinvitecopy'),
        ];
    }
}
$data['hasinvites'] = (bool) $data['invites'];
foreach (
    ['voiceinfo', 'voicerecord', 'voicestop', 'voiceagain', 'voicewhat', 'voicewhathint', 'voicesend', 'voicemine',
        'voicedelete', 'voicenomic', 'backtorecord', 'voiceinvitetitle', 'voiceinvitehint', 'voiceinvitename',
        'voiceinvitenamehint', 'voiceinvitemake', 'voicesignature', 'voicesignaturehint', 'voicetextheading', 'voicepaper',
        'voiceinvitechild', 'voiceinvitechildnone', 'voiceinvitechildhint'] as $key
) {
    $data[$key] = $str($key);
}
$recordurl = (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
    ->out(false);
shell::output($found['record']->title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/voice', $data), $library, '', [
    'search' => false,
    'scripts' => $data['epub'] ? ['epubjs/jszip.min', 'epubjs/epub.min', 'reader', 'voice'] : ['voice'],
    'nav' => [['url' => $recordurl, 'label' => $str('backtorecord')]],
    'config' => [
        'sendurl' => (new moodle_url('/local/oksigeniaayvurenda/voice_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'record' => $recordid,
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/digital_ajax.php'))->out(false),
        'item' => $text ? (int) $text->id : 0,
        'position' => $place['position'],
        'bookmarks' => $place['bookmarks'],
        'pagecss' => (new moodle_url('/local/oksigeniaayvurenda/app/reader-page.css'))->out(false),
        'strings' => ['voicesending' => $str('voicesending'), 'voicesent' => $str('voicesent'),
            'voicefailed' => $str('voicefailed'), 'voicenomic' => $str('voicenomic'), 'voicetime' => $str('voicetime'),
            'voicelength' => $str('voicelength'), 'copied' => $str('voiceinvitecopied'), 'readpage' => $str('readpage')],
    ],
]);
