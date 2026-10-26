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
 * Posters for the shelves of the room: one for each place, with its colour and a QR code that opens that shelf online,
 * to print and stick on the real shelves.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\spine;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\shell;
use local_oksigeniaayvurenda\local\stacks;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
shell::require('local/oksigeniaayvurenda:catalogue', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/posters.php', ['library' => $ref]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

// The places of the room; a library that has none is laid out by its corners.
$posters = [];
foreach (stacks::places($library) as $place) {
    if ($place['id']) {
        $posters[] = ['name' => $place['label'], 'colour' => $place['colour'], 'params' => ['place' => $place['id']]];
    }
}
if (!$posters) {
    foreach (catalogue::corners($library) as $corner) {
        $posters[] = ['name' => $corner['label'], 'colour' => null, 'params' => ['corner' => $corner['code']]];
    }
}
$house = ['#17574c', '#2c7a6a', '#e2654c', '#f0b43c', '#0f3a33'];
// The QR code as an SVG in the page: without its XML head, scaling with its box, and without an id repeated on each.
$qr = function (string $url): string {
    $svg = (new core_qrcode($url))->getBarcodeSVGcode(5, 5, '#1e2b2a');
    $svg = substr($svg, (int) strpos($svg, '<svg'));
    $svg = preg_replace('/<desc>.*?<\/desc>/s', '', $svg);
    $svg = str_replace(' id="elements"', '', $svg);
    $box = '<svg class="ayv-poster-qr" viewBox="0 0 $1 $2" aria-hidden="true"';
    return (string) preg_replace('/<svg width="(\d+)" height="(\d+)"/', $box, $svg, 1);
};
$cards = [];
foreach ($posters as $i => $poster) {
    $url = (new moodle_url('/local/oksigeniaayvurenda/stacks.php', ['library' => $library->slug] + $poster['params']))->out(false);
    // Letters in cream or in ink on the band, whichever stands out more; a band where neither reaches 4.5:1 is darkened
    // a little until the cream does.
    $colour = $poster['colour'] ?? $house[$i % count($house)];
    [$l, $c, $h] = spine::oklch(spine::rgb($colour));
    while (max(spine::contrast(spine::rgb($colour), [251, 246, 236]), spine::contrast(spine::rgb($colour), [30, 43, 42])) < 4.5) {
        $l -= 0.03;
        $colour = spine::hex(spine::from_oklch($l, $c, $h));
    }
    $cards[] = [
        'name' => $poster['name'],
        'colour' => $colour,
        'ink' => spine::contrast(spine::rgb($colour), [251, 246, 236]) >= spine::contrast(spine::rgb($colour), [30, 43, 42])
            ? '#fbf6ec' : '#1e2b2a',
        'qr' => $qr($url),
        'url' => $url,
    ];
}
$data = [
    'heading' => $str('posterstitle'),
    'hint' => $str('postershint'),
    'library' => format_string($library->name),
    'scan' => $str('postersscan'),
    'printnow' => $str('printnow'),
    'empty' => $str('postersempty'),
    'hassheets' => (bool) $cards,
    'sheets' => array_map(fn($pair) => ['posters' => $pair], array_chunk($cards, 2)),
    'emblem' => (new moodle_url('/local/oksigeniaayvurenda/app/emblema.svg'))->out(false),
];
shell::output($data['heading'], $OUTPUT->render_from_template('local_oksigeniaayvurenda/posters', $data), $library, '', [
    'search' => false,
]);
