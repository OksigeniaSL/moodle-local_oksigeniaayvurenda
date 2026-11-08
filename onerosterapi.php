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
 * The connection with the OneRoster REST API of a management program: its state, trying it, bringing the readers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\plan_full;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\sources\oneroster_api as api;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:manage', $library);
shell::no_practice($library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/onerosterapi.php', ['library' => $ref]));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
if (!plans::allows($entityid, 'connectors')) {
    shell::refuse(plans::without($entityid, 'connectors'), $library, get_string('planofftitle', 'local_oksigeniaayvurenda'));
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$number = fn(int $n) => \local_oksigeniaayvurenda\local\screens::number($n);
core_php_time_limit::raise(600);
raise_memory_limit(MEMORY_EXTRA);
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('do', '', PARAM_ALPHA);
    if ($action === 'night') {
        api::save($entityid, ['enabled' => optional_param('enabled', 0, PARAM_BOOL)]);
        $notice = $str('sourcessaved');
    } else if ($action === 'who') {
        api::save($entityid, ['school' => optional_param('school', '', PARAM_RAW_TRIMMED),
            'families' => optional_param('families', 0, PARAM_BOOL)]);
        $notice = $str('sourcessaved');
    } else if ($action === 'save' || $action === 'connect') {
        api::save($entityid, [
            'url' => optional_param('url', '', PARAM_URL),
            'version' => optional_param('version', '1.2', PARAM_RAW_TRIMMED),
            'auth' => optional_param('auth', 'oauth2', PARAM_ALPHANUM),
            'tokenurl' => optional_param('tokenurl', '', PARAM_URL),
            'clientid' => optional_param('clientid', '', PARAM_RAW_TRIMMED),
            'scope' => optional_param('scope', '', PARAM_RAW_TRIMMED),
            'secret' => optional_param('secret', '', PARAM_RAW_TRIMMED),
        ]);
        $notice = $str('sourcessaved');
    }
    try {
        if ($action === 'test' || $action === 'connect') {
            $notice = $str('onerosterapitested', (object) array_map($number, api::test($entityid)));
        } else if ($action === 'sync') {
            $stats = plans::within($entityid, fn() => api::sync($entityid, null, true));
            $notice = $str('onerosterapisynced', (object) array_map($number, $stats));
        }
    } catch (plan_full $e) {
        $error = $e->getMessage();
    } catch (moodle_exception $e) {
        $error = $str('cifrafailed', $e->getMessage());
    }
}
$settings = api::settings($entityid);
$connected = api::connected($entityid);
$brought = \local_oksigeniaayvurenda\local\readers::from_sources($entityid)['oneroster'] ?? 0;
$schools = [['value' => '', 'label' => $str('onerosterallschools'), 'selected' => $settings['school'] === '']];
foreach ($settings['schools'] as $id => $name) {
    $schools[] = ['value' => (string) $id, 'label' => (string) $name, 'selected' => (string) $id === $settings['school']];
}
$data = [
    'heading' => $str('onerosterapititle'),
    'intro' => $str('onerosterapiintro'),
    'how' => $str('onerosterapihow'),
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'connected' => $connected,
    'state' => $str($connected ? 'cifrastateon' : 'cifrastateoff'),
    'stateline' => !$connected ? $str('onerosterapistateoffhint') : ($brought
        ? $str('onerosterapistatereaders', $number($brought)) : $str('cifrastatenone')),
    'nightline' => $str($settings['enabled'] ? 'cifranighton' : 'cifranightoff'),
    'nightvalue' => $settings['enabled'] ? 0 : 1,
    'nightgo' => $str($settings['enabled'] ? 'cifranightstop' : 'cifranightstart'),
    'url' => $settings['url'],
    'tokenurl' => $settings['tokenurl'],
    'clientid' => $settings['clientid'],
    'scope' => $settings['scope'],
    'moreopen' => $settings['tokenurl'] !== '' || $settings['scope'] !== '',
    'v11' => $settings['version'] === '1.1',
    'v12' => $settings['version'] === '1.2',
    'oauth1' => $settings['auth'] === 'oauth1',
    'oauth2' => $settings['auth'] === 'oauth2',
    'secrethint' => $settings['hassecret'] ? $str('cifrakeptsecret') : '',
    'who' => $connected && (count($schools) > 2 || $settings['schools'] || $brought) ? [
        'schoolchoice' => count($schools) > 2 ? ['options' => $schools] : null,
        'families' => $settings['families'],
        'line' => $settings['school'] !== '' && isset($settings['schools'][$settings['school']])
            ? $str('onerosterapiwhoschool', $settings['schools'][$settings['school']]) : $str('onerosterapiwhoall'),
    ] : null,
];
foreach (
    ['cifrachange', 'cifraconnect', 'cifrasavetest', 'cifrasecret', 'cifratest', 'cifrasync', 'sourcessave', 'offsiteclose',
        'onerosterapiurl', 'onerosterapiurlhint', 'onerosterapiversion', 'onerosterapiauth', 'onerosterapioauth2',
        'onerosterapioauth1', 'onerosterapitokenurl', 'onerosterapitokenurlhint', 'onerosterapiclientid', 'onerosterapiscope',
        'onerosterapiscopehint', 'onerosterapimore', 'onerosterschool', 'onerosterapifamilies', 'onerosterapiwho',
        'onerosterapizip'] as $key
) {
    $data[$key] = $str($key);
}
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
$data['urlzip'] = $url('oneroster.php');
shell::output(
    $str('onerosterapititle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/oneroster_api', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $url('sources.php'), 'label' => $str('readerssources')]]]
);
