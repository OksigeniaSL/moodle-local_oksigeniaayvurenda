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
 * Deletes a voice from the link sent to whoever recorded it, without logging in or asking anyone. Opening the link
 * shows one button; it is not deleted on opening, because some mail programs open links by themselves to check them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the link itself is the key: whoever recorded deletes without logging in.
require(__DIR__ . '/../../config.php');

$token = optional_param('t', '', PARAM_ALPHANUM);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/forget.php', ['t' => $token]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$voice = voices::by_token($token);
$data = ['action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'deleted' => false, 'gone' => !$voice];
if ($voice && data_submitted() && confirm_sesskey()) {
    voices::remove($voice);
    $data['deleted'] = true;
    $voice = null;
}
$title = $voice ? (string) $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $voice->recordid]) : '';
$data += [
    'heading' => $str($voice ? 'voiceforgettitle' : ($data['deleted'] ? 'voiceforgotten' : 'voiceforgetgone'), $title),
    'what' => $voice && $voice->title !== '' && $voice->title !== null ? $voice->title : '',
    'hint' => $str('voiceforgethint'),
    'button' => $str('voiceforgetbutton'),
    'ask' => (bool) $voice,
];
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/forget', $data),
    null,
    '',
    ['search' => false]
);
