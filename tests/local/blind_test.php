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
 * Tests for «La cita a ciegas»: a copy wrapped with three clues, lent by its number, and the reader's note.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\blind
 */
#[\PHPUnit\Framework\Attributes\CoversClass(blind::class)]
final class blind_test extends \advanced_testcase {
    /**
     * Wrapped with its clues and the next number; not twice, not without a clue, not a copy out. Lent at the desk, the desk
     * says so and it leaves the shelf; at home it is on the reader's card without its title being a secret any more; back,
     * the reader's note is kept, only theirs and only once it is back. The sums have no names; erasing the reader keeps
     * the date and what was said, without who.
     */
    public function test_date(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $ana = $gen->create_reader(['entityid' => $library->entityid]);
        $leo = $gen->create_reader(['entityid' => $library->entityid]);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro del fin del mundo']);
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $other = $gen->create_item(['libraryid' => $library->id,
            'recordid' => $gen->create_record(['entityid' => $library->entityid, 'title' => 'Otro'])->id]);
        $this->assertSame('blinderrclues', blind::wrap($library, (int) $item->id, ['', '  '], 2, $now));
        // Three clues, or it is not wrapped.
        $this->assertSame('blinderrclues', blind::wrap($library, (int) $item->id, ['Un barco', '', 'Un faro'], 2, $now));
        $three = fn(string $first) => [$first, 'Se lee en una tarde', 'Huele a sal'];
        $first = blind::wrap($library, (int) $item->id, ['Un barco', 'Un faro', str_repeat('mar ', 40)], 2, $now);
        $this->assertIsInt($first);
        $this->assertSame('blinderrwrapped', blind::wrap($library, (int) $item->id, $three('Otra vez'), 2, $now));
        $second = blind::wrap($library, (int) $other->id, $three('Una isla'), 2, $now);
        $open = blind::open($library);
        $this->assertSame([1, 2], array_column($open, 'number'));
        // Each in a paper no other date on the shelf wears.
        $this->assertSame([1, 2], array_column($open, 'paper'));
        $this->assertCount(3, $open[0]['clues']);
        $this->assertSame(blind::CLUELENGTH, \core_text::strlen($open[0]['clues'][2]));
        // The second is unwrapped without going home: its number is not given again.
        $this->assertTrue(blind::unwrap($second, (int) $library->id, $now));
        $this->assertFalse(blind::unwrap($second, (int) $library->id, $now));
        $third = blind::wrap($library, (int) $other->id, $three('Una isla'), 2, $now);
        $this->assertSame([1, 3], array_column(blind::open($library), 'number'));
        // The second left the shelf, so its paper is free again.
        $this->assertSame([1, 2], array_column(blind::open($library), 'paper'));
        // Lent at the desk by its code: the desk says it is a blind date, and it leaves the shelf.
        $desk = new desk($library, 2, $now);
        $answer = $desk->scan($item->barcode, (int) $ana->id);
        $this->assertSame(['lent', 1], [$answer['kind'], $answer['blind'] ?? null]);
        $this->assertSame([3], array_column(blind::open($library), 'number'));
        $this->assertSame('blinderritem', blind::wrap($library, (int) $item->id, $three('Fuera'), 2, $now));
        $mine = blind::mine((int) $ana->id, $now);
        $this->assertSame([1, true, 'El faro del fin del mundo'], [$mine[0]['number'], $mine[0]['out'], $mine[0]['title']]);
        $this->assertSame([], blind::mine((int) $leo->id, $now));
        // Not noted while it is at home, nor by someone else, nor with a word that is not one of the three.
        $this->assertFalse(blind::note($first, (int) $ana->id, 'loved', $now));
        $desk->scan($item->barcode, (int) $ana->id);
        $this->assertFalse(blind::mine((int) $ana->id, $now + 60)[0]['out']);
        $this->assertFalse(blind::note($first, (int) $leo->id, 'loved', $now));
        $this->assertFalse(blind::note($first, (int) $ana->id, 'meh', $now));
        $this->assertTrue(blind::note($first, (int) $ana->id, 'loved', $now));
        $this->assertSame('loved', blind::mine((int) $ana->id, $now)[0]['note']);
        $this->assertSame(['dates' => 1, 'loved' => 1, 'fine' => 0, 'notforme' => 0], blind::sums((int) $record->id));
        // A month and more after it came back, it leaves the card.
        $this->assertSame([], blind::mine((int) $ana->id, $now + (blind::KEEP + 1) * DAYSECS));
        // Erased, the reader goes; the date and what was said stay, without who.
        erasure::reader((int) $ana->id, $now);
        $date = $DB->get_record('local_oksigeniaayvurenda_blind', ['id' => $first]);
        $this->assertSame([null, 'loved'], [$date->readerid, $date->note]);
        $this->assertSame(1, blind::sums((int) $record->id)['loved']);
        $this->assertIsInt($third);
    }
}
