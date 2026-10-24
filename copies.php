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
 * The daily copies of a library, to download.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\copies;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:desk');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
if (data_submitted() && confirm_sesskey() && has_capability('local/oksigeniaayvurenda:manage', $context)) {
    copies::make((int) $library->entityid);
    $notice = $str('copymade');
}
$files = [];
foreach (copies::list((int) $library->entityid) as $file) {
    $files[] = [
        'url' => moodle_url::make_pluginfile_url(
            $context->id,
            'local_oksigeniaayvurenda',
            'copies',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        )->out(false),
        'label' => userdate($file->get_timecreated(), get_string('strftimedatetime', 'langconfig')),
        'size' => display_size($file->get_filesize()),
    ];
}
$data = [
    'heading' => $str('copiesheading'),
    'intro' => $str('copiesintro', copies::KEEP),
    'notice' => $notice,
    'files' => $files,
    'hasfiles' => (bool) $files,
    'empty' => $str('copiesempty'),
    'download' => $str('copydownload'),
    'make' => has_capability('local/oksigeniaayvurenda:manage', $context) ? ['action' => $PAGE->url->out(false),
        'sesskey' => sesskey(), 'label' => $str('copymake')] : null,
];
$deskurl = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/copies', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $deskurl, 'label' => $str('desktitle')]]]
);
