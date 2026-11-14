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

/**
 * Tests for the shield against robots.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\shield
 */
#[\PHPUnit\Framework\Attributes\CoversClass(shield::class)]
final class shield_test extends \advanced_testcase {
    /**
     * An address without an account gets its door's pages a minute and no more; another address, the next minute, a
     * private address (a proxy's), someone signed in and a site that turned it off are not held back.
     */
    public function test_pass(): void {
        $this->resetAfterTest();
        $now = 1800000000;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $passed = 0;
        for ($i = 0; $i < shield::LIMITS['oai'] + 5; $i++) {
            $passed += (int) shield::pass('oai', $now);
        }
        $this->assertSame(shield::LIMITS['oai'], $passed);
        $this->assertFalse(shield::pass('oai', $now + 10));
        // Each door counts apart, and so does the next minute.
        $this->assertTrue(shield::pass('sru', $now));
        $this->assertTrue(shield::pass('oai', $now + 60));
        // Another address.
        $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
        $this->assertTrue(shield::pass('oai', $now + 10));
        // A private address is a proxy's: never counted.
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        for ($i = 0; $i < shield::LIMITS['oai'] + 5; $i++) {
            $this->assertTrue(shield::pass('oai', $now));
        }
        // Someone signed in is not counted.
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(shield::pass('oai', $now + 20));
        $this->setUser(null);
        // A site that turned it off.
        set_config('shield', '0', 'local_oksigeniaayvurenda');
        $this->assertTrue(shield::pass('oai', $now + 30));
    }
}
