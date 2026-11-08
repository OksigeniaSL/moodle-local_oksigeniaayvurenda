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
 * What readers asked for in another format, and how the library answered.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\formats;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$answered = optional_param('done', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/formats.php', ['library' => $ref] + ($answered ? ['done' => 1] : [])
    + (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));
if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

if (data_submitted() && confirm_sesskey()) {
    $served = required_param('do', PARAM_ALPHA) === 'served';
    formats::answer(
        required_param('ask', PARAM_INT),
        $library,
        $served,
        optional_param('item', 0, PARAM_INT),
        (int) $USER->id
    );
    redirect(new moodle_url($PAGE->url, ['answered' => $served ? 'served' : 'declined']));
}
$answer = optional_param('answered', '', PARAM_ALPHA);
$notice = ['served' => $str('formatsdoneserved'), 'declined' => $str('formatsdonedeclined')][$answer] ?? '';

$asks = formats::of_library($library, $answered);
$covers = \local_oksigeniaayvurenda\local\covers::urls(array_column($asks, 'recordid'));
$link = fn(string $script, array $params) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$items = [];
foreach ($asks as $ask) {
    $choices = [];
    if (!$answered) {
        foreach (formats::items_of($library, $ask['recordid']) as $n => $item) {
            $choices[] = ['value' => (int) $item->id, 'first' => $n === 0,
                'label' => $item->title !== '' && $item->title !== null ? $item->title : $str('format' . $item->format)];
        }
    }
    $items[] = [
        'id' => $ask['id'],
        'title' => format_string($ask['title'], true, ['escape' => false]),
        'url' => $link('record.php', ['id' => $ask['recordid']]),
        'cover' => $covers[$ask['recordid']] ?? null,
        'colour' => $ask['recordid'] % 6,
        'name' => $ask['name'],
        'readerurl' => $link('reader.php', ['id' => $ask['readerid']]),
        'format' => $str('format' . $ask['format'] . 'label'),
        'when' => $answered
            ? $str('formats' . $ask['status'] . 'on', zones::date($ask['timemodified'], 'strftimedateshort', $library))
            : $str('formatsaskedon', zones::date($ask['timecreated'], 'strftimedateshort', $library)),
        'choices' => $choices,
        'haschoices' => (bool) $choices,
        'digitalurl' => $link('digital.php', ['record' => $ask['recordid']]),
    ];
}
// Nothing waiting: the herald says so, if she is on.
$herald = !$answered && !$items
    ? \local_oksigeniaayvurenda\local\characters::say((int) $library->entityid, 'herald', 'formatsquiet', [], 'lee') : null;
$data = [
    'heading' => $str('formatstitle'),
    'intro' => $str('formatsintro'),
    'notice' => $notice,
    'tabs' => [
        ['label' => $str('formatstabwaiting'), 'url' => $link('formats.php', []), 'current' => !$answered],
        ['label' => $str('formatstabdone'), 'url' => $link('formats.php', ['done' => 1]), 'current' => $answered],
    ],
    'items' => $items,
    'hasitems' => (bool) $items,
    'waiting' => !$answered,
    'herald' => $herald,
    'empty' => $str($answered ? 'formatsemptydone' : 'formatsempty'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
];
foreach (
    ['formatsserved', 'formatsdeclined', 'formatsservedtitle', 'formatsservedwith', 'formatsservedother',
        'formatsnoitem', 'formatsadditem', 'formatsservedgo', 'formatsclose', 'formatsreader'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($str('formatstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/formats', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'formats']
    ))->out(false), 'label' => $str('helpnav')]],
]);
