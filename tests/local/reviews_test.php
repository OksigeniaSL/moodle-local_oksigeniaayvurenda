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
}
