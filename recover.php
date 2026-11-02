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
 * A centre back from its daily copy, as a new centre.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\age;
use local_oksigeniaayvurenda\local\recover;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

require_login(null, false);
$context = context_system::instance();
$PAGE->set_context($context);
// Whoever runs the site: a new centre is not for someone who manages only one of them.
if (!has_capability('local/oksigeniaayvurenda:manage', $context)) {
    shell::refuse(get_string('staffonlyhint', 'local_oksigeniaayvurenda', get_string(
        'oksigeniaayvurenda:manage',
        'local_oksigeniaayvurenda'
    )));
}
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/recover.php'));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
core_php_time_limit::raise(1800);
raise_memory_limit(MEMORY_HUGE);

// The file waits between seeing what it holds and bringing it back; it is forgotten at the end either way.
$pending = $SESSION->ayvurendarecover ?? null;
if ($pending && !is_file($pending['path'])) {
    $pending = null;
}
$forget = function () use (&$pending) {
    if ($pending) {
        @unlink($pending['path']);
    }
    $pending = null;
};
$error = '';
$done = null;
$filesfor = optional_param('entity', 0, PARAM_INT);
$brought = '';
if (data_submitted() && confirm_sesskey()) {
    $step = optional_param('step', '', PARAM_ALPHA);
    if ($step === 'files' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $path = make_request_directory() . '/ficheros.zip';
        try {
            if (str_ends_with(strtolower(clean_filename((string) $_FILES['file']['name'])), '.age')) {
                age::decrypt($_FILES['file']['tmp_name'], $path, trim(optional_param('identity', '', PARAM_RAW_TRIMMED)));
            } else {
                move_uploaded_file($_FILES['file']['tmp_name'], $path);
            }
            $back = recover::bring_files($filesfor, $path, (int) $USER->id);
            if ($back === null) {
                $error = $str('recoverfilesnot');
            } else {
                $brought = $str('recoverfilesdone', (object) $back);
            }
        } catch (\Throwable $e) {
            $error = $str('recoverbadkey');
        }
    }
    if ($step === 'upload' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $forget();
        $dir = make_temp_directory('local_oksigeniaayvurenda');
        $path = $dir . '/' . random_string(20) . '.zip';
        $name = clean_filename((string) $_FILES['file']['name']);
        if (str_ends_with(strtolower($name), '.age')) {
            // Opened here, with the key that stays in this request only.
            try {
                age::decrypt($_FILES['file']['tmp_name'], $path, trim(optional_param('identity', '', PARAM_RAW_TRIMMED)));
            } catch (\Throwable $e) {
                @unlink($path);
                $error = $str('recoverbadkey');
            }
        } else {
            move_uploaded_file($_FILES['file']['tmp_name'], $path);
        }
        if ($error === '') {
            if (recover::look($path)) {
                $pending = ['path' => $path, 'name' => $name];
            } else {
                @unlink($path);
                $error = $str('recovernotcopy');
            }
        }
    } else if ($step === 'restore' && $pending) {
        $done = recover::restore($pending['path'], (int) $USER->id);
        $forget();
        $filesfor = (int) $done->entityid;
    } else if ($step === 'cancel') {
        $forget();
    }
    $SESSION->ayvurendarecover = $pending;
}
$firstslug = $filesfor ? (string) $DB->get_field_sql(
    'SELECT slug FROM {local_oksigeniaayvurenda_library} WHERE entityid = :entity ORDER BY id',
    ['entity' => $filesfor],
    IGNORE_MULTIPLE
) : '';
$figure = fn(int $count, string $key) => ['count' => screens::number($count), 'label' => $str($key)];
$look = $pending ? recover::look($pending['path']) : null;
$data = [
    'heading' => $str('recovertitle'),
    'intro' => $str('recoverintro'),
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'upload' => !$pending && !$filesfor,
    'check' => $look ? [
        'file' => $pending['name'],
        'title' => $str('recoverchecktitle', $look['name']),
        'figures' => [$figure($look['records'], 'importfigrecords'), $figure($look['items'], 'importfigitems'),
            $figure($look['readers'], 'readerslistfigreaders'), $figure($look['loans'], 'recoverfigloans')],
    ] : null,
    'files' => $filesfor && recover::recovered($filesfor) ? [
        'title' => $done ? $str('recoverdonetitle', format_string($done->name, true, ['escape' => false]))
            : $str('recoverfilestitle'),
        'brought' => $brought,
        'entity' => $filesfor,
        'limit' => $str('recoverfileslimit', display_size(get_max_upload_file_size())),
        'url' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $firstslug]))->out(false),
        'urllibraries' => (new moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false),
    ] : null,
];
foreach (
    ['recoverfilesintro', 'recoverfilesdrop', 'recoverdrop', 'recoverformats', 'recoverkey', 'recoverkeyhint', 'recovergo',
        'recoversafe', 'recovernot', 'recovergoin',
        'recovergolibraries', 'importcancel', 'importdropor', 'importreading', 'recoverbusy', 'importbusyhint'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/recover', $data), null, '', [
    'search' => false,
    'nav' => [['url' => (new moodle_url('/local/oksigeniaayvurenda/libraries.php'))->out(false), 'label' => $str('navlibraries')]],
]);
