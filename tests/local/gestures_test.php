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
 * Tests for the gestures of a setting in sight: the stages as chips, a number and a lamp.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\gestures
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gestures::class)]
final class gestures_test extends \advanced_testcase {
    /**
     * A stage touched comes in or goes out; all of them, or none left, is all of them; nothing sent is nothing.
     */
    public function test_stage_touched(): void {
        $stages = ['Infantil', 'Primaria', 'Secundaria'];
        $_POST = [];
        $this->assertNull(gestures::stage_touched([], $stages));
        $_POST = ['stage' => '1'];
        $this->assertSame(['Primaria'], gestures::stage_touched([], $stages));
        $this->assertSame(['Infantil', 'Primaria'], gestures::stage_touched(['Infantil'], $stages));
        $this->assertSame([], gestures::stage_touched(['Primaria'], $stages));
        $this->assertSame([], gestures::stage_touched(['Infantil', 'Secundaria'], $stages));
        $_POST = ['stage' => 'all'];
        $this->assertSame([], gestures::stage_touched(['Infantil'], $stages));
        $_POST = ['stage' => '7'];
        $this->assertNull(gestures::stage_touched(['Infantil'], $stages));
        $_POST = [];
    }

    /**
     * A number with its limits and the label of its signs; a lamp sends the other value; a card has every key.
     */
    public function test_gestures(): void {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $step = gestures::step('f', 'days', 'Días', 5, 1, 30, 1, '5', 'días', $str);
        $this->assertSame([4, 6, false, false], [$step['less'], $step['more'], $step['atmin'], $step['atmax']]);
        $step = gestures::step('f', 'days', 'Días', 30, 1, 30, 5, '30', 'días', $str);
        $this->assertSame([25, 30, true], [$step['less'], $step['more'], $step['atmax']]);
        $this->assertSame(0, gestures::lamp('f', 'online', true, 'En línea')['value']);
        $this->assertSame(1, gestures::lamp('f', 'online', false, 'En línea')['value']);
        $card = gestures::card('c', 'set', 'Título', 'Frase', false, []);
        foreach (['notes', 'dates', 'presets', 'currency', 'link', 'help', 'text'] as $key) {
            $this->assertArrayHasKey($key, $card);
        }
        $_POST = ['days' => '3'];
        $this->assertSame(3, gestures::number('days'));
        $this->assertNull(gestures::number('max'));
        $_POST = [];
    }
}
