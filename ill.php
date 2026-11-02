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
 * Lending between libraries of different centres.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\ill;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$inhelp = optional_param('help', 0, PARAM_BOOL);
$tab = optional_param('tab', 'in', PARAM_ALPHA) === 'out' ? 'out' : 'in';
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url(
    '/local/oksigeniaayvurenda/ill.php',
    ['library' => $ref, 'tab' => $tab] + ($inhelp ? ['help' => 1] : [])
));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = skins::speaker($library);
$now = time();
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'ask') {
        $made = ill::ask($library, [
            'title' => optional_param('title', '', PARAM_TEXT),
            'author' => optional_param('author', '', PARAM_TEXT),
            'isbn' => optional_param('isbn', '', PARAM_TEXT),
            'note' => optional_param('note', '', PARAM_TEXT),
            'partner' => optional_param('partner', '', PARAM_TEXT),
            'card' => optional_param('card', '', PARAM_TEXT),
        ], (int) $USER->id, $now);
        if (is_int($made)) {
            $notice = $str('illasked');
        } else {
            $error = $str('ill' . $made);
        }
    } else if ($do === 'lend') {
        $made = ill::lend(
            $library,
            optional_param('code', '', PARAM_TEXT),
            optional_param('partner', '', PARAM_TEXT),
            ill::date(optional_param('due', '', PARAM_TEXT)),
            (int) $USER->id,
            $now
        );
        if (is_int($made)) {
            $notice = $str('illsent');
        } else {
            $error = $str('ill' . $made);
        }
    } else if ($do === 'move') {
        $state = required_param('state', PARAM_ALPHA);
        $due = ill::date(optional_param('due', '', PARAM_TEXT));
        $done = ill::move($library, required_param('id', PARAM_INT), $state, $due, $now);
        if ($done) {
            $notice = $str('illmoved' . $state);
        }
    }
}
$date = fn(?int $time) => $time ? userdate($time, get_string('strftimedateshort', 'langconfig')) : '';
$next = ['asked' => 'arrived', 'arrived' => 'lent', 'lent' => 'back', 'back' => 'done'];
$lists = ill::rows($library, $tab, $now);
$card = function (array $row) use ($str, $date, $next) {
    $state = (string) $row['status'];
    $step = $next[$state] ?? '';
    // A book for nobody in particular goes home from here.
    if ($state === 'arrived' && empty($row['who'])) {
        $step = 'done';
    }
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'partner' => $row['partner'],
        'who' => $row['who'] !== '' ? $str('illfor', $row['who']) : '',
        'state' => $str('illstate' . $state),
        'until' => $row['timedue'] ? $str('illuntil', $date((int) $row['timedue'])) : '',
        'late' => (bool) $row['late'],
        'needsdate' => $step === 'arrived',
        'quick' => $step !== '' && $step !== 'arrived',
        'step' => $step,
        'steplabel' => $step !== '' ? $str('illgo' . $step) : '',
        'cancancel' => $state === 'asked',
    ];
};
$url = fn(string $to) => (new moodle_url('/local/oksigeniaayvurenda/ill.php', ['library' => $ref, 'tab' => $to]
    + ($inhelp ? ['help' => 1] : [])))->out(false);
$open = array_map($card, $lists['open']);
$closed = array_map($card, $lists['closed']);
$data = ['heading' => $str('illtitle'), 'intro' => $str('illintro'), 'notice' => $notice, 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'isin' => $tab === 'in', 'isout' => $tab === 'out',
    'tabs' => [['url' => $url('in'), 'label' => $str('illtabin'), 'current' => $tab === 'in'],
        ['url' => $url('out'), 'label' => $str('illtabout'), 'current' => $tab === 'out']],
    'open' => $open, 'hasopen' => (bool) $open, 'closed' => $closed, 'hasclosed' => (bool) $closed,
    'partners' => array_map(fn($p) => ['name' => $p], ill::partners($library))];
foreach (
    ['illask', 'illaskintro', 'illlend', 'illlendintro', 'illfieldtitle', 'illfieldauthor', 'illfieldisbn', 'illfieldnote',
        'illfieldpartner', 'illfieldcard', 'illfieldcode', 'illfielddue', 'illfielddueask', 'illnothingin', 'illnothingout',
        'illclosed', 'illcancel', 'illclose', 'illoutnote', 'illlate', 'illgoarrived', 'illarrivedintro'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/ill', $data), $library, '', [
    'search' => false,
    'nav' => $inhelp ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'ill']
    ))->out(false), 'label' => $str('helpnav')]],
]);
