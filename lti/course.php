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
 * LTI names and roles: a teacher who comes from a course brings its students to the library, with the group they say.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\ltitool;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../../config.php');

$launchid = required_param('launch', PARAM_RAW_TRIMMED);
require_login(null, false);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/lti/course.php', ['launch' => $launchid]));
$str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);

$launch = ltitool::kept($launchid, (int) $USER->id);
if (!$launch || ltitool::reader_type($launch->getLaunchData()) !== 'teacher') {
    throw new moodle_exception('ltipickgone', 'local_oksigeniaayvurenda');
}
$data = $launch->getLaunchData();
$course = ltitool::course($data);
if (!$course) {
    redirect(ltitool::arrive($data, (int) $USER->id));
}
$entityid = (int) ltitool::platform_of($data)->entityid;
$library = ltitool::library_of($entityid, $data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? []);
$home = ltitool::arrive($data, (int) $USER->id);
$notice = '';
$error = '';
if (data_submitted() && confirm_sesskey()) {
    $nogroup = optional_param('nogroup', 0, PARAM_BOOL);
    $group = $nogroup ? null : optional_param('group', '', PARAM_TEXT);
    try {
        $stats = ltitool::bring($course, $group);
        $notice = $str('lticoursebrought', (object) $stats);
        $course = ltitool::course($data);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}
$students = null;
if (!$notice) {
    try {
        $students = count(array_filter(ltitool::members($course), fn($m) => !empty($m['user_id'])
            && strcasecmp((string) ($m['status'] ?? 'Active'), 'Active') === 0
            && ltitool::type_of_roles((array) ($m['roles'] ?? [])) === 'student'));
    } catch (moodle_exception $e) {
        $error = $error ?: $e->getMessage();
    }
}
$template = [
    'heading' => $str('lticourseheading', format_string((string) $course->title)),
    'intro' => $students !== null ? $str('lticourseintro', $students) : '',
    'notice' => $notice,
    'error' => $error,
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'ask' => !$notice && $students,
    'grouplabel' => $str('lticoursegroup'),
    'grouphint' => $str('lticoursegrouphint'),
    'group' => (string) ($course->groupname ?? $course->title),
    'nogrouplabel' => $str('lticoursenogroup'),
    'bringlabel' => $str('lticoursebring'),
    'nightnote' => $str('lticoursenight'),
    'homeurl' => $home->out(false),
    'homelabel' => $notice ? $str('lticoursehome') : $str('lticourselater'),
];
shell::output(
    $template['heading'],
    $OUTPUT->render_from_template('local_oksigeniaayvurenda/lti_course', $template),
    $library,
    '',
    ['search' => false]
);
