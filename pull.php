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
 * The newest copy of a centre, encrypted with its key, for the library's own computer: no session, only the link's key.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The link's own key stands for the login; the copy is encrypted.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\copies;
use local_oksigeniaayvurenda\local\offsite;

$centre = required_param('centre', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUM);
$copy = offsite::pull_allowed($centre, $key) ? (copies::list($centre)[0] ?? null) : null;
if (!$copy) {
    // The same answer for a wrong key and for a centre that is not there.
    http_response_code(404);
    die();
}
$path = offsite::encrypted($centre, $copy);
\local_oksigeniaayvurenda\event\copy_downloaded::fire($centre, null, ['how' => 'pull']);
send_file($path, offsite::name($centre, (int) $copy->get_timecreated()), 0, 0, false, true, 'application/octet-stream');
