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
 * The centre's management program as the source of readers: Cifra, for now.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\readers\cifra_people;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\cifra;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/connector.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
core_php_time_limit::raise(300);
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    cifra::save($entityid, [
        'enabled' => optional_param('enabled', 0, PARAM_BOOL),
        'url' => optional_param('url', '', PARAM_URL),
        'mode' => optional_param('mode', 'key', PARAM_ALPHA),
        'example' => optional_param('example', 0, PARAM_BOOL),
        'key' => optional_param('key', '', PARAM_RAW_TRIMMED),
        'clientid' => optional_param('clientid', '', PARAM_RAW_TRIMMED),
        'secret' => optional_param('secret', '', PARAM_RAW_TRIMMED),
    ]);
    $notice = $str('sourcessaved');
    $action = optional_param('do', '', PARAM_ALPHA);
    try {
        if ($action === 'test') {
            $found = cifra_people::readers(cifra::people($entityid), time());
            $groups = array_count_values(array_filter(array_map(fn($r) => $r['group'] ?? '', $found['readers'])));
            ksort($groups, SORT_NATURAL);
            $notice = $str('cifratested', (object) ['people' => count($found['readers']), 'gone' => count($found['gone']),
                'groups' => count($groups), 'some' => implode(', ', array_slice(array_keys($groups), 0, 6))]);
        } else if ($action === 'sync') {
            $notice = $str('cifrasynced', (object) cifra::sync($entityid));
        }
    } catch (moodle_exception $e) {
        $error = $str('cifrafailed', $e->getMessage());
    }
}
$settings = cifra::settings($entityid);
$data = $settings + [
    'heading' => $str('cifratitle'),
    'intro' => $str('cifraintro'),
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'modekey' => $settings['mode'] === 'key',
    'modeoauth' => $settings['mode'] === 'oauth',
    'keyhint' => $settings['haskey'] ? $str('cifrakeptkey') : '',
    'secrethint' => $settings['hassecret'] ? $str('cifrakeptsecret') : '',
    'pair' => null,
];
// Readers already there (from Abies, from a list) are paired with Cifra first, or the ones in both would come twice.
$unpaired = $settings['enabled'] ? \local_oksigeniaayvurenda\local\pairing::unpaired($entityid) : 0;
if ($unpaired) {
    $data['pair'] = ['hint' => $str('pairhint', $unpaired), 'label' => $str('pairgo'),
        'url' => (new moodle_url('/local/oksigeniaayvurenda/pair.php', ['library' => $library->slug]))->out(false)];
}
foreach (
    ['cifraenabled', 'cifraurl', 'cifraurlhint', 'cifrahow', 'ciframodekey', 'ciframodeoauth', 'cifrakey', 'cifraclientid',
        'cifrasecret', 'cifraexample', 'cifratest', 'cifrasync', 'sourcessave'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output(
    $str('cifratitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/connector', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $url('readers.php'), 'label' => $str('readers')]]]
);
