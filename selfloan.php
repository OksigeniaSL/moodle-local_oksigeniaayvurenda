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
 * The self-loan posts of a library: making them, who may use them, and going over what was done at them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\selfloan;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$inhelp = optional_param('help', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/selfloan.php', ['library' => $ref] + ($inhelp ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = skins::speaker($library);
if (!selfloan::available($library)) {
    shell::refuse($str('selfnobank'), $library, $str('selftitle'));
}
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$now = time();
$notice = '';
$error = '';
$address = '';
$machine = false;
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'undo') {
        $done = selfloan::undo(
            $library,
            required_param('loan', PARAM_INT),
            required_param('what', PARAM_ALPHA),
            (int) $USER->id
        );
        if ($done) {
            $notice = $str('selfundone');
        } else {
            $error = $str('selfnotundone');
        }
    } else if ($manage && $do === 'newpost') {
        $name = trim(optional_param('name', '', PARAM_TEXT));
        $machine = (bool) optional_param('machine', 0, PARAM_BOOL);
        $address = selfloan::create($library, $name !== '' ? $name : $str('selfnewname'), (int) $USER->id, $now, $machine);
    } else if ($manage && $do === 'removepost') {
        selfloan::remove($library, required_param('post', PARAM_INT));
        $notice = $str('selfremoved');
    } else if ($manage && $do === 'reopen') {
        selfloan::reopen($library, required_param('post', PARAM_INT));
        $notice = $str('selfreopened');
    } else if ($manage && $do === 'undoday') {
        $count = selfloan::undo_since($library, required_param('post', PARAM_INT), $now - DAYSECS, (int) $USER->id);
        $notice = $str('selfundoneday', $count);
    } else if ($manage && $do === 'settings') {
        selfloan::save_settings($library, [
            'stages' => optional_param('allstages', 0, PARAM_BOOL) ? [] : optional_param_array('stages', [], PARAM_TEXT),
            'returns' => optional_param('returns', 0, PARAM_BOOL),
        ]);
        $notice = $str('selfsaved');
    }
}
$settings = selfloan::settings($library);
$stages = holds::stages((int) $library->entityid, $now);
$posts = array_map(fn($p) => [
    'id' => (int) $p->id,
    'name' => $p->name,
    'last' => $p->timelast ? $str('selflast', userdate((int) $p->timelast, get_string('strftimedatetimeshort', 'langconfig')))
        : $str('selfnever'),
    'stopped' => !empty($p->timestopped),
    'machine' => !empty($p->machine),
], selfloan::posts($library));
$rows = array_map(fn($r) => $r + [
    'when' => userdate($r['at'], get_string('strftimedatetimeshort', 'langconfig')),
    'verb' => $str($r['what'] === 'lent' ? 'selfrowlent' : 'selfrowreturned'),
], selfloan::recent($library, $now));
$data = [
    'heading' => $str('selftitle'),
    'intro' => $str('selfintro'),
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'manage' => $manage,
    'address' => $address,
    'ismachine' => $machine,
    'qr' => $address !== '' ? 'data:image/png;base64,' . base64_encode((new core_qrcode($address))->getBarcodePngData(4, 4)) : '',
    'hasposts' => (bool) $posts,
    'posts' => $posts,
    'who' => $settings['stages'] ? $str('selfwhostages', implode(', ', $settings['stages'])) : $str('selfwhoall'),
    'returnsword' => $str($settings['returns'] ? 'selfreturnson' : 'selfreturnsoff'),
    'returnson' => $settings['returns'],
    'allstages' => !$settings['stages'],
    'hasstages' => (bool) $stages,
    'stages' => array_map(fn($s) => ['name' => $s, 'chosen' => in_array($s, $settings['stages'], true)], $stages),
    'hasrows' => (bool) $rows,
    'rows' => $rows,
    'manyrows' => count($rows) >= 100,
];
foreach (
    ['selfnew', 'selfnewintro', 'selfnamelabel', 'selfnewname', 'selfcreate', 'selfpostsheading', 'selfremove',
        'selfcreatedheading', 'selfcreatedhint', 'selfaddress', 'selfcopy', 'selfmachinefield', 'selfmachinehint',
        'selfmachinetag', 'selfsiplink',
        'selfwhoheading', 'selfchange', 'selfstages',
        'selfallstages',
        'selfreturnslabel', 'selfsave', 'selfrecentheading', 'selfrecentintro', 'selfnothing', 'selfundo', 'selfclose',
        'selfstoppedhint', 'selfreopen', 'selfundoday', 'selfundodayintro', 'selfmanyrows'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/selfloan', $data), $library, '', [
    'search' => false,
    // Its script holds what copies an address, as for the platforms.
    'scripts' => ['voice'],
    'config' => ['strings' => ['copied' => $str('selfcopied')]],
    'nav' => $inhelp ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'selfloan']
    ))->out(false), 'label' => $str('helpnav')]],
]);
