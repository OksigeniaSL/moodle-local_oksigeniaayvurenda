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
 * What the desk may do with a copy and a reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\desk;

use local_oksigeniaayvurenda\core\loan_rules;

/**
 * The answer of the desk to a loan: it goes ahead, or it stops with a reason. Some reasons stop only until the librarian
 * says so with a tap (the reader already has the books the rule allows, or has one late): the librarian knows the
 * child, and the screen helps that conversation, it does not replace it. Others never go ahead (a copy that is not
 * lent, one that is lost or being repaired).
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class policy {
    /** @var string The loan goes ahead. */
    public const OK = 'ok';

    /** @var string The rule of this reader and copy lends nothing. Never forced. */
    public const NOT_LENDABLE = 'notlendable';

    /** @var string The copy is lost, withdrawn or being repaired. Never forced. */
    public const UNAVAILABLE = 'unavailable';

    /** @var string The reader has as many loans as the rule allows. The librarian may go ahead. */
    public const LIMIT = 'limit';

    /** @var string The reader has a late loan. The librarian may go ahead. */
    public const LATE = 'late';

    /** @var string[] Reasons the librarian may override. */
    public const FORCEABLE = [self::LIMIT, self::LATE];

    /**
     * Whether a copy can be lent to a reader now.
     *
     * @param array|null $rule The loan rule for this reader type and copy type.
     * @param string $status The status of the copy (available, onloan…), which must not be on loan here.
     * @param int $openloans Loans the reader has out under the same rule.
     * @param int $lateloans Loans the reader has out past their due date.
     * @param bool $force The librarian said to go ahead.
     * @return string One of the constants.
     */
    public static function lend(?array $rule, string $status, int $openloans, int $lateloans, bool $force = false): string {
        if ($status !== 'available' && $status !== 'onloan') {
            return self::UNAVAILABLE;
        }
        if ($rule === null || (int) $rule['loandays'] <= 0) {
            return self::NOT_LENDABLE;
        }
        if (!$force && !loan_rules::can_lend($rule, $openloans)) {
            return self::LIMIT;
        }
        if (!$force && $lateloans > 0) {
            return self::LATE;
        }
        return self::OK;
    }
}
