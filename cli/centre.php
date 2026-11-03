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
 * Gets a centre ready from the console: its names, whether its readers may come in yet, its characters and the
 * cohorts its readers come from.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\local\centre_setup;

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'list' => false, 'entity' => 0, 'name' => null, 'library' => null, 'closed' => null, 'memory' => null,
        'herald' => null, 'herald-sex' => null, 'herald-pack' => null, 'cohorts' => null],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || (!$options['list'] && !$options['entity'])) {
    echo "Gets a centre ready from the console, as its screens would, and says how it is. What is not asked is left
as it is. A centre open only to its staff shows its readers no way in (site menu, courses, app, block) and sends them
nothing, until it opens.

Options:
  --list                The centres of the site
  --entity=ID           The centre
  --name=NAME           The centre's name
  --library=NAME        The name of its own library
  --closed=1|0          1: open only to its staff, while it gets ready; 0: open to its readers
  --memory=NAME         The name of the character who remembers
  --herald=NAME         The name of the herald
  --herald-sex=f|m      Whether the herald is a woman or a man
  --herald-pack=ZIP     The herald's own drawings (retrato, saluda, senala, celebra, lee, piensa, marioneta)
  --cohorts=LIST        The cohorts its readers come from, replacing the ones chosen: each by its id number or its
                        exact name, = the kind of reader (student, teacher, staff, family), and then, optional, :the
                        stage and :group when the cohort's name is the readers' class.
                        Example: \"INF=student:Infantil,PRI=student:Primaria,CLAUSTRO=teacher\"
  -h, --help            This help

Example:
  php local/oksigeniaayvurenda/cli/centre.php --entity=3 --closed=1 --memory=Memoria --herald=Mainumby --herald-sex=m
";
    exit(0);
}

if ($options['list']) {
    foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id, name') as $entity) {
        if (!\local_oksigeniaayvurenda\local\practice::is((int) $entity->id)) {
            cli_writeln($entity->id . "\t" . $entity->name . "\t" . (\local_oksigeniaayvurenda\local\access::closed(
                (int) $entity->id
            ) ? 'only its staff' : 'open'));
        }
    }
    exit(0);
}

$entityid = (int) $options['entity'];
if (!$DB->record_exists('local_oksigeniaayvurenda_entity', ['id' => $entityid])) {
    cli_error('There is no centre ' . $entityid . ' (--list says which there are).');
}
if ($options['herald-sex'] !== null && !in_array($options['herald-sex'], ['f', 'm'], true)) {
    cli_error('--herald-sex is f or m.');
}
if ($options['closed'] !== null && !in_array((string) $options['closed'], ['0', '1'], true)) {
    cli_error('--closed is 1 or 0.');
}
if ($options['herald-pack'] !== null && !is_readable($options['herald-pack'])) {
    cli_error('The zip cannot be read: ' . $options['herald-pack']);
}

$changes = array_filter([
    'name' => $options['name'],
    'library' => $options['library'],
    'closed' => $options['closed'] === null ? null : (bool) (int) $options['closed'],
    'memory' => $options['memory'],
    'herald' => $options['herald'],
    'heraldsex' => $options['herald-sex'],
    'heraldpack' => $options['herald-pack'],
    'cohorts' => $options['cohorts'],
], fn($value) => $value !== null);

try {
    foreach (centre_setup::apply($entityid, $changes) as $line) {
        cli_writeln('Changed: ' . $line);
    }
} catch (\moodle_exception $e) {
    cli_error($e->debuginfo ?: $e->getMessage());
}
foreach (centre_setup::describe($entityid) as $what => $value) {
    cli_writeln(str_pad($what . ':', 13) . (is_array($value) ? implode('; ', $value) : $value));
}
