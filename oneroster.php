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
 * Readers from a OneRoster export.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\readers\oneroster as roster;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plan_full;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\oneroster;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/oneroster.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
core_php_time_limit::raise(300);
raise_memory_limit(MEMORY_EXTRA);
$entityid = (int) $library->entityid;
// The zip waits between seeing what it brings and loading it, for this library only.
$pending = $SESSION->ayvurendaoneroster ?? null;
if ($pending && (!is_file($pending['path']) || $pending['library'] !== $library->slug)) {
    $pending = null;
}
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $step = optional_param('step', '', PARAM_ALPHA);
    try {
        if ($step === 'upload' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $path = make_temp_directory('local_oksigeniaayvurenda') . '/' . random_string(20) . '.zip';
            move_uploaded_file($_FILES['file']['tmp_name'], $path);
            $pending = ['path' => $path, 'library' => $library->slug];
            oneroster::files($path);
        } else if ($step === 'load' && $pending) {
            $found = roster::readers(
                oneroster::files($pending['path']),
                optional_param('school', '', PARAM_RAW_TRIMMED),
                optional_param('families', 0, PARAM_BOOL),
                time()
            );
            $stats = plans::within($entityid, fn() => oneroster::load($entityid, $found, null, false));
            $notice = $str('onerosterloaded', (object) $stats);
            @unlink($pending['path']);
            $pending = null;
        } else if ($step === 'cancel' && $pending) {
            @unlink($pending['path']);
            $pending = null;
        }
    } catch (plan_full $e) {
        $error = $e->getMessage();
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
        if ($pending) {
            @unlink($pending['path']);
        }
        $pending = null;
    }
    $SESSION->ayvurendaoneroster = $pending;
}
$data = ['heading' => $str('onerostertitle'), 'intro' => $str('onerosterintro'), 'notice' => $notice, 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'upload' => !$pending, 'check' => null,
    'plansummary' => plans::summary($entityid)];
if ($pending) {
    $files = oneroster::files($pending['path']);
    $found = roster::readers($files, '', true, time());
    $kinds = array_count_values(array_column($found['readers'], 'type'));
    $groups = array_unique(array_filter(array_column($found['readers'], 'group')));
    $schools = [['value' => '', 'label' => $str('onerosterallschools')]];
    foreach ($found['schools'] as $id => $name) {
        $schools[] = ['value' => $id, 'label' => $name];
    }
    $data['check'] = [
        'mode' => $str($found['mode'] === 'delta' ? 'onerostermodedelta' : 'onerostermodebulk'),
        'counts' => $str('onerostercounts', (object) ['students' => $kinds['student'] ?? 0, 'teachers' => $kinds['teacher'] ?? 0,
            'staff' => $kinds['staff'] ?? 0, 'families' => $kinds['family'] ?? 0, 'groups' => count($groups),
            'tutors' => count(array_intersect_key($found['tutors'], array_flip($groups)))]),
        'schoolchoice' => count($schools) > 2 ? ['options' => $schools] : null,
        'hasfamilies' => !empty($kinds['family']),
    ];
}
foreach (
    ['onerosterfile', 'onerosternext', 'onerostercheck', 'onerosterschool', 'onerosterfamilies', 'onerosterload',
        'onerostercancel'] as $key
) {
    $data[$key] = $str($key);
}
$readersurl = (new moodle_url('/local/oksigeniaayvurenda/readers.php', ['library' => $library->slug]))->out(false);
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/oneroster', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $readersurl, 'label' => $str('readers')]]]
);
