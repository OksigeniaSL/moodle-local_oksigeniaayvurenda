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
 * Gives every centre its own course category, where the roles of its staff are given (a site shared by several
 * centres). The practice centre of the guided help gets none.
 *
 * php local/oksigeniaayvurenda/cli/categories.php
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(['help' => false], ['h' => 'help']);
if ($options['help'] || $unrecognized) {
    cli_writeln("Gives every centre its own course category, where the roles of its staff are given.\n\n" .
        "php local/oksigeniaayvurenda/cli/categories.php");
    exit($unrecognized ? 1 : 0);
}
foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id, name') as $entity) {
    if (\local_oksigeniaayvurenda\local\practice::is((int) $entity->id)) {
        continue;
    }
    $categoryid = \local_oksigeniaayvurenda\local\access::category((int) $entity->id);
    cli_writeln($entity->id . "\t" . $categoryid . "\t" . $entity->name);
}
