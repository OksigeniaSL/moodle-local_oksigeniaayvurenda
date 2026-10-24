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
 * My card: the flower of the year, how it grows, the code for the desk, and the album.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\core\flower;
use local_oksigeniaayvurenda\local\cards;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\garden;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
require_capability('local/oksigeniaayvurenda:view', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/card.php', ['library' => $ref]));

$library = catalogue::library($ref);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$conditions = ['userid' => $USER->id, 'status' => 'active'];
if ($library) {
    $conditions['entityid'] = $library->entityid;
}
$cardsdata = [];
foreach ($DB->get_records('local_oksigeniaayvurenda_reader', $conditions, 'id') as $reader) {
    $album = garden::album($reader);
    $group = (string) $DB->get_field(
        'local_oksigeniaayvurenda_member',
        'groupname',
        ['readerid' => $reader->id, 'yearid' => $album['year']->id],
        IGNORE_MULTIPLE
    );
    $entity = format_string($DB->get_field('local_oksigeniaayvurenda_entity', 'name', ['id' => $reader->entityid]));
    $card = cards::card($reader, fullname($USER), $group, $entity, $album['flower']['stage']);
    $card['flowersvg'] = cards::flower_svg($album['flower']['stage'], $album['flower']['levels'], $card['stage'], 160);
    $paths = [];
    foreach (flower::PATHS as $path) {
        $level = $album['flower']['levels'][$path];
        $paths[] = ['label' => $str('path' . $path), 'hint' => $str('path' . $path . 'hint'), 'level' => $level,
            'dots' => array_map(fn($i) => ['on' => $i < $level], range(0, 2))];
    }
    $past = array_map(
        fn($p) => ['year' => $p['year'], 'flower' => cards::flower_url($p['stage']),
        'stage' => $str('stage' . flower::STAGES[$p['stage']]),
        'svg' => cards::flower_svg(
            $p['stage'],
            $p['levels'],
            $str('stage' . flower::STAGES[$p['stage']]) . ' · ' . $p['year'],
            72
        )],
        $album['past']
    );
    $herald = \local_oksigeniaayvurenda\local\characters::say(
        (int) $reader->entityid,
        'herald',
        'heraldstage' . $album['flower']['stage']
    );
    $cardsdata[] = $card + [
        'herald' => $herald,
        'paths' => $paths,
        'books' => $album['books'],
        'hasbooks' => (bool) $album['books'],
        'past' => $past,
        'haspast' => (bool) $past,
        'yearname' => $album['year']->name,
    ];
}
$data = [
    'heading' => $str('cardtitle'),
    'cards' => $cardsdata,
    'hascards' => (bool) $cardsdata,
    'none' => $str('cardnone'),
    'pathsheading' => $str('cardpaths'),
    'booksheading' => $str('cardbooks'),
    'nobooks' => $str('cardnobooks'),
    'pastheading' => $str('cardpast'),
    'showcode' => $str('cardshowcode'),
];
shell::output($str('cardtitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/card', $data), $library, '');
