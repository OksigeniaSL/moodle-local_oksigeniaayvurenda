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
 * Printing reader cards: a group's, ten to an A4 sheet.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\cards;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\readers;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$group = optional_param('group', '', PARAM_TEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/print.php', ['library' => $ref, 'group' => $group]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entity = format_string(
    $DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $library->entityid]),
    true,
    ['escape' => false]
);
$skin = \local_oksigeniaayvurenda\local\skins::of((int) $library->entityid);
$printed = [];
$long = false;
foreach (readers::in_group($library, $group) as $reader) {
    $printed[] = cards::printed((object) ['id' => $reader['id'], 'entityid' => $library->entityid, 'userid' => $reader['userid'],
        'readertype' => $reader['type'], 'cardcode' => $reader['cardcode']], $reader['name'], $group, $entity, $skin);
    $long = $long || core_text::strlen((string) $reader['cardcode']) > 10;
}
$heading = $str('printcardsheading', $group !== '' ? $group : $str('readersnogroup'));
$guides = (string) file_get_contents(__DIR__ . '/app/printguides-a4.svg');
$chunks = array_chunk($printed, 10);
$sheets = [];
foreach ($chunks as $index => $chunk) {
    $sheets[] = ['paper' => 'a4', 'guides' => $guides, 'how' => $str('printcardshow'), 'ruler' => $str('printcardsruler'),
        'sample' => '',
        'title' => $str('printcardssheet', (object) ['heading' => $heading, 'n' => $index + 1, 'of' => count($chunks)]),
        'cards' => $chunk];
}
$data = [
    'heading' => $heading,
    'intro' => $str('printcardsintro', count($printed)) . ($long ? ' ' . $str('printcardslongcodes') : ''),
    'print' => $str('printnow'),
    'sheets' => $sheets,
    'hassheets' => (bool) $sheets,
    'empty' => $str('readersnone'),
];
$back = new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug, 'group' => $group]);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/printcards', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $back->out(false), 'label' => $str('readersback')]]]
);
