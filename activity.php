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
 * Who did what in a centre: joining readers, taking a copy, changing a rule. Loans and searches are not told.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\activity;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$kind = optional_param('kind', '', PARAM_ALPHA);
$kind = isset(activity::KINDS[$kind]) ? $kind : '';
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/activity.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
$found = activity::events($entityid, $kind, $from);
$url = fn(array $params) => (new moodle_url($PAGE->url, $params))->out(false);
$tabs = [['label' => $str('activityall'), 'url' => $url([]), 'current' => $kind === '']];
foreach (array_keys(activity::KINDS) as $key) {
    $tabs[] = ['label' => $str('activity' . $key), 'url' => $url(['kind' => $key]), 'current' => $kind === $key];
}
$params = $kind !== '' ? ['kind' => $kind] : [];
$data = [
    'heading' => $str('activityheading'),
    'intro' => $str('activityintro'),
    'tabs' => $tabs,
    'days' => activity::days($found['events'], $entityid),
    'empty' => !$found['events'] && !$from ? $str(activity::available() ? 'activityempty' : 'activitynolog') : '',
    'pager' => $from || $found['more'] ? [
        'prev' => $from ? $url($params + ['from' => max(0, $from - activity::PAGE)]) : '',
        'next' => $found['more'] ? $url($params + ['from' => $from + activity::PAGE]) : '',
        'line' => '',
        'prevlabel' => $str('activityprev'),
        'nextlabel' => $str('activitynext'),
    ] : null,
];
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/activity', $data),
    $library,
    '',
    ['search' => false]
);
