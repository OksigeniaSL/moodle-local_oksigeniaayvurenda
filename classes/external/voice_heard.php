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
use local_oksigeniaayvurenda\local\digital;
use local_oksigeniaayvurenda\local\voices;

/**
 * A voice heard for a while: one more listen, once per visit and day, and never who.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class voice_heard extends external_api {
    /**
     * The voice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'item' => new external_value(PARAM_INT, 'The voice heard'),
        ]);
    }

    /**
     * Counts it, where whoever asks may hear it.
     *
     * @param int $item
     * @return array ok: whether it was counted.
     */
    public static function execute(int $item): array {
        global $DB, $USER, $SESSION;
        $args = self::validate_parameters(self::execute_parameters(), ['item' => $item]);
        self::validate_context(\context_system::instance());
        $voice = $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $args['item'], 'kind' => 'voice']);
        if (!$voice) {
            return ['ok' => false];
        }
        self::validate_context(access::context((int) $voice->entityid));
        access::require('local/oksigeniaayvurenda:view', (int) $voice->entityid);
        if (!voices::can_hear($voice, digital::viewer((int) $voice->entityid, (int) $USER->id))) {
            return ['ok' => false];
        }
        return ['ok' => voices::heard($voice, (int) $USER->id, $SESSION)];
    }

    /**
     * Whether it was counted.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether it was counted'),
        ]);
    }
}
