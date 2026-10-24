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
}
