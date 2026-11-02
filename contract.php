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
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$readerid = optional_param('reader', 0, PARAM_INT);
$group = trim(optional_param('group', '', PARAM_TEXT));
require_login(null, false);
$library = catalogue::library($ref);
// Whoever works at the bank's desk: its staff, or whoever keeps it.
if (!access::keeps($library)) {
    shell::require('local/oksigeniaayvurenda:desk', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/contract.php', ['library' => $ref] +
    ($group !== '' ? ['group' => $group] : ['reader' => $readerid])));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library || !bank::is($library)) {
    shell::missing($str('banknotbank'), $library);
}
$now = time();
// One pupil's, or those of a whole class that has books, each on its page.
if ($group !== '') {
    $readers = array_column(
        array_filter(\local_oksigeniaayvurenda\local\bank::roll($library, $group, $now), fn($p) => $p['held']),
        'readerid'
    );
} else {
    if (!$DB->record_exists('local_oksigeniaayvurenda_reader', ['id' => $readerid, 'entityid' => $library->entityid])) {
        shell::missing($str('readernotfound'), $library);
    }
    $readers = [$readerid];
}
$centre = format_string(
    $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $library->entityid]),
    true,
    ['escape' => false]
);
$html = '';
foreach ($readers as $index => $oneid) {
    $contract = bank::contract($library, (int) $oneid, $now);
    $data = [
        'centre' => $centre,
        'library' => format_string($library->name, true, ['escape' => false]),
        'title' => $str('contracttitle'),
        'who' => $str('contractwho', (object) ['name' => $contract['name'], 'group' => $contract['group'],
            'year' => $contract['year']]),
        'copies' => array_map(fn($c) => ['title' => $c['title'], 'barcode' => $c['barcode'],
            'condition' => $str('condition' . $c['condition']), 'date' => zones::date(
                $c['date'],
                'strftimedateshort',
                $library
            )], $contract['copies']),
        'hascopies' => (bool) $contract['copies'],
        'terms' => $str('contractterms', $contract['year']),
        'date' => zones::date($now, 'strftimedate', $library),
        'canprint' => $index === 0,
    ];
    foreach (
        ['contracttitlecol', 'contractcodecol', 'contractstatecol', 'contractdatecol', 'contractnone', 'contractfamily',
            'contractcentre', 'contractprint'] as $key
    ) {
        $data[$key] = $str($key);
    }
    $html .= $OUTPUT->render_from_template('local_oksigeniaayvurenda/contract', $data);
}
if (!$readers) {
    $html = html_writer::tag('p', s($str('contractnoneclass')), ['class' => 'ayv-empty']);
}
shell::output(
    $group !== '' ? $group : $str('contracttitle'),
    $html,
    $library,
    '',
    ['search' => false]
);
