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
    ['help' => false, 'name' => 'Biblioteca de demostración', 'records' => 14500, 'readers' => 2000, 'seed' => 20261023,
        'accounts' => 0, 'entity' => 0, 'loans' => 0, 'library' => '', 'window' => 0, 'covers' => false],
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
  --accounts=N    Instead, add N made-up Moodle accounts of students (and a tenth more of teachers) in cohorts, one
                  per group, to the entity given with --entity, and bring them as readers
  --entity=ID     The entity for --accounts
  --loans=N       Instead, add N made-up loans of the last weeks (most returned, some out, some late) to the library
                  given with --library, and a tutor for each group among the teachers with an account
  --window=N      Instead, put N made-up works for everyone (an EPUB written on the spot) on titles of the library given
                  with --library, and open its public window
  --covers        Instead, give the made-up books of the library given with --library the made-up covers of the art
                  package (pix/portadas/)
  --library=SLUG  The library for --loans, --window or --covers
  -h, --help      This help

Example:
  php local/oksigeniaayvurenda/cli/demo.php --records=500 --readers=100
";
    exit(0);
}

$start = microtime(true);
if ((int) $options['accounts'] > 0) {
    if (!$DB->record_exists('local_oksigeniaayvurenda_entity', ['id' => (int) $options['entity']])) {
        cli_error('Say which entity the accounts are for (--entity).');
    }
    $stats = \local_oksigeniaayvurenda\local\demo::accounts((int) $options['entity'], (int) $options['accounts']);
    cli_writeln('Made-up accounts in cohorts, brought as readers: ' . json_encode($stats) . ', in ' .
        round(microtime(true) - $start, 1) . ' s.');
    exit(0);
}
if ($options['covers']) {
    $libraryid = $DB->get_field('local_oksigeniaayvurenda_library', 'id', ['slug' => $options['library']]);
    if (!$libraryid) {
        cli_error('Say which library the covers are for (--library).');
    }
    $given = \local_oksigeniaayvurenda\local\demo::covers((int) $libraryid);
    if ($given < 0) {
        cli_error('This library has books brought from a catalogue: made-up covers are only for made-up books.');
    }
    cli_writeln('Made-up covers given: ' . $given . ', in ' . round(microtime(true) - $start, 1) . ' s.');
    exit(0);
}
if ((int) $options['window'] > 0) {
    $libraryid = $DB->get_field('local_oksigeniaayvurenda_library', 'id', ['slug' => $options['library']]);
    if (!$libraryid) {
        cli_error('Say which library the window is for (--library).');
    }
    $stats = \local_oksigeniaayvurenda\local\demo::window((int) $libraryid, (int) $options['window']);
    cli_writeln('Made-up works in the public window: ' . json_encode($stats) . ', in ' . round(microtime(true) - $start, 1) .
        ' s.');
    exit(0);
}
if ((int) $options['loans'] > 0) {
    $libraryid = $DB->get_field('local_oksigeniaayvurenda_library', 'id', ['slug' => $options['library']]);
    if (!$libraryid) {
        cli_error('Say which library the loans are for (--library).');
    }
    $stats = \local_oksigeniaayvurenda\local\demo::activity((int) $libraryid, (int) $options['loans']);
    cli_writeln('Made-up loans and tutors: ' . json_encode($stats) . ', in ' . round(microtime(true) - $start, 1) . ' s.');
    exit(0);
}
$entityid = \local_oksigeniaayvurenda\local\demo::create(
    $options['name'],
    (int) $options['records'],
    (int) $options['readers'],
    (int) $options['seed']
);
$items = $DB->count_records('local_oksigeniaayvurenda_item', ['entityid' => $entityid]);
cli_writeln("Made-up entity {$entityid}: {$options['records']} titles, {$items} copies, {$options['readers']} readers, in " .
    round(microtime(true) - $start, 1) . ' s.');
