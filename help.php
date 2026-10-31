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
 * The guided help: the real desk or adding screen, in a practice library, moved step by step.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\control_letter;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\practice;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
$piece = optional_param('piece', 'desk', PARAM_ALPHA);
$auto = optional_param('auto', 0, PARAM_BOOL);
require_login(null, false);
$context = context_system::instance();
$pieces = ['desk' => ['capability' => 'desk', 'page' => 'desk.php', 'steps' => 10],
    'add' => ['capability' => 'catalogue', 'page' => 'add.php', 'steps' => 6],
    'card' => ['capability' => 'desk', 'page' => 'card.php', 'steps' => 7],
    'labels' => ['capability' => 'catalogue', 'page' => 'labels.php', 'steps' => 6],
    'reports' => ['capability' => 'desk', 'page' => 'reports.php', 'steps' => 5],
    'reviews' => ['capability' => 'desk', 'page' => 'reviews.php', 'steps' => 6],
    'wishes' => ['capability' => 'desk', 'page' => 'wishes.php', 'steps' => 4],
    'myclass' => ['capability' => 'view', 'page' => 'class.php', 'steps' => 7],
    'stacks' => ['capability' => 'view', 'page' => 'stacks.php', 'steps' => 7],
    'free' => ['capability' => 'catalogue', 'page' => 'free.php', 'steps' => 5],
    'keepers' => ['capability' => 'manage', 'page' => 'keepers.php', 'steps' => 4]];
if (!isset($pieces[$piece])) {
    throw new moodle_exception('invalidparameter', 'debug');
}
// The help plays on the practice library; the library given, whose header it wears, must be one of theirs.
$theirs = $ref !== '' ? catalogue::library($ref) : null;
shell::require('local/oksigeniaayvurenda:' . $pieces[$piece]['capability'], $theirs);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $ref, 'piece' => $piece]));

// The practice library, as new: the help always finds the same readers and books.
$practice = practice::reset();
$library = $theirs ?? $practice;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$steps = [];
for ($i = 1; $i <= $pieces[$piece]['steps']; $i++) {
    $steps[] = $str(sprintf('help%s%02d', $piece, $i));
}
$code = fn(int $number) => control_letter::append((string) $number);
// The voice of each step, from the art package (voz/<piece>/<language>/<nn>.mp3); a step without it is only read.
$art = core_component::get_component_directory('local_oksigeniaayvurendaarte');
$voices = [];
for ($i = 1; $i <= $pieces[$piece]['steps']; $i++) {
    $file = sprintf('/voz/%s/%s/%02d.mp3', $piece, substr(current_language(), 0, 2), $i);
    $voices[] = $art && is_readable($art . $file) ? (new moodle_url('/local/oksigeniaayvurendaarte' . $file))->out(false) : null;
}
$back = new moodle_url('/local/oksigeniaayvurenda/' . $pieces[$piece]['page'], ['library' => $library->slug]);
$main = '<section class="ayv-help" aria-labelledby="ayv-help-title">
<h1 id="ayv-help-title" class="ayv-results-title">' . s($str('helptitle' . $piece)) . '</h1>
<div class="ayv-help-stage" id="ayv-help-stage">
    <iframe id="ayv-help-frame" title="' . s($str('helpframe')) . '" tabindex="-1"></iframe>
    <div class="ayv-help-pointer" id="ayv-help-pointer" aria-hidden="true"></div>
    <div class="ayv-help-ring" id="ayv-help-ring" aria-hidden="true" hidden></div>
    <div class="ayv-help-start" id="ayv-help-start">
        <p>' . s($str('helpintro' . $piece)) . '</p>
        <button type="button" class="ayv-button" id="ayv-help-go">' . s($str('helpstart')) . '</button>
    </div>
    <div class="ayv-help-done" id="ayv-help-done" hidden>
        <img src="' . (new moodle_url('/local/oksigeniaayvurenda/app/emblema.svg'))->out() . '" alt="" width="96" height="96">
        <p class="ayv-help-done-title">' . s($str('helpdone')) . '</p>
        <p>' . s($str('helpdone' . $piece)) . '</p>
        <p class="ayv-help-actions"><button type="button" class="ayv-button ayv-button-quiet" id="ayv-help-again">' .
            s($str('helpagain')) . '</button>
        <a class="ayv-button" href="' . $back->out() . '">' . s($str('helpback' . $piece)) . '</a></p>
    </div>
</div>
<p class="ayv-help-caption" id="ayv-help-caption" role="status" aria-live="polite"></p>
<p class="ayv-help-actions"><button type="button" class="ayv-toggle" id="ayv-help-skip">' . s($str('helpskip')) .
    '</button>' . (array_filter($voices) ? ' <button type="button" class="ayv-toggle" id="ayv-help-voice" aria-pressed="true">' .
    s($str('helpvoice')) . '</button>' : '') . '</p>
</section>';
shell::output($str('helptitle' . $piece), $main, $library, '', [
    'search' => false,
    'scripts' => ['help-pieces', 'help'],
    'nav' => [['url' => $back->out(false), 'label' => $str('helpback' . $piece)]],
    'config' => [
        'piece' => $piece,
        'auto' => $auto,
        'frame' => (new moodle_url(
            '/local/oksigeniaayvurenda/' . $pieces[$piece]['page'],
            ['library' => practice::SLUG, 'help' => 1] + ($piece === 'card' ? ['reader' => $DB->get_field(
                'local_oksigeniaayvurenda_reader',
                'id',
                ['entityid' => $practice->entityid, 'cardcode' => $code(practice::READERS[0][0])]
            )] : []) + ($piece === 'stacks' ? ['all' => 1] : [])
        ))->out(false),
        'steps' => $steps,
        'voices' => $voices,
        'now' => $str('helpnow'),
        'codes' => [
            'ana' => $code(practice::READERS[0][0]),
            'noa' => $code(practice::READERS[2][0]),
            'faro' => $code(practice::BOOKS[0][0]),
            'ladrona' => $code(practice::BOOKS[3][0]),
            'isbn1' => practice::ISBNS[0][0],
            'isbn2' => practice::ISBNS[1][0],
            'wish' => $str('practicewishmine'),
        ],
    ],
]);
