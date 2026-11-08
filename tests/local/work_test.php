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
 * Tests for the library's card in «Your work».
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\work
 */
#[\PHPUnit\Framework\Attributes\CoversClass(work::class)]
final class work_test extends \advanced_testcase {
    /**
     * Whoever lends gets the desk as the door, the numbers and what to solve, the urgent first, each leading to where it
     * is solved; a reader gets nothing; the function the block calls gives the same.
     */
    public function test_cards(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido']);
        $student = $this->getDataGenerator()->create_user();
        $reader = $gen->create_reader(['entityid' => $library->entityid, 'userid' => $student->id]);
        $start = zones::today($library, $now);
        foreach ([$start - 40 * DAYSECS, $start - 3 * DAYSECS, $start + HOURSECS, $start + 5 * DAYSECS] as $due) {
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'timedue' => $due]);
        }
        force_current_language('en');

        $cards = work::cards((int) get_admin()->id, $now);
        $this->assertCount(1, $cards);
        $card = $cards[0];
        $this->assertSame('Library', $card['title']);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/desk.php', $card['url']);
        $this->assertSame('4 out on loan · 2 late', $card['status']);
        $this->assertSame(['urgent', 'today', 'today'], array_column($card['alerts'], 'level'));
        $this->assertSame(
            ['One book more than a month late', 'One book late', 'One book due today'],
            array_column($card['alerts'], 'text')
        );
        $this->assertStringContainsString('span=old', $card['alerts'][0]['url']);
        $this->assertStringContainsString('span=today', $card['alerts'][2]['url']);
        $this->assertSame(
            [$start - 40 * DAYSECS, $start - 3 * DAYSECS, $start + DAYSECS - 1],
            array_column($card['alerts'], 'due'),
            'each deadline: the oldest missed, or the end of today'
        );
        $this->assertCount(2, $card['actions']);

        // A reader runs nothing: no card, and nothing «all is well».
        $this->assertSame([], work::cards((int) $student->id, $now));
        $this->assertSame([], local_oksigeniaayvurenda_oksigenia_work((int) $student->id));
        $this->assertSame([], work::cards(0, $now));

        // Nothing out: no alerts, and the line says so.
        $other = $gen->create_library();
        $this->assertCount(2, work::cards((int) get_admin()->id, $now), 'a card for each centre');
        $empty = array_values(array_filter(
            work::cards((int) get_admin()->id, $now),
            fn($c) => $c['key'] === 'library-' . $other->entityid
        ))[0];
        $this->assertSame([], $empty['alerts']);
        $this->assertSame('Nothing out on loan now', $empty['status']);
        $this->assertStringStartsWith('Library · ', $empty['title']);
    }
}
