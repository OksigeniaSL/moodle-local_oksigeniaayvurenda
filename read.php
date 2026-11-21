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
use local_oksigeniaayvurenda\local\voices;
use local_oksigeniaayvurenda\local\window;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- what is in an open window is read without an account; the rest logs in.
require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$id = required_param('id', PARAM_INT);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $ref, 'id' => $id]));

$library = catalogue::library($ref);
if (!$library) {
    shell::missing(get_string('nolibrary', 'local_oksigeniaayvurenda'));
}
// What is not of this centre is not here, the same as what never was: the library's page that says so, never Moodle's.
$item = $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $id, 'entityid' => $library->entityid]);
if (!$item) {
    shell::missing(get_string('recordnotfound', 'local_oksigeniaayvurenda'), $library);
}
// Someone without an account reads what is in an open window; the place they reach stays in their browser.
$anyone = (!isloggedin() || isguestuser()) && window::shows($item);
if (!$anyone) {
    require_login(null, false);
    shell::require('local/oksigeniaayvurenda:view', $library);
    if (!digital::visible($item, digital::viewer((int) $library->entityid, (int) $USER->id))) {
        shell::refuse(get_string('readnotforyou', 'local_oksigeniaayvurenda'), $library);
    }
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$title = (string) $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => $item->recordid]);
$place = $anyone ? ['position' => null, 'bookmarks' => []] : digital::place((int) $item->id, (int) $USER->id);
$candownload = digital_rules::can_download($item->licence, (bool) $item->download);
// A reading in parts: its parts that this person may hear, in the book's order, and where they were in it.
$reading = null;
$parts = [];
if (!$anyone && $item->kind === 'voice' && $item->part !== null && $item->createdby) {
    $viewer = digital::viewer((int) $library->entityid, (int) $USER->id);
    $siblings = array_values(array_filter($DB->get_records_select(
        'local_oksigeniaayvurenda_digital',
        "entityid = :entityid AND recordid = :recordid AND kind = 'voice' AND createdby = :userid AND part IS NOT NULL",
        ['entityid' => $library->entityid, 'recordid' => $item->recordid, 'userid' => $item->createdby],
        'timecreated, id'
    ), fn($v) => digital::visible($v, $viewer)));
    $parts = $siblings ? voices::readings($siblings)[0]['parts'] : [];
}
if ($parts) {
    $first = $parts[0];
    // Where the listener was, kept on the reading's first part: «which part:second».
    $kept = (string) (digital::place((int) $first->id, (int) $USER->id)['position'] ?? '');
    [$keptid, $keptat] = array_pad(array_map('intval', explode(':', $kept)), 2, 0);
    $ids = array_map(fn($p) => (int) $p->id, $parts);
    $start = (int) $item->id === (int) $first->id && in_array($keptid, $ids, true) ? $keptid : (int) $item->id;
    $start = in_array($start, $ids, true) ? $start : (int) $first->id;
    $at = $start === $keptid ? $keptat : 0;
    $current = $parts[array_search($start, $ids, true)];
    $sounds = voices::backdrops();
    $soundof = fn($p) => isset($sounds[(string) $p->backdrop]) ? (string) $p->backdrop : '';
    $signed = array_values(array_filter($parts, fn($p) => $p->status === 'published' && (string) $p->signature !== ''));
    $who = $signed ? format_string((string) $signed[0]->signature, true, ['escape' => false]) : '';
    $mmss = fn(int $s) => intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
    $index = array_search($start, $ids, true);
    $name = voices::part_name($current);
    $reading = [
        'cover' => \local_oksigeniaayvurenda\local\covers::urls([(int) $item->recordid])[(int) $item->recordid] ?? '',
        'coveralt' => $str('readingcoveralt', $title),
        'booktitle' => $title,
        'who' => $who,
        'initial' => $who !== '' ? \core_text::strtoupper(\core_text::substr($who, 0, 1)) : '',
        'bybefore' => $str('readingbybefore'),
        'byafter' => $str('readingbyafter', count($parts)),
        'byanon' => $str('readingbyanon', count($parts)),
        // Back where they were: the part and the minute; else what sounds, with its own name.
        // At the start, where it starts; «Now: …» only once it sounds.
        // With parts heard before it, where it goes on; the first time, where it starts.
        'nowtext' => $str(
            $at > 0 ? 'readingresume' : ($index > 0 ? 'readingcarryon' : 'readingstart'),
            voices::part_title((int) $current->part)
        ),
        'nowsmall' => $at > 0 ? $str('readingresumeat', max(1, (int) round($at / 60))) : ($name !== '' ? '«' . $name . '»' : ''),
        'starturl' => digital::file_url($current),
        'startduration' => $current->seconds ? $mmss((int) $current->seconds) : '',
        'playlabel' => $str('readingplay'),
        'pauselabel' => $str('readingpause'),
        'wherelabel' => $str('readingwhere'),
        'notnow' => $str('readingnotnow'),
        'partsheading' => $str('readingparts'),
        // A voice is never downloaded: it is personal and stays in the library.
        'note' => $str('readingnote') . ' ' . $str('readingnodownload'),
        'parts' => array_map(fn($p, $i) => [
            'partid' => (int) $p->id,
            'parturl' => digital::file_url($p),
            'parttitle' => voices::part_title((int) $p->part),
            'partname' => voices::part_name($p),
            'partsound' => $soundof($p),
            'partsoundname' => $soundof($p) !== '' ? $str('voicebackdrop' . $soundof($p)) : '',
            'partsmall' => voices::part_name($p) !== '' || $soundof($p) !== '',
            'partnum' => $p->part ? (string) $p->part : '·',
            'partseconds' => (int) $p->seconds,
            'partduration' => $p->seconds ? $mmss((int) $p->seconds) : '',
            'partflex' => max(1, (int) ($p->seconds ?: 60)),
            'partheard' => $i < $index,
            'partcurrent' => (int) $p->id === $start,
        ], $parts, array_keys($parts)),
        // Each part with the sound its reader chose; whoever listens may take another for themselves.
        'backdrop' => $sounds ? [
            'bdnow' => $soundof($current) !== '' ? $str('readingbackdropunder', $str('voicebackdropthe' . $soundof($current)))
                : $str('voicebackdropnone'),
            'bdother' => $str('readingothersound'),
        ] : null,
    ];
}
$data = [
    'heading' => $title,
    'subtitle' => $item->title !== '' && $item->title !== null ? $item->title : $str('format' . $item->format),
    'licence' => $str('licence' . str_replace('-', '', $item->licence)),
    'epub' => $item->format === 'epub',
    'pdf' => $item->format === 'pdf',
    'audio' => $item->format === 'audio' && !$reading,
    'reading' => $reading,
    'video' => $item->format === 'video',
    'link' => $item->kind === 'link' ? \local_oksigeniaayvurenda\local\links::out($library, $item) : '',
    'throughproxy' => $item->kind === 'link'
        && \local_oksigeniaayvurenda\local\links::out($library, $item) !== $item->url,
    'other' => $item->format === 'other',
    'fileurl' => $item->kind === 'link' ? '' : digital::file_url($item),
    'downloadurl' => $candownload && $item->kind !== 'link' ? digital::file_url($item, true) : '',
    'marks' => true,
    // Two facing pages where the screen is wide; beside the recorder, one.
    'spread' => true,
    'recordurl' => $anyone ? (new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug]))->out(false)
        : (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $item->recordid]))
            ->out(false),
];
foreach (
    ['readthroughproxy', 'readprev', 'readnext', 'readsmaller', 'readbigger', 'readmark', 'readmarks', 'readdownload',
        'readopenlink',
        'readnodownload', 'backtorecord', 'readloading', 'readfailed', 'readprogress', 'readfullscreen',
        'readfullscreenexit'] as $key
) {
    $data[$key] = $str($key);
}
$scripts = $item->format === 'epub' ? ['epubjs/jszip.min', 'epubjs/epub.min', 'reader'] : [$reading ? 'lectura' : 'reader'];
// A book may ask other sites for its styles, pictures or scripts, and that would tell them who is reading it.
@header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' blob:; "
    . "img-src 'self' data: blob:; font-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self' blob:; "
    . "frame-src 'self' blob:; worker-src 'self' blob:");
shell::output($title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/read', $data), $library, '', [
    'search' => false,
    'scripts' => $scripts,
    'nav' => [['url' => $data['recordurl'], 'label' => $str($anyone ? 'windowback' : 'backtorecord')]],
    'config' => [
        'local' => $anyone,
        'ajaxurl' => (new moodle_url('/local/oksigeniaayvurenda/digital_ajax.php'))->out(false),
        'sesskey' => sesskey(),
        'item' => $reading ? (int) $parts[0]->id : (int) $item->id,
        'start' => $reading ? $start : 0,
        'who' => $reading ? $who : '',
        'sounds' => $reading ? array_map(fn($key, $files) => $files + ['key' => $key, 'name' => $str('voicebackdrop' . $key),
            'the' => $str('voicebackdropthe' . $key)], array_keys(voices::backdrops()), voices::backdrops()) : [],
        'at' => $reading ? $at : 0,
        'position' => $place['position'],
        'bookmarks' => $place['bookmarks'],
        'pagecss' => (new moodle_url('/local/oksigeniaayvurenda/app/reader-page.css'))->out(false),
        'strings' => ['readmarklabel' => $str('readmarklabel'), 'readpage' => $str('readpage'), 'readingnow' => $str('readingnow'),
            'readingnext' => $str('readingnext'), 'readingleft' => $str('readingleft'), 'readingplay' => $str('readingplay'),
            'readingpause' => $str('readingpause'), 'readingbackdropunder' => $str('readingbackdropunder'),
            'voicebackdropnone' => $str('voicebackdropnone'), 'readingchose' => $str('readingchose'),
            'readingchosenone' => $str('readingchosenone'), 'readingtheirs' => $str('readingtheirs'),
            'readingsomeone' => $str('readingsomeone'), 'readingwith' => $str('readingwith'),
            'readmarkdrop' => $str('readmarkdrop')],
    ],
]);
