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
        if (self::art()) {
            $this->markTestSkipped('The art package draws the card in layers: see test_layered.');
        }
        foreach ([0, 1, 2, 3] as $stage) {
            $svg = cards::flower_svg($stage, ['back' => 3, 'explore' => 1, 'tell' => 0, 'stay' => 9], 'Casi flor & co', 160);
            $this->assertStringStartsWith('<svg width="160" height="160" focusable="false" class="ayv-flor"', $svg);
            foreach (['volver="3"', 'explorar="1"', 'contar="0"', 'quedarse="3"'] as $mark) {
                $this->assertStringContainsString('data-' . $mark, $svg);
            }
            $this->assertStringContainsString('aria-label="Casi flor &amp; co"', $svg);
            $this->assertStringContainsString('<title>Casi flor &amp; co</title>', $svg);
            $this->assertSame(1, substr_count($svg, '<svg'));
        }
        // The sky, from twelve on: the same marks, and the sober style as a class.
        $sky = cards::sky_svg(2, ['back' => 1, 'explore' => 2, 'tell' => 3, 'stay' => 0], 'Media luna', 160, true);
        $this->assertStringContainsString('data-explorar="2"', $sky);
        $this->assertMatchesRegularExpression('/^<svg [^>]*class="ayv-cielo sobrio"/', $sky);
        $this->assertStringNotContainsString('sobrio"', cards::sky_svg(0, [], 'Luna nueva', 160, false));
        $this->assertStringContainsString('data-contar="3"', cards::sky_path_svg('tell', 3, 'La Voz', false));
        // The legend: the three marks of one path in a row.
        foreach (['back' => 'volver', 'explore' => 'explorar', 'tell' => 'contar', 'stay' => 'quedarse'] as $path => $attribute) {
            $svg = cards::path_svg($path, 2, 'Dos de tres');
            $this->assertStringStartsWith('<svg width="62" height="22"', $svg);
            $this->assertStringContainsString('data-' . $attribute . '="2"', $svg);
            $this->assertStringContainsString('aria-label="Dos de tres"', $svg);
        }
    }

    /**
     * With Gaudí's drawing in the art package: the base of the stage and each mark lit up to its path's level, waiting
     * beyond; the sober sky without the waiting ones; the small sky with the thicker marks.
     */
    public function test_layered(): void {
        if (!self::art()) {
            $this->markTestSkipped('Without the art package the card keeps its drawn SVG: see test_flower_svg.');
        }
        $html = cards::flower_svg(2, ['back' => 3, 'explore' => 1, 'tell' => 0, 'stay' => 9], 'Casi flor & co', 160);
        $this->assertStringStartsWith(
            '<span class="ayv-layered ayv-layered-flor" role="img" aria-label="Casi flor &amp; co"',
            $html
        );
        $this->assertStringContainsString('/pix/carne/flor/capullo.webp', $html);
        $this->assertSame(7, substr_count($html, 'class="ayv-layered-mark"'), 'three, one, none and three lit');
        $this->assertSame(5, substr_count($html, 'ayv-layered-waiting'));
        $sober = cards::sky_svg(3, ['back' => 1], 'Luna llena', 172, true);
        $this->assertStringContainsString('/pix/carne/cielo/llena.webp', $sober);
        $this->assertSame(0, substr_count($sober, 'ayv-layered-waiting'));
        $this->assertSame(1, substr_count($sober, 'ayv-layered-mark'));
        $this->assertStringContainsString('/pix/carne/cielo/pequeno/', cards::sky_svg(0, ['tell' => 2], 'Nueva', 132, false));
        $this->assertStringNotContainsString('/pequeno/', cards::sky_svg(0, ['tell' => 2], 'Nueva', 172, false));
    }

    /**
     * Whether the art package with the card's drawing is there.
     *
     * @return bool
     */
    private static function art(): bool {
        $dir = \core_component::get_component_directory('local_oksigeniaayvurendaarte');
        return $dir && is_readable($dir . '/pix/carne/flor/carne.json');
    }
}
