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
 * Signs a centre up for the hosted service, or completes what is missing of it: run again, it makes nothing twice.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\local\signup;

[$options] = cli_get_params([
    'name' => '', 'library' => '', 'plan' => '', 'email' => '', 'firstname' => '', 'lastname' => '', 'lang' => '',
    'role' => '', 'accepted' => '', 'terms' => '', 'privacy' => '', 'dpa' => '', 'yes' => false, 'help' => false,
], ['h' => 'help']);
if ($options['help'] || $options['name'] === '' || $options['email'] === '' || $options['plan'] === '') {
    cli_writeln("Signs a centre up: the centre, its first library and its category, its plan, an account for whoever runs it\n"
        . "with the manager's role there, an e-mail with how to get in, and when it accepted the terms. Without --yes, it\n"
        . "only says what it would do. Run again, it changes nothing that is already there (it does set the plan).\n\n"
        . "--name=\"…\"        The centre.\n"
        . "--library=\"…\"     Its first library (the centre's name if not given).\n"
        . "--plan=free|small|medium\n"
        . "--email=…         Whoever runs it.\n"
        . "--firstname=… --lastname=… --lang=es|en   For a new account.\n"
        . "--role=shortname  The role to give (ayvurendamanager if not given).\n"
        . "--accepted=YYYY-MM-DD  When it accepted the terms, the privacy policy and the data processing agreement\n"
        . "                  (now if not given).\n"
        . "--terms=URL --privacy=URL --dpa=URL  Where they are (ayvurenda.com's, in the language of --lang, if not given).\n"
        . "--yes             Do it.");
    exit($options['help'] ? 0 : 1);
}
$look = signup::look($options);
cli_writeln('Centre: ' . ($look['centre'] ? 'already here (' . $look['centre'] . ')' : 'new'));
cli_writeln('Account: ' . ($look['account'] ? 'already here (' . $look['account'] . ')' : 'new, mailed its password'));
if (!$options['yes']) {
    cli_writeln('Nothing done: add --yes.');
    exit(0);
}
\core\session\manager::set_user(get_admin());
try {
    $done = signup::centre($options);
} catch (invalid_parameter_exception $e) {
    cli_error($e->debuginfo ?: $e->getMessage());
}
cli_writeln('Centre ' . $done['entity'] . ($done['created']['centre'] ? ' created' : ' kept') . ', category ' . $done['category']
    . ', plan ' . $options['plan'] . '.');
cli_writeln('Account ' . $done['userid'] . ($done['created']['account'] ? ' created' : ' kept')
    . ($done['mailed'] ? ', its password sent by e-mail' : '') . '; role '
    . ($done['created']['role'] ? 'given' : 'already there') . '.');
cli_writeln('Library: ' . (new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $done['library']]))->out(false));
cli_writeln('Way in: ' . get_login_url());
