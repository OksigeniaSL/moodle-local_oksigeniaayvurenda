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
 * The entry of a site that is only a library: its own front door, instead of the platform's.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\onlylibrary;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

// phpcs:disable moodle.Files.RequireLogin.Missing -- This is the page where one logs in.
require(__DIR__ . '/../../config.php');

// Somebody already in goes to the library, and a site that is more than a library keeps its own entry.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/local/oksigeniaayvurenda/index.php'));
}
if (!onlylibrary::on()) {
    redirect(new moodle_url('/login/index.php', ['loginredirect' => 0]));
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(onlylibrary::entry());
$PAGE->set_pagelayout('embedded');

$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$error = optional_param('errorcode', 0, PARAM_INT);
// What an outside account (Google, Microsoft…) said when it could not let someone in: Moodle sends them here before
// showing it, so it is shown here, once.
$said = (string) ($SESSION->loginerrormsg ?? '');
unset($SESSION->loginerrormsg);
// The site's outside accounts, as Moodle's own entry shows them: this page is the entry of the site.
require_once($CFG->libdir . '/authlib.php');
$providers = \auth_plugin_base::prepare_identity_providers_for_output(
    \auth_plugin_base::get_identity_providers(get_enabled_auth_plugins()),
    $PAGE->get_renderer('core')
);
$library = onlylibrary::heading_for(optional_param('library', '', PARAM_ALPHANUMEXT), (string) ($SESSION->wantsurl ?? ''));
$look = $library ? \local_oksigeniaayvurenda\local\libraries::look($library) : null;
$languages = get_string_manager()->get_list_of_translations();
$data = [
    'site' => format_string($SITE->fullname, true, ['escape' => false]),
    'emblem' => (new moodle_url('/local/oksigeniaayvurenda/app/emblema.svg'))->out(false),
    'tagline' => $str('entrartagline'),
    'heading' => $library ? $str('entrarheadingnamed', format_string($library->name, true, ['escape' => false]))
        : $str('entrarheading'),
    'lead' => $look && $look['motto'] !== '' ? $look['motto'] : $str('entrarlead'),
    'error' => in_array($error, [1, 2, 3, 4], true) ? $str('entrarerror' . $error) : $said,
    'providers' => array_values(array_map(fn($p) => ['url' => (string) $p['url'], 'name' => (string) $p['name'],
        'iconurl' => (string) ($p['iconurl'] ?? '')], $providers)),
    'providersheading' => $str('entrarproviders'),
    'action' => (new moodle_url('/login/index.php'))->out(false),
    'logintoken' => \core\session\manager::get_login_token(),
    'username' => !empty($CFG->rememberusername) ? (string) get_moodle_cookie() : '',
    'userlabel' => $str(!empty($CFG->authloginviaemail) ? 'entraruseremail' : 'entraruser'),
    'passwordlabel' => $str('entrarpassword'),
    'go' => $str('entrargo'),
    'forgot' => (new moodle_url('/login/forgot_password.php'))->out(false),
    'forgotlabel' => $str('entrarforgot'),
    'noaccount' => $str('entrarnoaccount'),
    'applink' => onlylibrary::app_link(),
    'apphint' => $str('entrarapp'),
    'applabel' => $str('entrarappget'),
    'appnew' => $str('entrarappnew'),
    'day' => $look && $look['image'] ? $look['image'] : screens::art('entrada/dia'),
    'night' => $look && $look['image'] ? null : screens::art('entrada/noche'),
    'open' => onlylibrary::open_libraries(),
    'openheading' => $str('entraropen'),
    'openhint' => $str('entraropenhint'),
    'languages' => count($languages) > 1 && !empty($CFG->langmenu) ? array_map(
        fn($code, $name) => ['code' => $code, 'name' => trim(preg_replace('/\s*[-(\x{200e}].*$/u', '', html_entity_decode($name))),
            'current' => current_language() === $code,
            'url' => (new moodle_url(onlylibrary::entry(), ['lang' => $code]))->out(false)],
        array_keys($languages),
        array_values($languages)
    ) : [],
    'languagelabel' => $str('entrarlanguage'),
    'legal' => (string) get_config('local_oksigeniaayvurenda', 'libraryonlylegal'),
    'legallabel' => $str('entrarlegal'),
];
shell::standalone(
    $str('entrartitle') . ' · ' . $data['site'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/entrar', $data)
);
