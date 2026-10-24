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
 * A family gives a book a voice with the one-time link a teacher sent them, without an account: they read how their
 * voice is handled, give their permission, choose who else may hear it, record and send, reading from the book's text
 * if it has one meant for families. With the same link they hear it and delete it whenever they want.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the link itself is the key, for families without an account.
require(__DIR__ . '/../../config.php');

$token = optional_param('t', '', PARAM_ALPHANUM);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/family.php', ['t' => $token]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$invite = voices::invitation($token);
if (!$invite) {
    shell::output($str('voicefamilygone'), '<h1 class="ayv-results-title">' . s($str('voicefamilygone')) . '</h1><p>' .
        s($str('voicefamilygonehint')) . '</p>', null, '', ['search' => false]);
    die;
}
$voice = $invite->digitalid ? $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $invite->digitalid]) : null;
$library = $DB->get_record('local_oksigeniaayvurenda_library', ['entityid' => $invite->entityid], '*', IGNORE_MULTIPLE);
// The book's text, if it has one meant for families or for everyone: served from here, for they have no account.
$families = ['who' => 'families', 'stage' => null, 'userid' => 0, 'readerid' => 0];
$text = $library && !$invite->timeused ? voices::text_of((int) $invite->recordid, $library, $families) : null;
$send = $text && optional_param('text', 0, PARAM_BOOL) ? $text : ($voice && optional_param('play', 0, PARAM_BOOL) ? $voice : null);
if ($send) {
    $file = digital::file($send);
    if (!$file) {
        send_file_not_found();
    }
    \core\session\manager::write_close();
    send_stored_file($file, 0, 0, false);
}
$notice = '';
if ($voice && data_submitted() && confirm_sesskey() && optional_param('do', '', PARAM_ALPHA) === 'delete') {
    voices::remove($voice);
    $voice = null;
    $notice = $str('voicedeleted');
}
$title = (string) $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $invite->recordid]);
$settings = json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $invite->entityid]),
    true
) ?: [];
$inviter = $invite->invitedby ? core_user::get_user($invite->invitedby) : null;
$days = voices::keepdays((int) $invite->entityid);
$class = voices::group_of((int) $invite->readerid, (int) $invite->entityid);
$data = [
    'heading' => $str('voicetitle', $title),
    'invitedby' => $inviter ? $str('voicefamilyinvited', fullname($inviter)) : '',
    'points' => array_map(fn($k) => ['text' => $str($k, $days)], ['voicefamily1', 'voicefamily2', 'voicefamily3',
        $days ? 'voicefamily4' : 'voicefamily4forever']),
    'centre' => trim((string) ($settings['voicetext'] ?? '')),
    'notice' => $notice,
    'used' => (bool) $invite->timeused,
    'hasvoice' => (bool) $voice,
    'playurl' => (new moodle_url('/local/oksigeniaayvurenda/family.php', ['t' => $token, 'play' => 1]))->out(false),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'audiences' => array_map(
        fn($a) => ['value' => $a, 'label' => $str('voicefamilyaudience' . $a, $class['group'] ?? '')],
        voices::family_options($invite)
    ),
    'consent' => true,
    'askemail' => true,
    'hastext' => (bool) $text,
    'epub' => $text && $text->format === 'epub',
    'pdf' => $text && $text->format === 'pdf',
    'fileurl' => $text ? (new moodle_url('/local/oksigeniaayvurenda/family.php', ['t' => $token, 'text' => 1]))->out(false) : '',
    'marks' => false,
    'spread' => false,
];
foreach (
    ['voiceinfocentre', 'voicefamilyconsent', 'voicefamilywho', 'voicefamilywhohint', 'voicefamilydone', 'voicedelete',
        'voicerecord', 'voicestop', 'voiceagain', 'voicewhat', 'voicewhathint', 'voicesend', 'voicefamilydeletedall',
        'readprev', 'readnext', 'readsmaller', 'readbigger', 'readmark', 'readprogress', 'readloading', 'voicesignature',
        'voicesignaturehint', 'voiceemail', 'voiceemailhint', 'voicetextheading', 'voicepaper'] as $key
) {
    $data[$key] = $str($key);
}
$scripts = $data['epub'] ? ['epubjs/jszip.min', 'epubjs/epub.min', 'reader', 'voice'] : ['voice'];
shell::output(
    $str('voicetitle', $title),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/family', $data),
    $library ?: null,
    '',
    [
        'search' => false,
        'scripts' => $invite->timeused ? [] : $scripts,
        'config' => [
            'sendurl' => (new moodle_url('/local/oksigeniaayvurenda/family_ajax.php'))->out(false),
            'sesskey' => sesskey(),
            'token' => $token,
            'local' => true,
            'item' => $text ? (int) $text->id : 0,
            'pagecss' => (new moodle_url('/local/oksigeniaayvurenda/app/reader-page.css'))->out(false),
            'strings' => ['voicesending' => $str('voicesending'), 'voicesent' => $str('voicefamilysent'),
                'voicefailed' => $str('voicefailed'), 'voicenomic' => $str('voicenomic'), 'voicetime' => $str('voicetime'),
                'voicelength' => $str('voicelength'), 'readpage' => $str('readpage')],
        ],
    ]
);
