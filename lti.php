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
 * The learning platforms that open an entity's library by LTI 1.3: what to give them, and what they give.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\ltitool;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$edit = optional_param('edit', 0, PARAM_INT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:manage');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/lti.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$fields = ['name', 'issuer', 'clientid', 'authloginurl', 'authtokenurl', 'keyseturl', 'deployments'];
$notice = '';
$error = '';
$typed = [];
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('do', '', PARAM_ALPHA);
    $id = optional_param('id', 0, PARAM_INT);
    if ($action === 'save') {
        foreach ($fields as $field) {
            $typed[$field] = optional_param($field, '', $field === 'name' ? PARAM_TEXT : PARAM_RAW_TRIMMED);
        }
        try {
            ltitool::save_platform($entityid, $typed, $id);
            $notice = $str('ltisaved', s($typed['name']));
            $typed = [];
            $edit = 0;
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
            $edit = $id;
        }
    } else if ($action === 'delete') {
        ltitool::delete_platform($entityid, $id);
        $notice = $str('ltideleted');
    } else if ($action === 'newreg') {
        ltitool::registration_url($entityid, true);
        $notice = $str('ltiregisternewdone');
    }
}
$platforms = [];
$editing = null;
foreach (ltitool::platforms($entityid) as $platform) {
    if ((int) $platform->id === $edit) {
        $editing = $platform;
    }
    $platforms[] = ['platformid' => $platform->id, 'platformname' => $platform->name, 'issuer' => $platform->issuer,
        'clientid' => $platform->clientid,
        'editurl' => (new moodle_url($PAGE->url, ['edit' => $platform->id]))->out(false) . '#ayv-lti-form'];
}
$values = $typed ?: ($editing ? (array) $editing : []);
$form = [];
foreach ($fields as $field) {
    $form[] = ['field' => $field, 'label' => $str('lti' . $field), 'hint' => $str('lti' . $field . 'hint'),
        'value' => (string) ($values[$field] ?? ''), 'long' => $field === 'deployments',
        'required' => !in_array($field, ['authtokenurl', 'deployments'], true)];
}
$urls = ltitool::urls();
$toolurls = [];
foreach (['launch', 'login', 'jwks', 'domain'] as $key) {
    $toolurls[] = ['label' => $str('ltiurl' . $key), 'value' => $urls[$key]];
}
$toolurls[] = ['label' => $str('lticustom'), 'value' => 'library=' . $library->slug];
$registration = ltitool::registration_url($entityid);
$authoff = !is_enabled_auth('lti');
$data = [
    'heading' => $str('ltiheading'),
    'intro' => $str('ltiintro'),
    'notice' => $notice,
    'error' => $error,
    'authoff' => $authoff ? $str('ltiauthoff') : '',
    'authurl' => $authoff && has_capability('moodle/site:config', $context)
        ? (new moodle_url('/admin/settings.php', ['section' => 'manageauths']))->out(false) : '',
    'authlink' => $str('ltiauthlink'),
    'regheading' => $str('ltiregisterheading'),
    'regintro' => $str('ltiregisterintro'),
    'regurl' => $registration['url'],
    'reglabel' => $str('ltiregisterlabel'),
    'regexpires' => $str('ltiregisterexpires', userdate($registration['expires'], get_string('strftimedatetime', 'langconfig'))),
    'regnew' => $str('ltiregisternew'),
    'toolheading' => $str('ltitoolheading'),
    'toolintro' => $str('ltitoolintro'),
    'toolurls' => $toolurls,
    'copy' => $str('voiceinvitecopy'),
    'toolhint' => $str('ltitoolhint'),
    'platformsheading' => $str('ltiplatforms'),
    'platforms' => $platforms,
    'hasplatforms' => (bool) $platforms,
    'noplatforms' => $str('ltinoplatforms'),
    'editlabel' => $str('ltiedit'),
    'deletelabel' => $str('ltidelete'),
    'formheading' => $editing ? $str('ltichange', s($editing->name)) : $str('ltiadd'),
    'form' => $form,
    'editid' => $editing ? (int) $editing->id : 0,
    'moodlehint' => $str('ltimoodlehint'),
    'savelabel' => $str('ltisave'),
    'cancelurl' => $editing ? $PAGE->url->out(false) : '',
    'cancellabel' => $str('cancel', 'moodle'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
];
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/lti', $data), $library, '', [
    'search' => false,
    'scripts' => ['voice'],
    'config' => ['strings' => ['copied' => $str('voiceinvitecopied')]],
    'nav' => [['url' => $url('sources.php'), 'label' => $str('readerssources')]],
]);
