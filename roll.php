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
 * A textbook bank by classes: in September, who is still waiting for books of their set; in June, who still has books.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\bank;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:desk', $library);
$group = trim(optional_param('group', '', PARAM_TEXT));
$show = optional_param('show', 'all', PARAM_ALPHA);
$show = in_array($show, ['all', 'waiting', 'holding'], true) ? $show : 'all';
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $ref] + ($group !== '' ? ['group' => $group,
    'show' => $show] : [])));

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
if (!$library || !bank::is($library)) {
    shell::missing($str('banknotbank'), $library);
}
$url = fn(array $params) => (new moodle_url('/local/oksigeniaayvurenda/roll.php', ['library' => $library->slug] + $params))
    ->out(false);
$now = time();
$data = ['heading' => $str('bankrollheading', format_string($library->name)), 'intro' => $str('bankrollintro'),
    'isclass' => $group !== ''];
if ($group === '') {
    $data['classes'] = array_map(fn($c) => [
        'group' => $c['group'],
        'url' => $url(['group' => $c['group']]),
        'pupils' => $str('bankrollpupils', $c['pupils']),
        'waiting' => $c['waiting'] ? $str('bankrollwaiting', $c['waiting']) : '',
        'holding' => $c['out'] ? $str('bankrollholding', (object) $c) : '',
    ], bank::classes($library, $now));
    $data['hasclasses'] = (bool) $data['classes'];
} else {
    $roll = bank::roll($library, $group, $now);
    $kept = array_values(array_filter($roll, fn($p) => $show === 'all' || ($show === 'waiting' ? $p['missing'] : $p['held'])));
    $data += [
        'group' => $group,
        'back' => $url([]),
        'filters' => array_map(
            fn($f) => ['url' => $url(['group' => $group, 'show' => $f]),
            'label' => $str('bankrollshow' . $f), 'current' => $f === $show,
            'count' => count(array_filter($roll, fn($p) => $f === 'all' || ($f === 'waiting' ? $p['missing'] : $p['held'])))],
            ['all', 'waiting', 'holding']
        ),
        'pupils' => array_map(fn($p) => [
            'name' => $p['name'],
            'card' => $p['card'] !== '' ? $str('bankrollcard', $p['card']) : '',
            'gone' => !$p['member'],
            'held' => array_map(fn($h) => ['title' => $h['title'], 'barcode' => $h['barcode'],
                'condition' => $h['condition'] !== 'good' ? $str('condition' . $h['condition']) : ''], $p['held']),
            'hasheld' => (bool) $p['held'],
            'missing' => array_map(fn($t) => ['title' => $t], $p['missing']),
            'hasmissing' => (bool) $p['missing'],
            'complete' => $p['member'] && !$p['missing'],
        ], $kept),
        'haspupils' => (bool) $kept,
    ];
}
foreach (
    ['bankrollnone', 'bankrollhas', 'bankrollmissing', 'bankrollgone', 'bankrollcomplete', 'bankrollempty', 'bankrollback',
        'bankrollprint'] as $key
) {
    $data[$key] = $str($key);
}
$desk = (new moodle_url('/local/oksigeniaayvurenda/desk.php', ['library' => $library->slug]))->out(false);
shell::output(
    $group !== '' ? $group : $data['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/roll', $data),
    $library,
    '',
    ['search' => false, 'nav' => [['url' => $desk, 'label' => $str('desktitle')]]]
);
