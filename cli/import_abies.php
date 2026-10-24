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
 * Imports the catalogue of an Abies Web export into a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\core\abies\survey;
use local_oksigeniaayvurenda\local\import\abies_import;

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'file' => '', 'library' => 0, 'create' => '', 'dry-run' => false, 'without-notes' => false,
        'with-readers' => false],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || $options['file'] === '') {
    echo "Imports the catalogue of an Abies Web export into a library: records, authors, classification, subjects,
publishers, places, copies with their own codes, types and loan rules. Run it again with a newer export to bring the
library up to date: nothing is duplicated. With --with-readers, also the readers and their loans; they come
without an account, to be matched with the site's accounts in «Readers».

Options:
  --file=PATH       The export: the zip Abies Web gives, or the XML inside it
  --library=ID      The library to import into
  --create=NAME     Or create a new entity with a central library of that name
  -n, --dry-run     Only report what the export brings, without importing
  --without-notes   Leave out the free notes of the copies
  --with-readers    Bring the readers and their loans too
  -h, --help        This help

Example:
  php local/oksigeniaayvurenda/cli/import_abies.php --file=/tmp/exportacionAbiesWeb.zip --dry-run
";
    exit(0);
}

$file = $options['file'];
if (!is_readable($file)) {
    cli_error('The file cannot be read: ' . $file);
}
if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'zip') {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        cli_error('The zip cannot be opened: ' . $file);
    }
    $dir = make_request_directory();
    $xml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xml') {
            $xml = $dir . '/export.xml';
            file_put_contents($xml, $zip->getFromIndex($i));
            break;
        }
    }
    $zip->close();
    if ($xml === null) {
        cli_error('There is no XML file in the zip.');
    }
    $file = $xml;
}

$export = new export_reader($file);
$report = survey::run($export);
cli_heading('What the export brings');
cli_writeln('Records: ' . json_encode($report['records']));
cli_writeln("Copies: {$report['items']}; readers: {$report['readers']}; loans: {$report['loans']} " .
    "({$report['openloans']} out)");
$issues = $report['issues'];
cli_writeln('Records with a valid ISBN: ' . $issues['recordswithisbn']);
cli_writeln('Repeated copy codes: ' . count($issues['duplicatebarcodes']) .
    ($issues['duplicatebarcodes'] ? ' (' . implode(', ', array_keys($issues['duplicatebarcodes'])) . ')' : ''));
cli_writeln('Codes with a wrong control letter: ' . count($issues['badletters']));
cli_writeln('Copies of records that do not exist: ' . count($issues['orphanitems']));
cli_writeln('Records without copies: ' . $issues['recordswithoutitems']);
cli_writeln('Author links to records that do not exist: ' . $issues['orphanauthorlinks']);
cli_writeln('Copies without a place: ' . $issues['itemswithoutlocation']);
cli_writeln('Languages that cannot be told: ' . json_encode($issues['unknownlanguages'], JSON_UNESCAPED_UNICODE));
cli_writeln('Unknown copy statuses: ' . json_encode($issues['unknownstatuses']));

if ($options['dry-run']) {
    exit(0);
}

$libraryid = (int) $options['library'];
if ($libraryid === 0 && $options['create'] !== '') {
    $now = time();
    $entityid = $DB->insert_record('local_oksigeniaayvurenda_entity', ['name' => $options['create'], 'yearstart' => '09-01',
        'timecreated' => $now, 'timemodified' => $now]);
    $libraryid = $DB->insert_record('local_oksigeniaayvurenda_library', ['entityid' => $entityid,
        'name' => $options['create'], 'slug' => 'library-' . $entityid, 'kind' => 'central',
        'timecreated' => $now, 'timemodified' => $now]);
}
if ($libraryid === 0 || !$DB->record_exists('local_oksigeniaayvurenda_library', ['id' => $libraryid])) {
    cli_error('Say which library to import into (--library) or create one (--create).');
}

$start = microtime(true);
$stats = (new abies_import($libraryid, !$options['without-notes'], (bool) $options['with-readers']))->run($export);
\local_oksigeniaayvurenda\local\catalogue::purge((int) $DB->get_field(
    'local_oksigeniaayvurenda_library',
    'entityid',
    ['id' => $libraryid]
));
cli_heading('Imported into library ' . $libraryid . ' in ' . round(microtime(true) - $start, 1) . ' s');
foreach ($stats as $table => $counts) {
    cli_writeln($table . ': ' . json_encode($counts));
}
