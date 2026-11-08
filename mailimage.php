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
 * A picture for a notice sent by e-mail: a mail program has no session, so its address carries a seal instead. Only a book's
 * cover, a library's picture or a character's portrait, nothing about anyone.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- The seal stands for the session a mail program does not have.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\mail;

$area = required_param('a', PARAM_ALPHA);
$itemid = required_param('i', PARAM_INT);
$name = required_param('n', PARAM_ALPHANUMEXT);
$seal = required_param('s', PARAM_ALPHANUM);

$file = hash_equals(mail::seal($area, $itemid, $name), $seal) ? mail::file_of($area, $itemid, $name) : null;
if (!$file) {
    http_response_code(404);
    die;
}
@header('X-Content-Type-Options: nosniff');
send_stored_file($file, 30 * DAYSECS, 0, false, ['cacheability' => 'public']);
