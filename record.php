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
 * A record of the catalogue, with its copies.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\screens;
use local_oksigeniaayvurenda\local\shell;

require(__DIR__ . '/../../config.php');

$ref = required_param('library', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);
require_login(null, false);
$context = context_system::instance();
shell::require('local/oksigeniaayvurenda:view');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $ref, 'id' => $id]));

$library = catalogue::library($ref);
$found = $library ? catalogue::record($id, $library) : null;
if (!$found) {
    throw new moodle_exception('recordnotfound', 'local_oksigeniaayvurenda');
}
// A reader who took the book home tells what they thought, or takes it back.
$do = optional_param('review', '', PARAM_ALPHA);
if ($do !== '' && data_submitted() && confirm_sesskey()) {
    $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $library->entityid, 'userid' => $USER->id,
        'status' => 'active']);
    $done = '';
    if ($reader && \local_oksigeniaayvurenda\local\reviews::can_write($id, (int) $reader->id)) {
        if ($do === 'send') {
            \local_oksigeniaayvurenda\local\reviews::write(
                (int) $library->entityid,
                $id,
                (int) $reader->id,
                optional_param('text', '', PARAM_TEXT),
                null,
                optional_param('forlittle', 0, PARAM_BOOL) && \local_oksigeniaayvurenda\local\cards::look_of($reader) !== 'flower'
            );
            $done = 'sent';
        } else if ($do === 'remove') {
            \local_oksigeniaayvurenda\local\reviews::remove($id, (int) $reader->id);
            $done = 'removed';
        }
    }
    redirect(new moodle_url($PAGE->url, ['reviewed' => $done], 'ayv-reviews'));
}
$data = screens::record($found, $library);
$data['reviews'] = screens::reviews($found, $library, optional_param('reviewed', '', PARAM_ALPHA));
$data['hold'] = screens::hold($found, $library, optional_param('held', '', PARAM_ALPHA));
$data['digital'] = screens::digital($found, $library);
shell::output($found['record']->title, $OUTPUT->render_from_template('local_oksigeniaayvurenda/record', $data), $library);
