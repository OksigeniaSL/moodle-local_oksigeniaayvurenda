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
 * How the library handles voices: what anyone can read before recording, with what the centre adds.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
require_capability('local/oksigeniaayvurenda:view', context_system::instance());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/voicesinfo.php', ['library' => $ref]));
$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$settings = json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]),
    true
) ?: [];
$data = [
    'heading' => $str('voiceinfo'),
    'points' => array_map(
        fn($k) => ['text' => $str($k, (int) ($settings['voicekeepdays'] ?? voices::KEEPDAYS))],
        ['voiceinfo1', 'voiceinfo2', 'voiceinfo3', 'voiceinfo4', 'voiceinfo5', 'voiceinfo6']
    ),
    'centre' => trim((string) ($settings['voicetext'] ?? '')),
    'centreheading' => $str('voiceinfocentre'),
];
shell::output(
    $str('voiceinfo'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/voicesinfo', $data),
    $library,
    '',
    ['search' => false]
);
