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
 * The OPDS catalogue of a library's open public window, without an account.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\opds;
use local_oksigeniaayvurenda\local\window;

define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- an open public window is for everyone.
require(__DIR__ . '/../../config.php');

$library = window::open(required_param('library', PARAM_ALPHANUMEXT));
if (!$library) {
    http_response_code(404);
    die;
}
if (optional_param('osd', 0, PARAM_BOOL)) {
    header('Content-Type: application/opensearchdescription+xml; charset=utf-8');
    echo opds::search($library);
    die;
}
header('Content-Type: ' . opds::TYPE . '; charset=utf-8');
echo opds::feed($library, trim(optional_param('q', '', PARAM_TEXT)), max(0, optional_param('page', 0, PARAM_INT)));
