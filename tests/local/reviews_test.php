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
 * Tests for what readers tell about books.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\reviews
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reviews::class)]
final class reviews_test extends \advanced_testcase {
    /**
     * Only who took the book home writes; the library publishes with a touch; it is signed as the centre chose, counts on
     * the path of telling, waits again when changed, and goes when taken away.
     */
    public function test_write_publish_and_sign(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = years::around($entityid, $now);
        $item = $generator->create_item(['libraryid' => $library->id]);
        $recordid = (int) $item->recordid;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Leo Andrés', 'lastname' => 'Isla']);
        $reader = $generator->create_reader(['entityid' => $entityid, 'userid' => $user->id]);
        $generator->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '3.º A']);
        $other = $generator->create_reader(['entityid' => $entityid, 'firstname' => 'Noa', 'lastname' => 'Río']);

        $this->assertFalse(reviews::can_write($recordid, (int) $reader->id));
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        $this->assertTrue(reviews::can_write($recordid, (int) $reader->id));
        $this->assertSame(0, reviews::write($entityid, $recordid, (int) $reader->id, '   '));
        $id = reviews::write($entityid, $recordid, (int) $reader->id, "Me gustó\n el final. " . str_repeat('a', 400), $now);
        $this->assertSame(reviews::MAX, \core_text::strlen(reviews::mine($recordid, (int) $reader->id)->text));
        $this->assertSame(1, reviews::waiting($entityid));
        $this->assertSame([], reviews::published($recordid, $entityid));
        $this->assertSame(0, reviews::told((int) $reader->id, $year));

        reviews::decide($id, $entityid, true, (int) get_admin()->id, $now);
        $this->assertSame(0, reviews::waiting($entityid));
        $published = reviews::published($recordid, $entityid);
        $this->assertSame('Leo, 3.º A', $published[0]['who']);
        $this->assertStringStartsWith('Me gustó el final.', $published[0]['text']);
        $this->assertSame(1, reviews::told((int) $reader->id, $year));
        libraries::update_entity($entityid, ['reviewsign' => 'initial']);
        $this->assertSame('Leo I.', reviews::published($recordid, $entityid)[0]['who']);
        libraries::update_entity($entityid, ['reviewsign' => 'none']);
        $this->assertSame(
            get_string('reviewbyagroup', 'local_oksigeniaayvurenda', '3.º A'),
            reviews::published($recordid, $entityid)[0]['who']
        );

        // Changed, it waits again; taken away, it is gone.
        reviews::write($entityid, $recordid, (int) $reader->id, 'Mejor aún la segunda vez.');
        $this->assertSame('pending', reviews::mine($recordid, (int) $reader->id)->status);
        $this->assertSame([], reviews::published($recordid, $entityid));
        reviews::remove($recordid, (int) $reader->id);
        $this->assertNull(reviews::mine($recordid, (int) $reader->id));

        // Two cards of one person: the one that stays keeps its own review of a book both told about.
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $other->id]);
        reviews::write($entityid, $recordid, (int) $reader->id, 'La mía.');
        reviews::write($entityid, $recordid, (int) $other->id, 'La otra.');
        reviews::move((int) $other->id, (int) $reader->id);
        $this->assertSame('La mía.', reviews::mine($recordid, (int) $reader->id)->text);
        $this->assertNull(reviews::mine($recordid, (int) $other->id));

        // An older reader's recommendation reaches the young once published, one per book.
        $this->assertSame([], reviews::for_little($entityid));
        $id = reviews::write($entityid, $recordid, (int) $reader->id, 'Para los pequeños: tiene un faro.', $now, true);
        $this->assertSame([], reviews::for_little($entityid));
        reviews::decide($id, $entityid, true, (int) get_admin()->id, $now);
        $little = reviews::for_little($entityid);
        $this->assertCount(1, $little);
        $this->assertSame($recordid, $little[0]['recordid']);
        $this->assertSame('Para los pequeños: tiene un faro.', $little[0]['text']);
    }

    /**
     * A drawing can stand without words; only a real PNG of a sensible size is kept, and it goes with its review.
     */
    public function test_drawing(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $item = $generator->create_item(['libraryid' => $library->id]);
        $recordid = (int) $item->recordid;
        $reader = $generator->create_reader(['entityid' => $entityid]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        $image = imagecreatetruecolor(80, 50);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $this->assertSame(0, reviews::write($entityid, $recordid, (int) $reader->id, ''));
        $id = reviews::write($entityid, $recordid, (int) $reader->id, '', null, false, true);
        $this->assertNotSame(0, $id);
        $this->assertFalse(reviews::draw($id, 'not a picture'));
        $this->assertNull(reviews::drawing($id));
        $this->assertTrue(reviews::draw($id, $png));
        $this->assertNotNull(reviews::drawing($id));
        reviews::decide($id, $entityid, true, (int) get_admin()->id);
        $this->assertNotNull(reviews::published($recordid, $entityid)[0]['drawing']);
        reviews::remove($recordid, (int) $reader->id);
        $this->assertNull(reviews::drawing($id));
    }

    /**
     * A voice is heard by its author, their tutor and the staff; anyone else once published, and for a minor only with
     * the family's permission. A review that is only a voice nobody else may hear is not shown to them.
     */
    public function test_voice(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $item = $generator->create_item(['libraryid' => $library->id]);
        $recordid = (int) $item->recordid;
        $child = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $reader = $generator->create_reader(['entityid' => $entityid, 'userid' => $child->id]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
        // The first bytes of a WebM recording, as a browser makes it.
        $path = make_request_directory() . '/voz.webm';
        file_put_contents($path, "\x1a\x45\xdf\xa3\x9f\x42\x86\x81\x01\x42\xf7\x81\x01\x42\xf2\x81\x04\x42\xf3\x81\x08" .
            "\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02" . str_repeat("\0", 200));

        $id = reviews::write($entityid, $recordid, (int) $reader->id, '', null, false, true);
        $this->assertFalse(reviews::speak($id, __FILE__));
        $this->assertTrue(reviews::speak($id, $path));
        $this->assertNotNull(reviews::voice($id));
        $review = reviews::mine($recordid, (int) $reader->id);
        $this->assertTrue(reviews::can_hear($review, (int) $child->id));
        $this->assertTrue(reviews::can_hear($review, (int) get_admin()->id));
        $this->assertFalse(reviews::can_hear($review, (int) $other->id));

        reviews::decide($id, $entityid, true, (int) get_admin()->id);
        $review = reviews::mine($recordid, (int) $reader->id);
        $this->assertFalse(reviews::can_hear($review, (int) $other->id));
        $this->assertSame([], reviews::published($recordid, $entityid, (int) $other->id));
        $this->assertNotNull(reviews::published($recordid, $entityid, (int) $child->id)[0]['voice']);
        voices::permission((int) $reader->id, true, (int) get_admin()->id);
        $this->assertTrue(reviews::can_hear($review, (int) $other->id));
        $this->assertNotNull(reviews::published($recordid, $entityid, (int) $other->id)[0]['voice']);

        reviews::remove($recordid, (int) $reader->id);
        $this->assertNull(reviews::voice($id));
    }
}
