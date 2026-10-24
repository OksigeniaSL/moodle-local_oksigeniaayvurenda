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
 * Receives what a family recorded with their one-time link, with their permission and whom they let hear it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the link itself is the key, for families without an account.
require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\voices;

require_sesskey();
$invite = voices::invitation(required_param('t', PARAM_ALPHANUM));
if (
    !$invite || $invite->timeused || !optional_param('consent', 0, PARAM_BOOL)
        || empty($_FILES['voice']['tmp_name']) || !is_uploaded_file($_FILES['voice']['tmp_name'])
        || filesize($_FILES['voice']['tmp_name']) > 60 * 1024 * 1024
) {
    throw new moodle_exception('voicefailed', 'local_oksigeniaayvurenda');
}
$id = voices::record_invited(
    $invite,
    $_FILES['voice']['tmp_name'],
    optional_param('title', '', PARAM_TEXT),
    optional_param('ext', 'webm', PARAM_ALPHANUM),
    optional_param_array('audience', [], PARAM_ALPHA),
    null,
    optional_param('signature', '', PARAM_TEXT),
    trim(optional_param('email', '', PARAM_EMAIL))
);
echo json_encode(['id' => $id]);
