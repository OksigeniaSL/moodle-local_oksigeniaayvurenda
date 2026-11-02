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
 * The digital forms of a record, for the catalogue staff: adding a file or a link, its licence and who it is for.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\digital_rules;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$recordid = required_param('record', PARAM_INT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/digital.php', ['library' => $ref, 'record' => $recordid]));

$found = $library ? catalogue::record($recordid, $library) : null;
if (!$found) {
    throw new moodle_exception('recordnotfound', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$notice = '';
$fields = fn() => [
    'title' => optional_param('title', '', PARAM_TEXT),
    'licence' => optional_param('licence', 'rights', PARAM_ALPHANUMEXT),
    'audience' => optional_param_array('audience', [], PARAM_ALPHA),
    'stages' => optional_param_array('stages', [], PARAM_TEXT),
    'download' => optional_param('download', 0, PARAM_BOOL),
];
// Only for the students of a course, until a date, with the part of the work it is.
$fragment = function (int $id) use ($str) {
    $until = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        (string) optional_param('until', '', PARAM_TEXT),
        core_date::get_server_timezone_object()
    );
    \local_oksigeniaayvurenda\local\fragments::set(
        $id,
        $until ? optional_param('forcourse', 0, PARAM_INT) : 0,
        optional_param('pages', '', PARAM_TEXT),
        $until ? $until->setTime(23, 59, 59)->getTimestamp() : 0,
        time()
    );
};
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'add') {
        $url = trim(optional_param('url', '', PARAM_URL));
        if (
            !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])
            && !digital::acceptable($_FILES['file']['tmp_name'], (string) $_FILES['file']['name'])
        ) {
            $notice = $str('digitalwrongtype');
        } else if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $fragment(digital::add_file(
                $library,
                $recordid,
                $_FILES['file']['tmp_name'],
                (string) $_FILES['file']['name'],
                $fields(),
                (int) $USER->id
            ));
            $notice = $str('digitaladded');
        } else if ($url !== '') {
            $fragment(digital::add_link($library, $recordid, $url, $fields(), (int) $USER->id));
            $notice = $str('digitaladded');
        } else {
            $notice = $str('digitalnothing');
        }
    } else if ($do === 'save') {
        digital::update(required_param('item', PARAM_INT), $library, $fields());
        $fragment((int) digital::get(required_param('item', PARAM_INT), $library)->id);
        $notice = $str('digitalsaved');
    } else if ($do === 'delete') {
        digital::delete(required_param('item', PARAM_INT), $library);
        $notice = $str('digitaldeleted');
    }
}

$stages = holds::stages((int) $library->entityid, time());
$licences = fn(string $chosen) => array_map(fn($l) => ['value' => $l, 'label' => $str('licence' . str_replace('-', '', $l)),
    'selected' => $l === $chosen], digital_rules::LICENCES);
$audiences = fn(array $chosen) => array_map(fn($a) => ['value' => $a, 'label' => $str(
    \local_oksigeniaayvurenda\local\skins::words((int) $library->entityid, 'audience' . $a)
),
    'checked' => in_array($a, $chosen, true)], digital_rules::AUDIENCES);
$stagelist = fn(array $chosen) => array_map(fn($s) => ['value' => $s, 'checked' => in_array($s, $chosen, true)], $stages);
$courses = \local_oksigeniaayvurenda\local\fragments::courses($library, $recordid);
$yearend = (int) \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, time())->timeend - 1;
$courselist = fn(int $chosen) => array_values(array_map(fn($id, $name) => ['id' => $id, 'name' => $name,
    'selected' => $id === $chosen], array_keys($courses), $courses));
$items = [];
foreach (digital::of_record($recordid, $library, ['who' => 'desk', 'stage' => null]) as $item) {
    $chosenstages = json_decode((string) $item->stages, true) ?: [];
    $only = \local_oksigeniaayvurenda\local\fragments::of_item((int) $item->id);
    $audiencelabels = array_map(
        fn($a) => $str(\local_oksigeniaayvurenda\local\skins::words((int) $library->entityid, 'audience' . $a)),
        digital::audience($item)
    );
    $items[] = [
        'formatkey' => (string) $item->format,
        // Only for a course: its line says who; the audience above does not count.
        'audiencechips' => $only ? [] : array_values($audiencelabels),
        'courses' => $courselist($only ? (int) $only->courseid : 0),
        'pages' => $only ? (string) $only->pages : '',
        'until' => date('Y-m-d', $only ? (int) $only->timeend : $yearend),
        'onlyline' => $only ? $str('fragmentline', (object) ['course' => $courses[$only->courseid]
            ?? format_string((string) $DB->get_field('course', 'fullname', ['id' => $only->courseid]), true, ['escape' => false]),
            'until' => userdate((int) $only->timeend, get_string('strftimedateshort', 'langconfig'))]) : '',
        'id' => (int) $item->id,
        'title' => $item->title !== '' && $item->title !== null ? $item->title : $str('format' . $item->format),
        'rawtitle' => (string) $item->title,
        'format' => $str('format' . $item->format),
        'licencelabel' => $str('licence' . str_replace('-', '', $item->licence)),
        'readurl' => (new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $library->slug, 'id' => $item->id]))
            ->out(false),
        'licences' => $licences($item->licence),
        'audiences' => $audiences(digital::audience($item)),
        'stages' => $stagelist($chosenstages),
        'download' => (bool) $item->download,
    ];
}
$covers = \local_oksigeniaayvurenda\local\covers::urls([$recordid]);
$data = [
    'heading' => $found['record']->title,
    'eyebrow' => $str('digitaleyebrow'),
    'authors' => $found['card']['authors'] ?? '',
    'hascover' => isset($covers[$recordid]),
    'coverurl' => $covers[$recordid] ?? '',
    'colour' => (int) ($found['card']['colour'] ?? 0),
    'close' => $str('librariesclose'),
    'intro' => $str('digitalintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'items' => $items,
    'hasitems' => (bool) $items,
    'licences' => $licences('rights'),
    'audiences' => $audiences(['staff']),
    'stages' => $stagelist([]),
    'hasstages' => (bool) $stages,
    'hascourses' => (bool) $courses,
    'courses' => $courselist(0),
    'until' => date('Y-m-d', $yearend),
    'recordurl' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
        ->out(false),
];
foreach (
    ['digitaladd', 'digitalfile', 'digitalurl', 'digitalurlhint', 'digitaltitlefield', 'digitallicence',
        'digitallicencehint', 'digitalaudience', 'digitalstages', 'digitaldownload', 'digitalsave', 'digitaldelete',
        'digitalread', 'digitalnone', 'backtorecord', 'digitalchange', 'digitaladdhint', 'fragmentcourse', 'fragmentnone',
        'fragmentpages', 'fragmentpageshint', 'fragmentuntil', 'fragmenthint'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $found['record']->title,
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/digital', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $data['recordurl'], 'label' => $str('backtorecord')]]]
);
