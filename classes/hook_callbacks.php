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
 * Hook callbacks.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda;

use core\hook\after_config;
use core\hook\navigation\primary_extend;
use core\hook\output\before_http_headers;
use core\hook\output\before_standard_head_html_generation;
use local_oksigeniaayvurenda\local\onlylibrary;

/**
 * What the library adds to the pages of Moodle.
 */
final class hook_callbacks {
    /**
     * In the top bar of the site: «Biblioteca», when there is one and the user may look it up.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        global $DB;
        // A site that is only a library has no home, dashboard or courses to go to, except for whoever runs it.
        if (onlylibrary::on() && !onlylibrary::runs_the_site()) {
            foreach (['home', 'myhome', 'mycourses'] as $key) {
                $node = $hook->get_primaryview()->get($key);
                if ($node) {
                    $node->remove();
                }
            }
        }
        // The cheap question first: a site without libraries asks nothing more.
        if (!isloggedin() || isguestuser() || !$DB->record_exists('local_oksigeniaayvurenda_library', [])) {
            return;
        }
        // Not while every centre the person could see is still getting ready for its readers.
        if (!\local_oksigeniaayvurenda\local\access::sees_any()) {
            return;
        }
        $hook->get_primaryview()->add(
            get_string('navlibrary', 'local_oksigeniaayvurenda'),
            new \moodle_url('/local/oksigeniaayvurenda/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_oksigeniaayvurenda_library'
        );
    }

    /**
     * In a site that is only a library, the way in is the library's own. The platform's stays at login/index.php?loginredirect=0.
     *
     * @param after_config $hook
     */
    public static function after_config(after_config $hook): void {
        global $CFG;
        if (during_initial_install() || (defined('CLI_SCRIPT') && CLI_SCRIPT) || !onlylibrary::on()) {
            return;
        }
        $CFG->alternateloginurl = onlylibrary::entry()->out(false);
    }

    /**
     * In a site that is only a library, the pages of the platform lead to the library.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;
        if (during_initial_install() || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }
        $instead = onlylibrary::instead_of((string) $PAGE->pagetype);
        if ($instead) {
            redirect($instead);
        }
    }

    /**
     * In a site that is only a library, the pages left of the platform wear its colours.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $CFG;
        if (during_initial_install() || !onlylibrary::on()) {
            return;
        }
        $version = (int) get_config('local_oksigeniaayvurenda', 'version');
        $app = $CFG->wwwroot . '/local/oksigeniaayvurenda/app/';
        // The picture of the entry, also in the panel of the platform's own pages to get in (a forgotten password).
        $day = \local_oksigeniaayvurenda\local\screens::art('entrada/dia');
        $night = \local_oksigeniaayvurenda\local\screens::art('entrada/noche') ?? $day;
        $vars = '--ayv-emblem:url("' . s($app) . 'emblema.svg");' . ($day
            ? '--ayv-picture-day:url("' . s($day) . '");--ayv-picture-night:url("' . s($night) . '");' : '');
        $hook->add_html('<style>:root{' . $vars . '}</style><link rel="stylesheet" href="' . s($app) . 'sitio.css?v='
            . $version . '">' . (onlylibrary::runs_the_site() ? ''
            : '<style>.btn-footer-popover,#page-footer,.footer-popover{display:none!important}</style>'));
    }
}
