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
 * Results of a search, or the books of a corner.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = core_text::substr(trim(optional_param('q', '', PARAM_TEXT)), 0, 200);
$corner = optional_param('corner', '', PARAM_ALPHANUM);
$page = max(0, optional_param('page', 0, PARAM_INT));
$fragment = optional_param('fragment', false, PARAM_BOOL);
$onlyfree = optional_param('free', false, PARAM_BOOL);
$subject = optional_param('subject', 0, PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/search.php', ['library' => $ref, 'q' => $query,
    'corner' => $corner, 'subject' => $subject, 'page' => $page, 'free' => (int) $onlyfree]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
if ($query === '' && $corner === '') {
    redirect(new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]));
}
$data = screens::results($library, $query, $corner, $page, $onlyfree, $subject);
$html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/results', $data);
if ($fragment) {
    @header('Content-Type: text/html; charset=utf-8');
    echo $html;
    die();
}
shell::output($data['heading'], $html, $library, $query);
