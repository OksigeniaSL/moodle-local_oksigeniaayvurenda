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
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\relay;

/**
 * A code read with a phone, on its way to the desk or the adding screen the same person has open on a computer.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class relay_push extends external_api {
    /**
     * The code.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'code' => new external_value(PARAM_RAW, 'The code, as the phone read it'),
        ]);
    }

    /**
     * For whoever works at a desk or catalogues anywhere: the phone sends codes to its own user's screens.
     */
    public static function allowed(): void {
        if (!access::can('local/oksigeniaayvurenda:desk', null)) {
            access::require('local/oksigeniaayvurenda:catalogue', null);
        }
    }

    /**
     * Leaves it for their other screen.
     *
     * @param string $code
     * @return array ok.
     */
    public static function execute(string $code): array {
        global $USER;
        $args = self::validate_parameters(self::execute_parameters(), ['code' => $code]);
        self::validate_context(\context_system::instance());
        self::allowed();
        relay::push((int) $USER->id, clean_param($args['code'], PARAM_TEXT));
        return ['ok' => true];
    }

    /**
     * Whether it was left.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether it was left'),
        ]);
    }
}
