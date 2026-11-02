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

namespace local_oksigeniaayvurenda\local;
namespace local_oksigeniaayvurenda\core\desk;

/**
 * Tests for what a late book costs.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\desk\sanction_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sanction_rules::class)]
final class sanction_rules_test extends \basic_testcase {
    /**
     * Off unless a centre says otherwise; what comes in stays within reason.
     */
    public function test_settings(): void {
        $this->assertSame(sanction_rules::NONE, sanction_rules::settings([])['mode']);
        $this->assertSame(sanction_rules::NONE, sanction_rules::settings(['mode' => 'whatever'])['mode']);
        $settings = sanction_rules::settings(['mode' => 'days', 'perday' => 0, 'maxdays' => 9999, 'grace' => -3, 'cents' => 0,
            'currency' => 'nope']);
        $this->assertSame(1, $settings['perday']);
        $this->assertSame(365, $settings['maxdays']);
        $this->assertSame(0, $settings['grace']);
        $this->assertSame(1, $settings['cents']);
        $this->assertSame('EUR', $settings['currency']);
        $this->assertSame('USD', sanction_rules::settings(['currency' => 'usd'])['currency']);
    }

    /**
     * For each day late past the days of grace, up to the ceiling.
     */
    public function test_days_and_cents(): void {
        $settings = sanction_rules::settings(['grace' => 2, 'perday' => 2, 'maxdays' => 10, 'cents' => 25, 'maxcents' => 100]);
        $this->assertSame(0, sanction_rules::days($settings, 2));
        $this->assertSame(2, sanction_rules::days($settings, 3));
        $this->assertSame(10, sanction_rules::days($settings, 30));
        $this->assertSame(0, sanction_rules::cents($settings, 2));
        $this->assertSame(50, sanction_rules::cents($settings, 4));
        $this->assertSame(100, sanction_rules::cents($settings, 30));
    }

    /**
     * Who answers for a late book.
     */
    public function test_applies(): void {
        $off = sanction_rules::settings([]);
        $this->assertFalse(sanction_rules::applies($off, 'student', null));
        $all = sanction_rules::settings(['mode' => 'days']);
        $this->assertTrue(sanction_rules::applies($all, 'student', null));
        $this->assertTrue(sanction_rules::applies($all, 'teacher', null));
        $this->assertFalse(sanction_rules::applies($all, 'library', null), 'a library that borrows answers to nobody here');
        $some = sanction_rules::settings(['mode' => 'fine', 'stages' => ['Secundaria'], 'adults' => false]);
        $this->assertTrue(sanction_rules::applies($some, 'student', 'secundaria'));
        $this->assertFalse(sanction_rules::applies($some, 'student', 'Primaria'));
        $this->assertFalse(sanction_rules::applies($some, 'student', null));
        $this->assertFalse(sanction_rules::applies($some, 'teacher', null));
    }

    /**
     * An amount as a form gives it.
     */
    public function test_to_cents(): void {
        $this->assertSame(10, sanction_rules::to_cents('0,10'));
        $this->assertSame(10, sanction_rules::to_cents('0.10'));
        $this->assertSame(200, sanction_rules::to_cents('2'));
        $this->assertSame(0, sanction_rules::to_cents('nada'));
        $this->assertSame(0, sanction_rules::to_cents('-3'));
    }
}
