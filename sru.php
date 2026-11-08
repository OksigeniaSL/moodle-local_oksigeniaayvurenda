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
 * The SRU 1.2 server of a library's catalogue, where its window shows it to anyone: sru.php/<library>?operation=…
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\sru;
use local_oksigeniaayvurenda\local\window;

define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- a catalogue the window shows to anyone, other catalogues included.
require(__DIR__ . '/../../config.php');

$slug = trim((string) get_file_argument(), '/');
$library = $slug !== '' && preg_match('/^[a-z0-9_-]+$/i', $slug) ? window::open($slug) : null;
if (!$library || !window::catalogue_open($library)) {
    http_response_code(404);
    die;
}
$args = [];
foreach ($_GET as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $args[clean_param($name, PARAM_ALPHA)] = clean_param($value, PARAM_RAW_TRIMMED);
    }
}
header('Content-Type: text/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo sru::answer($library, $args);
