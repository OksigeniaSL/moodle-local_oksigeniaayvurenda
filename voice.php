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
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
// A long book is read in parts: its chapters as chips, the next one ready, and what is done and what waits.
$reading = voices::reading($recordid, $library, (int) $USER->id);
$next = voices::next_part($reading);
$state = [];
foreach ($reading as $part) {
    $state[(int) $part->part] = $part->status === 'published' || ($state[(int) $part->part] ?? '') === 'done' ? 'done' : 'waiting';
}
// The sound of each part: its own, if recorded; if not, the one of the nearest part before it.
$own = [];
foreach ($reading as $part) {
    $own[(int) $part->part] = (string) ($part->backdrop ?? '');
}
$soundof = function (int $n) use ($own): string {
    if (array_key_exists($n, $own)) {
        return $own[$n];
    }
    $before = array_filter(array_keys($own), fn($k) => $k < $n);
    return $before ? $own[max($before)] : '';
};
$chip = fn(int $n) => ['chipvalue' => $n, 'chiptitle' => voices::part_title($n), 'chipsound' => $soundof($n),
    'chiplabel' => $n ? (string) $n : voices::part_title(0),
    'chipdone' => ($state[$n] ?? '') === 'done', 'chipwaiting' => ($state[$n] ?? '') === 'waiting', 'chipchecked' => $n === $next,
    'chipstate' => isset($state[$n]) ? $str($state[$n] === 'done' ? 'voicepartdone' : 'voicepartwaiting') : ''];
$last = min(voices::LASTPART, max(5, $next + 2, $state ? max(array_keys($state)) + 3 : 0));
$parts = [
    'partswhich' => $str('voicepartswhich'),
    'partsprogress' => !$state ? $str('voicepartsfirst') : $str(
        count($state) === 1 ? 'voicepartsdoneone' : 'voicepartsdone',
        (object) ['count' => count($state), 'next' => \core_text::strtolower(voices::part_title($next))]
    ),
    'partschapter' => $str('voicepartschapter'),
    'partsmore' => $str('voicepartsmore'),
    'partsother' => $str('voicepartsother'),
    'partsname' => $str('voicepartname'),
    'partsnamehint' => $str('voicepartnamehint'),
    'barpart' => voices::part_title($next),
    'partsopening' => $chip(0),
    'chips' => array_map($chip, range(1, $last)),
];
// The soft sound under the whole reading, if the site has any: chosen with a touch, heard for a moment.
$sounds = voices::backdrops();
$current = $soundof($next);
$backdrops = $sounds ? [
    'backdropwhich' => $str('voicebackdropforpart', voices::part_title($next)),
    'options' => array_merge(
        [['bdkey' => '', 'bdogg' => '', 'bdm4a' => '', 'bdlabel' => $str('voicebackdropnone'), 'bdthe' => '',
            'bdchecked' => $current === '']],
        array_map(fn($key, $files) => ['bdkey' => $key, 'bdogg' => $files['ogg'], 'bdm4a' => $files['m4a'],
            'bdlabel' => $str('voicebackdrop' . $key), 'bdthe' => $str('voicebackdropthe' . $key),
            'bdchecked' => $current === $key], array_keys($sounds), $sounds)
    ),
] : null;
// What this person recorded of the book: the reading in the book's order, then the rest.
$recorded = array_merge($reading, array_values(array_filter(
    voices::mine($recordid, $library, (int) $USER->id),
    fn($v) => $v->part === null
)));
// Each part says its sound and changes it with a touch, without recording again.
$soundsays = fn(string $key) => $key !== '' && isset($sounds[$key])
    ? $str('voicemysound', \core_text::strtolower($str('voicebackdrop' . $key))) : $str('voicebackdropnone');
$mine = array_map(fn($v) => [
    'sound' => $v->part !== null && $sounds,
    'soundsays' => $soundsays((string) $v->backdrop),
    'soundoptions' => array_merge(
        [['sokey' => '', 'sosays' => $str('voicebackdropnone'), 'solabel' => $str('voicebackdropnone'),
            'sochecked' => (string) $v->backdrop === '']],
        array_map(fn($key) => ['sokey' => $key, 'sosays' => $soundsays($key), 'solabel' => $str('voicebackdrop' . $key),
            'sochecked' => (string) $v->backdrop === $key], array_keys($sounds))
    ),
    'id' => (int) $v->id,
    'title' => voices::part_label($v) . ((string) $v->signature !== '' ? ' · ' . $v->signature : ''),
    'url' => digital::file_url($v),
    'state' => $str($v->status === 'pending' ? 'voicepending' : 'voiceapproved'),
], $recorded);
$data = [
    'heading' => $str('voicetitle', $found['record']->title),
    'how' => $str(\local_oksigeniaayvurenda\local\skins::words((int) $library->entityid, 'voicehow')),
    // On a phone, two lines and the rest folded (the review of 21 Nov 2026, with the mock-up).
    'howshort' => $str('voicehowshort'),
    'backdroptitle' => $str('voicebackdroptitle'),
    'backdropchip' => $str('voicebackdropchip'),
    'backdropdone' => $str('voicebackdropdone'),
    'aftertitle' => $str('voiceaftertitle'),
    'calm' => voices::calm_strings(),
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
    'parts' => $parts,
    'backdrops' => $backdrops,
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
            'state' => $str($state, zones::date($invite->timeexpires, 'strftimedate', $library)),
            'link' => $open ? (new moodle_url('/local/oksigeniaayvurenda/family.php', ['t' => $invite->token]))->out(false) : '',
            'linklabel' => $str('voiceinvitelinkfor', (string) $invite->name),
            'copylabel' => $str('voiceinvitecopy'),
        ];
    }
}
$data['hasinvites'] = (bool) $data['invites'];
// The box «¿Para quién es?»: a reader past the flower reads it for the little ones, and their family may hear it.
$me = !empty($viewer['readerid']) ? $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $viewer['readerid']]) : null;
// A teacher or the librarian reads for the little ones too.
$data['forwho'] = \local_oksigeniaayvurenda\local\forlittle::can_read($me ?: null, time())
    || in_array($viewer['who'], ['desk', 'staff'], true) ? [
    'bud' => \local_oksigeniaayvurenda\local\screens::art('carne/flor/capullo'),
    'family' => \local_oksigeniaayvurenda\local\forlittle::can_tell($me),
    'off' => $str('voiceforlittleoff'),
    'on' => $str('voiceforlittleon'),
    'familyon' => $str('voicefamilyon'),
] : null;
foreach (
    ['voiceforwho', 'voiceforlittle', 'voicefamily', 'voiceinfo', 'voicerecord', 'voicestop', 'voiceagain', 'voicewhat',
        'voicewhathint', 'voicesend', 'voicemine', 'voiceagainsure',
        'voicedelete', 'voicenomic', 'backtorecord', 'voiceinvitetitle', 'voiceinvitehint', 'voiceinvitename',
        'voiceinvitenamehint', 'voiceinvitemake', 'voicesignature', 'voicesignaturehint', 'voicetextheading', 'voicepaper',
        'voiceinvitechild', 'voiceinvitechildnone', 'voiceinvitechildhint', 'voicepause', 'voiceresume'] as $key
) {
    $data[$key] = $str($key);
}
$recordurl = (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
    ->out(false);
shell::output($found['record']->title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/voice', $data), $library, '', [
    'search' => false,
    'scripts' => $data['epub'] ? ['epubjs/jszip.min', 'epubjs/epub.min', 'reader', 'voiceedit', 'voiceview', 'voice']
        : ['voiceedit', 'voiceview', 'voice'],
    'nav' => [['url' => $recordurl, 'label' => $str('backtorecord')]],
    'config' => [
        'sendurl' => (new moodle_url('/local/oksigeniaayvurenda/voice_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'record' => $recordid,
        'ajaxurl' => shell::service('digital_place'),
        'item' => $text ? (int) $text->id : 0,
        'position' => $place['position'],
        'bookmarks' => $place['bookmarks'],
        'pagecss' => (new moodle_url('/local/oksigeniaayvurenda/app/reader-page.css'))->out(false),
        'strings' => ['voicesending' => $str('voicesending'), 'voicesent' => $str('voicesent'),
            'voicepreparing' => $str('voicepreparing'),
            'voicefailed' => $str('voicefailed'), 'voicenomic' => $str('voicenomic'), 'voicetime' => $str('voicetime'),
            'voicecut' => $str('voicecut'), 'voicelimit' => $str('voicelimit'),
            'voicelength' => $str('voicelength'), 'copied' => $str('voiceinvitecopied'), 'readpage' => $str('readpage'),
            'voicepartchapter' => $str('voicepartchapter'), 'voicebackdropforpart' => $str('voicebackdropforpart')],
    ],
]);
