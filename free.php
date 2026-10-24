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
 * Free works: search Project Gutenberg, look at a book and what it says of its rights, and bring it into the catalogue
 * with its EPUB in the public domain.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\digital_rules;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\freeworks;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$bookid = optional_param('book', 0, PARAM_INT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:catalogue');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/free.php', ['library' => $ref]));
$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$here = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/free.php', ['library' => $library->slug] +
    $params))->out(false);
$recordurl = fn(int $id) => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug,
    'id' => $id]))->out(false);

if (data_submitted() && confirm_sesskey() && $bookid && optional_param('confirm', 0, PARAM_BOOL)) {
    $recordid = freeworks::bring($library, $bookid, optional_param_array('audience', [], PARAM_ALPHA), (int) $USER->id);
    redirect($recordurl($recordid));
}
$notice = '';
$results = [];
$book = null;
if ($bookid) {
    $found = freeworks::book($bookid);
    if (!$found) {
        $notice = $str('freeworksnoanswer');
    } else {
        $brought = freeworks::brought((int) $library->entityid, $bookid);
        $book = [
            'id' => $bookid,
            'title' => $found['title'],
            'authors' => implode(' · ', $found['authors']),
            'langs' => implode(', ', array_map(fn($l) => get_string_manager()->string_exists($l, 'iso6392')
                ? get_string($l, 'iso6392') : $l, $found['langs'])),
            'rights' => $str('freeworksrights', $found['rights'] !== '' ? $found['rights'] : '—'),
            'brought' => $brought ? $recordurl($brought) : '',
            'audiences' => array_map(fn($a) => ['value' => $a, 'label' => $str('audience' . $a),
                'checked' => in_array($a, ['students', 'families'], true)], digital_rules::AUDIENCES),
        ];
    }
} else if ($query !== '') {
    $found = freeworks::search($query);
    if ($found === null) {
        $notice = $str('freeworksnoanswer');
    } else {
        foreach ($found as $item) {
            $results[] = $item + ['url' => $here(['book' => $item['id'], 'q' => $query])];
        }
        $notice = $found ? '' : $str('freeworksnone');
    }
}
$data = [
    'heading' => $str('freeworks'),
    'intro' => $str('freeworksintro'),
    'action' => $here(),
    'slug' => $library->slug,
    'query' => $query,
    'notice' => $notice,
    'results' => $results,
    'hasresults' => (bool) $results,
    'book' => $book,
    'sesskey' => sesskey(),
    'backurl' => $here(['q' => $query]),
];
foreach (
    ['freeworkssearch', 'freeworkssearchbutton', 'freeworkssee', 'freeworksconfirm', 'freeworksconfirmhint',
        'freeworksfor', 'freeworksbring', 'freeworksalready', 'freeworksopen', 'freeworksback', 'freeworkslangs'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('freeworks'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/free', $data),
    $library,
    '',
    ['search' => false]
);
