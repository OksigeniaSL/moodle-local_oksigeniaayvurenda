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
 * «No more mail, please», from the sealed link at the foot of a library's mail to an adult without an account. Opening
 * it changes nothing (a mail program may open links to look at them): a button does, and the same page takes it back.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\mail;
use local_oksigeniaayvurenda\local\mailstop;
use local_oksigeniaayvurenda\local\shell;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- the sealed link is the key, for adults without an account.
require(__DIR__ . '/../../config.php');

$entityid = optional_param('e', 0, PARAM_INT);
$hash = optional_param('h', '', PARAM_ALPHANUM);
$seal = optional_param('s', '', PARAM_ALPHANUM);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/stop.php', ['e' => $entityid, 'h' => $hash, 's' => $seal]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$library = $entityid ? mail::main_library($entityid) : null;
if (!$library || strlen($hash) !== 64 || !hash_equals(mailstop::seal($entityid, $hash), $seal)) {
    shell::missing($str('stopgone'));
    die;
}
$name = format_string($library->name, true, ['escape' => false]);
$done = '';
if (data_submitted() && confirm_sesskey()) {
    $stop = optional_param('stop', 0, PARAM_BOOL);
    mailstop::set($entityid, $hash, (bool) $stop);
    $done = $stop ? 'stopped' : 'again';
}
$stopped = mailstop::listed($entityid, $hash);
$data = [
    'heading' => $stopped ? $str($done === 'stopped' ? 'stopdone' : 'stopalready') : $str($done === 'again' ? 'stopagaindone'
        : 'stoptitle'),
    'lead' => $stopped ? $str('stopdonelead', $name) : $str($done === 'again' ? 'stopagainlead' : 'stoplead', $name),
    'action' => (new moodle_url('/local/oksigeniaayvurenda/stop.php'))->out(false),
    'entity' => $entityid,
    'hash' => $hash,
    'seal' => $seal,
    'sesskey' => sesskey(),
    'stopping' => !$stopped,
    'button' => $str($stopped ? 'stopagain' : 'stopbutton'),
    'hint' => $str($stopped ? 'stopagainhint' : 'stophint'),
];
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/mailstop', $data),
    $library,
    '',
    ['search' => false]
);
