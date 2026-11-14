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
 * The questions «What do you want to do?» could not answer, the most asked first, for each centre: only their words,
 * without who asked nor anybody's name, to tune the finder by its use.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['entity' => 0, 'limit' => 40, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln("The questions the finder could not answer, the most asked first.

Options:
  --entity=ID   Only this centre (all of them otherwise).
  --limit=N     At most N for each centre (40).
  -h, --help    This help.");
    exit(0);
}
$entities = $options['entity'] ? [(int) $options['entity']]
    : array_map('intval', $DB->get_fieldset_select('local_oksigeniaayvurenda_entity', 'id', '1 = 1 ORDER BY id'));
foreach ($entities as $entityid) {
    $misses = array_slice(\local_oksigeniaayvurenda\local\howto::misses($entityid), 0, (int) $options['limit']);
    if (!$misses) {
        continue;
    }
    cli_writeln('== ' . $entityid);
    foreach ($misses as $miss) {
        cli_writeln(str_pad((string) $miss['times'], 5, ' ', STR_PAD_LEFT) . '  ' . $miss['question']);
    }
}
