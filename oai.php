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
 * The OAI-PMH 2.0 repository of a library's open public window: oai.php/<library>?verb=…
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\oai;
use local_oksigeniaayvurenda\local\window;

define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- an open public window is for everyone, harvesters included.
require(__DIR__ . '/../../config.php');

$slug = trim((string) get_file_argument(), '/');
$library = $slug !== '' && preg_match('/^[a-z0-9_-]+$/i', $slug) ? window::open($slug) : null;
if (!$library) {
    http_response_code(404);
    die;
}
// The arguments as they come, by GET or by POST; repeated or unknown ones are the protocol's errors, not ours.
$args = [];
foreach (array_merge($_GET, $_POST) as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $args[clean_param($name, PARAM_ALPHA)] = clean_param($value, PARAM_RAW_TRIMMED);
    }
}
header('Content-Type: text/xml; charset=utf-8');
echo oai::answer($library, $args);
