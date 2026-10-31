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
use local_oksigeniaayvurenda\local\renewals;

/**
 * A reader gives one of their loans more time, from the app. When it cannot be, the app shows why (an error with the
 * library's words), as the desk would say it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class renew_loan extends external_api {
    /**
     * The loan.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'loan' => new external_value(PARAM_INT, 'The loan'),
        ]);
    }

    /**
     * Renews it, if the library lets the reader.
     *
     * @param int $loan
     * @return array ok, message and the new date.
     */
    public static function execute(int $loan): array {
        global $USER;
        ['loan' => $loanid] = self::validate_parameters(self::execute_parameters(), ['loan' => $loan]);
        self::validate_context(\context_system::instance());
        $answer = renewals::mine($loanid, (int) $USER->id);
        if (!$answer['ok']) {
            throw new \moodle_exception('renewfailed', 'local_oksigeniaayvurenda', '', $answer['message']);
        }
        return $answer;
    }

    /**
     * What happened.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether it was renewed'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the reader'),
            'due' => new external_value(PARAM_INT, 'The new date it is due back'),
        ]);
    }
}
