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
 * Tests for the phone as the reader of a desk.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\relay
 */
#[\PHPUnit\Framework\Attributes\CoversClass(relay::class)]
final class relay_test extends \advanced_testcase {
    /**
     * Codes reach their own person's desk, in order, once; old ones are forgotten.
     */
    public function test_push_pull(): void {
        global $DB;
        $this->resetAfterTest();
        $ana = $this->getDataGenerator()->create_user();
        $leo = $this->getDataGenerator()->create_user();
        relay::push($ana->id, '9788490000014');
        relay::push($ana->id, ' 100042S ');
        relay::push($ana->id, '');
        relay::push($leo->id, '10000H');
        $this->assertSame(['9788490000014', '100042S'], relay::pull($ana->id));
        $this->assertSame([], relay::pull($ana->id));
        $DB->set_field('local_oksigeniaayvurenda_relay', 'timecreated', time() - relay::WAIT - 1, ['userid' => $leo->id]);
        $this->assertSame([], relay::pull($leo->id));
        relay::forget();
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_relay'));
    }
}
