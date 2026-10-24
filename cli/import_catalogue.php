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
 * Imports a catalogue from MARC or CSV into a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\core\import\catalogue_csv;
use local_oksigeniaayvurenda\local\import\catalogue_import;
use local_oksigeniaayvurenda\local\import\files;

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'file' => '', 'library' => '', 'dry-run' => false],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || $options['file'] === '' || $options['library'] === '') {
    echo "Imports a catalogue into a library from any library program's MARC21 file (ISO 2709 or MARCXML, with the copies
in fields 852 or 952) or from a spreadsheet saved as CSV (its columns are recognised by their headings). Records are
found again by where they came from or by their ISBN, copies by their code: it can be run again without repeating.
For an Abies Web export, use import_abies.php.

Options:
  --file=PATH       The file
  --library=SLUG    The library to import into (its address or its id)
  -n, --dry-run     Only say what the file brings
  -h, --help        This help

Example:
  php local/oksigeniaayvurenda/cli/import_catalogue.php --file=/tmp/koha.mrc --library=central -n
";
    exit(0);
}

$path = $options['file'];
if (!is_readable($path)) {
    cli_error('The file cannot be read: ' . $path);
}
$library = \local_oksigeniaayvurenda\local\catalogue::library((string) $options['library']);
if (!$library) {
    cli_error('There is no library ' . $options['library']);
}
$kind = files::kind($path, basename($path));
if (!in_array($kind, ['marc', 'marcxml', 'csv'], true)) {
    cli_error($kind === 'abies' ? 'This is an Abies Web export: use import_abies.php.' : 'This is neither MARC nor CSV.');
}
raise_memory_limit(MEMORY_HUGE);
if ($kind === 'csv') {
    $csv = files::csv($path);
    $map = catalogue_csv::guess($csv['columns']);
    cli_writeln('Columns: ' . json_encode(array_combine(
        array_map(fn($i) => $csv['columns'][$i], array_keys($map)),
        array_values($map)
    ), JSON_UNESCAPED_UNICODE));
    $records = catalogue_csv::records($csv['rows'], $map);
} else {
    $records = iterator_to_array(files::marc($path, $kind), false);
}
cli_writeln('Records: ' . count($records) . '; copies: ' . array_sum(array_map(fn($r) => count($r['items']), $records)));
if ($options['dry-run']) {
    exit(0);
}
$start = microtime(true);
$stats = (new catalogue_import($library, $kind === 'csv' ? 'csv' : 'marc'))->run($records);
cli_writeln('Imported: ' . json_encode($stats) . ', in ' . round(microtime(true) - $start, 1) . ' s.');
