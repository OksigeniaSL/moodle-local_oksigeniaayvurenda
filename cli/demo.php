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
 * Creates a made-up library to develop, test and show with.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'name' => 'Biblioteca de demostración', 'records' => 14500, 'readers' => 2000, 'seed' => 20261023],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help']) {
    echo "Creates a made-up entity with its central library, catalogue, copies, readers and loan rules.
Every name and title is invented. The same seed gives the same library.

Options:
  --name=TEXT     Name of the entity and its library (default: Biblioteca de demostración)
  --records=N     Titles in the catalogue; copies come to about a fifth more (default: 14500)
  --readers=N     Readers, most of them students in groups (default: 2000)
  --seed=N        Seed of the random choices (default: 20261023)
  -h, --help      This help

Example:
  php local/oksigeniaayvurenda/cli/demo.php --records=500 --readers=100
";
    exit(0);
}

$start = microtime(true);
$entityid = \local_oksigeniaayvurenda\local\demo::create(
    $options['name'],
    (int) $options['records'],
    (int) $options['readers'],
    (int) $options['seed']
);
$items = $DB->count_records('local_oksigeniaayvurenda_item', ['entityid' => $entityid]);
cli_writeln("Made-up entity {$entityid}: {$options['records']} titles, {$items} copies, {$options['readers']} readers, in " .
    round(microtime(true) - $start, 1) . ' s.');
