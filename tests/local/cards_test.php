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
 * Tests for what a card shows.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cards
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cards::class)]
final class cards_test extends \basic_testcase {
    /**
     * Each stage of the flower is drawn with its paths lit up to their level, its size and the words for a screen
     * reader; levels out of range are kept within 0 and 3.
     */
    public function test_flower_svg(): void {
        foreach ([0, 1, 2, 3] as $stage) {
            $svg = cards::flower_svg($stage, ['back' => 3, 'explore' => 1, 'tell' => 0, 'stay' => 9], 'Capullo & co', 160);
            $this->assertStringStartsWith('<svg width="160" height="160" focusable="false" class="ayv-flor"', $svg);
            foreach (['volver="3"', 'explorar="1"', 'contar="0"', 'quedarse="3"'] as $mark) {
                $this->assertStringContainsString('data-' . $mark, $svg);
            }
            $this->assertStringContainsString('aria-label="Capullo &amp; co"', $svg);
            $this->assertStringContainsString('<title>Capullo &amp; co</title>', $svg);
            $this->assertSame(1, substr_count($svg, '<svg'));
        }
    }
}
