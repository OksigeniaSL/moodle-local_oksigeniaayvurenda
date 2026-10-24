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
 * Receives a recording made in the browser, which waits for approval.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\voices;

require_login(null, false);
require_sesskey();
require_capability('local/oksigeniaayvurenda:view', context_system::instance());
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$viewer = digital::viewer((int) $library->entityid, (int) $USER->id);
if ($viewer['who'] === 'nobody' || empty($_FILES['voice']['tmp_name']) || !is_uploaded_file($_FILES['voice']['tmp_name'])) {
    throw new moodle_exception('voicefailed', 'local_oksigeniaayvurenda');
}
// Twenty minutes of speech is well under this; more is not a reading.
if (filesize($_FILES['voice']['tmp_name']) > 60 * 1024 * 1024) {
    throw new moodle_exception('voicefailed', 'local_oksigeniaayvurenda');
}
$id = voices::record(
    $library,
    required_param('record', PARAM_INT),
    (int) $viewer['readerid'],
    (int) $USER->id,
    $_FILES['voice']['tmp_name'],
    optional_param('title', '', PARAM_TEXT),
    optional_param('ext', 'webm', PARAM_ALPHANUM),
    null,
    optional_param('signature', '', PARAM_TEXT)
);
echo json_encode(['id' => $id]);
