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
 * LTI 1.3: the platform starts the login here (OpenID Connect), and the browser goes back to it to authenticate.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use auth_lti\local\ltiadvantage\utility\cookie_helper;
use local_oksigeniaayvurenda\local\lti;
use local_oksigeniaayvurenda\local\ltitool;
use Packback\Lti1p3\LtiOidcLogin;

// The platform's user is not logged in here yet: this page starts that login.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

$request = [
    'iss' => required_param('iss', PARAM_RAW_TRIMMED),
    'login_hint' => required_param('login_hint', PARAM_RAW),
    'target_link_uri' => required_param('target_link_uri', PARAM_URL),
    'lti_message_hint' => optional_param('lti_message_hint', null, PARAM_RAW),
    'lti_deployment_id' => optional_param('lti_deployment_id', null, PARAM_RAW_TRIMMED),
    // Some platforms call it id.
    'client_id' => optional_param('client_id', optional_param('id', '', PARAM_RAW_TRIMMED), PARAM_RAW_TRIMMED),
];
$request = array_filter($request, fn($value) => $value !== null && $value !== '');
if (strtok($request['target_link_uri'], '?') !== ltitool::urls()['launch']) {
    throw new moodle_exception('ltitarget', 'local_oksigeniaayvurenda');
}
if (!isloggedin()) {
    $here = new moodle_url('/local/oksigeniaayvurenda/lti/login.php', $request);
    cookie_helper::do_cookie_check($here);
    if (!cookie_helper::cookies_supported()) {
        // Inside a frame the browser keeps the cookies from this site: in a window of its own, it works.
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url($here);
        $PAGE->set_pagelayout('popup');
        echo $OUTPUT->header();
        echo html_writer::tag('p', get_string('lticookies', 'local_oksigeniaayvurenda'));
        echo html_writer::link($here, get_string('ltinewwindow', 'local_oksigeniaayvurenda'), ['target' => '_blank']);
        echo $OUTPUT->footer();
        die();
    }
}
redirect(LtiOidcLogin::new(new lti\store(), new lti\cache(), new lti\cookie())
    ->getRedirectUrl($request['target_link_uri'], $request));
