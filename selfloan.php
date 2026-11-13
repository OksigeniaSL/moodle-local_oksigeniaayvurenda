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
use local_oksigeniaayvurenda\local\gestures;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\selfloan;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
if ($manage && optional_param('set', '', PARAM_ALPHA) === 'self' && data_submitted() && confirm_sesskey()) {
    // Who may use the posts and whether they take books back: each gesture only what it changes.
    $settings = selfloan::settings($library);
    if (($returns = gestures::number('returns')) !== null) {
        $settings['returns'] = (bool) $returns;
    }
    $stages = gestures::stage_touched($settings['stages'], holds::stages((int) $library->entityid, $now));
    if ($stages !== null) {
        $settings['stages'] = $stages;
    }
    selfloan::save_settings($library, $settings);
    redirect(new moodle_url($PAGE->url, ['saved' => 'ayv-nr-quien'], 'ayv-nr-quien'));
}
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
    // A post never used has no day to undo.
    'used' => !empty($p->timelast),
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
    'savedword' => $str('hiwsaved'),
    'settings' => $manage ? [gestures::card(
        'ayv-nr-quien',
        'self',
        $str('selfwhoheading'),
        ($settings['stages'] ? $str('selfwhostages', implode(', ', $settings['stages'])) : $str('selfwhoall')) . ' '
            . $str($settings['returns'] ? 'selfreturnson' : 'selfreturnsoff'),
        optional_param('saved', '', PARAM_ALPHANUMEXT) === 'ayv-nr-quien',
        array_merge(
            [gestures::group('', [], [gestures::lamp(
                'ayv-nr-quien-f',
                'returns',
                $settings['returns'],
                $str('selfreturnslight')
            )], [])],
            $stages ? [gestures::group($str('selfstages'), [], [], gestures::stages(
                'ayv-nr-quien-f',
                $settings['stages'],
                $stages,
                $str
            ))] : []
        )
    )] : [],
    'hasrows' => (bool) $rows,
    'rows' => $rows,
    'manyrows' => count($rows) >= 100,
];
foreach (
    ['selfpostssub', 'selfnewtile', 'selfremovepost', 'selfremoveyes', 'selfkindlabel', 'selfkindscreen', 'selfkindmachine',
        'selfnew', 'selfnewintro', 'selfnamelabel', 'selfnewname', 'selfcreate', 'selfpostsheading', 'selfremove',
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
