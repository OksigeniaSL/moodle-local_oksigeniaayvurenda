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
 * The reservations of a library: copies set aside, the queues, and how readers reserve.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\gestures;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:desk', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/holds.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$entityid = (int) $library->entityid;
$str = \local_oksigeniaayvurenda\local\skins::speaker($library ?? null);
$manage = has_capability('local/oksigeniaayvurenda:manage', $context);
$now = time();
$notice = optional_param('notice', '', PARAM_ALPHA) === 'holdcancelled' ? $str('holdcancelled') : '';
if ($manage && data_submitted() && confirm_sesskey() && optional_param('set', '', PARAM_ALPHA) === 'holds') {
    // Each gesture sends only what it changes; back to its card, which says it is kept.
    $before = holds::settings($entityid);
    $changed = [];
    foreach (['online', 'renew', 'renewonline'] as $key) {
        if (($value = gestures::number($key)) !== null) {
            $changed[$key] = (bool) $value;
        }
    }
    foreach (['days', 'max'] as $key) {
        if (($value = gestures::number($key)) !== null) {
            $changed[$key] = $value;
        }
    }
    $stages = gestures::stage_touched($before['stages'], holds::stages($entityid, $now));
    if ($stages !== null) {
        $changed['stages'] = $stages;
    }
    holds::save_settings($entityid, array_merge($before, $changed));
    redirect(new moodle_url($PAGE->url, ['saved' => 'ayv-nr-reservas'], 'ayv-nr-reservas'));
}
$lists = holds::lists($library, $now);
$record = fn(int $id) => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $id]))
    ->out(false);
$covers = \local_oksigeniaayvurenda\local\covers::urls(array_merge(
    array_column($lists['ready'], 'recordid'),
    array_column($lists['waiting'], 'recordid')
));
$rows = function (array $list, string $when) use ($str, $record, $covers) {
    return array_map(fn($h) => $h + [
        'url' => $record($h['recordid']),
        'cover' => $covers[$h['recordid']] ?? null,
        'colour' => $h['recordid'] % 6,
        'when' => $when === 'until' ? $str('holdsuntil', $h['until']) : $str('holdssince', $h['since']),
        'aheadlabel' => $when === 'since' && $h['ahead'] ? $str('holdsahead', $h['ahead']) : '',
    ], $list);
};
$settings = holds::settings($entityid);
// How one reserves, in sight: a sentence, and its gestures under it.
$form = 'ayv-nr-reservas-f';
$days = (int) $settings['days'];
$max = (int) $settings['max'];
$says = $str($settings['online'] ? 'holdssayson' : 'holdssaysoff', (object) [
    'days' => $str($days === 1 ? 'holdsschoolday' : 'holdsschooldays', $days),
    'max' => $str($max === 1 ? 'holdsmaxone' : 'holdsmaxn', $max),
]) . ' ' . $str($settings['renew'] ? 'holdssaysrenew' : 'holdssaysnorenew');
$groups = [
    gestures::group($str('holdslabelonline'), [], [gestures::lamp(
        $form,
        'online',
        (bool) $settings['online'],
        $str('holdslightonline')
    )], []),
    gestures::group($str('holdslabelkept'), [gestures::step(
        $form,
        'days',
        $str('holdslabelkept'),
        $days,
        1,
        30,
        1,
        (string) $days,
        $str($days === 1 ? 'holdsunitday' : 'holdsunitdays'),
        $str
    )], [], []),
    gestures::group($str('hiwatonce'), [gestures::step(
        $form,
        'max',
        $str('hiwatonce'),
        $max,
        1,
        20,
        1,
        (string) $max,
        $str('holdsunitmax'),
        $str
    )], [], []),
    gestures::group($str('hiwrenewals'), [], [
        gestures::lamp($form, 'renew', (bool) $settings['renew'], $str('holdslightrenew')),
        gestures::lamp($form, 'renewonline', (bool) $settings['renewonline'], $str('holdslightrenewonline')),
    ], []),
];
$stagelist = holds::stages($entityid, $now);
if ($stagelist && ($settings['online'] || $settings['renewonline'])) {
    // The stages are for what is done online: reserving, renewing, or both.
    $label = $settings['online'] && $settings['renewonline'] ? 'holdsstageslabel'
        : ($settings['online'] ? 'holdsstagesreserve' : 'holdsstagesrenew');
    $chips = gestures::stages($form, $settings['stages'], $stagelist, $str);
    $groups[] = gestures::group($str($label), [], [], $chips);
}
$card = gestures::card(
    'ayv-nr-reservas',
    'holds',
    $str('holdssettings'),
    $says,
    optional_param('saved', '', PARAM_ALPHANUMEXT) === 'ayv-nr-reservas',
    $groups,
    // The page's action is the one that cancels a reservation: the card sends to this page.
    ['action' => $PAGE->url->out(false)]
);
// Nothing waiting: the herald says so, if she is on.
$herald = !$lists['ready'] && !$lists['waiting']
    ? \local_oksigeniaayvurenda\local\characters::say($entityid, 'herald', 'holdsquiet', [], 'lee') : null;
$data = [
    'heading' => $str('holdstitle'),
    'life' => \local_oksigeniaayvurenda\local\life::holds($library, time()),
    'intro' => $str('holdsintroshort'),
    'notice' => $notice,
    'action' => (new moodle_url('/local/oksigeniaayvurenda/hold.php'))->out(false),
    'self' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'library' => $library->slug,
    'readyheading' => $str('holdsready'),
    'ready' => $rows($lists['ready'], 'until'),
    'noready' => $str('holdsnoready'),
    'waitingheading' => $str('holdswaiting'),
    'waiting' => $rows($lists['waiting'], 'since'),
    'nowaiting' => $str('holdsnowaiting'),
    'cancel' => $str('holdcancel'),
    'manage' => $manage,
    'herald' => $herald,
    'quiet' => !$lists['ready'] && !$lists['waiting'],
    'savedword' => $str('hiwsaved'),
    'settings' => $manage ? [$card] : [],
];
$url = fn(string $page) => (new moodle_url('/local/oksigeniaayvurenda/' . $page, ['library' => $library->slug]))->out(false);
shell::output($str('holdstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/holds', $data), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [
        ['url' => $url('desk.php'), 'label' => $str('desktitle')],
        ['url' => $url('index.php'), 'label' => $str('deskcatalogue')],
        ['url' => (new moodle_url('/local/oksigeniaayvurenda/help.php', ['library' => $library->slug, 'piece' => 'holds']))
            ->out(false), 'label' => $str('helpnav')],
    ],
]);
