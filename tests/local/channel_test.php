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
 * Tests for the library in the organisation's channel (Oksigenia Marandu).
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\channel
 */
#[\PHPUnit\Framework\Attributes\CoversClass(channel::class)]
final class channel_test extends \advanced_testcase {
    /**
     * Only round numbers; a recommended book with its author, its age and its card; the year's big number once it
     * passes a milestone, never below; and no channel on a site shared by centres.
     */
    public function test_channel(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/lib.php');
        $this->resetAfterTest();
        $this->assertSame(['book' => [], 'big_number' => []], local_oksigeniaayvurenda_oksigeniamarandu_posts());
        $this->assertSame(0, channel::milestone(999));
        $this->assertSame(1000, channel::milestone(1000));
        $this->assertSame(10000, channel::milestone(12480));

        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        force_current_language('en');
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'La luna que no quería dormir',
            'summary' => str_repeat('Una noche, la luna decide quedarse despierta. ', 10)]);
        digital::add_link($library, (int) $record->id, 'https://example.org/luna.epub', ['licence' => 'cc-by',
            'audience' => ['public'], 'homeages' => [3]], (int) get_admin()->id);
        $book = channel::book($library, (int) $record->id);
        $this->assertSame('La luna que no quería dormir', $book['title']);
        $this->assertContains('3 to 5 years', $book['lines']);
        $this->assertLessThanOrEqual(280, \core_text::strlen($book['text']));
        $this->assertSame('book', $book['card']['type']);
        $this->assertSame('Recommended book', $book['card']['zones']['kicker']);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/record.php', $book['url']);
        $this->assertNull(channel::book($gen->create_library(), (int) $record->id), 'not a book of another centre');

        // The year's big number: nothing below a thousand, and the milestone once it passes it.
        $now = time();
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $this->getDataGenerator()->create_user()->id]);
        $this->assertNull(channel::number($entityid, $now)['content']);
        $start = (int) years::around($entityid, $now)->timestart;
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                'timeloaned' => $start + $i, 'timedue' => $start + $i + DAYSECS, 'timereturned' => $start + $i + HOURSECS,
                'renewals' => 0, 'timecreated' => $start + $i, 'timemodified' => $start + $i];
        }
        $DB->insert_records('local_oksigeniaayvurenda_loan', $rows);
        $number = channel::number($entityid, $now);
        $this->assertSame(1000, $number['count']);
        $this->assertSame(1000, $number['milestone']);
        $this->assertSame('big_number', $number['content']['card']['type']);
        $this->assertSame('1,000', $number['content']['card']['zones']['number']);
        $this->assertSame('books lent this school year', $number['content']['card']['zones']['phrase']);
        $this->assertStringEndsWith('-1000', $number['key']);

        // No channel of a centre on a site shared by centres, nor without Marandu.
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $this->assertFalse(channel::ready());
        $this->assertSame(0, channel::numbers($now, [$entityid]));
    }
}
