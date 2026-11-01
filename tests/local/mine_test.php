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
 * Tests for the summary of what a person has in the libraries.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\mine
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mine::class)]
final class mine_test extends \advanced_testcase {
    /**
     * A reader sees their books out, late or not, and nobody else's; someone who is no reader sees nothing.
     */
    public function test_summary(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $ana = $this->getDataGenerator()->create_user();
        $leo = $this->getDataGenerator()->create_user();
        $anareader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'student']);
        $leoreader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $leo->id, 'readertype' => 'student']);
        $lend = function (string $title, int $readerid, int $due) use ($gen, $library, $now) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
            $item = $gen->create_item(['entityid' => $library->entityid, 'libraryid' => $library->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $readerid,
                'timeloaned' => $now - 10 * DAYSECS, 'timedue' => $due]);
        };
        $lend('El faro dormido', (int) $anareader->id, $now + 5 * DAYSECS);
        $lend('La sal del mar', (int) $anareader->id, $now - DAYSECS);
        $lend('Un libro de Leo', (int) $leoreader->id, $now + DAYSECS);

        $summary = mine::summary((int) $ana->id, $now);
        $this->assertCount(1, $summary);
        $this->assertSame(['La sal del mar', 'El faro dormido'], array_column($summary[0]['loans'], 'title'));
        $this->assertSame([true, false], array_column($summary[0]['loans'], 'late'));
        $this->assertStringContainsString('card.php', $summary[0]['card']);
        $this->assertSame([], mine::summary((int) $this->getDataGenerator()->create_user()->id, $now));
    }

    /**
     * The home of the app: nothing for whoever is not a reader; the way in for a reader with nothing out; their books,
     * the late ones first, five at most, without the textbooks lent for the year, and what is set aside for them.
     */
    public function test_home_cards(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $this->assertSame([], mine::home_cards((int) $this->getDataGenerator()->create_user()->id, $now));

        $ana = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id, 'readertype' => 'student']);
        $cards = mine::home_cards((int) $ana->id, $now);
        $this->assertCount(1, $cards);
        $this->assertSame('mobile_library_view', $cards[0]['tile']['method']);
        $this->assertArrayNotHasKey('items', $cards[0]);

        $lend = function (string $title, int $due, ?\stdClass $where = null) use ($gen, $library, $reader, $now): int {
            $where = $where ?? $library;
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
            $item = $gen->create_item(['entityid' => $library->entityid, 'libraryid' => $where->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $where->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'timeloaned' => $now - 10 * DAYSECS, 'timedue' => $due]);
            return (int) $record->id;
        };
        $faro = $lend('El faro', $now + 5 * DAYSECS);
        $lend('La sal', $now - DAYSECS);
        $bank = $gen->create_library(['entityid' => $library->entityid, 'kind' => bank::KIND]);
        $lend('Matemáticas 3', $now + DAYSECS, $bank);
        $cards = mine::home_cards((int) $ana->id, $now);
        $this->assertSame(['La sal', 'El faro'], array_column($cards[0]['items'], 'title'));
        $this->assertSame([true, false], array_column($cards[0]['items'], 'urgent'));
        $this->assertSame($now + 5 * DAYSECS, $cards[0]['items'][1]['date']);
        $this->assertSame(['library' => $library->slug, 'id' => $faro], $cards[0]['items'][1]['args']);
        $this->assertSame('mobile_record_view', $cards[0]['items'][1]['method']);

        // What is set aside for them comes too, by the day it stops being kept; what they only wait for does not.
        $held = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Apartado']);
        $waited = $gen->create_record(['entityid' => $library->entityid, 'title' => 'En cola']);
        foreach ([[$held, 'ready', $now + 2 * DAYSECS], [$waited, 'waiting', null]] as [$record, $status, $expires]) {
            $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $library->id, 'recordid' => $record->id,
                'readerid' => $reader->id, 'status' => $status, 'timeexpires' => $expires, 'timecreated' => $now,
                'timemodified' => $now]);
        }
        $cards = mine::home_cards((int) $ana->id, $now);
        $this->assertSame(['La sal', 'Apartado', 'El faro'], array_column($cards[0]['items'], 'title'));

        foreach (range(1, 6) as $n) {
            $lend('Otro ' . $n, $now + (10 + $n) * DAYSECS);
        }
        $this->assertCount(5, mine::home_cards((int) $ana->id, $now)[0]['items']);
    }
}
