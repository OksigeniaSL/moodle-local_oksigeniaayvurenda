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

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$recordid = required_param('record', PARAM_INT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:view', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/voice.php', ['library' => $ref, 'record' => $recordid]));

$library = catalogue::library($ref);
$found = $library ? catalogue::record($recordid, $library) : null;
if (!$found) {
    throw new moodle_exception('recordnotfound', 'local_oksigeniaayvurenda');
}
$viewer = digital::viewer((int) $library->entityid, (int) $USER->id);
// Readers of the centre and the library staff record; anyone else is no one here.
if ($viewer['who'] === 'nobody') {
    throw new required_capability_exception($context, 'local/oksigeniaayvurenda:view', 'nopermissions', '');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'delete') {
    $item = digital::get(required_param('item', PARAM_INT), $library);
    if ($item->kind === 'voice' && (int) $item->createdby === (int) $USER->id) {
        voices::remove($item);
        $notice = $str('voicedeleted');
    }
}
$mine = array_map(fn($v) => [
    'id' => (int) $v->id,
    'title' => (string) $v->title,
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
];
foreach (
    ['voiceinfo', 'voicerecord', 'voicestop', 'voiceagain', 'voicewhat', 'voicewhathint', 'voicesend', 'voicemine',
        'voicedelete', 'voicenomic', 'backtorecord'] as $key
) {
    $data[$key] = $str($key);
}
$recordurl = (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
    ->out(false);
shell::output($found['record']->title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/voice', $data), $library, '', [
    'search' => false,
    'scripts' => ['voice'],
    'nav' => [['url' => $recordurl, 'label' => $str('backtorecord')]],
    'config' => [
        'sendurl' => (new moodle_url('/local/oksigeniaayvurenda/voice_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'record' => $recordid,
        'strings' => ['voicesending' => $str('voicesending'), 'voicesent' => $str('voicesent'),
            'voicefailed' => $str('voicefailed'), 'voicenomic' => $str('voicenomic'), 'voicetime' => $str('voicetime')],
    ],
]);
