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
 * LTI 1.3: the platform posts the launch here. Whoever comes enters with Moodle's LTI account, bound to the platform's
 * user, becomes a reader of the platform's entity and lands in its library, or in the book the link names.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\ltitool;
use local_oksigeniaayvurenda\local\plans;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

if (!is_enabled_auth('lti')) {
    throw new moodle_exception('ltiauthoff', 'local_oksigeniaayvurenda');
}
if (optional_param('id_token', '', PARAM_RAW) === '') {
    throw new moodle_exception('ltinotoken', 'local_oksigeniaayvurenda');
}
try {
    $launch = ltitool::launch($_POST);
} catch (\Packback\Lti1p3\LtiException $e) {
    throw new moodle_exception('ltifailed', 'local_oksigeniaayvurenda', '', $e->getMessage());
}
if (!$launch->isResourceLaunch() && !$launch->isDeepLinkLaunch()) {
    throw new moodle_exception('ltionlylinks', 'local_oksigeniaayvurenda');
}
$data = $launch->getLaunchData();
plans::require((int) ltitool::platform_of($data)->entityid, 'lti');
$auth = get_auth_plugin('lti');
$auth->complete_login(
    $data,
    new moodle_url('/local/oksigeniaayvurenda/lti/launch.php'),
    auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY
);
require_login(null, false);
if ($launch->isDeepLinkLaunch()) {
    // A teacher putting the library, or a book, in a course: they choose it first.
    ltitool::reader((int) ltitool::platform_of($data)->entityid, (int) $USER->id, ltitool::reader_type($data));
    redirect(new moodle_url('/local/oksigeniaayvurenda/lti/pick.php', ['launch' => $launch->getLaunchId()]));
}
// A teacher whose course lists its members is offered, the first time, to bring its students.
if (ltitool::reader_type($data) === 'teacher') {
    $course = ltitool::course($data);
    if ($course && $course->lastsync === null) {
        ltitool::reader((int) ltitool::platform_of($data)->entityid, (int) $USER->id, 'teacher');
        redirect(new moodle_url('/local/oksigeniaayvurenda/lti/course.php', ['launch' => $launch->getLaunchId()]));
    }
}
redirect(ltitool::arrive($data, (int) $USER->id));
