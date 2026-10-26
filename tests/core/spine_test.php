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
 * Tests for the spines on the shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\spine
 */
#[\PHPUnit\Framework\Attributes\CoversClass(spine::class)]
final class spine_test extends \basic_testcase {
    /**
     * The height from the centimetres the record gives, the thickness from its pages; and something steady when it
     * gives neither.
     */
    public function test_size(): void {
        $size = spine::size('120 p. : il. ; 24 cm', '', 1);
        $this->assertSame([24.0, 120, true], [$size['cm'], $size['pages'], $size['known']]);
        $this->assertSame(30, $size['width']);
        $size = spine::size('144', '20cm', 5);
        $this->assertSame([20.0, 144, true], [$size['cm'], $size['pages'], $size['known']]);
        $this->assertSame(230, spine::size('XII, 230 p.', '', 1)['pages']);
        $this->assertSame(30.0, spine::size('1 v. (sin paginar) ; 31 x 22 cm', '', 3)['cm']);
        $this->assertSame(18.0, spine::size('', '12 cm', 3)['cm']);
        $unknown = spine::size('', '', 7);
        $this->assertFalse($unknown['known']);
        $this->assertGreaterThanOrEqual(spine::MIN_CM, $unknown['cm']);
        $this->assertLessThanOrEqual(24.0, $unknown['cm']);
        $this->assertSame($unknown, spine::size('', '', 7));
        $this->assertSame(spine::MIN_PX, spine::size('8 p.', '', 1)['width']);
        $this->assertSame(spine::MAX_PX, spine::size('1500 p.', '', 1)['width']);
    }

    /**
     * Any colour becomes one that looks like a book, with letters that stand out 4.5:1; a grey cover takes the corner's.
     */
    public function test_colour(): void {
        $inks = ['cream' => [251, 246, 236], 'ink' => [30, 43, 42]];
        foreach ([[120, 80, 40], [250, 250, 20], [10, 10, 60], [255, 0, 0], [40, 200, 210], [200, 120, 200], null] as $i => $rgb) {
            $colour = spine::colour($rgb, '#e2654c', $i);
            [$l, $c] = spine::oklch(spine::rgb($colour['background']));
            $this->assertGreaterThanOrEqual(0.35, $l);
            $this->assertLessThanOrEqual(0.86, $l);
            $this->assertGreaterThanOrEqual(4.5, spine::contrast(spine::rgb($colour['background']), $inks[$colour['ink']]));
            $this->assertGreaterThanOrEqual(4.5, spine::contrast(spine::rgb($colour['dark']), $inks[$colour['ink']]));
        }
        // A grey cover is a corner's book: the hue of the corner, not grey.
        [, $c, $h] = spine::oklch(spine::rgb(spine::colour([128, 128, 128], '#17574c', 3)['background']));
        $this->assertGreaterThanOrEqual(0.05, $c);
        $this->assertEqualsWithDelta(spine::oklch(spine::rgb('#17574c'))[2], $h, 10);
    }

    /**
     * English titles run from the head down; Spanish ones, from the foot up; without a language, the person's.
     */
    public function test_top_down(): void {
        $this->assertTrue(spine::top_down('eng', 'es'));
        $this->assertFalse(spine::top_down('spa', 'en'));
        $this->assertTrue(spine::top_down('', 'en'));
        $this->assertFalse(spine::top_down('', 'es'));
    }

    /**
     * A long title is cut with an ellipsis; a short one stays whole.
     */
    public function test_fit(): void {
        $this->assertSame('Sol', spine::fit('Sol', 200, 13));
        $cut = spine::fit('El dragón que leía demasiados libros de noche', 60, 13);
        $this->assertStringEndsWith('…', $cut);
        $this->assertLessThan(20, mb_strlen($cut));
    }

    /**
     * A place called by a colour has that colour, lighter when pale and darker when dark; others have none.
     */
    public function test_place_colour(): void {
        $this->assertNotNull(spine::place_colour('Rojo'));
        $this->assertNull(spine::place_colour('Estantería circular 1'));
        $this->assertNull(spine::place_colour('Interior Infantil, 2ª estantería, 3ª balda'));
        $orange = spine::oklch(spine::rgb(spine::place_colour('Naranja')))[0];
        $this->assertGreaterThan($orange, spine::oklch(spine::rgb(spine::place_colour('Naranja Pálido')))[0]);
        $blue = spine::oklch(spine::rgb(spine::place_colour('Azul')))[0];
        $this->assertLessThan($blue, spine::oklch(spine::rgb(spine::place_colour('Azul oscuro')))[0]);
        $this->assertSame(spine::place_colour('blue'), spine::place_colour('AZUL'));
    }
}
