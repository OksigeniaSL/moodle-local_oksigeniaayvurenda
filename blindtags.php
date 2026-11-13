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
 * The labels of the blind dates, to stick on their paper: four on an A4, with their cutting lines (P7).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\barcode\code39;
use local_oksigeniaayvurenda\local\blind;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:desk', $library);
$id = optional_param('id', 0, PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/blindtags.php', ['library' => $library->slug, 'id' => $id]));

// The dates waiting on the shelf: the one asked for, or all of them.
$dates = array_values(array_filter(blind::open($library), fn($date) => !$id || $date['id'] === $id));
$tags = [];
foreach ($dates as $date) {
    $barcode = (string) $DB->get_field_sql(
        'SELECT i.barcode FROM {local_oksigeniaayvurenda_item} i JOIN {local_oksigeniaayvurenda_blind} b ON b.itemid = i.id
          WHERE b.id = ?',
        [$date['id']]
    );
    $tags[] = ['number' => $date['number'], 'clues' => $date['clues'], 'barcode' => $barcode,
        'svg' => $barcode !== '' && code39::can($barcode) ? code39::svg($barcode, 40, false) : ''];
}
// Back to the book of the one asked for; with all of them, to the home of the library.
$recordid = $id ? (int) $DB->get_field_sql(
    'SELECT i.recordid FROM {local_oksigeniaayvurenda_item} i JOIN {local_oksigeniaayvurenda_blind} b ON b.itemid = i.id
      WHERE b.id = ?',
    [$id]
) : 0;
$back = $recordid ? new moodle_url(
    '/local/oksigeniaayvurenda/record.php',
    ['library' => $library->slug, 'id' => $recordid],
    'ayv-cc-ficha'
) : new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug], 'ayv-citas');
$data = [
    'says' => count($tags) === 1 ? $str('blindtagsone') : $str('blindtagsmany', count($tags)),
    'hint' => $str('blindtagshint'),
    'printnow' => $str('printnow'),
    'back' => $back->out(false),
    'backlabel' => $str($recordid ? 'blindtagsbackrecord' : 'blindtagsbackhome'),
    'emblem' => (new moodle_url('/local/oksigeniaayvurenda/app/emblema-pequeno.svg'))->out(false),
    'tagtitle' => $str('blindcardheading'),
    'blindnumberabbr' => $str('blindnumberabbr'),
    'library' => format_string($library->name, true, ['escape' => false]),
    'tagfoot' => $str('blindtagfoot'),
    'sheets' => array_map(fn($four) => ['tags' => $four], array_chunk($tags, 4)),
];
shell::output(
    $str('blindtagsheading'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/blind_tags', $data),
    $library,
    '',
    ['search' => false, 'scripts' => ['labels']]
);
