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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniaayvurenda\local\access;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\lots;

/**
 * Suggestions while filling a box: the free copies whose book has the words typed.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lot_suggest extends external_api {
    /**
     * The box and the words.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'lot' => new external_value(PARAM_INT, 'The box'),
            'q' => new external_value(PARAM_RAW, 'The words typed'),
        ]);
    }

    /**
     * Looks them up, if the box is still being filled.
     *
     * @param string $library
     * @param int $lot
     * @param string $q
     * @return array suggestions.
     */
    public static function execute(string $library, int $lot, string $q): array {
        $args = self::validate_parameters(self::execute_parameters(), ['library' => $library, 'lot' => $lot, 'q' => $q]);
        $where = catalogue::library($args['library']);
        if (!$where) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        self::validate_context(access::context($where));
        access::require('local/oksigeniaayvurenda:desk', $where);
        $box = lots::get($args['lot'], $where);
        $text = trim(clean_param($args['q'], PARAM_TEXT));
        return ['suggestions' => $box->status === 'open' && preg_match('/\p{L}{2,}/u', $text) ? lots::suggest($box, $text) : []];
    }

    /**
     * The copies.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'suggestions' => new external_multiple_structure(new external_single_structure([
                'barcode' => new external_value(PARAM_RAW, 'The code of the copy'),
                'title' => new external_value(PARAM_RAW, 'The title, as plain text'),
                'authors' => new external_value(PARAM_RAW, 'Who wrote it, as plain text'),
                'callnumber' => new external_value(PARAM_RAW, 'Its place, as plain text'),
            ]), 'Eight at most'),
        ]);
    }
}
