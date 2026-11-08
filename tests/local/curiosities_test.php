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
 * Tests for the curious things of a catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\curiosities
 */
#[\PHPUnit\Framework\Attributes\CoversClass(curiosities::class)]
final class curiosities_test extends \advanced_testcase {
    /** @var \local_oksigeniaayvurenda_generator */
    private $gen;

    /** @var \stdClass */
    private \stdClass $library;

    /**
     * A book with one copy in the library.
     *
     * @param string $title
     * @param string|null $year
     * @param array $item What the copy has of its own.
     * @param string $doctype
     * @return \stdClass The record.
     */
    private function book(string $title, ?string $year = null, array $item = [], string $doctype = 'book'): \stdClass {
        $record = $this->gen->create_record(['entityid' => $this->library->entityid, 'title' => $title, 'pubyear' => $year,
            'doctype' => $doctype]);
        $this->gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id] + $item);
        return $record;
    }

    /**
     * The longest title, one of a word, one that asks, and the oldest still in service; nothing that is withdrawn, lost or
     * not a book.
     */
    public function test_of(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $this->assertSame([], curiosities::of($this->library));

        $long = $this->book('Zeta: el atlas de las islas que no existen en ningún mapa /', '2010');
        $this->book('Un título bastante largo pero no tanto', '2015');
        $one = $this->book('Marianela', '[1878]');
        $this->book('Yo', '2020');
        $asks = $this->book('¿Quién teme al lobo?', '1999');
        $this->book('1984', '1949');
        $this->book('Pergamino de las islas antiguas y olvidadas del mar del sur', '1550', ['status' => 'withdrawn']);
        $this->book('Mapa', '1600', ['status' => 'lost']);
        $this->book('Tableta', '1700', [], 'device');
        \cache::make('local_oksigeniaayvurenda', 'curiosities')->purge();

        $found = curiosities::of($this->library);
        $this->assertSame((int) $long->id, $found['long']['id']);
        $this->assertSame(12, $found['long']['words']);
        $this->assertSame('Zeta: el atlas de las islas que no existen en ningún mapa', $found['long']['title']);
        $this->assertSame((int) $one->id, $found['short']['id']);
        $this->assertSame((int) $asks->id, $found['question']['id']);
        $this->assertSame((int) $one->id, $found['oldest']['id']);
        $this->assertSame(1878, $found['oldest']['year']);

        // The pulse tells the oldest, but in a company.
        $this->assertSame(1878, window::oldest($this->library));
        libraries::update_entity((int) $this->library->entityid, ['skin' => 'company']);
        $this->assertSame(0, window::oldest($this->library));

        // A bank of textbooks has nothing curious to tell.
        $this->assertSame([], curiosities::of((object) ['id' => $this->library->id, 'entityid' => $this->library->entityid,
            'kind' => bank::KIND]));
    }

    /**
     * With nothing of the reader's own, the memory tells one of them.
     */
    public function test_memory_tells_one(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid, 'userid' => $user->id]);
        $this->assertNull(memory::remembers($this->library, $reader));

        $asks = $this->book('¿Dónde está el faro?');
        \cache::make('local_oksigeniaayvurenda', 'curiosities')->purge();
        $said = memory::remembers($this->library, $reader);
        $this->assertSame('curious', $said['kind']);
        $this->assertSame((int) $asks->id, $said['recordid']);
        $this->assertStringContainsString('¿Dónde está el faro?', $said['say']['text']);
    }
}
