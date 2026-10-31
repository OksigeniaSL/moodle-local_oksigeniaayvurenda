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
 * What the family signs for the textbooks of a bank lent to a pupil: one page to print.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\bank;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$readerid = required_param('reader', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
// Whoever works at the bank's desk: its staff, or whoever keeps it.
if (!access::keeps($library)) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/contract.php', ['library' => $ref, 'reader' => $readerid]));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library || !bank::is($library)) {
    shell::missing($str('banknotbank'), $library);
}
if (!$DB->record_exists('local_oksigeniaayvurenda_reader', ['id' => $readerid, 'entityid' => $library->entityid])) {
    shell::missing($str('readernotfound'), $library);
}
$contract = bank::contract($library, $readerid, time());
$data = [
    'centre' => format_string($DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $library->entityid])),
    'library' => format_string($library->name),
    'title' => $str('contracttitle'),
    'who' => $str('contractwho', (object) ['name' => $contract['name'], 'group' => $contract['group'],
        'year' => $contract['year']]),
    'copies' => array_map(fn($c) => ['title' => $c['title'], 'barcode' => $c['barcode'],
        'condition' => $str('condition' . $c['condition']), 'date' => userdate(
            $c['date'],
            get_string('strftimedateshort', 'langconfig')
        )], $contract['copies']),
    'hascopies' => (bool) $contract['copies'],
    'terms' => $str('contractterms', $contract['year']),
    'date' => userdate(time(), get_string('strftimedate', 'langconfig')),
];
foreach (
    ['contracttitlecol', 'contractcodecol', 'contractstatecol', 'contractdatecol', 'contractnone', 'contractfamily',
        'contractcentre', 'contractprint'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $data['title'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/contract', $data),
    $library,
    '',
    ['search' => false]
);
