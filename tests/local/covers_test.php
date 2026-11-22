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

    /**
     * In the family's app a list carries each cover as narrow as it draws it, made once; the whole cover otherwise, and
     * nothing when it is bigger than the page may carry (the review of 21-11, A3).
     */
    public function test_inline(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid]);
        $this->assertSame('', covers::inline((int) $record->id));
        $image = imagecreatetruecolor(200, 300);
        for ($y = 0; $y < 300; $y += 3) {
            imageline($image, 0, $y, 199, $y, imagecolorallocate($image, ($y * 7) % 256, ($y * 13) % 256, ($y * 3) % 256));
        }
        ob_start();
        imagejpeg($image, null, 95);
        $this->assertTrue(covers::store((int) $record->id, (string) ob_get_clean(), 'test'));
        $whole = covers::inline((int) $record->id);
        $thumb = covers::inline((int) $record->id, 200 * 1024, 100);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $thumb);
        $this->assertSame(100, getimagesizefromstring(base64_decode(substr($thumb, 23)))[0]);
        $this->assertLessThan(strlen($whole), strlen($thumb));
        $this->assertSame($thumb, covers::inline((int) $record->id, 200 * 1024, 100), 'the same, kept');
        $this->assertSame('', covers::inline((int) $record->id, 10));
    }

    /**
     * A book without a cover asks for it once, in the background, and is not asked again for a while; a book with no ISBN,
     * or with a cover, is not asked.
     */
    public function test_seek_and_find(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $book = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Zeta con ISBN', 'isbn' => '9780306406157']);
        $plain = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Zeta sin ISBN']);
        $this->assertFalse(covers::seek((int) $plain->id));
        $this->assertTrue(covers::seek((int) $book->id));
        $this->assertFalse(covers::seek((int) $book->id));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks('\\local_oksigeniaayvurenda\\task\\find_cover'));

        // A picture with something in it: what Open Library gives when it has no cover is tiny.
        $image = imagecreatetruecolor(200, 300);
        for ($i = 0; $i < 400; $i++) {
            imagesetpixel($image, $i % 200, ($i * 7) % 300, imagecolorallocate($image, $i % 255, ($i * 3) % 255, ($i * 5) % 255));
        }
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();
        $this->assertGreaterThan(1000, strlen($jpeg));
        $asked = [];
        lookup::$fetcher = function (string $url) use (&$asked, $jpeg) {
            $asked[] = $url;
            return $jpeg;
        };
        try {
            $this->assertTrue(covers::find((int) $book->id));
            $this->assertNotNull(covers::url((int) $book->id));
            // With its cover, nothing more is asked.
            $this->assertFalse(covers::find((int) $book->id));
            $this->assertCount(1, $asked);
            $this->assertStringContainsString('9780306406157', $asked[0]);
        } finally {
            lookup::$fetcher = null;
        }
        $extra = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_record', 'extra', ['id' => $book->id]), true);
        $this->assertArrayHasKey('coverasked', $extra);
    }
}
