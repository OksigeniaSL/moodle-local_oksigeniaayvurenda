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
 * Takes a centre away whole: when it leaves the site, or an example before opening. There is no way back but its copy.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\local\removal;

[$options] = cli_get_params(['entity' => 0, 'yes' => false, 'accounts' => false, 'help' => false], ['h' => 'help']);
$entity = $DB->get_record('local_oksigeniaayvurenda_entity', ['id' => (int) $options['entity']]);
if ($options['help'] || !$entity) {
    cli_writeln("Takes a centre away whole. Without --yes, it only says what would go.\n\n"
        . "--entity=ID  The centre.\n--yes        Do it.\n--accounts   Also the accounts that have nothing else on this site.");
    foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id, name') as $row) {
        cli_writeln($row->id . ': ' . $row->name);
    }
    exit($options['help'] ? 0 : 1);
}
cli_writeln($entity->name);
foreach (removal::count((int) $entity->id) as $table => $rows) {
    cli_writeln(sprintf('  %-16s %d', $table, $rows));
}
$lonely = removal::lonely_accounts((int) $entity->id);
$stays = $options['accounts'] ? '' : ' (they stay without --accounts)';
cli_writeln(sprintf('  %-16s %d', 'lonely accounts', count($lonely)) . $stays);
if (!$options['yes']) {
    cli_writeln('Nothing done: add --yes.');
    exit(0);
}
\core\session\manager::set_user(get_admin());
removal::remove((int) $entity->id);
if ($options['accounts']) {
    require_once($CFG->dirroot . '/user/lib.php');
    foreach ($lonely as $userid) {
        delete_user($DB->get_record('user', ['id' => $userid], '*', MUST_EXIST));
    }
    cli_writeln(count($lonely) . ' accounts deleted.');
}
cli_writeln('Gone.');
