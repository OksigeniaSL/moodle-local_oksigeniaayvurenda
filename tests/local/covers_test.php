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
 * Tests for the covers.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\covers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(covers::class)]
final class covers_test extends \advanced_testcase {
    /**
     * A JPEG already as small as covers are kept goes as it is, without GD (some servers' GD reads no WebP); a bigger
     * one is made small; what is not a picture is nothing.
     */
    public function test_prepare(): void {
        $image = imagecreatetruecolor(200, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 90, 60));
        ob_start();
        imagejpeg($image, null, 85);
        $small = (string) ob_get_clean();
        $this->assertSame($small, covers::prepare($small));
        $big = imagecreatetruecolor(400, 600);
        ob_start();
        imagejpeg($big, null, 85);
        $prepared = covers::prepare((string) ob_get_clean());
        $this->assertSame(covers::HEIGHT, getimagesizefromstring($prepared)[1]);
        $this->assertNull(covers::prepare('not a picture'));
    }
}
