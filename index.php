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
 * The home of a library: search, corners and what came in last.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$context = access::context($library);
shell::require('local/oksigeniaayvurenda:view', $library);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/index.php', $ref !== '' ? ['library' => $ref] : []));

// No library of the site's own yet: whoever may set one up gets the page that names it, even with the sample library
// there (which would otherwise be the only one, and open by itself).
$setup = $ref === '' && \local_oksigeniaayvurenda\local\libraries::none()
    && has_capability('local/oksigeniaayvurenda:manage', context_system::instance());
if (!$library || $setup) {
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/choose', screens::choose());
    shell::output(get_string('productname', 'local_oksigeniaayvurenda'), $html);
    die();
}
// A bank of textbooks is not a library to visit: its pupil sees the books lent for the year, and until when.
if (
    \local_oksigeniaayvurenda\local\bank::is($library) && !access::can('local/oksigeniaayvurenda:desk', $library)
        && !access::keeps($library)
) {
    $html = $OUTPUT->render_from_template('local_oksigeniaayvurenda/bank_mine', screens::bank_mine($library));
    shell::output(get_string('bankminetitle', 'local_oksigeniaayvurenda'), $html, $library, '', ['search' => false]);
    die();
}
// While the centre is closed to its readers, its staff's home is «To open your library»; folded, a line on top of the
// home the readers will see. «See it as the readers will» shows that home alone.
$asreader = optional_param('asreader', 0, PARAM_BOOL);
$steps = $asreader ? null : \local_oksigeniaayvurenda\local\opening::context($library, (int) $USER->id, 'ayv-open', true);
if ($steps && !$steps['folded']) {
    $link = fn(string $page, string $key, array $more = []) => '<a class="ayv-chip" href="' . (new moodle_url(
        '/local/oksigeniaayvurenda/' . $page,
        ['library' => $library->slug] + $more
    ))->out() . '">' . s(get_string($key, 'local_oksigeniaayvurenda')) . '</a>';
    $html = '<section class="ayv-staffhome" aria-label="' . s(get_string('openstaffhome', 'local_oksigeniaayvurenda')) . '">'
        . '<h1 class="ayv-sr">' . s(format_string($library->name, true, ['escape' => false])) . '</h1>'
        . $OUTPUT->render_from_template('local_oksigeniaayvurenda/open_steps', $steps)
        . '<nav class="ayv-staffhome-meanwhile" aria-label="' . s(get_string('openmeanwhile', 'local_oksigeniaayvurenda'))
        . '"><p>' . s(get_string('openmeanwhile', 'local_oksigeniaayvurenda')) . '</p>'
        . $link('index.php', 'openasreader', ['asreader' => 1]) . $link('desk.php', 'opendesk')
        . $link('catalogued.php', 'menucataloged') . '</nav></section>';
    shell::output(get_string('homeheading', 'local_oksigeniaayvurenda'), $html, $library, '', ['search' => false]);
    die();
}
$home = screens::home($library);
// A young reader (the card of the flower) gets one screen with three doors; «For the grown-ups» (todo=1), the whole home.
if (!$steps && $home['hero']['young'] && !optional_param('todo', 0, PARAM_BOOL)) {
    shell::output(
        get_string('homeheading', 'local_oksigeniaayvurenda'),
        $OUTPUT->render_from_template('local_oksigeniaayvurenda/home_little', screens::little($library, $home['hero'])),
        $library,
        '',
        ['search' => false]
    );
    die();
}
$html = ($steps ? $OUTPUT->render_from_template('local_oksigeniaayvurenda/open_steps', $steps) : '')
    . $OUTPUT->render_from_template('local_oksigeniaayvurenda/home', $home);
// The home has its own search, at the top: the phone's magnifier takes the focus there.
shell::output(get_string('homeheading', 'local_oksigeniaayvurenda'), $html, $library, '', ['search' => false, 'lens' => true]);
