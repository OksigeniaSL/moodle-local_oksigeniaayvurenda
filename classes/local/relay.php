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
 * A phone as the reader of a desk.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Codes read with a phone's camera, on their way to the desk or the adding screen the same person has open on a
 * computer: for a desk without a scanner. The phone leaves each code here; the computer takes them, in order, and they
 * are gone. Nothing waits more than a few minutes.
 */
final class relay {
    /** @var string The table. */
    private const TABLE = 'local_oksigeniaayvurenda_relay';

    /** @var int How long a code waits to be taken. */
    public const WAIT = 5 * MINSECS;

    /**
     * Leaves a code read by the phone.
     *
     * @param int $userid
     * @param string $code
     */
    public static function push(int $userid, string $code): void {
        global $DB;
        $code = trim(\core_text::substr($code, 0, 200));
        if ($code !== '') {
            $DB->insert_record(self::TABLE, ['userid' => $userid, 'code' => $code, 'timecreated' => time()]);
        }
    }

    /**
     * Takes the codes waiting for a person, in order.
     *
     * @param int $userid
     * @return string[]
     */
    public static function pull(int $userid): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::TABLE,
            'userid = :userid AND timecreated > :since',
            ['userid' => $userid, 'since' => time() - self::WAIT],
            'id',
            'id, code'
        );
        if ($rows) {
            [$in, $params] = $DB->get_in_or_equal(array_keys($rows));
            $DB->delete_records_select(self::TABLE, 'id ' . $in, $params);
        }
        return array_values(array_map(fn($r) => $r->code, $rows));
    }

    /**
     * Forgets the codes nobody took.
     */
    public static function forget(): void {
        global $DB;
        $DB->delete_records_select(self::TABLE, 'timecreated <= :since', ['since' => time() - self::WAIT]);
    }
}
