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
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\desk;
use local_oksigeniaayvurenda\local\stocktake;

/**
 * A copy found in a stocktake that was thought lent: returned there and then.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stocktake_return extends external_api {
    /**
     * The stocktake and the copy.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'stocktake' => new external_value(PARAM_INT, 'The stocktake'),
            'code' => new external_value(PARAM_RAW, 'The code of the copy'),
        ]);
    }

    /**
     * Returns it, for whoever counts and may lend.
     *
     * @param string $library
     * @param int $stocktake
     * @param string $code
     * @return array kind, message and how the stocktake goes.
     */
    public static function execute(string $library, int $stocktake, string $code): array {
        global $USER;
        $args = self::validate_parameters(
            self::execute_parameters(),
            ['library' => $library, 'stocktake' => $stocktake, 'code' => $code]
        );
        $where = catalogue::library($args['library']);
        if (!$where) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        self::validate_context(access::context($where));
        access::require('local/oksigeniaayvurenda:catalogue', $where);
        access::require('local/oksigeniaayvurenda:desk', $where);
        $counted = stocktake::get($args['stocktake'], $where);
        $returned = (new desk($where, (int) $USER->id))->scan(clean_param($args['code'], PARAM_TEXT));
        return ['kind' => $returned['kind'], 'message' => (string) $returned['message']] + stocktake_read::progress($counted);
    }

    /**
     * What the desk said, and how the stocktake goes.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'kind' => new external_value(PARAM_ALPHANUMEXT, 'What happened'),
            'message' => new external_value(PARAM_RAW, 'What to say, as plain text'),
        ] + stocktake_read::progress_returns());
    }
}
