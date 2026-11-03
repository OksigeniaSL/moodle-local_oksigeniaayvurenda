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
shell::no_practice($library);
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
$done = null;
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
            $done = plans::within($entityid, fn() => oneroster::load($entityid, $found, null, false, true));
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
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
$figure = fn(int $count, string $key) => $count ? ['count' => $number($count), 'label' => $str($key)] : null;
$page = fn(string $file) => (new moodle_url('/local/oksigeniaayvurenda/' . $file, ['library' => $library->slug]))->out(false);
$data = ['heading' => $str('onerostertitle'), 'intro' => $str('onerosterintro'), 'error' => $error,
    'action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'upload' => !$pending && !$done, 'check' => null,
    'done' => null, 'said' => null, 'plansummary' => plans::summary($entityid), 'urlself' => $PAGE->url->out(false)];
if ($done) {
    $data['done'] = [
        'figures' => array_values(array_filter([
            $figure((int) $done['created'], 'readerslistfigcreated'),
            $figure((int) $done['updated'], 'readerslistfigupdated'),
            $figure((int) $done['left'], 'onerosterfigleft'),
            $figure((int) $done['tutors'], 'onerosterfigtutors'),
        ])),
        'urlreaders' => $page('readers.php'),
        'urldesk' => $page('desk.php'),
    ];
    $data['said'] = \local_oksigeniaayvurenda\local\characters::say($entityid, 'memory', 'readerslistmemory', [], 'celebra');
}
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
        'figures' => array_values(array_filter([
            $figure($kinds['student'] ?? 0, 'onerosterfigstudents'),
            $figure($kinds['teacher'] ?? 0, 'onerosterfigteachers'),
            $figure($kinds['staff'] ?? 0, 'onerosterfigstaff'),
            $figure(count($groups), 'readerslistfiggroups'),
            $figure(count(array_intersect_key($found['tutors'], array_flip($groups))), 'onerosterfigtutors'),
        ])),
        'schoolchoice' => count($schools) > 2 ? ['options' => $schools] : null,
        'hasfamilies' => !empty($kinds['family']),
        'familiesline' => empty($kinds['family']) ? '' : ($kinds['family'] === 1 ? $str('onerosterfamiliesone')
            : $str('onerosterfamilies', $number($kinds['family']))),
    ];
}
foreach (
    ['onerosterdrop', 'onerosterformats', 'onerosterchecktitle', 'onerosterschool', 'onerosterload', 'importcancel',
        'importdropor', 'importreading', 'importbusy', 'importbusyhint', 'readerslistdonetitle', 'importnextheading',
        'readerslistgoreaders', 'readerslistgoreadershint', 'importnextdesk', 'importnextdeskhint'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/oneroster', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $page('sources.php'), 'label' => $str('readerssources')]]]
);
