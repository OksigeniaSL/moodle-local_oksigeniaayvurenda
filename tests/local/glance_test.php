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
 * Tests for the library at a glance.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\glance
 */
#[\PHPUnit\Framework\Attributes\CoversClass(glance::class)]
final class glance_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $library;

    /** @var \component_generator_base */
    private $gen;

    /** @var \DateTimeImmutable The day of the tests: 25 November 2026, at noon where the library is. */
    private \DateTimeImmutable $today;

    /**
     * A library of a centre whose year starts in September.
     */
    private function prepare(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $this->today = new \DateTimeImmutable('2026-11-25 12:00', zones::of($this->library));
    }

    /**
     * A loan of a copy on a day.
     *
     * @param \stdClass $item
     * @param \stdClass $reader
     * @param string $day
     * @param bool $back Whether it came back.
     */
    private function loan(\stdClass $item, \stdClass $reader, string $day, bool $back = true): void {
        $time = (new \DateTimeImmutable($day . ' 10:00', zones::of($this->library)))->getTimestamp();
        $year = years::around((int) $this->library->entityid, $time);
        $this->gen->create_loan(['libraryid' => $this->library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $time, 'timedue' => $time + WEEKSECS,
            'timereturned' => $back ? $time + DAYSECS : null]);
    }

    /**
     * A copy of the library.
     *
     * @param array $record
     * @return \stdClass
     */
    private function item(array $record = []): \stdClass {
        return $this->gen->create_item($record + ['libraryid' => $this->library->id]);
    }

    /**
     * The figures of the year up to today.
     *
     * @return array
     */
    private function year(): array {
        $now = $this->today->getTimestamp();
        $year = years::around((int) $this->library->entityid, $now);
        return glance::of($this->library, (int) $year->timestart, (int) $year->timeend, $now);
    }

    /**
     * Ten loans in the year before, enough to set it beside this one.
     *
     * @param \stdClass $item
     * @param \stdClass $reader
     * @param string[] $days
     */
    private function known(\stdClass $item, \stdClass $reader, array $days): void {
        for ($i = count($days); $i < moments::RECORD; $i++) {
            $days[] = '2026-05-' . (10 + $i);
        }
        foreach ($days as $day) {
            $this->loan($item, $reader, $day);
        }
    }

    /**
     * A library with nothing yet: everything at nothing, and nothing of the year before.
     */
    public function test_empty(): void {
        $this->prepare();
        $glance = $this->year();
        $this->assertSame(['copies' => 0, 'titles' => 0, 'kinds' => [], 'never' => 0, 'nevershare' => 0.0,
            'acquired' => 0], $glance['collection']);
        $this->assertSame(0, $glance['use']['loans']);
        $this->assertSame(0.0, $glance['use']['usedshare']);
        $this->assertSame(0.0, $glance['use']['perreader']);
        $this->assertNull($glance['before']);
        $this->assertSame('month', $glance['series']['unit']);
        $this->assertCount(12, $glance['series']['points']);
        $this->assertSame([], $glance['top']);
        $this->assertSame([], $glance['stages']);
        $this->assertSame([], $glance['corners']);
    }

    /**
     * What there is and how much of it is used: withdrawn copies are not there, a copy lent twice is used once, and one
     * read in the room is used too.
     */
    public function test_collection_and_use(): void {
        global $DB;
        $this->prepare();
        $autumn = (new \DateTimeImmutable('2026-10-01', zones::of($this->library)))->getTimestamp();
        $first = $this->item(['timeacquired' => $autumn]);
        $this->item(['recordid' => $first->recordid, 'timeacquired' => $autumn - 2 * YEARSECS]);
        $second = $this->item();
        $third = $this->item();
        $gone = $this->item(['status' => 'withdrawn']);
        $DB->set_field('local_oksigeniaayvurenda_record', 'doctype', 'video', ['id' => $second->recordid]);
        $DB->set_field('local_oksigeniaayvurenda_record', 'doctype', 'tapestry', ['id' => $third->recordid]);
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $this->loan($first, $reader, '2026-09-20');
        $this->loan($first, $reader, '2026-10-20');
        $this->loan($second, $reader, '2026-11-20', false);
        $this->loan($gone, $reader, '2026-11-01');
        $DB->insert_record('local_oksigeniaayvurenda_roomread', (object) ['libraryid' => $this->library->id,
            'itemid' => $third->id, 'recordid' => $third->recordid, 'timecreated' => $autumn]);
        $glance = $this->year();
        $this->assertSame(['copies' => 4, 'titles' => 3, 'kinds' => ['book' => 2, 'video' => 1, 'other' => 1], 'never' => 2,
            'nevershare' => 50.0, 'acquired' => 1], $glance['collection']);
        $this->assertSame(['loans' => 4, 'used' => 3, 'usedshare' => 75.0, 'other' => 0, 'room' => 1, 'out' => 1,
            'outshare' => 25.0, 'perhundred' => 100.0, 'perreader' => 4.0], $glance['use']);
        // In November only the last one; the other two of this year were lent some other time.
        $november = (new \DateTimeImmutable('2026-11-01', zones::of($this->library)))->getTimestamp();
        $month = glance::of($this->library, $november, $november + 30 * DAYSECS, $this->today->getTimestamp());
        $this->assertSame(1, $month['use']['used']);
        $this->assertSame(1, $month['use']['other']);
        $this->assertSame((int) $first->recordid, $glance['top'][0]['id']);
        $this->assertSame(2, $glance['top'][0]['value']);
    }

    /**
     * Beside the year before: the same days of it, not the whole year; and its months. With next to nothing in it, nothing
     * is said of it.
     */
    public function test_year_before(): void {
        $this->prepare();
        $item = $this->item();
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $this->loan($item, $reader, '2026-10-15');
        $this->assertNull($this->year()['before']);
        $this->known($item, $reader, ['2025-10-10', '2025-11-24', '2026-03-02']);
        $glance = $this->year();
        $this->assertSame(1, $glance['use']['loans']);
        $this->assertSame(2, $glance['before']['loans']);
        $this->assertSame('2025-2026', $glance['before']['name']);
        $points = $glance['series']['points'];
        $this->assertSame([0, 1, 0, null], array_column(array_slice($points, 0, 4), 'loans'));
        $this->assertSame([0, 1, 1, 0], array_column(array_slice($points, 0, 4), 'before'));
        // To come: nothing yet, but it is known what the year before did.
        $this->assertNull($points[6]['loans']);
        $this->assertSame(1, $points[6]['before']);
        // Of the year before, nothing was known: its figures have nothing to be set beside.
        $past = years::around((int) $this->library->entityid, $this->today->getTimestamp() - YEARSECS);
        $this->assertNull(glance::of(
            $this->library,
            (int) $past->timestart,
            (int) $past->timeend,
            $this->today->getTimestamp()
        )['before']);
    }

    /**
     * A week goes day by day, with nothing beside it; the whole history, year by year.
     */
    public function test_series(): void {
        $this->prepare();
        $item = $this->item();
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $this->known($item, $reader, ['2025-11-24']);
        $this->loan($item, $reader, '2026-11-24');
        $now = $this->today->getTimestamp();
        $monday = (new \DateTimeImmutable('2026-11-23 00:00', zones::of($this->library)))->getTimestamp();
        $week = glance::of($this->library, $monday, $monday + WEEKSECS, $now);
        $this->assertSame('day', $week['series']['unit']);
        $this->assertSame([0, 1, 0, null, null, null, null], array_column($week['series']['points'], 'loans'));
        $this->assertSame([null], array_values(array_unique(array_column($week['series']['points'], 'before'))));
        $this->assertSame(1, $week['before']['loans']);
        [$from, $to, $key] = periods::resolve($this->library, $now, 'siempre', '', '');
        $this->assertSame('siempre', $key);
        $always = glance::of($this->library, $from, $to, $now, true);
        $this->assertSame('year', $always['series']['unit']);
        $this->assertSame(['2025-2026', '2026-2027'], array_column($always['series']['points'], 'name'));
        $this->assertSame([10, 1], array_column($always['series']['points'], 'loans'));
        $this->assertNull($always['before']);
        $this->assertNull($always['readers']['of']);
        $this->assertSame(1, $always['readers']['borrowers']);
    }

    /**
     * The readers who came, of the ones in groups: the staff borrow too and do not count; of the year before, only if who
     * was in its groups is known. Without groups, of the active readers.
     */
    public function test_readers(): void {
        $this->prepare();
        $item = $this->item();
        $entityid = (int) $this->library->entityid;
        $pupils = [];
        for ($i = 0; $i < 4; $i++) {
            $pupils[] = $this->gen->create_reader(['entityid' => $entityid, 'status' => 'active']);
        }
        $this->known($item, $pupils[0], ['2025-11-10']);
        $this->loan($item, $pupils[0], '2026-11-10');
        $glance = $this->year();
        $this->assertSame(['borrowers' => 1, 'came' => 1, 'of' => 4, 'share' => 25.0], $glance['readers']);
        $this->assertSame(['borrowers' => 1, 'came' => 1, 'of' => null, 'share' => null], $glance['before']['readers']);
        $now = $this->today->getTimestamp();
        $year = years::around($entityid, $now);
        foreach (array_slice($pupils, 0, 2) as $pupil) {
            $this->gen->create_member(['readerid' => $pupil->id, 'yearid' => $year->id, 'groupname' => '1º A',
                'stage' => 'Primaria']);
        }
        $this->loan($item, $pupils[3], '2026-11-12');
        $last = years::around($entityid, $now - YEARSECS);
        $this->gen->create_member(['readerid' => $pupils[0]->id, 'yearid' => $last->id, 'groupname' => '1º A']);
        $glance = $this->year();
        $this->assertSame(['borrowers' => 2, 'came' => 1, 'of' => 2, 'share' => 50.0], $glance['readers']);
        $this->assertSame(['borrowers' => 1, 'came' => 1, 'of' => 1, 'share' => 100.0], $glance['before']['readers']);
        // By stage, the rest last.
        $this->assertSame([['stage' => 'Primaria', 'loans' => 1, 'share' => 50.0], ['stage' => '', 'loans' => 1,
            'share' => 50.0]], $glance['stages']);
    }

    /**
     * The corners books go out from, and how each moved: a book of two corners counts once.
     */
    public function test_corners(): void {
        global $DB;
        $this->prepare();
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $items = [];
        foreach (['Volcanes' => ['551', '82'], 'Cuentos' => ['82-3'], 'Mares' => ['556']] as $title => $class) {
            cataloguer::add($this->library, ['record' => ['doctype' => 'book', 'title' => $title],
                'authorities' => ['class' => $class], 'extra' => []], ['count' => 1]);
            $recordid = $DB->get_field('local_oksigeniaayvurenda_record', 'id', ['title' => $title]);
            $items[$title] = $DB->get_record('local_oksigeniaayvurenda_item', ['recordid' => $recordid]);
        }
        $this->known($items['Cuentos'], $reader, ['2025-10-01', '2025-10-20']);
        $this->loan($items['Volcanes'], $reader, '2026-10-01');
        $this->loan($items['Mares'], $reader, '2026-10-02');
        $this->loan($items['Cuentos'], $reader, '2026-10-03');
        $corners = $this->year()['corners'];
        $this->assertSame(['5', '8'], array_column($corners, 'code'));
        $this->assertSame([2, 1], array_column($corners, 'loans'));
        $this->assertSame([0, 2], array_column($corners, 'before'));
        $this->assertSame([2, -1], array_column($corners, 'change'));
    }
}
