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
 * The phone as a reader: what its camera reads goes to the desk or the adding screen open on a computer.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
if (!access::can('local/oksigeniaayvurenda:desk', $library)) {
    shell::require('local/oksigeniaayvurenda:catalogue', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/phone.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$main = '<section class="ayv-phone">
<h1 class="ayv-results-title">' . s($str('phonetitle')) . '</h1>
<p>' . s($str('phoneintro')) . '</p>
<p><button type="button" id="ayv-phone-open" class="ayv-button ayv-phone-open">' . s($str('camerabutton')) . '</button></p>
<ol id="ayv-phone-log" class="ayv-log" aria-live="polite"></ol>
</section>';
$strings = [];
foreach (
    ['cameratitle', 'camerahint', 'cameraclose', 'cameranone', 'camerastarting', 'cameraread', 'phonesent',
        'phonefailed', 'cameranotsecure'] as $key
) {
    $strings[$key] = $str($key);
}
shell::output($str('phonetitle'), $main, $library, '', [
    'search' => false,
    'scripts' => ['camera', 'phone'],
    'config' => [
        'relayurl' => (new moodle_url('/local/oksigeniaayvurenda/relay_ajax.php'))->out(false),
        'zxing' => (new moodle_url('/local/oksigeniaayvurenda/app/zxing/zxing.min.js'))->out(false),
        'sesskey' => sesskey(),
        'strings' => $strings,
    ],
]);
