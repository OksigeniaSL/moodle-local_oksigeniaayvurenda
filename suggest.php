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
 * A reader asks the library for a book it does not have.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\purchases;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/suggest.php', ['library' => $ref]));
$str = skins::speaker($library);
$now = time();
$reader = $library ? ($DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
    'status' => 'active'], '*', IGNORE_MULTIPLE) ?: null) : null;
if (!$library || !purchases::may_ask($library, $reader, $now)) {
    shell::refuse($str('suggestoff'), $library, $str('suggesttitle'));
}
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $said = purchases::ask($library, $reader, [
        'title' => optional_param('title', '', PARAM_TEXT),
        'author' => optional_param('author', '', PARAM_TEXT),
        'isbn' => optional_param('isbn', '', PARAM_TEXT),
        'note' => optional_param('note', '', PARAM_TEXT),
    ], $now);
    if (in_array($said, ['asked', 'joined', 'known'], true)) {
        $notice = $str('suggest' . $said);
    } else {
        $error = $str('suggest' . $said, purchases::MAXOPEN);
    }
}
$mine = array_map(fn($p) => ['title' => $p['title'],
    'state' => $str('suggeststate' . $p['status'])], purchases::of_reader((int) $reader->id));
$data = ['heading' => $str('suggesttitle'), 'intro' => $str('suggestintro'), 'notice' => $notice, 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'title' => $notice ? '' : optional_param('q', '', PARAM_TEXT),
    'hasmine' => (bool) $mine, 'mine' => $mine];
foreach (
    ['purchasefieldtitle', 'purchasefieldauthor', 'purchasefieldisbn', 'suggestnotelabel', 'suggestsend', 'suggestmine'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/suggest', $data), $library);
