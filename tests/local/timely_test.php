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
 * Tests for the functions presented at their moment.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\timely
 */
#[\PHPUnit\Framework\Attributes\CoversClass(timely::class)]
final class timely_test extends \advanced_testcase {
    /**
     * The first late books with the notices off make a card; it stays the week, goes with «not now» and comes back only
     * when the situation comes again, and never after a second «not now». «Turn it on» turns the notices on, and the
     * situation is over.
     */
    public function test_late_and_rhythm(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $USER;
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        sanctions::save_settings($entityid, ['noticereaders' => false, 'noticefamilies' => false] + sanctions::settings($entityid));
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $record = $gen->create_record(['entityid' => $entityid]);
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $loan = $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => $now - 20 * DAYSECS, 'timedue' => $now - 5 * DAYSECS]);
        $userid = (int) $USER->id;

        $card = timely::pick($library, $userid, $now);
        $this->assertSame(['late', 1], [$card['kind'], $card['count']]);
        $this->assertSame('late', timely::pick($library, $userid, $now + DAYSECS)['kind']);
        $this->assertTrue(timely::can_turn_on($library, 'late'));
        $this->assertStringContainsString('rules.php', timely::show_url($library, $card)->out(false));

        // Not now: gone the week, and after it too while the books are still late.
        timely::answer($library, 'late', 'no', $now + DAYSECS);
        $this->assertNull(timely::pick($library, $userid, $now + 2 * DAYSECS));
        $this->assertNull(timely::pick($library, $userid, $now + 10 * DAYSECS));
        // The books come back: the situation went away; when it comes again, so does the card.
        global $DB;
        $DB->set_field('local_oksigeniaayvurenda_loan', 'timereturned', $now + 11 * DAYSECS, ['id' => $loan->id]);
        $this->assertNull(timely::pick($library, $userid, $now + 11 * DAYSECS));
        $DB->set_field('local_oksigeniaayvurenda_loan', 'timereturned', null, ['id' => $loan->id]);
        $this->assertSame('late', timely::pick($library, $userid, $now + 12 * DAYSECS)['kind']);
        // A second «not now»: never again.
        timely::answer($library, 'late', 'no', $now + 12 * DAYSECS);
        $DB->set_field('local_oksigeniaayvurenda_loan', 'timereturned', $now + 20 * DAYSECS, ['id' => $loan->id]);
        $this->assertNull(timely::pick($library, $userid, $now + 21 * DAYSECS));
        $DB->set_field('local_oksigeniaayvurenda_loan', 'timereturned', null, ['id' => $loan->id]);
        $this->assertNull(timely::pick($library, $userid, $now + 30 * DAYSECS));

        // The light answers it for good: the notices go out, and nothing is shown.
        timely::answer($library, 'late', 'on', $now + 31 * DAYSECS);
        $settings = sanctions::settings($entityid);
        $this->assertTrue($settings['noticereaders'] && $settings['noticefamilies']);
        $this->assertNull(timely::situation($library, 'late', $now + 31 * DAYSECS));
        $this->assertSame(1, timely::state($entityid)['used']['late']);
    }

    /**
     * The end of the year is a moment three weeks before its last day; turning it on sends the booklets to the families.
     * Someone who may not manage the library does not see it.
     */
    public function test_yearend(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $tz = zones::of($entityid);
        $gen->create_year(['entityid' => $entityid, 'name' => '2026-2027',
            'timestart' => (new \DateTimeImmutable('2026-09-01 00:00:00', $tz))->getTimestamp(),
            'timeend' => (new \DateTimeImmutable('2027-09-01 00:00:00', $tz))->getTimestamp()]);
        $this->assertTrue(challenges::set_ends($entityid, ['2026-12-22', '2027-03-26', '2027-06-18']));
        $day = fn(string $date) => (new \DateTimeImmutable($date . ' 10:00:00', $tz))->getTimestamp();
        $this->assertNull(timely::situation($library, 'yearend', $day('2027-05-01')));
        $this->assertSame(10, timely::situation($library, 'yearend', $day('2027-06-09'))['count']);
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertNull(timely::pick($library, (int) $nobody->id, $day('2027-06-09')));
        $this->setAdminUser();
        global $USER;
        $this->assertSame('yearend', timely::pick($library, (int) $USER->id, $day('2027-06-09'))['kind']);
        $this->assertTrue(timely::can_turn_on($library, 'yearend'));
        timely::answer($library, 'yearend', 'on', $day('2027-06-09'));
        $this->assertTrue(booklet::on($entityid));
        $this->assertFalse(timely::can_turn_on($library, 'yearend'));
    }
}
