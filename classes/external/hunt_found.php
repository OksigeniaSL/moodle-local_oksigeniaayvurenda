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
use local_oksigeniaayvurenda\local\carry;
use local_oksigeniaayvurenda\local\catalogue;
use local_oksigeniaayvurenda\local\orientation;

/**
 * «Llévatelo»: the hidden book a reader found in the visit, with the lanterns left, kept until they take it home.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hunt_found extends external_api {
    /**
     * The book, and where it was found.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'corner' => new external_value(PARAM_ALPHANUM, 'The room'),
            'turn' => new external_value(PARAM_INT, 'Which hidden book of today'),
            'record' => new external_value(PARAM_INT, 'The book'),
            'lanterns' => new external_value(PARAM_INT, 'The lanterns still lit'),
        ]);
    }

    /**
     * Keeps it, for a reader with a card.
     *
     * @param string $library
     * @param string $corner
     * @param int $turn
     * @param int $record
     * @param int $lanterns
     * @return array kept, and how the orientation goes, if they are doing it.
     */
    public static function execute(string $library, string $corner, int $turn, int $record, int $lanterns): array {
        global $DB, $OUTPUT, $USER;
        $args = self::validate_parameters(
            self::execute_parameters(),
            ['library' => $library, 'corner' => $corner, 'turn' => $turn, 'record' => $record, 'lanterns' => $lanterns]
        );
        $where = catalogue::library($args['library']);
        if (!$where) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        self::validate_context(access::context($where));
        access::require('local/oksigeniaayvurenda:view', $where);
        // Only a reader with a card keeps what they found: the game is the same for everyone else.
        $reader = $DB->get_record(
            'local_oksigeniaayvurenda_reader',
            ['entityid' => $where->entityid, 'userid' => $USER->id, 'status' => 'active'],
            '*',
            IGNORE_MULTIPLE
        );
        $kept = $reader && carry::found(
            $where,
            (int) $reader->id,
            $args['corner'],
            $args['turn'],
            $args['record'],
            $args['lanterns'],
            time()
        );
        // A room more of the orientation, and its badge with the third: what the game shows, drawn here.
        $route = $kept ? orientation::step($reader, time()) : null;
        if (!$route) {
            return ['kept' => $kept];
        }
        $shown = $route['left'] > 0 || $route['finished'] ? $OUTPUT->render_from_template(
            'local_oksigeniaayvurenda/orientation_step',
            orientation::shown($reader, $where, $route) + ['avance' => null, 'insignia' => null]
        ) : '';
        return ['kept' => $kept, 'orientation' => ['done' => $route['done'], 'left' => $route['left'],
            'finished' => $route['finished'], 'says' => orientation::says($route), 'html' => $shown]];
    }

    /**
     * Whether it was kept, and how the orientation goes.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'kept' => new external_value(PARAM_BOOL, 'Whether it was kept'),
            'orientation' => new external_single_structure([
                'done' => new external_value(PARAM_INT, 'The rooms so far'),
                'left' => new external_value(PARAM_INT, 'The rooms left'),
                'finished' => new external_value(PARAM_BOOL, 'Whether this was the last'),
                'says' => new external_value(PARAM_RAW, 'What to say, as plain text'),
                'html' => new external_value(PARAM_RAW, 'The path or the badge, from the plugin\'s template'),
            ], 'The orientation, if the reader is doing it', VALUE_OPTIONAL),
        ]);
    }
}
