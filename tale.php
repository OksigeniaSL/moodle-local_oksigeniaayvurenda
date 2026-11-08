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
 * A story to read at home, as Gaudí drew its reader (8 November 2026): one page at a time, big letters to read aloud,
 * the two big buttons within the thumb's reach, night for the dark bedroom, the screen that does not go off, and the open
 * book when the phone lies down. For anyone, without an account: only what a library marked to read at home and shows at
 * its open door. An e-book; anything else opens in the library's reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\window;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- a story to read at home is for anyone, as the library's open door.
require(__DIR__ . '/../../config.php');

$ref = shell::library_ref();
$id = required_param('id', PARAM_INT);
$age = optional_param('age', '', PARAM_RAW_TRIMMED);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/tale.php', ['library' => $ref, 'id' => $id]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

$library = catalogue::library($ref);
if (!$library) {
    shell::missing($str('nolibrary'));
}
$item = $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $id, 'entityid' => $library->entityid]);
// Only a story the library marked to read at home and shows at its open door: the rest is not here, for anyone.
if (!$item || $item->homeages === null || $item->homeages === '' || !window::shows($item)) {
    shell::missing($str('recordnotfound'), $library);
}
$read = new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $library->slug, 'id' => $item->id]);
if ($item->format !== 'epub') {
    redirect($read);
}
$record = $DB->get_record('local_oksigeniaayvurenda_record', ['id' => $item->recordid], '*', MUST_EXIST);
$found = catalogue::record((int) $item->recordid, $library);
$back = new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug, 'tales' => 1]
    + (preg_match('/^\d{1,2}(-\d{1,2})?$/', $age) ? ['age' => $age] : []));
// A recording the work brings, offered quietly on the cover; never a made voice.
$audio = $DB->get_records_select(
    'local_oksigeniaayvurenda_digital',
    "recordid = ? AND format = 'audio' AND kind <> 'voice' AND status = 'published'",
    [$item->recordid],
    'id',
    '*',
    0,
    1
);
$audio = $audio ? reset($audio) : null;
$audiourl = $audio && window::shows($audio)
    ? (new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $library->slug, 'id' => $audio->id]))->out(false) : '';
$authors = (string) ($found['card']['authors'] ?? '');
$credit = implode(' · ', array_filter([format_string($record->title, true, ['escape' => false]), $authors,
    $str('licence' . str_replace('-', '', $item->licence))]));
$data = [
    'title' => format_string($record->title, true, ['escape' => false]),
    'cover' => (string) covers::url((int) $item->recordid),
    'colour' => (int) $item->recordid % 6,
    'back' => $back->out(false),
    'audiourl' => $audiourl,
    'credit' => $credit,
    'chosen' => $str('talereaderchosen', format_string($library->name, true, ['escape' => false])),
];
foreach (
    ['talereaderclose', 'talereadersize', 'talereadersmaller', 'talereaderbigger', 'talereadernight', 'talereaderprev',
        'talereadernext', 'talereaderstart', 'talereaderaloud', 'talereaderend', 'talereaderbye', 'talereaderother',
        'talereaderagain', 'talereaderloading', 'talereaderfailed', 'talereaderaudio', 'talereaderlisten'] as $key
) {
    $data[$key] = $str($key);
}
// A book may ask other sites for its styles, pictures or scripts, and that would tell them who is reading it.
@header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' blob:; "
    . "img-src 'self' data: blob:; font-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self' blob:; "
    . "frame-src 'self' blob:; worker-src 'self' blob:");
shell::output($data['title'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/tale', $data), $library, '', [
    'bare' => true,
    'styles' => ['tale'],
    'scripts' => ['epubjs/jszip.min', 'epubjs/epub.min', 'tale'],
    'config' => [
        'file' => digital::file_url($item),
        'back' => $back->out(false),
        'pagecss' => (new moodle_url('/local/oksigeniaayvurenda/app/reader-page.css'))->out(false),
        'strings' => ['next' => $str('talereadernext'), 'start' => $str('talereaderstart'),
            'page' => $str('talereaderpage', (object) ['at' => '{at}', 'of' => '{of}']), 'cover' => $str('talereadercover'),
            'end' => $str('talereaderend'),
            'spot' => $str('talereaderspot', (object) ['chapter' => '{chapter}', 'page' => '{page}', 'total' => '{total}']),
            'screen' => $str('talereaderscreen', (object) ['page' => '{page}', 'total' => '{total}'])],
    ],
]);
