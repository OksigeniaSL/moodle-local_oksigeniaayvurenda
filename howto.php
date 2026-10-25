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
 * How do I…? The staff's tasks, searched by what someone would ask, each with its steps, its screen and its guided help.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\howto;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:view');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/howto.php', ['library' => $ref] + ($query !== '' ? ['q' => $query] : [])));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$found = howto::find($query, $library, (int) $USER->id);
$all = $query !== '' ? howto::find('', $library, (int) $USER->id) : [];
$card = fn(array $task) => ['title' => $task['title'], 'steps' => array_map(fn($s) => ['step' => $s], $task['steps']),
    'url' => $task['url'], 'help' => $task['help']];

shell::output($str('howtotitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/howto', [
    'heading' => $str('howtotitle'),
    'intro' => $str('howtointro'),
    'action' => (new moodle_url('/local/oksigeniaayvurenda/howto.php'))->out(false),
    'library' => $library->slug,
    'query' => $query,
    'label' => $str('howtolabel'),
    'placeholder' => $str('howtoplaceholder'),
    'search' => $str('howtosearch'),
    'searched' => $query !== '',
    'found' => array_map($card, $found),
    'hasfound' => (bool) $found,
    'none' => $str('howtonone'),
    'allheading' => $str('howtoall'),
    'all' => array_map(fn($t) => ['title' => $t['title'], 'url' => (new moodle_url(
        '/local/oksigeniaayvurenda/howto.php',
        ['library' => $library->slug, 'q' => $t['title']]
    ))->out(false)], $all),
    'hasall' => (bool) $all,
    'go' => $str('howtogo'),
    'helplabel' => $str('howtohelp'),
]), $library, '', ['search' => false]);
