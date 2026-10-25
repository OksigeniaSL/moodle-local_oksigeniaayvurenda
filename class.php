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
 * My class: the tutor of a group sees the challenge of the term and chooses it, the class's garden and what the class
 * reads, never who.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\challenges;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\years;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$asked = optional_param('group', '', PARAM_TEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:view');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => $ref] +
    ($asked !== '' ? ['group' => $asked] : [])));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = (int) $library->entityid;
$now = time();
$groups = challenges::tutored($entityid, (int) $USER->id, $now);
// The library's staff may open any group's page as its tutor sees it.
if ($asked !== '' && !in_array($asked, $groups, true) && has_capability('local/oksigeniaayvurenda:desk', $context)) {
    $groups = [$asked];
}

$notice = '';
if (data_submitted() && confirm_sesskey()) {
    $group = required_param('group', PARAM_TEXT);
    $kind = required_param('challenge', PARAM_ALPHA);
    if (in_array($group, $groups, true) && ($kind === 'off' || isset(challenges::KINDS[$kind]))) {
        challenges::choose($entityid, $group, $kind, $now);
        $notice = $str('challengesaved');
    }
}

$year = $groups ? years::around($entityid, $now) : null;
$data = [];
foreach ($groups as $i => $group) {
    $challenge = challenges::of($entityid, $group, $now);
    $off = challenges::stage_off($entityid, (int) $year->id, $group);
    $kinds = [];
    foreach (array_merge(array_keys(challenges::KINDS), ['off']) as $kind) {
        $kinds[] = ['value' => $kind, 'label' => $str('challengekind' . $kind),
            'checked' => $challenge ? $challenge->kind === $kind : $kind === 'off'];
    }
    $garden = array_map(
        fn($p) => ['label' => $str('challengeplanted', $p['term']) . ' · ' . $str('challengekind' . $p['kind'])],
        challenges::garden($entityid, $group, $now)
    );
    $reads = screens::cards(catalogue::class_reads($library, $group, (int) $year->id, $now), $library);
    $data[] = [
        'index' => $i,
        'group' => $group,
        'offstage' => $off !== null ? $str('classoff', $off) : '',
        'current' => $challenge ? challenges::view($challenge, $entityid, true) : null,
        'canchoose' => $off === null && !($challenge && $challenge->complete),
        'kinds' => $kinds,
        'garden' => $garden,
        'hasgarden' => (bool) $garden,
        'cards' => $reads,
        'hasreads' => (bool) $reads,
    ];
}

shell::output($str('classtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/class', [
    'heading' => $str('classtitle'),
    'intro' => $str('classintro'),
    'notice' => $notice,
    'none' => $groups ? '' : $str('classnone'),
    'groups' => $data,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'choose' => $str('challengechoose'),
    'choosehint' => $str('challengechoosehint'),
    'save' => $str('challengesave'),
    'gardenheading' => $str('challengegarden'),
    'gardenempty' => $str('challengegardenempty'),
    'readsheading' => $str('homeclass'),
    'readshint' => $str('homeclasshint'),
    'readsempty' => $str('classreadsempty'),
    'shelfprev' => $str('shelfprev'),
    'shelfnext' => $str('shelfnext'),
]), $library, '', ['search' => false]);
