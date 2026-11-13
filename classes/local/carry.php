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
 * «Llévatelo» (P1 of the review of 12 November): the hidden book of the visit, found and then taken home from the desk.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The hidden book could not be failed and kept nothing. Now a game starts with three lanterns and each wrong spine puts
 * one out; it always ends. A reader who then takes that book home from the desk within the week earns a seal on their
 * card: plain, or golden when found with a lantern still lit. The result is theirs alone.
 */
final class carry {
    /** @var string The table. */
    private const T = 'local_oksigeniaayvurenda_find';

    /** @var int The lanterns of a game. */
    public const LANTERNS = 3;

    /** @var int How long a book found waits to be taken home. */
    public const DAYS = 7;

    /** @var int[] Books found and taken for each level of the seal. */
    public const LEVELS = [1, 3, 10];

    /**
     * Keeps a book found in the visit: only the one hidden for this reader in that corner and turn today (or the next
     * turns, as the visit looks for one with a cover), once a day.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param string $corner
     * @param int $turn
     * @param int $recordid
     * @param int $lanterns Still lit, of three.
     * @param int $now
     * @return bool Whether it was kept.
     */
    public static function found(
        \stdClass $library,
        int $readerid,
        string $corner,
        int $turn,
        int $recordid,
        int $lanterns,
        int $now
    ): bool {
        global $DB;
        $hidden = false;
        for ($try = 0; $try < 5 && !$hidden; $try++) {
            $hidden = hunt::target($library, $corner, $readerid, max(0, $turn) + $try, $now) === $recordid;
        }
        if (!$hidden) {
            return false;
        }
        if (
            $DB->record_exists_select(
                self::T,
                'readerid = :readerid AND recordid = :recordid AND timefound > :since',
                ['readerid' => $readerid, 'recordid' => $recordid, 'since' => $now - DAYSECS]
            )
        ) {
            return true;
        }
        $DB->insert_record(self::T, ['entityid' => $library->entityid, 'readerid' => $readerid, 'recordid' => $recordid,
            'lanterns' => max(0, min(self::LANTERNS, $lanterns)), 'timefound' => $now, 'timetaken' => null]);
        return true;
    }

    /**
     * A book lent at the desk: if this reader found it in the visit this week and has not taken it yet, now they have.
     *
     * @param int $readerid
     * @param int $recordid
     * @param int $now
     * @return bool Whether it was a book found (the desk celebrates it).
     */
    public static function taken(int $readerid, int $recordid, int $now): bool {
        global $DB;
        $finds = $DB->get_records_select(
            self::T,
            'readerid = :readerid AND recordid = :recordid AND timetaken IS NULL AND timefound > :since',
            ['readerid' => $readerid, 'recordid' => $recordid, 'since' => $now - self::DAYS * DAYSECS],
            'lanterns DESC, timefound DESC',
            'id',
            0,
            1
        );
        if (!$finds) {
            return false;
        }
        $DB->set_field(self::T, 'timetaken', $now, ['id' => reset($finds)->id]);
        return true;
    }

    /**
     * What a screen reader says of the seal.
     *
     * @param array $seal As seal() gives it.
     * @param callable $str
     * @return string
     */
    public static function says(array $seal, callable $str): string {
        if (!$seal['level']) {
            return $str('cardsealwaiting');
        }
        return $seal['gold'] ? $str('cardsealgold', $seal['lit']) : $str('cardsealplain', $seal['taken']);
    }

    /**
     * The seal of a reader's card: its level by the books found and taken (1, 3, 10), golden when as many were taken with
     * a lantern still lit. It never goes down.
     *
     * @param int $readerid
     * @return array level (0 to 3), gold, taken (books found and taken) and lit (of them, with a lantern).
     */
    public static function seal(int $readerid): array {
        global $DB;
        $taken = $DB->count_records_select(self::T, 'readerid = :readerid AND timetaken IS NOT NULL', ['readerid' => $readerid]);
        $lit = $DB->count_records_select(
            self::T,
            'readerid = :readerid AND timetaken IS NOT NULL AND lanterns > 0',
            ['readerid' => $readerid]
        );
        $level = count(array_filter(self::LEVELS, fn($n) => $taken >= $n));
        return ['level' => $level, 'gold' => $level > 0 && $lit >= self::LEVELS[$level - 1], 'taken' => $taken, 'lit' => $lit];
    }
}
