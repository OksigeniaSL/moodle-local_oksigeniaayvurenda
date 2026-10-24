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
 * Moodle callbacks.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves the daily copies to the library staff, the covers to whoever sees the catalogue, and the covers of books being
 * added to whoever adds them.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when the file is not served.
 */
function local_oksigeniaayvurenda_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;
    if ($context->contextlevel != CONTEXT_SYSTEM || !in_array($filearea, ['copies', 'cover', 'coverdraft'], true)) {
        return false;
    }
    require_login(null, false);
    $itemid = (int) array_shift($args);
    if ($filearea === 'copies') {
        require_capability('local/oksigeniaayvurenda:desk', $context);
    } else if ($filearea === 'cover') {
        require_capability('local/oksigeniaayvurenda:view', $context);
        $forcedownload = false;
    } else {
        require_capability('local/oksigeniaayvurenda:catalogue', $context);
        if ($itemid !== (int) $USER->id) {
            return false;
        }
        $forcedownload = false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_oksigeniaayvurenda', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, $filearea === 'cover' ? WEEKSECS : 0, 0, $forcedownload, $options);
}
