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

namespace local_oksigeniaayvurenda\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniaayvurenda\local\holds;

/**
 * A reader lets go of a book they asked for, from the app: the same as «I do not want it any more» on its record. A copy
 * set aside for them goes to the next reader, or back to the shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cancel_hold extends external_api {
    /**
     * The reservation.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'hold' => new external_value(PARAM_INT, 'The reservation'),
        ]);
    }

    /**
     * Cancels it, if it is the person's own and still alive.
     *
     * @param int $hold
     * @return array ok.
     */
    public static function execute(int $hold): array {
        global $DB, $USER;
        ['hold' => $holdid] = self::validate_parameters(self::execute_parameters(), ['hold' => $hold]);
        self::validate_context(\context_system::instance());
        [$open, $params] = $DB->get_in_or_equal(holds::OPEN, SQL_PARAMS_NAMED);
        $mine = $DB->record_exists_sql(
            "SELECT 1
               FROM {local_oksigeniaayvurenda_hold} h
               JOIN {local_oksigeniaayvurenda_reader} r ON r.id = h.readerid
              WHERE h.id = :id AND r.userid = :userid AND h.status $open",
            $params + ['id' => $holdid, 'userid' => $USER->id]
        );
        if (!$mine) {
            throw new \moodle_exception('holdnotyours', 'local_oksigeniaayvurenda');
        }
        holds::cancel($holdid);
        return ['ok' => true];
    }

    /**
     * Whether it was cancelled.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether it was cancelled'),
        ]);
    }
}
