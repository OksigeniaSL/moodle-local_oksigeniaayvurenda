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
 * Downloads a record of the catalogue in RIS, to cite it in a reference manager.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\export\ris;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/cite.php', ['library' => $ref, 'id' => $id]));

$found = $library ? catalogue::record($id, $library) : null;
if (!$found) {
    shell::missing(get_string('recordnotfound', 'local_oksigeniaayvurenda'), $library);
}
// The authorities as they are kept («Surname, Name»), which is how reference managers want the authors.
$by = [];
$rows = $DB->get_records_sql(
    'SELECT ra.id, a.kind, a.label
       FROM {local_oksigeniaayvurenda_recauth} ra
       JOIN {local_oksigeniaayvurenda_authority} a ON a.id = ra.authorityid
      WHERE ra.recordid = :recordid
   ORDER BY ra.sortorder, ra.id',
    ['recordid' => $id]
);
foreach ($rows as $row) {
    $by[$row->kind][] = $row->label;
}
$here = array_values(array_filter($found['copies'], fn($c) => $c['here']));
$entry = ris::entry((array) $found['record'], $by, $here[0]['callnumber'] ?? '');
if ($entry === '') {
    shell::missing(get_string('recordnotfound', 'local_oksigeniaayvurenda'), $library);
}
send_file(
    $entry,
    clean_filename(core_text::substr($found['record']->title, 0, 60)) . '.ris',
    0,
    0,
    true,
    true,
    'application/x-research-info-systems'
);
