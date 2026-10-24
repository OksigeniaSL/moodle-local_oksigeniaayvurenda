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
 * The reservations of a library: copies set aside, the queues, and how readers reserve.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:desk');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/holds.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$now = time();
$notice = optional_param('notice', '', PARAM_ALPHA) === 'holdcancelled' ? $str('holdcancelled') : '';
if ($manage && data_submitted() && confirm_sesskey()) {
    holds::save_settings($entityid, [
        'online' => optional_param('online', 0, PARAM_BOOL),
        'stages' => optional_param_array('stages', [], PARAM_TEXT),
        'days' => optional_param('days', 5, PARAM_INT),
        'max' => optional_param('max', 3, PARAM_INT),
        'renew' => optional_param('renew', 0, PARAM_BOOL),
    ]);
    $notice = $str('holdssaved');
}
$lists = holds::lists($library, $now);
$record = fn(int $id) => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $id]))
    ->out(false);
$rows = function (array $list, string $when) use ($str, $record) {
    return array_map(fn($h) => $h + [
        'url' => $record($h['recordid']),
        'when' => $when === 'until' ? $str('holdsuntil', $h['until']) : $str('holdssince', $h['since']),
        'aheadlabel' => $when === 'since' && $h['ahead'] ? $str('holdsahead', $h['ahead']) : '',
    ], $list);
};
$settings = holds::settings($entityid);
$chosen = array_flip($settings['stages']);
$data = [
    'heading' => $str('holdstitle'),
    'intro' => $str('holdsintro'),
    'notice' => $notice,
    'action' => (new moodle_url('/local/oksigeniaayvurenda/hold.php'))->out(false),
    'self' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'library' => $library->slug,
    'readyheading' => $str('holdsready'),
    'ready' => $rows($lists['ready'], 'until'),
    'noready' => $str('holdsnoready'),
    'waitingheading' => $str('holdswaiting'),
    'waiting' => $rows($lists['waiting'], 'since'),
    'nowaiting' => $str('holdsnowaiting'),
    'cancel' => $str('holdcancel'),
    'manage' => $manage,
    'online' => $settings['online'],
    'renew' => $settings['renew'],
    'days' => $settings['days'],
    'max' => $settings['max'],
    'stages' => array_map(fn($s) => ['name' => $s, 'chosen' => isset($chosen[$s])], holds::stages($entityid, $now)),
];
$data['hasstages'] = (bool) $data['stages'];
foreach (
    ['holdssettings', 'holdssettingsintro', 'holdsonline', 'holdsstages', 'holdsnostages', 'holdsdays', 'holdsmax',
        'holdsrenew', 'holdssave'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('holdstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/holds', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => $url('desk.php'), 'label' => $str('desktitle')], ['url' => $url('index.php'),
        'label' => $str('deskcatalogue')]],
]);
