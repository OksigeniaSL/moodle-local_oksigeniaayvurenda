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
 * Reading or listening to a digital form of a record: an EPUB in the browser, a PDF, a recording, a video, or a link.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\digital_rules;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\window;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- what is in an open window is read without an account; the rest logs in.
require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $ref, 'id' => $id]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$item = digital::get($id, $library);
// Someone without an account reads what is in an open window; the place they reach stays in their browser.
$anyone = (!isloggedin() || isguestuser()) && window::shows($item);
if (!$anyone) {
    require_login(null, false);
    require_capability('local/oksigeniaayvurenda:view', $context);
    if (!digital::visible($item, digital::viewer((int) $library->entityid, (int) $USER->id))) {
        throw new required_capability_exception($context, 'local/oksigeniaayvurenda:desk', 'nopermissions', '');
    }
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$title = (string) $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $item->recordid]);
$place = $anyone ? ['position' => null, 'bookmarks' => []] : digital::place((int) $item->id, (int) $USER->id);
$candownload = digital_rules::can_download($item->licence, (bool) $item->download);
$data = [
    'heading' => $title,
    'subtitle' => $item->title !== '' && $item->title !== null ? $item->title : $str('format' . $item->format),
    'licence' => $str('licence' . str_replace('-', '', $item->licence)),
    'epub' => $item->format === 'epub',
    'pdf' => $item->format === 'pdf',
    'audio' => $item->format === 'audio',
    'video' => $item->format === 'video',
    'link' => $item->kind === 'link' ? $item->url : '',
    'other' => $item->format === 'other',
    'fileurl' => $item->kind === 'link' ? '' : digital::file_url($item),
    'downloadurl' => $candownload && $item->kind !== 'link' ? digital::file_url($item, true) : '',
    'recordurl' => $anyone ? (new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug]))->out(false)
        : (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $item->recordid]))
            ->out(false),
];
foreach (
    ['readprev', 'readnext', 'readsmaller', 'readbigger', 'readmark', 'readmarks', 'readdownload', 'readopenlink',
        'readnodownload', 'backtorecord', 'readloading'] as $key
) {
    $data[$key] = $str($key);
}
$scripts = $item->format === 'epub' ? ['epubjs/jszip.min', 'epubjs/epub.min', 'reader'] : ['reader'];
shell::output($title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/read', $data), $library, '', [
    'search' => false,
    'scripts' => $scripts,
    'nav' => [['url' => $data['recordurl'], 'label' => $str($anyone ? 'windowback' : 'backtorecord')]],
    'config' => [
        'local' => $anyone,
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/digital_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'item' => (int) $item->id,
        'position' => $place['position'],
        'bookmarks' => $place['bookmarks'],
        'strings' => ['readmarklabel' => $str('readmarklabel'), 'readpage' => $str('readpage')],
    ],
]);
