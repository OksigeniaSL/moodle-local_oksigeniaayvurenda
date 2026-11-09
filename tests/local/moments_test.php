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
 * Tests for the moments to celebrate.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\moments
 */
#[\PHPUnit\Framework\Attributes\CoversClass(moments::class)]
final class moments_test extends \advanced_testcase {
    /**
     * A week with something of everything: the hundredth book of the year and which one it was, a book that went out
     * ten times, the busiest day, a reader at the goal of a challenge of the library, a class that met its challenge and
     * new readers; staff see them all and readers only those of the whole library that count several people.
     */
    public function test_a_week_to_celebrate(): void {
        global $DB;
        $this->resetAfterTest();
        force_current_language('en');
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $today = zones::today($library, $now);
        // A year of its own, so that the test does not depend on the day it runs.
        $year = $gen->create_year(['entityid' => $entityid, 'name' => 'test', 'timestart' => $today - 60 * DAYSECS,
            'timeend' => $today + 300 * DAYSECS]);
        $old = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro viejo']);
        $new = $gen->create_record(['entityid' => $entityid, 'title' => 'La isla del cometa']);
        $olditem = $gen->create_item(['libraryid' => $library->id, 'recordid' => $old->id]);
        $newitem = $gen->create_item(['libraryid' => $library->id, 'recordid' => $new->id]);
        $regular = $gen->create_reader(['entityid' => $entityid]);
        // 95 books before this week, two a day; then ten on one day this week, by ten readers who never took one.
        $rows = [];
        for ($i = 0; $i < 95; $i++) {
            $at = $today - 55 * DAYSECS + intdiv($i, 2) * DAYSECS + 10 * HOURSECS + ($i % 2) * 60;
            $rows[] = ['libraryid' => $library->id, 'itemid' => $olditem->id, 'readerid' => $regular->id, 'yearid' => $year->id,
                'timeloaned' => $at, 'timedue' => $at + WEEKSECS, 'timereturned' => $at + DAYSECS, 'renewals' => 0,
                'timecreated' => $at, 'timemodified' => $at];
        }
        for ($i = 0; $i < 10; $i++) {
            $at = $today - 2 * DAYSECS + 11 * HOURSECS + $i * 60;
            $reader = $gen->create_reader(['entityid' => $entityid]);
            $rows[] = ['libraryid' => $library->id, 'itemid' => $newitem->id, 'readerid' => $reader->id, 'yearid' => $year->id,
                'timeloaned' => $at, 'timedue' => $at + WEEKSECS, 'timereturned' => $at + HOURSECS, 'renewals' => 0,
                'timecreated' => $at, 'timemodified' => $at];
        }
        $DB->insert_records('local_oksigeniaayvurenda_loan', $rows);
        // A reader reached the goal of the library's challenge yesterday, and a class met its own.
        $quest = $DB->insert_record('local_oksigeniaayvurenda_quest', ['libraryid' => $library->id, 'name' => 'Otoño lector',
            'timestart' => $today - 10 * DAYSECS, 'timeend' => $today + 10 * DAYSECS, 'goal' => 3,
            'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('local_oksigeniaayvurenda_questreader', ['questid' => $quest, 'readerid' => $regular->id,
            'timejoined' => $now - 5 * DAYSECS, 'timedone' => $now - DAYSECS]);
        $DB->insert_record('local_oksigeniaayvurenda_challenge', ['yearid' => $year->id, 'groupname' => '3.º B', 'term' => 1,
            'seq' => 1, 'kind' => 'corners', 'timecompleted' => $now - DAYSECS, 'timecreated' => $now, 'timemodified' => $now]);

        $found = moments::find($entityid, $now);
        $this->assertSame(
            ['loans', 'book', 'record', 'quest', 'challenge', 'first'],
            array_column($found, 'kind'),
            'no anniversary: the library has not lent here before'
        );
        $staff = moments::of($library, $now, true);
        $text = array_column($staff, 'text', 'kind');
        $this->assertSame('100 books lent so far this school year. Number 100 was «La isla del cometa».', $text['loans']);
        $this->assertSame((int) $new->id, $staff[0]['recordid']);
        $this->assertStringContainsString('/record.php', $staff[0]['url']);
        $this->assertSame('«La isla del cometa» has now gone out 10 times.', $text['book']);
        $this->assertStringContainsString('was the busiest day of the school year: 10 books lent.', $text['record']);
        $this->assertSame('One reader reached the goal of «Otoño lector» this week.', $text['quest']);
        $this->assertSame('Class 3.º B met its challenge.', $text['challenge']);
        $this->assertSame('10 readers took their very first book from the library this week.', $text['first']);
        // Readers: not the classes' challenges, nor a count of one reader.
        $this->assertSame(['loans', 'book', 'record', 'first'], array_column(moments::of($library, $now, false), 'kind'));

        // For other plugins (the screens of a centre): the readers' moments, with the book's cover where there is one.
        $api = \local_oksigeniaayvurenda\api::moments($entityid, $now);
        $this->assertSame(['loans', 'book', 'record', 'first'], array_column($api, 'kind'));
        $this->assertSame((int) $new->id, $api[0]['recordid']);
        $this->assertNull($api[0]['cover']);
        access::set_closed($entityid, true);
        $this->assertSame([], \local_oksigeniaayvurenda\api::moments($entityid, $now), 'not a centre still closed');
        access::set_closed($entityid, false);

        // A library without classes has no class to cheer; its year is a year, not a course.
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        ) ?: [];
        $DB->set_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            json_encode(['skin' => 'public'] + $settings),
            ['id' => $entityid]
        );
        skins::forget($entityid);
        \cache::make('local_oksigeniaayvurenda', 'moments')->purge();
        $public = moments::of($library, $now + 1, true);
        $this->assertNotContains('challenge', array_column($public, 'kind'));
        $this->assertSame('100 books lent so far this year. Number 100 was «La isla del cometa».', $public[0]['text']);

        // The record falls to the past: a busier day before this week means no record.
        $at = $today - 30 * DAYSECS + 9 * HOURSECS;
        $more = [];
        for ($i = 0; $i < 12; $i++) {
            $more[] = ['libraryid' => $library->id, 'itemid' => $olditem->id, 'readerid' => $regular->id, 'yearid' => $year->id,
                'timeloaned' => $at + $i * 60, 'timedue' => $at + WEEKSECS, 'timereturned' => $at + DAYSECS, 'renewals' => 0,
                'timecreated' => $at, 'timemodified' => $at];
        }
        $DB->insert_records('local_oksigeniaayvurenda_loan', $more);
        $kinds = array_column(moments::find($entityid, $now), 'kind');
        $this->assertNotContains('record', $kinds);
        $this->assertNotContains('loans', $kinds, 'the hundredth book was before this week now');
    }

    /**
     * Past four classes that met their challenge, how many of them, never «and so many more».
     */
    public function test_many_classes(): void {
        global $DB;
        $this->resetAfterTest();
        force_current_language('en');
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = years::around($entityid, $now);
        foreach (['1.º A', '1.º B', '2.º A', '2.º B', '3.º A'] as $group) {
            $DB->insert_record('local_oksigeniaayvurenda_challenge', ['yearid' => $year->id, 'groupname' => $group, 'term' => 1,
                'seq' => 1, 'kind' => 'corners', 'timecompleted' => $now - DAYSECS, 'timecreated' => $now, 'timemodified' => $now]);
        }
        $found = array_column(moments::find($entityid, $now), null, 'kind');
        $this->assertSame('momentchallengemany', $found['challenge']['key']);
        $this->assertSame(
            '5 classes met their challenge.',
            skins::say($entityid, 'momentchallengemany', (object) $found['challenge']['a'])
        );
    }

    /**
     * The library turning a hundred days; the day it opens is written down; nothing in a practice library.
     */
    public function test_anniversary_and_opening(): void {
        global $DB;
        $this->resetAfterTest();
        force_current_language('en');
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $this->assertNull(moments::opened($entityid));
        access::set_closed($entityid, true);
        $this->assertNull(moments::opened($entityid), 'closing is not opening');
        access::set_closed($entityid, false);
        $this->assertEqualsWithDelta($now, moments::opened($entityid), 5);

        // Opened 102 days ago: it turned a hundred days two days ago.
        $start = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($entityid))->setTime(0, 0)->modify('-102 days');
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        );
        $settings['opened'] = $start->getTimestamp() + 10 * HOURSECS;
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode($settings), ['id' => $entityid]);
        $found = moments::find($entityid, $now);
        $this->assertSame('anniversary', $found[0]['kind']);
        $this->assertSame('momenthundred', $found[0]['key']);
        $this->assertSame(
            'The online library turns a hundred days old this week.',
            moments::of($library, $now, false)[0]['text']
        );
        $this->assertSame([], moments::find($entityid, $now + 10 * DAYSECS), 'a week later, it is over');

        // Without anything written, its first loan here, not the old ones a programme of before brought.
        $other = $gen->create_library();
        $item = $gen->create_item(['libraryid' => $other->id]);
        $reader = $gen->create_reader(['entityid' => $other->entityid]);
        $gen->create_loan(['libraryid' => $other->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => $now - 3 * 365 * DAYSECS, 'timecreated' => $now - 100]);
        $this->assertNull(moments::opened((int) $other->entityid));
        $gen->create_loan(['libraryid' => $other->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => $now - 50, 'timecreated' => $now - 50]);
        $this->assertSame($now - 50, moments::opened((int) $other->entityid));

        $this->setAdminUser();
        $practice = practice::library();
        $this->assertSame([], moments::of($practice, $now, true));
    }
}
