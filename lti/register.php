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
 * LTI dynamic registration: a platform opens this address (the one the library page gives) and both register each
 * other in one go. A small page of its own, without Moodle's headers or cookies, since the platform shows it in a frame.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\ltitool;

define('NO_MOODLE_COOKIES', true);

// The platform's administrator is not a user of this site: the address itself is the permission.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

$token = optional_param('k', '', PARAM_ALPHANUM);
$configurl = optional_param('openid_configuration', '', PARAM_URL);
$regtoken = optional_param('registration_token', '', PARAM_RAW_TRIMMED);
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
$entityid = ltitool::registration_entity($token);
$done = false;
if (!$entityid) {
    $message = $str('ltiregisterexpired');
} else if ($configurl === '') {
    $message = $str('ltiregisterhow');
} else {
    try {
        $platform = ltitool::register($entityid, $configurl, $regtoken);
        $message = $str('ltiregistered', $platform->name);
        $done = true;
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
    }
}
@header('Content-Type: text/html; charset=utf-8');
$lang = current_language();
echo '<!doctype html><html lang="' . s($lang) . '"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . s($str('ltiregistertitle')) . '</title>'
    . '<style>body{margin:0;padding:24px;font:16px/1.5 system-ui,sans-serif;background:#faf6ee;color:#1f2a24}'
    . 'p{margin:0 0 12px}.ok{color:#1d5c4a;font-weight:700}@media (prefers-color-scheme: dark){'
    . 'body{background:#16201b;color:#eef2ee}.ok{color:#8fd3b6}}</style></head><body>'
    . '<p class="' . ($done ? 'ok' : '') . '" role="status">' . s($message) . '</p>'
    . ($done ? '<p>' . s($str('ltiregisterclose')) . '</p><script>(window.opener || window.parent).postMessage('
        . '{subject: "org.imsglobal.lti.close"}, "*");</script>' : '')
    . '</body></html>';
