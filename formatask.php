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
 * A reader asks for a book in the format they need.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\formats;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

require_login(null, false);
$library = catalogue::library(\local_oksigeniaayvurenda\local\shell::library_ref());
shell::require('local/oksigeniaayvurenda:view', $library);
if (!$library) {
    shell::missing(get_string('nolibrary', 'local_oksigeniaayvurenda'));
}
$recordid = required_param('record', PARAM_INT);
// Opened by its address, not from the book's page: back to the book, asking nothing.
if (!confirm_sesskey()) {
    redirect(new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]));
}
$reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
    'status' => 'active'], '*', IGNORE_MULTIPLE);
$asked = $reader ? formats::ask($library, $reader, $recordid, required_param('format', PARAM_ALPHA)) : 0;
redirect(new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid,
    'format' => $asked ? 'asked' : 'no'], 'ayv-format'));
