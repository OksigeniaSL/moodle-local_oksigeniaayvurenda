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

namespace local_oksigeniaayvurenda\core\desk;

/**
 * Tests for what the desk may do.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\desk\policy
 */
#[\PHPUnit\Framework\Attributes\CoversClass(policy::class)]
final class policy_test extends \basic_testcase {
    /** @var array One book at a time, for a week. */
    private const RULE = ['readertype' => 'student', 'itemtype' => 'normal', 'loandays' => 7, 'maxloans' => 1,
        'renewals' => 2, 'renewdays' => 7];

    /**
     * Free, within the limit and nothing late: it goes ahead.
     */
    public function test_ok(): void {
        $this->assertSame(policy::OK, policy::lend(self::RULE, 'available', 0, 0));
    }

    /**
     * The limit and a late book stop until the librarian says so; a copy not lent or lost never goes.
     */
    public function test_stops(): void {
        $this->assertSame(policy::LIMIT, policy::lend(self::RULE, 'available', 1, 0));
        $this->assertSame(policy::OK, policy::lend(self::RULE, 'available', 1, 0, true));
        $this->assertSame(policy::LATE, policy::lend(self::RULE, 'available', 0, 1));
        $this->assertSame(policy::OK, policy::lend(self::RULE, 'available', 0, 1, true));
        $this->assertSame(policy::NOT_LENDABLE, policy::lend(['loandays' => 0] + self::RULE, 'available', 0, 0, true));
        $this->assertSame(policy::NOT_LENDABLE, policy::lend(null, 'available', 0, 0, true));
        $this->assertSame(policy::UNAVAILABLE, policy::lend(self::RULE, 'lost', 0, 0, true));
        $this->assertContains(policy::LIMIT, policy::FORCEABLE);
        $this->assertNotContains(policy::NOT_LENDABLE, policy::FORCEABLE);
    }
}
