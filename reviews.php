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
 * The reviews readers sent, waiting for the library: each published or not with a touch.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\reviews;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:desk');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/reviews.php', ['library' => $ref] +
    (optional_param('help', 0, PARAM_BOOL) ? ['help' => 1] : [])));

$library = catalogue::library($ref);
if (!$library) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

if (data_submitted() && confirm_sesskey()) {
    $publish = required_param('do', PARAM_ALPHA) === 'publish';
    reviews::decide(required_param('review', PARAM_INT), (int) $library->entityid, $publish, (int) $USER->id);
    redirect(new moodle_url($PAGE->url, ['done' => $publish ? 'published' : 'declined']));
}
$done = optional_param('done', '', PARAM_ALPHA);

$items = array_map(fn($r) => [
    'id' => (int) $r->id,
    'title' => format_string($r->title),
    'url' => (new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $r->recordid]))
        ->out(false),
    'text' => $r->text,
    'who' => $r->who,
    'forlittle' => $r->forlittle ? $str('reviewforlittleflag') : '',
    'drawing' => $r->drawing,
    'drawingalt' => $str('reviewdrawingof', $r->who),
    'when' => userdate((int) $r->timemodified, get_string('strftimedateshort', 'langconfig')),
], reviews::pending((int) $library->entityid));

shell::output($str('reviewstitle'), $OUTPUT->render_from_template('local_oksigeniaayvurenda/reviews', [
    'heading' => $str('reviewstitle'),
    'intro' => $str('reviewsintro'),
    'notice' => in_array($done, ['published', 'declined'], true) ? $str('reviews' . $done) : '',
    'items' => $items,
    'hasitems' => (bool) $items,
    'empty' => $str('reviewsempty'),
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'publish' => $str('reviewspublish'),
    'decline' => $str('reviewsdecline'),
]), $library, '', [
    'search' => false,
    'nav' => optional_param('help', 0, PARAM_BOOL) ? [] : [['url' => (new moodle_url(
        '/local/oksigeniaayvurenda/help.php',
        ['library' => $library->slug, 'piece' => 'reviews']
    ))->out(false), 'label' => $str('helpnav')]],
]);
