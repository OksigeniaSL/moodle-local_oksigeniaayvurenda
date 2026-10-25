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
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'add') {
        $url = trim(optional_param('url', '', PARAM_URL));
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            digital::add_file(
                $library,
                $recordid,
                $_FILES['file']['tmp_name'],
                (string) $_FILES['file']['name'],
                $fields(),
                (int) $USER->id
            );
            $notice = $str('digitaladded');
        } else if ($url !== '') {
            digital::add_link($library, $recordid, $url, $fields(), (int) $USER->id);
            $notice = $str('digitaladded');
        } else {
            $notice = $str('digitalnothing');
        }
    } else if ($do === 'save') {
        digital::update(required_param('item', PARAM_INT), $library, $fields());
        $notice = $str('digitalsaved');
    } else if ($do === 'delete') {
        digital::delete(required_param('item', PARAM_INT), $library);
        $notice = $str('digitaldeleted');
    }
}

$stages = holds::stages((int) $library->entityid, time());
$licences = fn(string $chosen) => array_map(fn($l) => ['value' => $l, 'label' => $str('licence' . str_replace('-', '', $l)),
    'selected' => $l === $chosen], digital_rules::LICENCES);
$audiences = fn(array $chosen) => array_map(fn($a) => ['value' => $a, 'label' => $str('audience' . $a),
    'checked' => in_array($a, $chosen, true)], digital_rules::AUDIENCES);
$stagelist = fn(array $chosen) => array_map(fn($s) => ['value' => $s, 'checked' => in_array($s, $chosen, true)], $stages);
$items = [];
foreach (digital::of_record($recordid, $library, ['who' => 'desk', 'stage' => null]) as $item) {
    $chosenstages = json_decode((string) $item->stages, true) ?: [];
    $items[] = [
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
$data = [
    'heading' => $str('digitaltitle', $found['record']->title),
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
    'recordurl' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
        ->out(false),
];
foreach (
    ['digitaladd', 'digitalfile', 'digitalurl', 'digitalurlhint', 'digitaltitlefield', 'digitallicence',
        'digitallicencehint', 'digitalaudience', 'digitalstages', 'digitaldownload', 'digitalsave', 'digitaldelete',
        'digitalread', 'digitalnone', 'backtorecord'] as $key
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
