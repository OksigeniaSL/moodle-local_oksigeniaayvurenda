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
 * LTI deep linking: the teacher chooses what to put in their course, a book of the library or the library itself, and
 * goes back to the platform with it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\ltitool;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../../config.php');

$launchid = required_param('launch', PARAM_RAW_TRIMMED);
$query = optional_param('q', '', PARAM_TEXT);
require_login(null, false);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/lti/pick.php', ['launch' => $launchid]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

$launch = ltitool::kept($launchid, (int) $USER->id);
if (!$launch || !$launch->isDeepLinkLaunch()) {
    throw new moodle_exception('ltipickgone', 'local_oksigeniaayvurenda');
}
$data = $launch->getLaunchData();
$entityid = (int) ltitool::platform_of($data)->entityid;
$library = ltitool::library_of($entityid, $data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? []);

if (data_submitted() && confirm_sesskey()) {
    $recordid = optional_param('record', 0, PARAM_INT);
    $record = $recordid ? $DB->get_record(
        'local_oksigeniaayvurenda_record',
        ['id' => $recordid, 'entityid' => $entityid],
        '*',
        MUST_EXIST
    ) : null;
    echo ltitool::link_back($launch, ltitool::link_to($library, $record));
    die();
}
$found = $query !== '' ? catalogue::search($library, $query) : ['total' => 0, 'cards' => []];
$cards = array_map(
    fn($card) => ['recordid' => $card['id'], 'title' => $card['title'], 'authors' => $card['authors']],
    array_slice($found['cards'], 0, 20)
);
$action = $PAGE->url->out(false);
$template = [
    'heading' => $str('ltipickheading'),
    'intro' => $str('ltipickintro'),
    'action' => $action,
    'launch' => $launchid,
    'sesskey' => sesskey(),
    'libraryname' => format_string($library->name),
    'wholelabel' => $str('ltipickwhole'),
    'wholehint' => $str('ltipickwholehint'),
    'searchlabel' => $str('ltipicksearch'),
    'searchbutton' => $str('windowsearchbutton'),
    'query' => $query,
    'cards' => $cards,
    'hascards' => (bool) $cards,
    'nothing' => $query !== '' && !$cards ? $str('ltipicknothing', s($query)) : '',
    'putlabel' => $str('ltipickput'),
];
shell::output(
    $template['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/lti_pick', $template),
    $library,
    '',
    ['search' => false]
);
