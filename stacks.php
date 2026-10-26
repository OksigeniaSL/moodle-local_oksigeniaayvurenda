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
 * Walking along the shelves of a library: its places and corners, and the books of each as spines, in their order.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stacks;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$place = optional_param('place', -1, PARAM_INT);
$corner = optional_param('corner', '', PARAM_ALPHANUM);
$all = optional_param('all', 0, PARAM_BOOL);
$after = optional_param('after', '', PARAM_ALPHANUM);
$before = optional_param('before', '', PARAM_ALPHANUM);
$at = optional_param('at', 0, PARAM_INT);
$library = catalogue::library($ref);
// Whoever is not of the library walks its shelves only where its window shows them, as anyone would.
$public = $library && \local_oksigeniaayvurenda\local\window::catalogue_open($library)
    && (!isloggedin() || isguestuser() || !access::can('local/oksigeniaayvurenda:view', $library));
if (!$public) {
    require_login(null, false);
    $context = access::context($library);
    shell::require('local/oksigeniaayvurenda:view', $library);
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $ref]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$here = fn(array $params = []) => (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug] +
    $params))->out(false);
$count = fn(int $n) => $str($n === 1 ? 'onebook' : 'books', $n);

$corner = preg_match('/^\d$/', $corner) ? $corner : '';
$filter = [];
$where = [];
$heading = $str('stackstitle');
$places = stacks::places($library);
if ($place >= 0) {
    $filter = ['place' => $place];
    $where = ['place' => $place];
    foreach ($places as $known) {
        if ($known['id'] === $place) {
            $heading = $known['id'] ? $known['label'] : $str('stacksnoplace');
        }
    }
} else if ($corner !== '') {
    $filter = $where = ['corner' => $corner];
    foreach (catalogue::corners($library, true) as $known) {
        if ($known['code'] === $corner) {
            $heading = $known['label'];
        }
    }
}
$shelf = $filter || $all || $at;
$data = [
    'heading' => $heading,
    'intro' => $shelf ? $str('stacksshelfintro') : $str('stacksintro'),
    'map' => !$shelf,
    'shelf' => $shelf,
    'places' => [],
    'corners' => [],
    'spines' => [],
    'allurl' => $here(['all' => 1]),
    'mapurl' => $here(),
    'empty' => $str('stacksempty'),
    'bookcase' => $str('stacksbookcase', $heading),
    'bookend' => screens::art('estanteria/sujetalibros', 'svg'),
    'bird' => screens::art('estanteria/mainumby', 'svg'),
    'prevurl' => '',
    'nexturl' => '',
];
if ($shelf) {
    $stretch = stacks::page($library, $filter, $after, $before, $at);
    $data['spines'] = screens::spines($public ? screens::public_spines($stretch['spines'], $library) : $stretch['spines']);
    // Coming from a record: that book, marked and in sight.
    foreach ($data['spines'] as &$spine) {
        $spine['here'] = $at && $spine['id'] === $at;
    }
    unset($spine);
    $data['prevurl'] = $stretch['prev'] !== '' ? $here($where + ($all ? ['all' => 1] : []) + ['before' => $stretch['prev']]) : '';
    $data['nexturl'] = $stretch['next'] !== '' ? $here($where + ($all ? ['all' => 1] : []) + ['after' => $stretch['next']]) : '';
} else {
    foreach ($places as $known) {
        $data['places'][] = [
            'id' => $known['id'],
            'label' => $known['id'] ? $known['label'] : $str('stacksnoplace'),
            'countlabel' => $count($known['count']),
            'url' => $here(['place' => $known['id']]),
            'colour' => $known['colour'],
            'hascolour' => $known['colour'] !== null,
        ];
    }
    // One place only is no choice: then the corners are the way in.
    if (count($data['places']) < 2) {
        $data['places'] = [];
    }
    foreach (catalogue::corners($library) as $known) {
        $data['corners'][] = [
            'label' => $known['label'],
            'countlabel' => $count($known['count']),
            'url' => $here(['corner' => $known['code']]),
            'colour' => $known['colour'],
            'art' => screens::art('rincon-' . $known['code']),
        ];
    }
}
foreach (
    ['stacksplaces', 'stackscorners', 'stacksall', 'stacksprev', 'stacksnext', 'stacksmap', 'stackslectern', 'stacksrecord',
        'stackswhere', 'stackslabel', 'stacksputback', 'stackswalk', 'whyask', 'whyorder'] as $key
) {
    $data[$key] = $str($key);
}
shell::output($heading, $OUTPUT->render_from_template('local_oksigeniaayvurenda/stacks', $data), $library, '', [
    'scripts' => $shelf ? ['stacks'] : [],
    'nav' => optional_param('help', 0, PARAM_BOOL) || $public ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'stacks']
    ))->out(false), 'label' => $str('helpnav')]],
]);
