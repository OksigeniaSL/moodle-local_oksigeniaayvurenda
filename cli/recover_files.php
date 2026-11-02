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
 * Brings a centre's files back from their copy, into a centre recovered from its daily copy. For a copy too big to upload.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_oksigeniaayvurenda\core\age;
use local_oksigeniaayvurenda\local\recover;

[$options] = cli_get_params(['entity' => 0, 'file' => '', 'keyfile' => '', 'help' => false], ['h' => 'help']);
if ($options['help'] || !$options['entity'] || !is_readable((string) $options['file'])) {
    cli_writeln("Brings the files of a centre back from their copy.\n\n"
        . "--entity=ID      The recovered centre.\n--file=PATH      The copy: .zip, or .zip.age with --keyfile.\n"
        . "--keyfile=PATH   The centre's secret key.");
    exit($options['help'] ? 0 : 1);
}
$path = (string) $options['file'];
if (str_ends_with(strtolower($path), '.age')) {
    $plain = make_request_directory() . '/ficheros.zip';
    $key = (string) file_get_contents((string) $options['keyfile']);
    age::decrypt($path, $plain, trim((string) preg_replace('/^#.*$/m', '', $key)));
    $path = $plain;
}
\core\session\manager::set_user(get_admin());
$done = recover::bring_files((int) $options['entity'], $path, (int) get_admin()->id);
if ($done === null) {
    cli_error('It is not the copy of the files of this centre.');
}
cli_writeln($done['files'] . ' files and ' . $done['digital'] . ' works of the digital collection.');
