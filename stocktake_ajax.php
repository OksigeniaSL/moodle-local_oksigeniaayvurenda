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
 * What the stocktake screen asks the server: the codes read (several at a time, as the phone sends them when it has
 * network again), or returning a copy that was thought lent.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\stocktake;

require_login(null, false);
require_sesskey();
$library = catalogue::library(required_param('library', PARAM_ALPHANUMEXT));
access::require('local/oksigeniaayvurenda:catalogue', $library);
if (!$library) {
    throw new moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
}
$stocktake = stocktake::get(required_param('id', PARAM_INT), $library);
$action = required_param('action', PARAM_ALPHA);
$answer = [];
if ($action === 'read') {
    if ($stocktake->status !== 'open') {
        throw new moodle_exception('stocktakeclosed', 'local_oksigeniaayvurenda');
    }
    $reads = json_decode(required_param('reads', PARAM_RAW), true);
    $answers = [];
    foreach (array_slice(is_array($reads) ? $reads : [], 0, 200) as $read) {
        $code = clean_param((string) ($read['code'] ?? ''), PARAM_TEXT);
        $here = (int) ($read['here'] ?? 0);
        $answers[] = stocktake::read($stocktake, $code, $here ?: null, (int) $USER->id);
    }
    $answer = ['answers' => $answers, 'counts' => stocktake::counts($stocktake)];
} else if ($action === 'return') {
    access::require('local/oksigeniaayvurenda:desk', $library);
    $returned = (new desk($library, (int) $USER->id))->scan(required_param('code', PARAM_TEXT));
    $answer = ['kind' => $returned['kind'], 'message' => $returned['message'], 'counts' => stocktake::counts($stocktake)];
}
echo json_encode($answer);
