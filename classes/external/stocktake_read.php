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
use local_oksigeniaayvurenda\local\stocktake;

/**
 * The codes read in a stocktake, several at a time, as the phone sends them when it has network again.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stocktake_read extends external_api {
    /** @var int How many codes are taken at once. */
    private const MOST = 200;

    /**
     * The stocktake and the codes.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'stocktake' => new external_value(PARAM_INT, 'The stocktake'),
            'reads' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_RAW, 'The code, as it was read'),
                'here' => new external_value(PARAM_INT, 'The shelf one stands at, or 0', VALUE_DEFAULT, 0),
            ]), 'What was read, in its order'),
        ]);
    }

    /**
     * Notes them, if the stocktake is open.
     *
     * @param string $library
     * @param int $stocktake
     * @param array $reads
     * @return array answers, one a code, and how the stocktake goes.
     */
    public static function execute(string $library, int $stocktake, array $reads): array {
        global $USER;
        $args = self::validate_parameters(
            self::execute_parameters(),
            ['library' => $library, 'stocktake' => $stocktake, 'reads' => $reads]
        );
        $where = catalogue::library($args['library']);
        if (!$where) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        self::validate_context(access::context($where));
        access::require('local/oksigeniaayvurenda:catalogue', $where);
        $open = stocktake::get($args['stocktake'], $where);
        if ($open->status !== 'open') {
            throw new \moodle_exception('stocktakeclosed', 'local_oksigeniaayvurenda');
        }
        $answers = [];
        foreach (array_slice($args['reads'], 0, self::MOST) as $read) {
            $answers[] = stocktake::read($open, clean_param($read['code'], PARAM_TEXT), $read['here'] ?: null, (int) $USER->id);
        }
        return ['answers' => $answers] + self::progress($open);
    }

    /**
     * How a stocktake goes, with what was read on each shelf, for the plan whose spines light up.
     *
     * @param \stdClass $stocktake
     * @return array counts and places.
     */
    public static function progress(\stdClass $stocktake): array {
        $places = [];
        foreach (stocktake::byplace($stocktake) as $place => $read) {
            $places[] = ['place' => $place, 'count' => $read['count'], 'seen' => $read['seen']];
        }
        return ['counts' => stocktake::counts($stocktake), 'places' => $places];
    }

    /**
     * What progress() gives, described.
     *
     * @return array
     */
    public static function progress_returns(): array {
        return [
            'counts' => new external_single_structure([
                'expected' => new external_value(PARAM_INT, 'Copies expected'),
                'seen' => new external_value(PARAM_INT, 'Copies read'),
                'missing' => new external_value(PARAM_INT, 'Expected on their shelf and not read'),
                'misplaced' => new external_value(PARAM_INT, 'Read on another shelf'),
                'lent' => new external_value(PARAM_INT, 'Read and lent'),
                'found' => new external_value(PARAM_INT, 'Read and lost'),
                'unknown' => new external_value(PARAM_INT, 'Codes of no copy'),
            ], 'How it goes'),
            'places' => new external_multiple_structure(new external_single_structure([
                'place' => new external_value(PARAM_INT, 'The shelf, or 0 for the copies without one'),
                'count' => new external_value(PARAM_INT, 'Its copies'),
                'seen' => new external_value(PARAM_INT, 'How many were read'),
            ]), 'What was read on each shelf'),
        ];
    }

    /**
     * What each code is, and how the stocktake goes.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'answers' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_RAW, 'The code'),
                'kind' => new external_value(PARAM_ALPHANUMEXT, 'What it is: ok, misplaced, lent, unknown…'),
                'title' => new external_value(PARAM_RAW, 'The title, as plain text'),
                'where' => new external_value(PARAM_RAW, 'Its place, as plain text'),
            ]), 'One a code, in the order they were read'),
        ] + self::progress_returns());
    }
}
