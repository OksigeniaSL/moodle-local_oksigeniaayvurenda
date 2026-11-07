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
 * A record of the catalogue, with its copies.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $ref, 'id' => $id]));

$found = $library ? catalogue::record($id, $library) : null;
if (!$found) {
    shell::missing(get_string('recordnotfound', 'local_oksigeniaayvurenda'), $library);
}
// Whoever catalogues corrects what was entered, or takes it out.
$correct = access::can('local/oksigeniaayvurenda:catalogue', $library);
$fix = optional_param('fix', '', PARAM_ALPHA);
$fixnotice = '';
$fixerror = '';
if ($correct && $fix !== '' && data_submitted() && confirm_sesskey()) {
    $corrections = \local_oksigeniaayvurenda\local\corrections::class;
    $item = optional_param('item', 0, PARAM_INT);
    $copyform = [];
    foreach (['barcode', 'callnumber1', 'callnumber2', 'callnumber3', 'itemtype', 'notes'] as $field) {
        $copyform[$field] = optional_param($field, '', PARAM_TEXT);
    }
    $copyform['locationid'] = optional_param('locationid', 0, PARAM_INT);
    $recordform = [];
    foreach (['title', 'subtitle', 'isbn', 'edition', 'pubplace', 'pubyear', 'summary', 'notes', 'authors', 'subjects'] as $field) {
        $recordform[$field] = optional_param($field, '', PARAM_TEXT);
    }
    $failed = match ($fix) {
        'record' => $corrections::record($library, $id, $recordform) ? '' : 'correctnotitle',
        'copy' => $corrections::copy($library, $item, $copyform),
        'dropcopy' => $corrections::remove_copy($library, $item),
        'droprecord' => $corrections::remove_record($library, $id),
        default => 'recordnotfound',
    };
    if (!$failed && $fix === 'droprecord') {
        redirect(new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $ref]), get_string(
            'correctrecordgone',
            'local_oksigeniaayvurenda'
        ));
    }
    $fixerror = $failed ? get_string($failed, 'local_oksigeniaayvurenda') : '';
    $fixnotice = $failed ? '' : get_string('correctsaved', 'local_oksigeniaayvurenda');
    $found = catalogue::record($id, $library);
}
// A reader who took the book home tells what they thought, or takes it back.
$do = optional_param('review', '', PARAM_ALPHA);
if ($do !== '' && data_submitted() && confirm_sesskey()) {
    $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
        'status' => 'active']);
    $done = '';
    if ($reader && \local_oksigeniaayvurenda\local\reviews::can_write($id, (int) $reader->id)) {
        if ($do === 'send') {
            // A drawing comes from the drawing pad as a PNG in a data address.
            $drawing = optional_param('drawing', '', PARAM_RAW_TRIMMED);
            $png = str_starts_with($drawing, 'data:image/png;base64,')
                ? (string) base64_decode(substr($drawing, strlen('data:image/png;base64,')), true) : '';
            // A voice comes from the recorder as an uploaded file.
            $upload = $_FILES['voice'] ?? null;
            $spoken = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($upload['tmp_name'])
                ? $upload['tmp_name'] : '';
            $reviewid = \local_oksigeniaayvurenda\local\reviews::write(
                (int) $library->entityid,
                $id,
                (int) $reader->id,
                optional_param('text', '', PARAM_TEXT),
                null,
                optional_param('forlittle', 0, PARAM_BOOL) && \local_oksigeniaayvurenda\local\cards::look_of($reader) !== 'flower',
                $png !== '' || $spoken !== ''
            );
            if ($reviewid && $png !== '') {
                \local_oksigeniaayvurenda\local\reviews::draw($reviewid, $png);
            }
            if ($reviewid && $spoken !== '') {
                \local_oksigeniaayvurenda\local\reviews::speak($reviewid, $spoken);
            }
            // Neither a picture nor a recording after all, and no words: there is nothing to tell.
            $kept = $reviewid ? \local_oksigeniaayvurenda\local\reviews::mine($id, (int) $reader->id) : null;
            if (
                $kept && $kept->text === '' && !\local_oksigeniaayvurenda\local\reviews::drawing($reviewid)
                    && !\local_oksigeniaayvurenda\local\reviews::voice($reviewid)
            ) {
                \local_oksigeniaayvurenda\local\reviews::remove($id, (int) $reader->id);
                $reviewid = 0;
            }
            $done = $reviewid ? 'sent' : '';
        } else if ($do === 'remove') {
            \local_oksigeniaayvurenda\local\reviews::remove($id, (int) $reader->id);
            $done = 'removed';
        }
    }
    redirect(new moodle_url($PAGE->url, ['reviewed' => $done], 'ayv-reviews'));
}
$data = screens::record($found, $library);
// Back to the results it came from, as a reader expects (DIS-KIMI-08): only a search of this same library.
$came = (string) get_local_referer(false);
$data['backresults'] = $came !== '' && str_contains($came, '/local/oksigeniaayvurenda/search.php')
    && str_contains($came, 'library=' . rawurlencode($library->slug)) ? $came : '';
$data['backresultslabel'] = get_string('recordbackresults', 'local_oksigeniaayvurenda');
// A book without a cover that someone looks at asks for it, once, in the background.
\local_oksigeniaayvurenda\local\covers::seek((int) $found['record']->id);
$data['reviews'] = screens::reviews($found, $library, optional_param('reviewed', '', PARAM_ALPHA));
$data['hold'] = screens::hold($found, $library, optional_param('held', '', PARAM_ALPHA));
$data['digital'] = screens::digital($found, $library);
$data['formatask'] = screens::formatask($found, $library, optional_param('format', '', PARAM_ALPHA));
// How to find it on the shelf, and the books beside it there, to find it in the room and to come across another.
$data['where'] = screens::where($library, $id);
$beside = \local_oksigeniaayvurenda\local\stacks::neighbours($library, $id);
$data['beside'] = $beside && count($beside['spines']) > 1 ? [
    'heading' => get_string('stacksbeside', 'local_oksigeniaayvurenda'),
    'spines' => screens::spines($beside['spines']),
    'walk' => get_string('stackswalkhere', 'local_oksigeniaayvurenda'),
    'walkurl' => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug,
        'place' => $beside['place'], 'at' => $id]))->out(false),
    'bird' => screens::art('estanteria/mainumby', 'svg'),
    'bookend' => screens::art('estanteria/sujetalibros', 'svg'),
] : null;
// What else its readers took, said by the memory (only when enough of them did, so that no one is singled out).
$also = \local_oksigeniaayvurenda\local\memory::also($library, $id);
$said = count($also) >= 2 ? \local_oksigeniaayvurenda\local\characters::say(
    (int) $library->entityid,
    'memory',
    'memoryalso',
    [],
    'senala'
) : null;
$data['memoryalso'] = $said ? [
    'heading' => get_string('memoryalsoheading', 'local_oksigeniaayvurenda'),
    'said' => $said,
    'cards' => array_values(screens::cards(catalogue::cards_of($also, $library), $library)),
] : null;
if ($correct) {
    $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
    $form = \local_oksigeniaayvurenda\local\corrections::record_form($found['record']);
    $places = \local_oksigeniaayvurenda\local\cataloguer::locations((int) $library->entityid);
    $data['correct'] = [
        'action' => $PAGE->url->out(false),
        'sesskey' => sesskey(),
        'notice' => $fixnotice,
        'error' => $fixerror,
        'form' => $form,
        // The subjects as chips, with what the centre already uses offered while typing.
        'subjectsurl' => (new moodle_url('/local/oksigeniaayvurenda/add_ajax.php', ['library' => $library->slug,
            'sesskey' => sesskey()]))->out(false),
        'subjectsadd' => get_string('subjectsadd', 'local_oksigeniaayvurenda'),
        'subjectsremove' => get_string('subjectsremove', 'local_oksigeniaayvurenda'),
        'subjectshint' => get_string('subjectshint', 'local_oksigeniaayvurenda'),
    ];
    foreach (
        ['correctrecord', 'correctcopy', 'correctsave', 'correcttitle', 'correctsubtitle', 'correctauthors', 'correctsubjects',
        'correctlines', 'correctisbn', 'correctedition', 'correctplace', 'correctyear', 'correctsummary', 'correctnotes',
        'correctcode', 'correctcall1', 'correctcall2', 'correctcall3', 'correctlocation', 'correctremoverecord',
        'correctremovecopy', 'correctremovehint', 'correctclose'] as $key
    ) {
        $data['correct'][$key] = $str($key);
    }
    foreach ($data['copies'] as $n => $copy) {
        if (empty($copy['here'])) {
            continue;
        }
        $item = $DB->get_record('local_oksigeniaayvurenda_item', ['id' => $copy['id']]);
        $data['copies'][$n]['fix'] = ['id' => $item->id, 'barcode' => $item->barcode, 'call1' => $item->callnumber1,
            'call2' => $item->callnumber2, 'call3' => $item->callnumber3, 'notes' => $item->notes, 'itemtype' => $item->itemtype,
            'places' => array_map(fn($id, $label) => ['id' => $id, 'label' => $label,
                'selected' => (int) $id === (int) $item->locationid], array_keys($places), $places)];
        foreach (
            ['correctcopy', 'correctsave', 'correctcode', 'correctcall1', 'correctcall2', 'correctcall3', 'correctlocation',
            'correctnotes', 'correctremovecopy', 'correctremovehint', 'correctclose'] as $key
        ) {
            $data['copies'][$n]['fix'][$key] = $str($key);
        }
        $data['copies'][$n]['fix']['action'] = $PAGE->url->out(false);
        $data['copies'][$n]['fix']['sesskey'] = sesskey();
        $data['copies'][$n]['fix']['classesurl'] = (new moodle_url('/local/oksigeniaayvurenda/add_ajax.php', [
            'library' => $library->slug, 'sesskey' => sesskey()]))->out(false);
    }
}
shell::output($found['record']->title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/record', $data), $library, '', [
    'scripts' => array_merge(!empty($data['reviews']['write']) ? ['draw', 'speak'] : [], $data['beside'] ? ['stacks'] : []),
    // Who corrects records has the help of correcting them.
    'nav' => $correct && !optional_param('help', 0, PARAM_BOOL) ? [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'record']
    ))->out(false), 'label' => get_string('helpnav', 'local_oksigeniaayvurenda')]] : [],
]);
