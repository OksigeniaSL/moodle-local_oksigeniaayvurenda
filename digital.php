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
use local_oksigeniaayvurenda\local\window;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
    'accessible' => optional_param('accessible', 0, PARAM_BOOL),
    'homeages' => optional_param_array('homeages', [], PARAM_INT),
    'homeminutes' => optional_param('homeminutes', 0, PARAM_INT),
    'homeparts' => optional_param('homeparts', 0, PARAM_INT),
    'homelines' => optional_param_array('homelines', [], PARAM_TEXT),
    'homenight' => optional_param('homenight', 0, PARAM_BOOL),
    'homeorder' => optional_param('homeorder', 0, PARAM_INT),
];
// Adding asks only for the file or the link: it comes in as the most careful, with others' rights and for the staff,
// and the rest is set on its card.
$addfields = fn() => array_replace($fields(), optional_param_array('audience', null, PARAM_ALPHA) === null
    ? ['audience' => ['staff'], 'licence' => optional_param('licence', 'rights', PARAM_ALPHANUMEXT)] : []);
// Only for the students of a course, until a date, with the part of the work it is.
$fragment = function (int $id) use ($str, $library, $recordid) {
    // Only a course that already has the book in its readings.
    $coursesasking = \local_oksigeniaayvurenda\local\fragments::courses($library, $recordid);
    $until = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        (string) optional_param('until', '', PARAM_TEXT),
        zones::of($library)
    );
    $course = optional_param('forcourse', 0, PARAM_INT);
    \local_oksigeniaayvurenda\local\fragments::set(
        $id,
        $until && isset($coursesasking[$course]) ? $course : 0,
        optional_param('pages', '', PARAM_TEXT),
        $until ? $until->setTime(23, 59, 59)->getTimestamp() : 0,
        time()
    );
};
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'add') {
        // Raw: a DOI pasted as it comes is not an address yet, and the function that adds it makes it one, or cleans it.
        $url = trim(optional_param('url', '', PARAM_RAW_TRIMMED));
        $url = \local_oksigeniaayvurenda\core\links::doi($url) ?? clean_param($url, PARAM_URL);
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
                $addfields(),
                (int) $USER->id
            ));
            $notice = $str('digitaladded');
        } else if ($url !== '') {
            $fragment(digital::add_link($library, $recordid, $url, $addfields() + ['subscribed' => optional_param(
                'subscribed',
                0,
                PARAM_BOOL
            )], (int) $USER->id));
            $notice = $str('digitaladded');
        } else {
            $notice = $str('digitalnothing');
        }
    } else if ($do === 'set') {
        // One gesture of an open card: what the form has now, with the one thing that came changed.
        $itemid = required_param('item', PARAM_INT);
        $item = digital::get($itemid, $library);
        $now = [
            'title' => (string) $item->title,
            'licence' => (string) $item->licence,
            'audience' => digital::audience($item),
            'stages' => json_decode((string) $item->stages, true) ?: [],
            'download' => (int) $item->download,
            'accessible' => empty($item->accessformat) ? 0 : 1,
            // Age 0 is an age (the youngest): only an empty list is none.
            'homeages' => array_map('intval', array_values(array_filter(
                explode(',', (string) $item->homeages),
                fn($age) => $age !== ''
            ))),
            'homeminutes' => (int) $item->homeminutes,
            'homeparts' => (int) $item->homeparts,
            'homelines' => json_decode((string) $item->homelines, true) ?: [],
            'homenight' => (int) $item->homenight,
            'homeorder' => (int) $item->homeorder,
        ];
        $toggle = optional_param('toggle', '', PARAM_TEXT);
        if (preg_match('/^(audience|stage|homeage):(.+)$/', $toggle, $m)) {
            $key = ['audience' => 'audience', 'stage' => 'stages', 'homeage' => 'homeages'][$m[1]];
            $value = $m[1] === 'homeage' ? (int) $m[2] : $m[2];
            $now[$key] = in_array($value, $now[$key], true) ? array_values(array_diff($now[$key], [$value]))
                : array_merge($now[$key], [$value]);
        }
        foreach (['title' => PARAM_TEXT, 'licence' => PARAM_ALPHANUMEXT] as $name => $type) {
            $value = optional_param($name, null, $type);
            if ($value !== null) {
                $now[$name] = $value;
            }
        }
        foreach (['download', 'accessible', 'homenight', 'homeminutes', 'homeparts', 'homeorder'] as $name) {
            $value = optional_param($name, null, PARAM_INT);
            if ($value !== null) {
                $now[$name] = max(0, $value);
            }
        }
        $lines = optional_param_array('homelines', null, PARAM_TEXT);
        if ($lines !== null) {
            $now['homelines'] = array_replace($now['homelines'], $lines);
        }
        digital::update($itemid, $library, $now);
        $subscribed = optional_param('subscribed', null, PARAM_INT);
        if ($subscribed !== null) {
            digital::mark_subscribed($itemid, $library, (bool) $subscribed);
        }
        // Only for a course: the course, the part and until when, each on its own.
        $forcourse = optional_param('forcourse', null, PARAM_INT);
        $until = optional_param('until', null, PARAM_TEXT);
        $pages = optional_param('pages', null, PARAM_TEXT);
        if ($forcourse !== null || $until !== null || $pages !== null) {
            $only = \local_oksigeniaayvurenda\local\fragments::of_item($itemid);
            $yearend = (int) \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, time())->timeend - 1;
            $day = $until !== null ? DateTimeImmutable::createFromFormat('!Y-m-d', $until, zones::of($library)) : false;
            \local_oksigeniaayvurenda\local\fragments::set(
                $itemid,
                $forcourse ?? ($only ? (int) $only->courseid : 0),
                $pages ?? ($only ? (string) $only->pages : ''),
                $day ? $day->setTime(23, 59, 59)->getTimestamp() : ($only ? (int) $only->timeend : $yearend),
                time()
            );
        }
        redirect(new moodle_url($PAGE->url, ['open' => $itemid, 'saved' => 1], 'ayv-dg-' . $itemid));
    } else if ($do === 'save') {
        digital::update(required_param('item', PARAM_INT), $library, $fields());
        digital::mark_subscribed(required_param('item', PARAM_INT), $library, optional_param('subscribed', 0, PARAM_BOOL));
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
// The ages a tale to read at home suits.
$agelabel = fn(int $age) => $str('talesage' . $age);
// Age 0 is an age (the youngest): only an empty list lights none.
$homeages = fn(?string $chosen) => array_map(fn($age) => ['value' => $age, 'label' => $agelabel($age),
    'checked' => in_array((string) $age, explode(',', (string) $chosen), true)], window::AGES);
// Why a tale is lovely, a line for each age, as whoever looked at it wrote it.
$homelines = fn(?string $json) => array_map(fn($age) => ['value' => $age, 'linelabel' => $str('digitalhomeline', $agelabel($age)),
    'line' => (string) ((json_decode((string) $json, true) ?: [])[(string) $age] ?? '')], window::AGES);
$courses = \local_oksigeniaayvurenda\local\fragments::courses($library, $recordid);
$yearend = (int) \local_oksigeniaayvurenda\local\years::around((int) $library->entityid, time())->timeend - 1;
$courselist = fn(int $chosen) => array_values(array_map(fn($id, $name) => ['id' => $id, 'name' => $name,
    'selected' => $id === $chosen], array_keys($courses), $courses));
$open = optional_param('open', 0, PARAM_INT);
$pageurl = fn(array $params = []) => new moodle_url('/local/oksigeniaayvurenda/digital.php', ['library' => $ref,
    'record' => $recordid] + $params);
$items = [];
$hasproxy = \local_oksigeniaayvurenda\local\links::settings($library)['proxy'] !== '';
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
        'until' => zones::date($only ? (int) $only->timeend : $yearend, '%Y-%m-%d', $library),
        'onlyline' => $only ? $str('fragmentline', (object) ['course' => $courses[$only->courseid]
            ?? format_string((string) $DB->get_field('course', 'fullname', ['id' => $only->courseid]), true, ['escape' => false]),
            'until' => zones::date((int) $only->timeend, 'strftimedateshort', $library)]) : '',
        'id' => (int) $item->id,
        'accessible' => !empty($item->accessformat),
        'canproxy' => $hasproxy && $item->kind === 'link',
        'subscribed' => !empty($item->subscribed),
        'proxied' => $hasproxy && $item->kind === 'link' && !empty($item->subscribed),
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
        'homeages' => $homeages($item->homeages),
        // Only what anyone sees goes to read at home: otherwise, the group says where to light it.
        'homepublic' => in_array('public', digital::audience($item), true),
        'homenotpublic' => $str('digitalhomenotpublic', $str(
            \local_oksigeniaayvurenda\local\skins::words((int) $library->entityid, 'audiencepublic')
        )),
        'homelines' => $homelines($item->homelines),
        'homeminutes' => $item->homeminutes ? (int) $item->homeminutes : '',
        'homeparts' => $item->homeparts ? (int) $item->homeparts : '',
        'homenight' => !empty($item->homenight),
        'homeorder' => $item->homeorder ? (int) $item->homeorder : '',
        // The card open across the page, its gestures beside it; «Done» closes it.
        'open' => (int) $item->id === $open,
        'changeurl' => $pageurl(['open' => $item->id])->out(false) . '#ayv-dg-' . $item->id,
        'releaseurl' => $pageurl()->out(false) . '#ayv-dg-' . $item->id,
        'justsaved' => (int) $item->id === $open && optional_param('saved', 0, PARAM_BOOL) ? $str('hiwsaved') : '',
        'nocourse' => !$only,
        'course' => $only ? (function () use ($only, $library, $str) {
            $end = (int) $only->timeend;
            $at = fn(int $t) => zones::date($t, '%Y-%m-%d', $library);
            return ['untilsheet' => ['month' => core_text::strtolower(zones::date($end, '%b', $library)),
                'day' => zones::date($end, '%e', $library), 'week' => zones::date($end, '%A', $library)],
                'untilless' => $at($end - 7 * DAYSECS), 'untilmore' => $at($end + 7 * DAYSECS)];
        })() : false,
        'home' => (string) $item->homeages !== '' ? [
            'homeminutes' => (int) $item->homeminutes,
            'minutesless' => max(0, (int) $item->homeminutes - 1), 'minutesmore' => (int) $item->homeminutes + 1,
            'minutesmin' => (int) $item->homeminutes <= 0,
            'homeparts' => (int) $item->homeparts,
            'partsless' => max(0, (int) $item->homeparts - 1), 'partsmore' => (int) $item->homeparts + 1,
            'partsmin' => (int) $item->homeparts <= 0,
            'homeorder' => $item->homeorder ? (int) $item->homeorder : false,
            'orderless' => max(0, (int) $item->homeorder - 1), 'ordermore' => (int) $item->homeorder + 1,
            'ordermin' => (int) $item->homeorder <= 0,
        ] : false,
        'homechip' => (string) $item->homeages !== '' ? $str('digitalhomechip', implode(', ', array_map(
            fn($age) => $agelabel((int) $age),
            explode(',', (string) $item->homeages)
        ))) : '',
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
    'newhomeages' => $homeages(null),
    'newhomelines' => $homelines(null),
    'hasstages' => (bool) $stages,
    'hasproxy' => $hasproxy,
    'hascourses' => (bool) $courses,
    'courses' => $courselist(0),
    'until' => zones::date($yearend, '%Y-%m-%d', $library),
    'savedword' => $str('profilesaved'),
    'lesslabel' => $str('hiwless'),
    'morelabel' => $str('hiwmore'),
    'weekless' => $str('digitalweekless'),
    'weekmore' => $str('digitalweekmore'),
    'recordurl' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
        ->out(false),
];
foreach (
    ['digitaladd', 'digitalfile', 'digitalurl', 'digitalurlhint', 'digitalsubscribed', 'digitalsubscribedline',
        'digitalaccessible', 'digitalaccessiblehint', 'digitalaccessiblechip',
        'digitaltitlefield', 'digitallicence',
        'digitallicencehint', 'digitalaudience', 'digitalstages', 'digitaldownload', 'digitalsave', 'digitaldelete',
        'digitalread', 'digitalnone', 'backtorecord', 'digitalchange', 'digitaladdhint', 'fragmentcourse', 'fragmentnone',
        'fragmentpages', 'fragmentpageshint', 'fragmentuntil', 'fragmenthint', 'digitalhome', 'digitalhomehint',
        'digitalhomeminutes', 'digitalhomeparts', 'digitalhomepartshint', 'digitalhomelinehint', 'digitalhomenight',
        'digitalhomeorder', 'digitalhomeorderhint', 'digitaldone', 'digitaladdgo', 'digitalurlplaceholder', 'digitalor',
        'digitaladdafter', 'digitalwhosees', 'digitalhomeages', 'digitalhomeorderauto', 'digitaldeletesure'] as $key
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
