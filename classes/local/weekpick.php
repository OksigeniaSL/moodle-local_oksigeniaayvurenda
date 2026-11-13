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

/**
 * The book of the week a librarian picks, with a line of their own (P9 of the review of 12 November).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The book of the week on the home was always the one most taken: now whoever works at the desk picks one from its
 * record with a line of their own, and for seven days the herald says it with their first name; then, again, the one
 * most taken. Kept in the library's settings: one pick per library.
 */
final class weekpick {
    /** @var int How long a pick lasts. */
    public const DAYS = 7;

    /** @var int The longest line. */
    public const PHRASE = 160;

    /**
     * The pick of a library while it lasts, with its record still in the catalogue.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null recordid, title, phrase, name (the first name of who picked it), until.
     */
    public static function of(\stdClass $library, int $now): ?array {
        global $DB;
        $pick = self::settings($library)['weekpick'] ?? null;
        if (!is_array($pick) || (int) ($pick['time'] ?? 0) + self::DAYS * DAYSECS <= $now) {
            return null;
        }
        $title = $DB->get_field('local_oksigeniaayvurenda_record', 'title', ['id' => (int) $pick['recordid'],
            'entityid' => $library->entityid]);
        if ($title === false) {
            return null;
        }
        $name = (string) $DB->get_field('user', 'firstname', ['id' => (int) $pick['userid'], 'deleted' => 0]);
        return ['recordid' => (int) $pick['recordid'], 'title' => (string) $title, 'phrase' => (string) $pick['phrase'],
            'name' => $name, 'until' => (int) $pick['time'] + self::DAYS * DAYSECS];
    }

    /**
     * Picks a book for this week, with its line: the one picked before goes.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param string $phrase
     * @param int $userid
     * @param int $now
     */
    public static function pick(\stdClass $library, int $recordid, string $phrase, int $userid, int $now): void {
        $phrase = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $phrase)), 0, self::PHRASE);
        self::save($library, ['recordid' => $recordid, 'phrase' => $phrase, 'userid' => $userid, 'time' => $now]);
    }

    /**
     * Takes the pick away: the home goes back to the book most taken.
     *
     * @param \stdClass $library
     */
    public static function clear(\stdClass $library): void {
        self::save($library, null);
    }

    /**
     * The library's settings as kept.
     *
     * @param \stdClass $library
     * @return array
     */
    private static function settings(\stdClass $library): array {
        global $DB;
        return json_decode((string) $DB->get_field('local_oksigeniaayvurenda_library', 'settings', ['id' => $library->id]), true)
            ?: [];
    }

    /**
     * Keeps the pick, or none.
     *
     * @param \stdClass $library
     * @param array|null $pick
     */
    private static function save(\stdClass $library, ?array $pick): void {
        global $DB;
        $all = self::settings($library);
        if ($pick === null) {
            unset($all['weekpick']);
        } else {
            $all['weekpick'] = $pick;
        }
        $DB->set_field('local_oksigeniaayvurenda_library', 'settings', json_encode($all), ['id' => $library->id]);
        $library->settings = json_encode($all);
    }
}
