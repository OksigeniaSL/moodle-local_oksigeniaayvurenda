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
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\gestures;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\voices;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/voices.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$notice = '';
$set = optional_param('set', '', PARAM_ALPHA);
if ($manage && $set !== '' && data_submitted() && confirm_sesskey()) {
    // The settings, each gesture only what it changes; back to its card, which says it is kept.
    $settings = json_decode((string) $DB->get_field(
        'local_oksigeniaayvurenda_entity',
        'settings',
        ['id' => $library->entityid]
    ), true) ?: [];
    $keep = gestures::number('keepdays');
    if ($set === 'keep' && $keep !== null) {
        // 0 keeps voices until whoever recorded them deletes them.
        $settings['voicekeepdays'] = $keep <= 0 ? 0 : max(30, min(3650, $keep));
    }
    if ($set === 'hear') {
        $on = (array) ($settings['voicefamilyoptions'] ?? voices::FAMILYAUDIENCES);
        foreach (optional_param_array('option', [], PARAM_INT) as $option => $value) {
            $on = $value ? array_merge($on, [$option]) : array_diff($on, [$option]);
        }
        $settings['voicefamilyoptions'] = array_values(array_intersect(voices::FAMILYAUDIENCES, $on));
    }
    if ($set === 'text') {
        $settings['voicetext'] = trim(optional_param('voicetext', '', PARAM_TEXT));
    }
    $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $library->entityid]);
    \local_oksigeniaayvurenda\event\voice_settings_changed::fire((int) $library->entityid);
    $anchor = ['keep' => 'ayv-nr-guardan', 'hear' => 'ayv-nr-oyen', 'text' => 'ayv-nr-texto'][$set] ?? 'ayv-nr-guardan';
    redirect(new moodle_url($PAGE->url, ['saved' => $anchor], $anchor));
}
if (data_submitted() && confirm_sesskey()) {
    $do = required_param('do', PARAM_ALPHA);
    $item = digital::get(required_param('item', PARAM_INT), $library);
    if ($item->kind !== 'voice' || !voices::can_approve($item, (int) $USER->id)) {
        shell::refuse(get_string('voicenotyours', 'local_oksigeniaayvurenda'), $library);
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
        // Whom what is approved reaches: the little ones, and their family (Jujol).
        'marks' => $item->forlittle ? ['bud' => \local_oksigeniaayvurenda\local\screens::art('carne/flor/capullo'),
            'little' => $str('voicemarklittle'), 'family' => $item->tellfamily ? $str('voicemarkfamily') : ''] : null,
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
    'infourl' => (new moodle_url('/local/oksigeniaayvurenda/voicesinfo.php', ['library' => $library->slug]))->out(false),
];
// Nothing to listen to: the memory says so.
$data['quiet'] = !$pending ? \local_oksigeniaayvurenda\local\characters::say(
    (int) $library->entityid,
    'memory',
    'voicesquiet',
    [],
    'lee'
) : null;
// The settings in sight, for whoever manages: three cards, each gesture kept at once.
$saved = optional_param('saved', '', PARAM_ALPHANUMEXT);
$entityid = (int) $library->entityid;
$keepdays = voices::keepdays($entityid);
$years = max(1, (int) round($keepdays / 365));
$keepword = $keepdays === 0 ? '' : (in_array($keepdays, [365, 730, 1095, 1825], true)
    ? \core_text::strtolower($str('voiceskeep' . $keepdays)) : $str('voiceskeepdaysn', $keepdays));
$form = 'ayv-nr-guardan-f';
$keepgroups = [gestures::group('', [], [gestures::light(
    $form,
    'keepdays',
    $keepdays === 0 ? voices::KEEPDAYS : 0,
    $keepdays === 0,
    $str('voiceskeeplight')
)], [])];
if ($keepdays !== 0) {
    array_unshift($keepgroups, gestures::group('', [gestures::step(
        $form,
        'keepdays',
        $str('voiceskeepdays'),
        $keepdays,
        365,
        3650,
        365,
        (string) $years,
        $str($years === 1 ? 'voicesunityear' : 'voicesunityears'),
        $str
    )], [], []));
}
$on = (array) ($settings['voicefamilyoptions'] ?? voices::FAMILYAUDIENCES);
$upper = fn(string $text) => \core_text::strtoupper(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
$offered = \local_oksigeniaayvurenda\local\skins::no_classes($entityid)
    ? array_diff(voices::FAMILYAUDIENCES, ['class']) : voices::FAMILYAUDIENCES;
$owntext = trim((string) ($settings['voicetext'] ?? ''));
$data['savedword'] = $str('hiwsaved');
$data['settings'] = !$manage ? [] : [
    gestures::card('ayv-nr-guardan', 'keep', $str('voiceskeeptitle'), $keepdays === 0 ? $str('voiceskeepsaysforever')
        : $str('voiceskeepsays', $keepword), $saved === 'ayv-nr-guardan', $keepgroups),
    gestures::card(
        'ayv-nr-oyen',
        'hear',
        $str('voiceshear'),
        $str('voiceshearsays'),
        $saved === 'ayv-nr-oyen',
        [gestures::group(
            '',
            [],
            array_map(
                fn($option) => gestures::lamp(
                    'ayv-nr-oyen-f',
                    'option[' . $option . ']',
                    in_array($option, $on, true),
                    $upper($str('voicefamilyshort' . $option))
                ),
                array_values($offered)
            ),
            []
        )]
    ),
    gestures::card(
        'ayv-nr-texto',
        'text',
        $str('voicestextcard'),
        $str($owntext !== '' ? 'voicestextown' : 'voicestextnone'),
        $saved === 'ayv-nr-texto',
        [],
        [
            'text' => ['summary' => $str($owntext !== '' ? 'voicestextchange' : 'voicestextadd'), 'open' => false,
                'label' => $str('voicestext'), 'name' => 'voicetext', 'value' => $owntext, 'hint' => $str('voicestextblank'),
                'save' => $str('voicessave')],
            'link' => ['lead' => $str('voicestextsee'), 'url' => $data['infourl'], 'label' => $str('voiceinfo')],
        ]
    ),
];
foreach (
    ['voicesnone', 'voiceapprove', 'voicereject', 'voicelicence', 'voiceaudience',
        'voicepermissiongive', 'voicepermissiontake', 'voicepermissionhas', 'voicepermissionnot', 'voicessettings',
        'voiceskeepdays', 'voicestext', 'voicestexthint', 'voicessave', 'voiceinfo', 'voicesignaturereview',
        'voicesignaturereviewhint', 'voicesfamilyoptions', 'holdschange', 'offsiteclose'] as $key
) {
    $data[$key] = $str($key);
}
shell::output(
    $str('voicestitle'),
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/voices', $data),
    $library,
    '',
    ['search' => false, 'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'voices']))
            ->out(false), 'label' => $str('helpnav')],
    ]]
);
