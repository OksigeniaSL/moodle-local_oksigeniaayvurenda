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
 * «Leyó para los pequeños»: the page where a family hears what their reader read for the little ones, from the link they
 * were sent. Without an account and without Moodle around it: the cover, who read it, the player and the library. Its
 * address carries a seal instead of a session, and opens only that voice while it is published.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- The seal stands for the session a family does not have.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\forlittle;
use local_oksigeniaayvurenda\local\mail;

$id = required_param('i', PARAM_INT);
$seal = required_param('s', PARAM_ALPHANUM);
$play = optional_param('play', 0, PARAM_BOOL);

$heard = forlittle::heard($id, $seal);
if (!$heard) {
    http_response_code(404);
    die;
}
if ($play) {
    @header('X-Content-Type-Options: nosniff');
    send_stored_file(digital::file($heard['voice']), 0, 0, false, ['cacheability' => 'private']);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/hear.php', ['i' => $id, 's' => $seal]));
$reader = $heard['reader'];
$user = $reader->userid ? \core_user::get_user((int) $reader->userid) : null;
$name = (string) ($user->firstname ?? $reader->firstname);
$title = format_string($heard['record']->title, true, ['escape' => false]);
$library = format_string($heard['library']->name, true, ['escape' => false]);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$book = mail::book((int) $heard['record']->id, $title);
$app = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/app/' . $file))->out(false);
@header('Content-Type: text/html; charset=utf-8');
@header('Cache-Control: private, no-store');
@header('X-Content-Type-Options: nosniff');
@header('Referrer-Policy: no-referrer');
@header('X-Robots-Tag: noindex, nofollow');
echo $OUTPUT->render_from_template('local_oksigeniaayvurenda/hear', [
    'lang' => str_replace('_', '-', current_language()),
    'title' => $str('forlittlesubject', (object) ['child' => $name]),
    'ogdescription' => $str('familypageogdesc', $library),
    // The cover as the notices carry it, sealed: what a chat shows before the link is opened.
    'ogimage' => $book['cover'],
    'url' => $PAGE->url->out(false),
    'css' => $app('escuchar.css'),
    'js' => $app('escuchar.js'),
    'emblem' => $app('emblema.svg'),
    'library' => $library,
    'cover' => $book['cover'],
    'coveralt' => $str('familypagecover', $title),
    'coverbg' => $book['coverbg'],
    'coverink' => $book['coverink'],
    'booktitle' => $title,
    'kicker' => $str('familypagekicker'),
    'name' => $name,
    'sentence' => $str('familypagesentence', $title),
    'audio' => (new moodle_url('/local/oksigeniaayvurenda/hear.php', ['i' => $id, 's' => $seal, 'play' => 1]))->out(false),
    'play' => $str('familypageplay'),
    'pause' => $str('familypagepause'),
    'seek' => $str('familypageseek'),
    'share' => $str('familypagesend'),
    'sharetitle' => $str('familypagesharetitle', (object) ['name' => $name, 'title' => $title]),
    'sharetext' => $str('familypagesharetext', $name),
    'copied' => $str('familypagecopied'),
    'foot' => $str('familypagefoot'),
    'footlink' => $str('familypagefootlink'),
]);
