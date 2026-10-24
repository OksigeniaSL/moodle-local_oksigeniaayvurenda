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
 * Ayvu rande, «the spring of words» in Guarani: the voices given to the library's books, that someone may hear.
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
$page = max(0, optional_param('page', 0, PARAM_INT));
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:view');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/ayvurande.php', ['library' => $ref]));
$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$url = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$found = voices::spring($library, digital::viewer((int) $library->entityid, (int) $USER->id), $page);
$records = [];
foreach ($found['records'] as $record) {
    $records[] = [
        'title' => $record['title'],
        'url' => $url('record.php', ['id' => $record['id']]),
        'cover' => $record['cover'],
        'hascover' => (bool) $record['cover'],
        'colour' => $record['id'] % 6,
        'voices' => array_map(fn($voice) => [
            'label' => trim(((string) $voice->title !== '' ? $voice->title : $str('ayvurandevoice')) .
                ((string) $voice->signature !== '' ? ' · ' . $voice->signature : '')),
            'audio' => digital::file_url($voice),
        ], $record['voices']),
    ];
}
$data = [
    'heading' => $str('ayvurande'),
    'motto' => $str('ayvurandemotto'),
    'intro' => $str('ayvurandeintro'),
    'records' => $records,
    'hasrecords' => (bool) $records,
    'empty' => $str('ayvurandeempty'),
    'nexturl' => $found['more'] ? $url('ayvurande.php', ['page' => $page + 1]) : '',
    'prevurl' => $page > 0 ? $url('ayvurande.php', ['page' => $page - 1]) : '',
    'windownext' => $str('windownext'),
    'windowprev' => $str('windowprev'),
    'infourl' => $url('voicesinfo.php'),
    'voiceinfo' => $str('voiceinfo'),
];
shell::output($str('ayvurande'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/ayvurande', $data), $library);
