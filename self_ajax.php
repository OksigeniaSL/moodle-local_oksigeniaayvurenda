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
 * What a self-loan post reads: a card or a book.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The post's own key stands for the login: no session stays open on it.
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\selfloan;

$PAGE->set_context(context_system::instance());
$found = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? selfloan::by_key(required_param('key', PARAM_ALPHANUM)) : null;
if (!$found) {
    http_response_code(404);
    echo json_encode(['gone' => true]);
    die();
}
if ((string) $found['post']->lang !== '') {
    force_current_language($found['post']->lang);
}
echo json_encode(selfloan::scan(
    $found['post'],
    $found['library'],
    required_param('code', PARAM_TEXT),
    optional_param('ticket', '', PARAM_NOTAGS)
), JSON_UNESCAPED_UNICODE);
