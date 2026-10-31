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
 * A reader renews their own loans online.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Where the library lets them (its switch and stages, as reserving online), a reader gives one of their loans more
 * time from the app or their dashboard, by the same rules as at the desk: as many times as their loan rules say, and
 * not while another reader waits for the book (unless the library says it may). Never in a textbook bank.
 */
final class renewals {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Renews a loan of the account's reader.
     *
     * @param int $loanid
     * @param int $userid
     * @param int|null $now
     * @return array ok, message (what to tell them) and due (the new date, when renewed).
     */
    public static function mine(int $loanid, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $loan = $DB->get_record_sql(
            "SELECT l.id, l.libraryid, l.readerid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
              WHERE l.id = :id AND r.userid = :userid AND r.status = 'active' AND l.timereturned IS NULL",
            ['id' => $loanid, 'userid' => $userid]
        );
        if (!$loan) {
            return ['ok' => false, 'message' => $str('renewnotyours'), 'due' => 0];
        }
        $library = $DB->get_record(self::T . 'library', ['id' => $loan->libraryid], '*', MUST_EXIST);
        if (!holds::renew_online($library, (int) $loan->readerid, $now)) {
            return ['ok' => false, 'message' => $str('renewnotallowed'), 'due' => 0];
        }
        $answer = (new desk($library, $userid, $now))->renew($loanid);
        $ok = $answer['kind'] === 'renewed';
        return ['ok' => $ok, 'message' => $answer['message'],
            'due' => $ok ? (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $loanid]) : 0];
    }
}
