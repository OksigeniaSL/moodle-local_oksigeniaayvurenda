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

namespace local_oksigeniaayvurenda\core;

/**
 * Tests for the rules of reservations.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\hold_rules
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hold_rules::class)]
final class hold_rules_test extends \basic_testcase {
    /**
     * Defaults, limits, and who may reserve online by stage.
     */
    public function test_settings_and_online(): void {
        $this->assertSame(hold_rules::DEFAULTS, hold_rules::settings([]));
        $this->assertSame(
            ['online' => true, 'stages' => ['Primaria'], 'days' => 30, 'max' => 1, 'renew' => false],
            hold_rules::settings(['online' => 1, 'stages' => ['Primaria', ' '], 'days' => 90, 'max' => 0])
        );
        $off = hold_rules::settings([]);
        $this->assertFalse(hold_rules::online($off, 'student', 'Primaria'));
        $all = hold_rules::settings(['online' => true]);
        $this->assertTrue(hold_rules::online($all, 'student', null));
        $some = hold_rules::settings(['online' => true, 'stages' => ['Educación Secundaria']]);
        $this->assertTrue(hold_rules::online($some, 'student', 'educacion secundaria'));
        $this->assertFalse(hold_rules::online($some, 'student', 'Infantil'));
        $this->assertFalse(hold_rules::online($some, 'student', null));
        $this->assertTrue(hold_rules::online($some, 'teacher', null));
    }

    /**
     * First come, first served.
     */
    public function test_queue(): void {
        $holds = [(object) ['id' => 7, 'timecreated' => 20], (object) ['id' => 3, 'timecreated' => 10],
            (object) ['id' => 5, 'timecreated' => 20]];
        $this->assertSame([3, 5, 7], array_map(fn($h) => $h->id, hold_rules::queue($holds)));
        $this->assertSame(0, hold_rules::ahead($holds, 3));
        $this->assertSame(2, hold_rules::ahead($holds, 7));
        $this->assertSame(3, hold_rules::ahead($holds, 99));
    }
}
