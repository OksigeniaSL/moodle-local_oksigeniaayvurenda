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
 * Ayvurenda: what was catalogued lately, today, this week, this fortnight or this month, the latest first.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\catalogued;
use local_oksigeniaayvurenda\local\covers;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\zones;

require(__DIR__ . '/../../config.php');

$ref = \local_oksigeniaayvurenda\local\shell::library_ref();
$span = optional_param('span', 'week', PARAM_ALPHA);
$span = in_array($span, catalogued::SPANS, true) ? $span : 'week';
$from = max(0, optional_param('from', 0, PARAM_INT));
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/catalogued.php', ['library' => $ref, 'span' => $span]));
$str = \local_oksigeniaayvurenda\local\skins::speaker($library);
$now = time();
$link = fn(string $script, array $params = []) => (new moodle_url(
    '/local/oksigeniaayvurenda/' . $script,
    ['library' => $library->slug] + $params
))->out(false);
$starts = catalogued::starts((int) $library->entityid, $now);
$counts = array_map(fn($since) => catalogued::count($library, $since), $starts);
$lines = catalogued::lines($library, $starts[$span], $from);
$covers = covers::urls(array_column($lines, 'recordid'));
$days = [];
$today = zones::date($now, '%Y%m%d', $library);
$yesterday = zones::date($now - DAYSECS, '%Y%m%d', $library);
foreach ($lines as $line) {
    $key = zones::date($line['time'], '%Y%m%d', $library);
    $days[$key] ??= ['day' => $key === $today ? $str('catalogedtoday') : ($key === $yesterday ? $str('catalogedyesterday')
        : zones::date($line['time'], 'strftimedaydate', $library)), 'rows' => []];
    $count = count($line['codes']);
    $days[$key]['rows'][] = [
        'title' => $line['title'],
        'url' => $link('record.php', ['id' => $line['recordid']]),
        'hascover' => isset($covers[$line['recordid']]),
        'coverurl' => $covers[$line['recordid']] ?? '',
        'colour' => $line['recordid'] % 6,
        'when' => zones::date($line['time'], 'strftimetime', $library),
        'copies' => $count === 1 ? $str('catalogedcopyone', $line['codes'][0])
            : $str('catalogedcopies', (object) ['count' => $count, 'codes' => implode(', ', $line['codes'])]),
        'call' => $line['call'],
        'location' => $line['location'],
        'twice' => $line['twice'],
        'twin' => $line['twin'],
    ];
}
$total = $counts[$span];
$size = catalogued::PAGE;
$page = fn(int $start) => $link('catalogued.php', ['span' => $span, 'from' => $start]);
$data = [
    'heading' => $str('catalogedtitle'),
    'life' => \local_oksigeniaayvurenda\local\life::catalogued($library, $now),
    'intro' => $str('catalogedintro'),
    'spans' => array_map(fn($key) => ['key' => $key, 'count' => screens::number($counts[$key]),
        'label' => $str('catalogedspan' . $key), 'url' => $link('catalogued.php', ['span' => $key]),
        'current' => $span === $key], catalogued::SPANS),
    'days' => array_values($days),
    'empty' => $total ? '' : $str('catalogedempty' . $span),
    'twicelabel' => $str('catalogedtwice'),
    'twinlabel' => $str('catalogedtwin'),
    'twinhint' => $str('catalogedtwinhint'),
    'addurl' => $link('add.php'),
    'addlabel' => $str('menuadd'),
    'pager' => $total > $size ? [
        'prev' => $from > 0 ? $page(max(0, $from - $size)) : '',
        'next' => $from + $size < $total ? $page($from + $size) : '',
        'line' => $str('readerspage', (object) ['from' => $from + 1, 'to' => min($from + $size, $total), 'total' => $total]),
        'prevlabel' => $str('readersprev'),
        'nextlabel' => $str('readersnext'),
    ] : null,
];
shell::output($str('catalogedtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/catalogued', $data), $library, '', [
    'search' => false,
    'nav' => [['url' => $link('add.php'), 'label' => $str('menuadd')],
        ['url' => $link('index.php'), 'label' => $str('deskcatalogue')]],
]);
