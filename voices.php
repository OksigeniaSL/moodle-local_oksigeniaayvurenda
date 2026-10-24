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
 * Voices waiting for approval, the families' permissions, and how long the centre keeps voices.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\digital_rules;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:view', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/voices.php', ['library' => $ref]));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    if ($do === 'settings' && $manage) {
        $settings = json_decode((string) $DB->get_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            ['id' => $library->entityid]
        ), true) ?: [];
        // 0 keeps voices until whoever recorded them deletes them.
        $keep = optional_param('keepdays', voices::KEEPDAYS, PARAM_INT);
        $settings['voicekeepdays'] = $keep <= 0 ? 0 : max(30, min(3650, $keep));
        $settings['voicetext'] = trim(optional_param('voicetext', '', PARAM_TEXT));
        $settings['voicefamilyoptions'] = array_values(array_intersect(
            voices::FAMILYAUDIENCES,
            optional_param_array('familyoptions', [], PARAM_ALPHA)
        ));
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $library->entityid]);
        $notice = $str('voicessaved');
    } else {
        $item = digital::get(required_param('item', PARAM_INT), $library);
        if ($item->kind !== 'voice' || !voices::can_approve($item, (int) $USER->id)) {
            throw new required_capability_exception($context, 'local/oksigeniaayvurenda:desk', 'nopermissions', '');
        }
        if ($do === 'approve') {
            voices::approve((int) $item->id, $library, ['licence' => optional_param('licence', 'rights', PARAM_ALPHANUMEXT),
                'audience' => optional_param_array('audience', [], PARAM_ALPHA),
                'signature' => optional_param('signature', '', PARAM_TEXT)]);
            $notice = $str('voiceapprovednow');
        } else if ($do === 'reject') {
            voices::remove($item);
            $notice = $str('voicerejected');
        } else if ($do === 'permission' && $item->readerid) {
            voices::permission((int) $item->readerid, (bool) optional_param('given', 0, PARAM_BOOL), (int) $USER->id);
            $notice = $str('voicessaved');
        }
    }
}
$desk = new desk($library, 0);
$pending = [];
foreach (voices::pending($library, (int) $USER->id) as $item) {
    $who = $item->readerid ? $desk->reader((int) $item->readerid) : ['name' => '', 'group' => '', 'type' => 'staff'];
    $share = voices::may_share($item);
    // A family who recorded with a link chose who else may hear it: that is the most the approval gives.
    $invite = voices::invite_of((int) $item->id);
    $class = voices::class_of($item);
    $offered = array_merge($class ? ['class'] : [], digital_rules::AUDIENCES);
    if ($invite) {
        $offered = array_values(array_intersect($offered, array_filter(explode(',', (string) $invite->audience))));
        $who['name'] = $str('voicefamilyfrom', (string) $invite->name);
        $share = (bool) $offered;
    }
    $pending[] = [
        'id' => (int) $item->id,
        'book' => $item->booktitle,
        'title' => (string) $item->title,
        'who' => trim($who['name'] . ($who['group'] !== '' ? ' (' . $who['group'] . ')' : '')),
        'url' => digital::file_url($item),
        'share' => $share,
        'minor' => $item->readerid && $who['type'] === 'student',
        'permission' => $item->readerid ? voices::has_permission((int) $item->readerid) : false,
        'licences' => array_map(fn($l) => ['value' => $l, 'label' => $str('licence' . str_replace('-', '', $l)),
            'selected' => $l === 'rights'], digital_rules::LICENCES),
        'audiences' => array_map(fn($a) => ['value' => $a, 'label' => $str('audience' . $a, $class['group'] ?? '')], $offered),
        'privatehint' => $str($invite ? 'voicefamilyprivate' : 'voiceprivatehint'),
        // The signature is read here first: it shows only once approved, as it is left here.
        'signature' => (string) $item->signature,
    ];
}
$settings = json_decode(
    (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $library->entityid]),
    true
) ?: [];
$data = [
    'heading' => $str('voicestitle'),
    'intro' => $str('voicesintro'),
    'notice' => $notice,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'pending' => $pending,
    'haspending' => (bool) $pending,
    'manage' => $manage,
    'keeps' => array_map(
        fn($days) => ['days' => $days, 'label' => in_array($days, [0, 365, 730, 1095, 1825], true)
        ? $str('voiceskeep' . $days) : $str('voiceskeepdaysn', $days), 'selected' => $days === voices::keepdays(
            (int) $library->entityid
        )],
        array_values(array_unique(array_merge([0, 365, 730, 1095, 1825], [voices::keepdays((int) $library->entityid)])))
    ),
    'familyoptions' => array_map(
        fn($a) => ['value' => $a, 'label' => $str('voicefamilyoption' . $a),
        'checked' => in_array($a, (array) ($settings['voicefamilyoptions'] ?? voices::FAMILYAUDIENCES), true)],
        voices::FAMILYAUDIENCES
    ),
    'voicetext' => (string) ($settings['voicetext'] ?? ''),
    'infourl' => (new moodle_url('/local/oksigeniaayvurenda/voicesinfo.php', ['library' => $library->slug]))->out(false),
];
foreach (
    ['voicesnone', 'voiceapprove', 'voicereject', 'voicelicence', 'voiceaudience',
        'voicepermissiongive', 'voicepermissiontake', 'voicepermissionhas', 'voicepermissionnot', 'voicessettings',
        'voiceskeepdays', 'voicestext', 'voicestexthint', 'voicessave', 'voiceinfo', 'voicesignaturereview',
        'voicesignaturereviewhint', 'voicesfamilyoptions'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('voicestitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/voices', $data),
    $library,
    '',
    ['search' => false]
);
