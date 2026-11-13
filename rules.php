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
 * The loan rules of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\rulesheet;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$help = optional_param('help', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/rules.php', ['library' => $ref] + ($help ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
if (data_submitted() && confirm_sesskey()) {
    // Each gesture sends only what it changes; back to its card, which says it is kept.
    $anchor = rulesheet::apply($library);
    $back = new moodle_url($PAGE->url, ['saved' => $anchor]);
    $back->set_anchor($anchor);
    redirect($back);
}
$data = rulesheet::context($library, $PAGE->url->out(false), optional_param('saved', '', PARAM_ALPHANUMEXT), (bool) $help);
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('rulestitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/rules', $data), $library, '', [
    'search' => false,
    'nav' => $help ? [] : [
        ['url' => $url('readers.php'), 'label' => $str('readers')],
        ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'rules']))
            ->out(false), 'label' => $str('helpnav')],
    ],
]);
