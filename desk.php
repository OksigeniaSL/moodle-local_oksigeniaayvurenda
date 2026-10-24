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
 * The desk of a library: lend and take back with a scanner, the keyboard or a phone.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:desk', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$today = (new desk($library, (int) $USER->id))->today();
$settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]), true);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$strings = [];
foreach (
    ['deskundo', 'deskrenew', 'desklendanyway', 'deskdismiss', 'deskclose', 'noteloved', 'noteunfinished', 'notemore',
        'deskbadletter', 'desknoloans', 'deskloans', 'deskerror', 'desklate', 'deskreaderopen', 'deskchoosehint'] as $key
) {
    $strings[$key] = $str($key);
}
$cards = '';
foreach (['today' => 'todaytoday', 'late' => 'todaylate', 'out' => 'todayout'] as $key => $label) {
    if ($today[$key] > 0) {
        $cards .= '<li class="ayv-today-card ayv-today-' . $key . '"><span class="ayv-today-number">' . $today[$key] .
            '</span><span class="ayv-today-label">' . s($str($label)) . '</span></li>';
    }
}
$main = '<section class="ayv-desk" aria-labelledby="ayv-desk-title">
<h1 id="ayv-desk-title" class="ayv-sr">' . s($str('desktitle')) . '</h1>
<form id="ayv-scan" class="ayv-scan" autocomplete="off">
    <label for="ayv-code" class="ayv-scan-label">' . s($str('deskprompt')) . '</label>
    <input id="ayv-code" name="code" type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false"
        autofocus maxlength="200">
</form>
<div id="ayv-reader" class="ayv-reader" hidden></div>
<ul id="ayv-today" class="ayv-today">' . $cards . '</ul>
<ol id="ayv-log" class="ayv-log" aria-live="polite"></ol>
</section>';
shell::output($str('desktitle'), $main, $library, '', [
    'search' => false,
    'scripts' => ['desk'],
    'nav' => [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false),
            'label' => $str('readers')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
            'label' => $str('deskcatalogue')],
    ],
    'config' => [
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/desk_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'controlletter' => !empty($settings['controlletter']),
        'strings' => $strings,
    ],
]);
