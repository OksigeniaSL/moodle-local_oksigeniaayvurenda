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
use local_oksigeniaayvurenda\local\holds;

/**
 * A reader asks for a book that is out, from the app: the same as «Tell me when it is back» on its record.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ask_hold extends external_api {
    /**
     * The library and the book.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'record' => new external_value(PARAM_INT, 'The book'),
        ]);
    }

    /**
     * Asks for it, if the reader may ask online.
     *
     * @param string $library
     * @param int $record
     * @return array ok and what to say.
     */
    public static function execute(string $library, int $record): array {
        global $DB, $USER;
        ['library' => $slug, 'record' => $recordid] = self::validate_parameters(
            self::execute_parameters(),
            ['library' => $library, 'record' => $record]
        );
        $where = catalogue::library($slug);
        if (!$where) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        self::validate_context(access::context($where));
        if (!access::can('local/oksigeniaayvurenda:view', $where)) {
            throw new \required_capability_exception(access::context($where), 'local/oksigeniaayvurenda:view', 'nopermissions', '');
        }
        $now = time();
        $reader = $DB->get_record('local_oksigeniaayvurenda_reader', ['entityid' => $where->entityid, 'userid' => $USER->id,
            'status' => 'active'], 'id', IGNORE_MULTIPLE);
        if (!$reader || !holds::online($where, (int) $reader->id, $now)) {
            return ['ok' => false, 'message' => get_string('holdnotallowed', 'local_oksigeniaayvurenda')];
        }
        $result = holds::add($where, $recordid, (int) $reader->id, (int) $USER->id, $now);
        return ['ok' => $result['ok'], 'message' => get_string(
            $result['ok'] ? 'holdadded' : 'hold' . $result['reason'],
            'local_oksigeniaayvurenda'
        )];
    }

    /**
     * Whether it was asked for, and what to say.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether the book was asked for'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the reader'),
        ]);
    }
}
