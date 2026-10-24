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
 * The public window of a library, without an account: what it has for everyone to read, hear or download. Whoever
 * manages the library opens it, closes it and says what it shows from the same page.
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

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the window is for everyone; managing it checks the capability.
require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $ref]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

$library = catalogue::library($ref);
$manage = $library && isloggedin() && !isguestuser() && has_capability('local/oksigeniaayvurenda:manage', $context);
$notice = '';
if ($manage && data_submitted() && confirm_sesskey()) {
    window::save($library, ['open' => optional_param('open', 0, PARAM_BOOL), 'text' => optional_param('text', '', PARAM_TEXT),
        'voices' => optional_param('voices', 0, PARAM_BOOL), 'index' => optional_param('index', 0, PARAM_BOOL)]);
    $notice = $str('windowsaved');
}
$settings = $library ? window::settings($library) : null;
if (!$library || (!$settings['open'] && !$manage)) {
    http_response_code(404);
    shell::output($str('windowclosed'), '<h1 class="ayv-results-title">' . s($str('windowclosed')) . '</h1><p>' .
        s($str('windowclosedhint')) . '</p>', null, '', ['search' => false]);
    die;
}
$here = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/public.php', ['library' => $library->slug] +
    $params))->out(false);
$found = window::records($library, $query, $page);
$records = [];
foreach ($found['records'] as $record) {
    $items = [];
    foreach ($record['items'] as $item) {
        $label = $item->kind === 'voice' ? $str('windowvoice') : $str('format' . $item->format);
        $items[] = [
            'label' => $label . ($item->title !== '' && $item->title !== null ? ' · ' . $item->title : ''),
            'licence' => $str('licence' . str_replace('-', '', $item->licence)),
            'action' => $str('digitalaction' . $item->format),
            'url' => (new moodle_url('/local/oksigeniaayvurenda/read.php', ['library' => $library->slug, 'id' => $item->id]))
                ->out(false),
            'download' => $item->kind === 'file' && digital_rules::can_download($item->licence, (bool) $item->download)
                ? digital::file_url($item, true) : '',
        ];
    }
    $records[] = [
        'title' => $record['title'],
        'subtitle' => $record['subtitle'],
        'authors' => implode(', ', $record['authors']),
        'cover' => $record['cover'],
        'hascover' => (bool) $record['cover'],
        'items' => $items,
    ];
}
$opds = (new moodle_url('/local/oksigeniaayvurenda/opds.php', ['library' => $library->slug]))->out(false);
$data = [
    'heading' => $str('windowheading'),
    'text' => $settings['text'],
    'notice' => $notice,
    'closednote' => !$settings['open'] ? $str('windowclosednote') : '',
    'query' => $query,
    'slug' => $library->slug,
    'action' => $here(),
    'records' => $records,
    'hasrecords' => (bool) $records,
    'empty' => $str($query !== '' ? 'windownothingfound' : 'windowempty'),
    'nexturl' => $found['more'] ? $here(['q' => $query, 'page' => $page + 1]) : '',
    'prevurl' => $page > 0 ? $here(['q' => $query, 'page' => $page - 1]) : '',
    'opds' => $opds,
    'loginurl' => (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]))->out(false),
    'manage' => $manage,
    'sesskey' => sesskey(),
    'open' => $settings['open'],
    'voices' => $settings['voices'],
    'index' => $settings['index'],
    'publicurl' => $here(),
];
foreach (
    ['windowsearch', 'windowsearchbutton', 'windowdownload', 'windowopds', 'windowopdshint', 'windowlogin', 'windownext',
        'windowprev', 'windowmanage', 'windowopen', 'windowopenhint', 'windowtext', 'windowtexthint', 'windowvoices',
        'windowindex', 'windowsave', 'windowaddress', 'voiceinvitecopy'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/public', $data), $library, '', [
    'search' => false,
    'index' => $settings['open'] && $settings['index'],
    'feed' => $settings['open'] ? $opds : '',
    'scripts' => $manage ? ['voice'] : [],
    'config' => ['strings' => ['copied' => $str('voiceinvitecopied')]],
]);
