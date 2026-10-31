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
 * Tests for the memory at work.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\memory
 */
#[\PHPUnit\Framework\Attributes\CoversClass(memory::class)]
final class memory_test extends \advanced_testcase {
    /** @var \local_oksigeniaayvurenda_generator */
    private $gen;

    /** @var \stdClass */
    private \stdClass $library;

    /**
     * A book with an author and a class, and one free copy in the library.
     *
     * @param string $title
     * @param string $author
     * @param string $class
     * @return \stdClass The record.
     */
    private function book(string $title, string $author, string $class): \stdClass {
        global $DB;
        $now = time();
        $record = $this->gen->create_record(['entityid' => $this->library->entityid, 'title' => $title]);
        foreach (['author' => $author, 'class' => $class] as $kind => $value) {
            $id = $DB->get_field('local_oksigeniaayvurenda_authority', 'id', ['entityid' => $this->library->entityid,
                'kind' => $kind, 'label' => $value]) ?: $DB->insert_record('local_oksigeniaayvurenda_authority', [
                'entityid' => $this->library->entityid, 'kind' => $kind, 'label' => $value,
                'code' => $kind === 'class' ? $value : null, 'timecreated' => $now, 'timemodified' => $now]);
            $DB->insert_record('local_oksigeniaayvurenda_recauth', ['recordid' => $record->id, 'authorityid' => $id,
                'sortorder' => 0]);
        }
        $this->gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id]);
        return $record;
    }

    /**
     * A loan of a book, given back some days ago with a note of the desk.
     *
     * @param \stdClass $reader
     * @param \stdClass $record
     * @param string|null $note
     * @param int $daysago
     */
    private function took(\stdClass $reader, \stdClass $record, ?string $note, int $daysago): void {
        global $DB;
        $item = $DB->get_records('local_oksigeniaayvurenda_item', ['recordid' => $record->id], 'id', '*', 0, 1);
        $when = time() - $daysago * DAYSECS;
        $this->gen->create_loan(['libraryid' => $this->library->id, 'itemid' => reset($item)->id, 'readerid' => $reader->id,
            'timeloaned' => $when - 7 * DAYSECS, 'timedue' => $when, 'timereturned' => $when, 'returnnote' => $note]);
    }

    /**
     * What the memory tells a reader, in its order: half read, then like what they loved, then the last search, then
     * their corner; and nothing for someone she knows nothing of.
     */
    public function test_remembers(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid, 'userid' => $user->id]);
        $faro = $this->book('El faro dormido', 'Montes, Ana', '821');
        $isla = $this->book('La isla de los faros', 'Montes, Ana', '821');
        $nube = $this->book('La nube', 'Lago, Óscar', '821');
        $this->book('Los volcanes', 'Ruiz, Pedro', '55');
        $this->assertNull(memory::remembers($this->library, $reader));

        // Their corner: two books of literature, and a third there they have not read.
        $this->took($reader, $nube, null, 20);
        $this->took($reader, $faro, null, 15);
        $said = memory::remembers($this->library, $reader);
        $this->assertSame('corner', $said['kind']);
        $this->assertSame((int) $isla->id, $said['recordid']);

        // What they looked for, after their last loan.
        $this->setUser($user);
        memory::searched($this->library, 'dragones');
        $said = memory::remembers($this->library, $reader);
        $this->assertSame('searched', $said['kind']);
        $this->assertSame('dragones', $said['query']);

        // They loved a book of an author: another of hers.
        $this->took($reader, $faro, 'loved', 2);
        $said = memory::remembers($this->library, $reader);
        $this->assertSame('loved', $said['kind']);
        $this->assertSame((int) $isla->id, $said['recordid']);
        $this->assertStringContainsString('La isla de los faros', $said['say']['text']);

        // Half read, still on its shelf: that first.
        $this->took($reader, $nube, 'unfinished', 1);
        $said = memory::remembers($this->library, $reader);
        $this->assertSame('unfinished', $said['kind']);
        $this->assertSame((int) $nube->id, $said['recordid']);
    }

    /**
     * What else the readers of a book took: only when three of them took it, each book taken by two at least.
     */
    public function test_also(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $faro = $this->book('El faro dormido', 'Montes, Ana', '821');
        $isla = $this->book('La isla de los faros', 'Montes, Ana', '821');
        $nube = $this->book('La nube', 'Lago, Óscar', '821');
        $readers = [];
        for ($i = 0; $i < 3; $i++) {
            $readers[$i] = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        }
        $this->took($readers[0], $faro, null, 9);
        $this->took($readers[1], $faro, null, 8);
        $this->took($readers[0], $isla, null, 5);
        $this->took($readers[1], $isla, null, 4);
        $this->took($readers[2], $nube, null, 3);
        $this->assertSame([], memory::also($this->library, (int) $faro->id), 'two readers are too few');
        $this->took($readers[2], $faro, null, 2);
        $this->assertSame([(int) $isla->id], memory::also($this->library, (int) $faro->id), 'la nube: only one of them');
    }
}
