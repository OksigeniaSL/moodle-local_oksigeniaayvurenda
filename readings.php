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
 * The readings of a course, made by its teacher from the library and seen by its students; and, for the library staff,
 * what the courses ask of it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\readings;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$courseid = optional_param('course', 0, PARAM_INT);

if (!$courseid) {
    // The library's side: what the courses ask of it.
    $ref = required_param('library', PARAM_ALPHANUMEXT);
    require_login(null, false);
    $library = catalogue::library($ref);
    shell::require('local/oksigeniaayvurenda:desk', $library);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readings.php', ['library' => $ref]));
    $names = [];
    $count = fn(int $n, string $one, string $many) => $n === 1 ? $str($one) : $str($many, $n);
    $titles = [];
    foreach (readings::demand($library) as $row) {
        foreach ($row['courses'] as $id) {
            $names[$id] = $names[$id] ?? format_string((string) $DB->get_field('course', 'fullname', ['id' => $id]));
        }
        $titles[] = [
            'title' => $row['title'],
            'url' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
                'id' => $row['recordid']]))->out(false),
            'courses' => $str('readingsin', implode(' · ', array_map(fn($id) => $names[$id], $row['courses']))),
            'facts' => implode(' · ', array_filter([
                $count($row['students'], 'readingsstudentsone', 'readingsstudents'),
                $count($row['copies'], 'readingscopiesone', 'readingscopies'),
                $row['waiting'] ? $str('readingswaiting', $row['waiting']) : '',
            ])),
            'ratio' => !$row['copies'] ? $str('readingsnocopy')
                : ($row['students'] > $row['copies'] ? $str('readingsratio', (int) ceil($row['students'] / $row['copies'])) : ''),
            'short' => !$row['copies'] || $row['waiting'] > 0,
        ];
    }
    $data = ['heading' => $str('readingsdemandheading', format_string($library->name)), 'intro' => $str('readingsdemandintro'),
        'titles' => $titles, 'hastitles' => (bool) $titles, 'none' => $str('readingsdemandnone')];
    shell::output(
        $data['heading'],
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/readings_demand', $data),
        $library,
        '',
        ['search' => false, 'nav' => [['url' => (new moodle_url(
            '/local/oksigeniaayvurenda/reports.php',
            ['library' => $library->slug]
        ))->out(false),
        'label' => $str('reportsnav')]]]
    );
    exit;
}

// A course's side.
$course = get_course($courseid);
require_login($course, false);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/readings.php', ['course' => $course->id]));
$canedit = has_capability('moodle/course:update', $context);
$list = readings::of_course((int) $course->id);
$library = $list ? $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $list->libraryid]) : readings::library_for($course);
if (!$library) {
    shell::missing($str('readingsnolibrary'));
}
// Seeing a list asks for seeing its library (a centre getting ready shows only that it opens soon).
shell::require('local/oksigeniaayvurenda:view', $library);
$notice = '';
$topic = trim(optional_param('topic', '', PARAM_TEXT));
if ($canedit && data_submitted() && confirm_sesskey()) {
    $do = optional_param('do', '', PARAM_ALPHA);
    $list = readings::start($course, $library, (int) $USER->id);
    $itemid = optional_param('item', 0, PARAM_INT);
    if ($do === 'add') {
        $notice = readings::add($list, required_param('record', PARAM_INT), $topic, optional_param('required', 0, PARAM_BOOL))
            ? $str('readingsadded') : '';
    } else if ($do === 'change') {
        readings::change($list, $itemid, $topic, optional_param('required', 0, PARAM_BOOL), optional_param('note', '', PARAM_TEXT));
        $notice = $str('readingssaved');
    } else if ($do === 'remove') {
        readings::remove($list, $itemid);
    } else if ($do === 'up' || $do === 'down') {
        readings::move($list, $itemid, $do === 'up' ? -1 : 1);
    }
}
$items = $list ? readings::items($list, $library) : [];
// By topic, in the order the teacher put them; what has no topic goes first.
$topics = ['' => ['topic' => '', 'hastopic' => false, 'books' => []]];
foreach ($items as $item) {
    $key = $item['topic'];
    if (!isset($topics[$key])) {
        $topics[$key] = ['topic' => $key, 'hastopic' => $key !== '', 'books' => []];
    }
    $card = $item['card'];
    $topics[$key]['books'][] = [
        'id' => $item['id'],
        'title' => $card['title'],
        'authors' => $card['authors'],
        'url' => $card['url'],
        'hascover' => !empty($card['cover']),
        'coverurl' => $card['cover'] ?? '',
        'colour' => $card['colour'],
        'statelabel' => $card['statelabel'],
        'stateclass' => $card['stateclass'],
        'location' => $card['location'] ?? '',
        'required' => $item['required'],
        'note' => $item['note'],
        'digital' => $item['digital'],
        'topic' => $item['topic'],
        'first' => !$topics[$key]['books'],
        'last' => false,
    ];
}
// The ends of each topic: its first cannot go up, its last cannot go down.
foreach ($topics as &$group) {
    if ($group['books']) {
        $group['books'][count($group['books']) - 1]['last'] = true;
    }
}
unset($group);
$topics = array_filter($topics, fn($t) => $t['books']);
$query = $canedit ? trim(core_text::substr(optional_param('q', '', PARAM_TEXT), 0, 200)) : '';
$found = $query !== '' ? array_slice(catalogue::search($library, $query)['cards'], 0, 8) : [];
$data = [
    'heading' => $str('readingsheading', format_string($course->fullname)),
    'intro' => $str('readingsintro', format_string($library->name)),
    'notice' => $notice,
    'canedit' => $canedit,
    'action' => $PAGE->url->out(false),
    'courseid' => (int) $course->id,
    'sesskey' => sesskey(),
    'topics' => array_values($topics),
    'hasbooks' => (bool) $items,
    'empty' => $str($canedit ? 'readingsemptyteacher' : 'readingsempty'),
    'query' => $query,
    'topic' => $topic,
    'searched' => $query !== '',
    'found' => array_map(fn($c) => ['recordid' => $c['id'], 'title' => $c['title'], 'authors' => $c['authors']], $found),
    'hasfound' => (bool) $found,
    'topiclist' => array_values(array_map(fn($t) => ['value' => $t], array_filter(array_keys($topics), 'strlen'))),
];
foreach (
    ['readingsadd', 'readingsfind', 'readingsfindbutton', 'readingstopic', 'readingstopichint', 'readingsrequired',
        'readingsrecommended', 'readingsnote', 'readingssave', 'readingsremove', 'readingsup', 'readingsdown',
        'readingschange', 'readingsaddthis', 'readingsnothing', 'readingsread'] as $key
) {
    $data[$key] = $str($key);
}
$back = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/readings', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $back, 'label' => $str('readingsback')]]]
);
