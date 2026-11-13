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
 * Tests for «Llévatelo»: the hidden book found, taken home from the desk within the week, and the seal of the card.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\carry
 */
#[\PHPUnit\Framework\Attributes\CoversClass(carry::class)]
final class carry_test extends \advanced_testcase {
    /**
     * A book that was not hidden for the reader is not kept; one found this week goes home once, a late one does not;
     * the seal grows at 1, 3 and 10 and is golden when as many went with a lantern; it says so.
     */
    public function test_found_taken_and_seal(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $now = time();
        $this->assertFalse(carry::found($library, (int) $reader->id, '8', 0, (int) $record->id, 3, $now));
        $this->assertFalse(carry::found($library, (int) $reader->id, 'x', 0, (int) $record->id, 3, $now));
        $this->assertSame(['level' => 0, 'gold' => false, 'taken' => 0, 'lit' => 0], carry::seal((int) $reader->id));
        $find = fn($recordid, $lanterns, $when) => $DB->insert_record('local_oksigeniaayvurenda_find', [
            'entityid' => $library->entityid,
            'readerid' => $reader->id, 'recordid' => $recordid, 'lanterns' => $lanterns, 'timefound' => $when,
            'timetaken' => null]);
        $find($record->id, 2, $now - DAYSECS);
        $this->assertTrue(carry::taken((int) $reader->id, (int) $record->id, $now));
        $this->assertFalse(carry::taken((int) $reader->id, (int) $record->id, $now));
        $seal = carry::seal((int) $reader->id);
        $this->assertSame([1, true], [$seal['level'], $seal['gold']]);
        $old = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Encontrado hace tiempo']);
        $find($old->id, 3, $now - 8 * DAYSECS);
        $this->assertFalse(carry::taken((int) $reader->id, (int) $old->id, $now));
        // Two more, without lanterns: level 2, plain, since only one of three went with a lantern.
        foreach (['Uno', 'Dos'] as $title) {
            $more = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
            $find($more->id, 0, $now - 60);
            carry::taken((int) $reader->id, (int) $more->id, $now);
        }
        $seal = carry::seal((int) $reader->id);
        $this->assertSame([2, false, 3, 1], [$seal['level'], $seal['gold'], $seal['taken'], $seal['lit']]);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $this->assertStringContainsString('3', carry::says($seal, $str));
        $this->assertSame($str('cardsealwaiting'), carry::says(['level' => 0, 'gold' => false, 'taken' => 0, 'lit' => 0], $str));
        // The reader goes: what they found goes with them.
        erasure::reader((int) $reader->id, $now);
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_find', ['readerid' => $reader->id]));
    }
}
