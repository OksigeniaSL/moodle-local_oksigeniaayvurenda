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

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\howto;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$query = trim(optional_param('q', '', PARAM_TEXT));
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/howto.php', ['library' => $ref] + ($query !== '' ? ['q' => $query] : [])));

if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
// In words, when the site has its own AI: first its policy, accepted once; then the answer, from the guide only.
$ai = $query !== '' && howto::ai_ready();
$answer = null;
$failed = false;
if ($ai && data_submitted() && confirm_sesskey()) {
    if (optional_param('policy', 0, PARAM_BOOL)) {
        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
    }
    if (optional_param('ask', 0, PARAM_BOOL) && \core_ai\manager::get_user_policy_status((int) $USER->id)) {
        $answer = howto::ask($query, $library, (int) $USER->id);
        $failed = $answer === null;
    }
}
$accepted = $ai && \core_ai\manager::get_user_policy_status((int) $USER->id);
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
    'ai' => $ai ? [
        'action' => $PAGE->url->out(false),
        'sesskey' => sesskey(),
        'accepted' => $accepted,
        'policy' => $accepted ? '' : get_string('userpolicy', 'ai'),
        'policyhint' => $str('howtoaipolicy'),
        'policyread' => $str('howtoaipolicyread'),
        'accept' => $str('howtoaiaccept'),
        'ask' => $str('howtoai'),
        'hint' => $str('howtoaihint'),
        'answer' => $answer !== null ? nl2br(s($answer)) : '',
        'answerlabel' => $str('howtoaianswer'),
        'failed' => $failed ? $str('howtoaifailed') : '',
    ] : null,
]), $library, '', ['search' => false]);
