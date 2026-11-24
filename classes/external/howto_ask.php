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
use local_oksigeniaayvurenda\local\howto;

/**
 * «What do you want to do?», answered as someone types in «More»: the tasks and settings that answer it, each with what to
 * do in a line, where to go, its guided help and, for a setting, what it is now.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class howto_ask extends external_api {
    /**
     * The library and what they ask.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'library' => new external_value(PARAM_ALPHANUMEXT, 'The library, by its short name'),
            'q' => new external_value(PARAM_RAW, 'What they ask, as they type it'),
        ]);
    }

    /**
     * Answers it, for the staff; nobody else gets any answer.
     *
     * @param string $library
     * @param string $q
     * @return array answers, and the words and icons they come with.
     */
    public static function execute(string $library, string $q): array {
        global $USER;
        $args = self::validate_parameters(self::execute_parameters(), ['library' => $library, 'q' => $q]);
        $where = catalogue::library($args['library']);
        self::validate_context(access::context($where));
        $query = trim(\core_text::substr(clean_param($args['q'], PARAM_TEXT), 0, 200));
        if (!$where || $query === '' || !howto::may_ask($where)) {
            return ['answers' => []];
        }
        return howto::answers($query, $where, (int) $USER->id);
    }

    /**
     * A way to somewhere.
     *
     * @param string $what
     * @return external_single_structure
     */
    private static function way(string $what): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_RAW, 'Where it leads'),
            'label' => new external_value(PARAM_RAW, 'What it says, as plain text'),
        ], $what, VALUE_OPTIONAL);
    }

    /**
     * The answers.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $text = fn(string $what) => new external_value(PARAM_RAW, $what . ', as plain text');
        $asked = fn(string $what) => new external_value(PARAM_RAW, $what, VALUE_OPTIONAL);
        return new external_single_structure([
            'answers' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Which task or setting'),
                'setting' => new external_value(PARAM_BOOL, 'Whether it is a setting'),
                'kind' => $text('Task or setting'),
                'text' => $text('What it is called'),
                'value' => $text('What the setting is now'),
                'sub' => $text('What to do, in a line'),
                'wait' => new external_value(PARAM_BOOL, 'Whether something has to come first'),
                'url' => new external_value(PARAM_RAW, 'Where to do it'),
                'go' => $text('What its button says'),
                'help' => new external_value(PARAM_RAW, 'Its guided help, if it has one', VALUE_REQUIRED, null, NULL_ALLOWED),
            ]), 'What answers it'),
            'icons' => new external_single_structure([
                'task' => new external_value(PARAM_RAW, 'The drawing of a task, from the plugin\'s template'),
                'setting' => new external_value(PARAM_RAW, 'The drawing of a setting'),
                'play' => new external_value(PARAM_RAW, 'The drawing of a guided help'),
            ], 'The drawings', VALUE_OPTIONAL),
            'title' => $asked('What they are called together'),
            'showlabel' => $asked('What the button of a guided help says'),
            'none' => $asked('What to say when nothing answers it'),
            'catalogue' => self::way('Looking it up in the catalogue'),
            'howto' => self::way('All the tasks'),
        ]);
    }
}
