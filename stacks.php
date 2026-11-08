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
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\skins;
use local_oksigeniaayvurenda\local\stacks;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
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
$count = fn(int $n) => $str($n === 1 ? 'onebook' : 'books', \local_oksigeniaayvurenda\local\screens::number($n));

$corner = catalogue::is_corner(strtoupper($corner)) ? strtoupper($corner) : '';
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
    foreach (catalogue::corners($library, true, false) as $known) {
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
    // What the memory and the hummingbird of the gap say on the lectern, now and then (jue-humor H48, H59).
    'upsidesaid' => $str('stacksupside'),
    'gapagain' => \local_oksigeniaayvurenda\local\characters::band((int) $library->entityid) !== '' ? $str('stacksgapagain') : '',
    'prevurl' => '',
    'nexturl' => '',
    'end' => '',
    'endurl' => '',
    'endask' => '',
    'posters' => !$public && access::can('local/oksigeniaayvurenda:catalogue', $library) ? ['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/posters.php',
        ['library' => $library->slug]
    ))->out(false), 'label' => $str('postersnav')] : null,
];
if ($shelf) {
    $stretch = stacks::page($library, $filter, $after, $before, $at);
    $data['spines'] = screens::spines($public ? screens::public_spines($stretch['spines'], $library) : $stretch['spines']);
    // Coming from a record: that book, marked and in sight. On the first of April, one spine of the library stands upside
    // down, the same all day, and the memory owns up to it on the lectern (jue-humor H48).
    $upside = \local_oksigeniaayvurenda\local\zones::date(time(), '%m-%d', $library) === '04-01' && $data['spines']
        ? crc32('04-01-' . $library->id . '-' . $heading) % count($data['spines']) : -1;
    // Whoever looks: a reader of the flower, or one of the sky's in a school (the twelve to fourteen of jue-humor).
    $entityid = (int) $library->entityid;
    $young = characters::band($entityid) !== '';
    $teen = !$young && characters::step($entityid) === 'cielo' && in_array(skins::of($entityid), ['school', 'secondary'], true);
    // The thickest book of this stretch, when its pages are counted and they are many: the memory says so on the
    // lectern, as a fact (jue-humor H39d).
    $fattest = -1;
    if ($teen) {
        foreach ($data['spines'] as $i => $spine) {
            if (
                empty($spine['out']) && !empty($spine['counted']) && $spine['pages'] >= 300
                    && ($fattest < 0 || $spine['pages'] > $data['spines'][$fattest]['pages'])
            ) {
                $fattest = $i;
            }
        }
    }
    foreach ($data['spines'] as $i => &$spine) {
        $spine['here'] = $at && $spine['id'] === $at;
        $spine['upside'] = $i === $upside && empty($spine['out']);
        $spine['said'] = $i === $fattest ? $str('stacksfattest', screens::number($spine['pages'])) : '';
    }
    unset($spine);
    // At the end of the last shelf, a sign: to the flower, that the library ends here, for now; to the sky's in a school,
    // that what is missing can be asked for. Grown-ups walk on without one (jue-humor H60).
    if ($all && $stretch['next'] === '' && $data['spines'] && ($young || $teen)) {
        $data['end'] = $str($young ? 'stacksend' : 'stacksendsecondary');
        if ($teen) {
            $reader = $DB->get_record(
                'local_oksigeniaayvurenda_reader',
                ['entityid' => $entityid, 'userid' => $USER->id, 'status' => 'active'],
                '*',
                IGNORE_MULTIPLE
            );
            if (\local_oksigeniaayvurenda\local\purchases::may_ask($library, $reader ?: null, time())) {
                $data['endurl'] = (new moodle_url(
                    '/local/oksigeniaayvurenda/suggest.php',
                    ['library' => $library->slug]
                ))->out(false);
                $data['endask'] = $str('stacksendask');
            }
        }
    }
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
    foreach (catalogue::corners($library, false, false) as $known) {
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
        'stackswhere', 'stackslabel', 'stacksputback', 'stackswalk', 'whyask', 'whyorder', 'stacksneighbour',
        'stacksneighbouryes', 'stacksneighbourfurther', 'stacksneighbourbefore'] as $key
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
