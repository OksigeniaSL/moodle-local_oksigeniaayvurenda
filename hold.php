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
 * Reserving a book, or cancelling a reservation: by the reader online, or by the staff for a reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\holds;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

require_login(null, false);
require_sesskey();
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:view');
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$action = required_param('action', PARAM_ALPHA);
$recordid = optional_param('record', 0, PARAM_INT);
$back = optional_param('back', '', PARAM_ALPHA);
$now = time();
$isdesk = has_capability('local/oksigeniaayvurenda:desk', $context);
$mine = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
    'status' => 'active'], 'id', IGNORE_MULTIPLE);
$notice = '';
$params = [];
if ($action === 'ask') {
    if (!$mine || !holds::online($library, (int) $mine->id, $now)) {
        $notice = 'holdnotallowed';
    } else {
        $result = holds::add($library, $recordid, (int) $mine->id, (int) $USER->id, $now);
        $notice = $result['ok'] ? 'holdadded' : 'hold' . $result['reason'];
    }
} else if ($action === 'for') {
    shell::require('local/oksigeniaayvurenda:desk');
    $text = trim(required_param('reader', PARAM_TEXT));
    $desk = new desk($library, (int) $USER->id, $now);
    $readerid = (int) $DB->get_field_select('local_oksigeniaayvurenda_reader', 'id', 'entityid = :entityid AND ' .
        $DB->sql_equal('cardcode', ':code', false) . " AND status = 'active'", ['entityid' => $library->entityid,
        'code' => $text], IGNORE_MULTIPLE);
    if (!$readerid) {
        $found = $desk->find_readers($text);
        $readerid = count($found) === 1 ? (int) $found[0]['id'] : 0;
        $notice = count($found) > 1 ? 'holdmanyreaders' : 'holdnoreader';
    }
    if ($readerid) {
        $result = holds::add($library, $recordid, $readerid, (int) $USER->id, $now);
        $notice = $result['ok'] ? 'holdaddedfor' : 'hold' . $result['reason'] . 'for';
        if ($result['ok']) {
            $params['who'] = $desk->reader($readerid)['name'];
        }
    }
} else if ($action === 'cancel') {
    $hold = $DB->get_record('local_oksigeniaayvurenda_hold', ['id' => required_param('hold', PARAM_INT),
        'libraryid' => $library->id], '*', MUST_EXIST);
    if (!$isdesk && (!$mine || (int) $hold->readerid !== (int) $mine->id)) {
        throw new required_capability_exception($context, 'local/oksigeniaayvurenda:desk', 'nopermissions', '');
    }
    holds::cancel((int) $hold->id, $now);
    $recordid = (int) $hold->recordid;
    $notice = 'holdcancelled';
}
if ($back === 'holds' && $isdesk) {
    redirect(new moodle_url('/local/oksigeniaayvurenda/holds.php', ['library' => $library->slug, 'notice' => $notice]));
}
redirect(new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid,
    'held' => $notice] + $params));
