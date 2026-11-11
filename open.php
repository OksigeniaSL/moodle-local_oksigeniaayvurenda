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
 * Opening a library to its readers, the last of the five steps; and what the list of steps keeps for each person.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\characters;
use local_oksigeniaayvurenda\local\opening;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = optional_param('library', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$library = catalogue::library($ref);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/open.php', $library ? ['library' => $library->slug] : []));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$do = optional_param('do', '', PARAM_ALPHA);

// Folding or unfolding the list is kept for the person, wherever they did it.
if ($do === 'fold') {
    require_sesskey();
    set_user_preference(opening::FOLDED, optional_param('folded', 0, PARAM_BOOL) ? 1 : null);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    die();
}
if (!$library) {
    shell::missing($str('librarynotfound'));
}
shell::require('local/oksigeniaayvurenda:desk', $library);
$entityid = (int) $library->entityid;
$home = new moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]);
if ($do !== '' && confirm_sesskey()) {
    access::require('local/oksigeniaayvurenda:manage', $entityid);
    if ($do === 'say') {
        opening::say($entityid, required_param('say', PARAM_ALPHA));
        redirect(new moodle_url(optional_param('back', $home->out(false), PARAM_LOCALURL)));
    }
    if ($do === 'open') {
        access::set_closed($entityid, false);
        redirect(new moodle_url('/local/oksigeniaayvurenda/open.php', ['library' => $library->slug]));
    }
}
$steps = opening::steps($library, (int) $USER->id);
$open = $steps['open']['done'];
$step = characters::step($entityid);
$figure = characters::whole($entityid) ? characters::figure('herald', $open ? 'celebra' : 'senala', $step) : null;
$puppet = $figure ? characters::puppet('herald', $open ? 'celebra' : 'senala', $step) : '';
$html = '<section class="ayv-open ayv-opening">';
if ($figure) {
    $html .= '<span class="ayv-figure ayv-opening-figure"><img src="' . s($figure) . '" alt="" width="512" height="512"' .
        ($puppet !== '' ? ' data-puppet="' . s($puppet) . '"' : '') . '></span>';
}
$html .= '<div class="ayv-opening-text">';
if ($open) {
    $html .= '<h1 class="ayv-results-title">' . s($str('openeddone')) . '</h1><p>' . s($str('openedafter')) . '</p>' .
        '<p class="ayv-book-actions"><a class="ayv-button" href="' . (new moodle_url(
            '/local/oksigeniaayvurenda/desk.php',
            ['library' => $library->slug]
        ))->out() . '">' . s($str('opendesk')) . '</a> <a class="ayv-button ayv-button-quiet" href="' . $home->out() . '">' .
        s($str('openhome')) . '</a></p>';
} else {
    $html .= '<h1 class="ayv-results-title">' . s($str('openpagetitle')) . '</h1><p>' . s($str('openpageintro')) . '</p>';
    if ($steps['open']['blocked']) {
        $html .= '<p class="ayv-open-meta ayv-open-meta-why">' . s($str('openblocked')) . '</p>';
    } else if (access::can('local/oksigeniaayvurenda:manage', $library)) {
        $html .= '<form method="post" action="' . $PAGE->url->out() . '"><input type="hidden" name="sesskey" value="' .
            sesskey() . '"><p class="ayv-book-actions"><button type="submit" class="ayv-button" name="do" value="open">' .
            s($str('openpagego')) . '</button></p></form>';
    } else {
        $html .= '<p class="ayv-open-meta ayv-open-meta-who">' . s($steps['open']['who']) . '</p>';
    }
    $html .= '<p class="ayv-more"><a class="ayv-button ayv-button-quiet" href="' . $home->out() . '">' . s($str('openhome')) .
        '</a></p>';
}
$html .= '</div></section>';
shell::output($str($open ? 'openeddone' : 'openpagetitle'), $html, $library, '', ['search' => false]);
