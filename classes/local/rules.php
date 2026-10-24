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
 * The loan rules of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The rules of a library: for each kind of reader and kind of copy, how many days, how many at once and how many
 * renewals. «*» is any. A library without rules uses those of the library it hangs from, and the last one a plain
 * default; a library with rules lends only what they cover.
 */
final class rules {
    /** @var string The table. */
    private const TABLE = 'local_oksigeniaayvurenda_rule';

    /** @var array The rule material gets when a library with rules has none for it: a week, one at a time. */
    public const MATERIAL = ['readertype' => '*', 'itemtype' => 'device', 'loandays' => 7, 'maxloans' => 1, 'renewals' => 1,
        'renewdays' => 7];

    /**
     * The rules of a library, by reader type and copy type.
     *
     * @param int $libraryid
     * @return array[]
     */
    public static function of(int $libraryid): array {
        global $DB;
        return array_values(array_map(fn($r) => (array) $r, $DB->get_records(
            self::TABLE,
            ['libraryid' => $libraryid],
            'readertype, itemtype'
        )));
    }

    /**
     * Puts the rules of a library, all at once: rows with the same reader and copy type are one; days, limits and
     * renewals cannot be negative.
     *
     * @param int $libraryid
     * @param array[] $rows readertype, itemtype, loandays, maxloans, renewals, renewdays.
     * @return int Rules kept.
     */
    public static function save(int $libraryid, array $rows): int {
        global $DB;
        $now = time();
        $clean = [];
        foreach ($rows as $row) {
            $reader = trim((string) ($row['readertype'] ?? '')) ?: '*';
            $item = trim((string) ($row['itemtype'] ?? '')) ?: '*';
            $clean[$reader . '|' . $item] = [
                'libraryid' => $libraryid,
                'readertype' => \core_text::substr($reader, 0, 30),
                'itemtype' => \core_text::substr($item, 0, 30),
                'loandays' => max(0, min(9999, (int) ($row['loandays'] ?? 0))),
                'maxloans' => max(0, min(9999, (int) ($row['maxloans'] ?? 0))),
                'renewals' => max(0, min(99, (int) ($row['renewals'] ?? 0))),
                'renewdays' => max(0, min(9999, (int) ($row['renewdays'] ?? 0))),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE, ['libraryid' => $libraryid]);
        if ($clean) {
            $DB->insert_records(self::TABLE, array_values($clean));
        }
        $transaction->allow_commit();
        return count($clean);
    }

    /**
     * Makes sure material can be lent in a library that has rules: if none covers its kind, it gets one.
     *
     * @param int $libraryid
     * @param string $itemtype
     * @return bool Whether a rule was added.
     */
    public static function cover(int $libraryid, string $itemtype): bool {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['libraryid' => $libraryid])) {
            return false;
        }
        // Only a rule for any reader covers every reader.
        [$in, $params] = $DB->get_in_or_equal([$itemtype, '*'], SQL_PARAMS_NAMED);
        if (
            $DB->record_exists_select(
                self::TABLE,
                "libraryid = :libraryid AND readertype = '*' AND itemtype " . $in,
                $params + ['libraryid' => $libraryid]
            )
        ) {
            return false;
        }
        $now = time();
        $DB->insert_record(self::TABLE, ['libraryid' => $libraryid, 'itemtype' => $itemtype, 'timecreated' => $now,
            'timemodified' => $now] + self::MATERIAL);
        return true;
    }
}
