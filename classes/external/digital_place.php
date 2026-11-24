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
use local_oksigeniaayvurenda\local\digital;

/**
 * Where someone is in a digital book or recording, and their bookmarks, kept for the next time.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class digital_place extends external_api {
    /**
     * The book or recording, and what to keep.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'item' => new external_value(PARAM_INT, 'The digital book or recording'),
            'position' => new external_value(PARAM_RAW, 'Where they are, if it is to be kept', VALUE_DEFAULT, null, NULL_ALLOWED),
            'marked' => new external_value(PARAM_BOOL, 'Whether the bookmarks are to be kept too', VALUE_DEFAULT, false),
            'bookmarks' => new external_multiple_structure(new external_single_structure([
                'place' => new external_value(PARAM_RAW, 'Where'),
                'label' => new external_value(PARAM_RAW, 'What they call it'),
            ]), 'The bookmarks they have now', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Keeps it, for who may read it.
     *
     * @param int $item
     * @param string|null $position
     * @param bool $marked
     * @param array $bookmarks
     * @return array ok.
     */
    public static function execute(int $item, ?string $position = null, bool $marked = false, array $bookmarks = []): array {
        global $DB, $USER;
        $args = self::validate_parameters(
            self::execute_parameters(),
            ['item' => $item, 'position' => $position, 'marked' => $marked, 'bookmarks' => $bookmarks]
        );
        self::validate_context(\context_system::instance());
        // Whoever comes without an account reads from the window: their place is kept by their browser, not in a row everyone
        // without an account would share.
        if (isguestuser()) {
            return ['ok' => false];
        }
        $read = $DB->get_record('local_oksigeniaayvurenda_digital', ['id' => $args['item']], '*', MUST_EXIST);
        self::validate_context(access::context((int) $read->entityid));
        access::require('local/oksigeniaayvurenda:view', (int) $read->entityid);
        if (!digital::visible($read, digital::viewer((int) $read->entityid, (int) $USER->id))) {
            return ['ok' => false];
        }
        digital::keep_place(
            (int) $read->id,
            (int) $USER->id,
            $args['position'] === null ? null : trim($args['position']),
            $args['marked'] ? $args['bookmarks'] : null
        );
        return ['ok' => true];
    }

    /**
     * Whether it was kept.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Whether it was kept'),
        ]);
    }
}
