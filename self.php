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
 * A self-loan post: what stays on the screen of a tablet or computer in the library, with no account open on it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The post's own key stands for the login: no session stays open on it.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\selfloan;
use local_oksigeniaayvurenda\local\shell;

$key = optional_param('key', '', PARAM_ALPHANUM);
$found = selfloan::by_key($key);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/self.php'));
// The address is the key: it does not go to anyone in a Referer.
@header('Referrer-Policy: no-referrer');
if (!$found) {
    // The same page for a key that never was and for one taken away.
    http_response_code(404);
    shell::output(
        get_string('selftitle', 'local_oksigeniaayvurenda'),
        '<div class="ayv-self"><p class="ayv-self-title">' . s(get_string('selfgone', 'local_oksigeniaayvurenda')) . '</p></div>',
        null,
        '',
        ['bare' => true, 'scripts' => ['selfloan']]
    );
    die();
}
['post' => $post, 'library' => $library] = $found;
if ((string) $post->lang !== '') {
    force_current_language($post->lang);
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$returns = selfloan::settings($library)['returns'];
$said = characters::say((int) $library->entityid, 'memory', 'selfidle', [], 'saluda');
$data = [
    'library' => format_string($library->name, true, ['escape' => false]),
    'figure' => $said['figure'] ?? '',
    'idle' => $str('selfidle'),
    'idlehint' => $returns ? $str('selfidlereturn') : '',
    'codelabel' => $str('selfcodelabel'),
    'camerabutton' => $str('camerabutton'),
    'typebutton' => $str('selftype'),
    'done' => $str('selfdone'),
];
$strings = [];
foreach (
    ['selfidle', 'selfnext', 'selfdue', 'selfbye', 'selfbyenoname', 'selfoffline', 'selfgonenow', 'cameratitle', 'camerahint',
        'cameraclose', 'cameranone', 'camerastarting', 'cameraread', 'cameranotsecure'] as $word
) {
    $strings[$word] = $str($word);
}
$strings['selfidlereturn'] = $data['idlehint'];
shell::output($str('selftitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/self', $data), $library, '', [
    'bare' => true,
    'scripts' => ['camera', 'selfloan'],
    'config' => [
        'selfurl' => (new moodle_url('/local/oksigeniaayvurenda/self_ajax.php'))->out(false),
        'selfkey' => $key,
        'zxing' => (new moodle_url('/local/oksigeniaayvurenda/app/zxing/zxing.min.js'))->out(false),
        'strings' => $strings,
    ],
]);
