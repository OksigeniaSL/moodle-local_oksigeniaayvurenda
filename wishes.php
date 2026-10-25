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
 * What classes ask for: the books they chose as the reward for a challenge met, until the library gets them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\wishes;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/wishes.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

if (data_submitted() && confirm_sesskey()) {
    $got = required_param('do', PARAM_ALPHA) === 'got';
    wishes::decide(required_param('wish', PARAM_INT), (int) $library->entityid, $got, (int) $USER->id);
    redirect(new moodle_url($PAGE->url, ['done' => $got ? 'got' : 'declined']));
}
$done = optional_param('done', '', PARAM_ALPHA);

$items = array_map(fn($w) => [
    'id' => (int) $w->id,
    'title' => format_string($w->title),
    'note' => (string) $w->note,
    'from' => $str('wishesfrom', (object) ['group' => $w->groupname,
        'when' => userdate((int) $w->timemodified, get_string('strftimedateshort', 'langconfig'))]),
    'search' => (new moodle_url('/local/oksigeniaayvurenda/search.php', ['library' => $library->slug, 'q' => $w->title]))
        ->out(false),
], wishes::asked((int) $library->entityid));

shell::output($str('wishestitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/wishes', [
    'heading' => $str('wishestitle'),
    'intro' => $str('wishesintro'),
    'notice' => in_array($done, ['got', 'declined'], true) ? $str('wishesdone' . $done) : '',
    'items' => $items,
    'hasitems' => (bool) $items,
    'empty' => $str('wishesempty'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'got' => $str('wishesgot'),
    'declined' => $str('wishesdeclined'),
    'searchlabel' => $str('wishessearch'),
]), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'wishes']
    ))->out(false), 'label' => $str('helpnav')]],
]);
