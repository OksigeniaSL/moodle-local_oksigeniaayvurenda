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
 * The diploma of a full bingo card, as a picture: small on the card, whole to download or print. For the reader it is
 * theirs, and for the staff of their centre, who print it for a reader without an account.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\diploma;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$questid = required_param('quest', PARAM_INT);
$readerid = required_param('reader', PARAM_INT);
$small = optional_param('small', 0, PARAM_BOOL);
$download = optional_param('download', 0, PARAM_BOOL);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:view', $library);

$reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['id' => $readerid, 'status' => 'active']);
$theirs = $reader && $library && (int) $reader->entityid === (int) $library->entityid
    && ((int) $reader->userid === (int) $USER->id || access::can('local/oksigeniaayvurenda:desk', (int) $library->entityid));
$joinedid = $theirs ? diploma::joined($questid, $readerid) : 0;
$jpeg = $joinedid ? diploma::make($joinedid, $small ? diploma::SMALL : 0) : null;
if ($jpeg === null) {
    http_response_code(404);
    die;
}
@header('Content-Type: image/jpeg');
@header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="'
    . clean_filename(get_string('diplomafile', 'local_oksigeniaayvurenda')) . '.jpg"');
@header('Cache-Control: private, max-age=3600');
@header('X-Content-Type-Options: nosniff');
echo $jpeg;
