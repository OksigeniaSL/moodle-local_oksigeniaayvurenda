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
 * Tests for the little life on the staff's screens.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\life
 */
#[\PHPUnit\Framework\Attributes\CoversClass(life::class)]
final class life_test extends \advanced_testcase {
    /** @var \stdClass */
    private $library;

    /** @var \stdClass This school year, from sixty days ago. */
    private $year;

    /** @var int Noon of today in the library's zone: nothing depends on the hour the test runs. */
    private $now;

    /** @var \local_oksigeniaayvurenda_generator */
    private $gen;

    /**
     * A library with its year, and the last one before it.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        force_current_language('en');
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
        $today = zones::today($this->library, time());
        $this->now = $today + 12 * HOURSECS;
        $this->year = $this->gen->create_year(['entityid' => $this->library->entityid, 'name' => 'this',
            'timestart' => $today - 60 * DAYSECS, 'timeend' => $today + 300 * DAYSECS]);
        $this->gen->create_year(['entityid' => $this->library->entityid, 'name' => 'last',
            'timestart' => $today - 425 * DAYSECS, 'timeend' => $today - 60 * DAYSECS]);
    }

    /**
     * A loan of a copy in the test's library.
     *
     * @param int $itemid
     * @param int $readerid
     * @param int $at
     * @param array $more
     * @return \stdClass
     */
    private function lend(int $itemid, int $readerid, int $at, array $more = []): \stdClass {
        // What the test says first: its due date wins over a week's.
        return $this->gen->create_loan($more + ['libraryid' => $this->library->id, 'itemid' => $itemid, 'readerid' => $readerid,
            'yearid' => $this->year->id, 'timeloaned' => $at, 'timedue' => $at + WEEKSECS, 'timecreated' => $at]);
    }

    /**
     * The words of a note, one after the other.
     *
     * @param array|null $note
     * @return string[]
     */
    private static function texts(?array $note): array {
        return $note ? array_column($note['lifeitems'], 'lifetext') : [];
    }

    /**
     * The late books tell how much came back on time when it is most of it, and this week's returns; a book given up for
     * lost is not a return; a library of every age says year, not school year.
     */
    public function test_what_comes_back(): void {
        global $DB;
        $item = $this->gen->create_item(['libraryid' => $this->library->id]);
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        // 30 back this year: 27 on time, 3 late; 5 of them this week.
        for ($i = 0; $i < 30; $i++) {
            $at = $this->now - (50 - $i) * DAYSECS;
            $back = $i < 25 ? $at + DAYSECS : $this->now - ($i - 24) * DAYSECS;
            $late = $i >= 27;
            $this->lend((int) $item->id, (int) $reader->id, $at, ['timedue' => $late ? $back - DAYSECS : $back + DAYSECS,
                'timereturned' => $back]);
        }
        // Lost this week: not a book back.
        $this->lend((int) $item->id, (int) $reader->id, $this->now - 20 * DAYSECS, ['timereturned' => $this->now - HOURSECS,
            'conditionin' => 'lost']);
        $note = life::late($this->library, $this->now);
        $this->assertSame('What comes back', $note['lifelead']);
        $this->assertSame([
            '90% of what came back this school year came back on time.',
            '5 books came back this week.',
        ], self::texts($note));

        // Mostly late: the share is not good news, and is left out.
        $DB->set_field_select('local_oksigeniaayvurenda_loan', 'timedue', 0, 'timereturned IS NOT NULL');
        $this->assertSame(['5 books came back this week.'], self::texts(life::late($this->library, $this->now)));

        $this->public_skin();
        $DB->execute('UPDATE {local_oksigeniaayvurenda_loan} SET timedue = timereturned');
        $this->assertSame(
            '100% of what came back this year came back on time.',
            self::texts(life::late($this->library, $this->now))[0]
        );
    }

    /**
     * Holds reached whoever waited for them; readers who came, and who came for the very first time this week, without the
     * other libraries that borrow from this one.
     */
    public function test_holds_and_readers(): void {
        global $DB;
        $item = $this->gen->create_item(['libraryid' => $this->library->id]);
        $old = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $this->lend((int) $item->id, (int) $old->id, $this->now - 200 * DAYSECS, ['timereturned' => $this->now - 190 * DAYSECS]);
        $this->lend((int) $item->id, (int) $old->id, $this->now - 30 * DAYSECS, ['timereturned' => $this->now - 25 * DAYSECS]);
        foreach ([1, 2] as $n) {
            $new = $this->gen->create_reader(['entityid' => $this->library->entityid]);
            $this->lend((int) $item->id, (int) $new->id, $this->now - $n * DAYSECS);
        }
        $other = $this->gen->create_reader(['entityid' => $this->library->entityid, 'readertype' => ill::READERTYPE]);
        $this->lend((int) $item->id, (int) $other->id, $this->now - DAYSECS);
        $this->assertSame([
            '3 readers have taken a book this school year.',
            '2 readers took their very first book from the library this week.',
        ], self::texts(life::readers($this->library, $this->now)));

        $this->assertNull(life::holds($this->library, $this->now));
        foreach (['done', 'done', 'waiting'] as $n => $status) {
            $DB->insert_record('local_oksigeniaayvurenda_hold', ['libraryid' => $this->library->id,
                'recordid' => $item->recordid, 'readerid' => $old->id, 'status' => $status,
                'timeclosed' => $status === 'done' ? $this->now - ($n + 1) * DAYSECS : null,
                'timecreated' => $this->now - 9 * DAYSECS, 'timemodified' => $this->now]);
        }
        $this->assertSame(
            ['This school year, 2 books on hold reached whoever was waiting for them.'],
            self::texts(life::holds($this->library, $this->now))
        );
    }

    /**
     * What was catalogued by hand is already read; what an import brought is not counted; with nothing this month, the most
     * read of the year. Purchases that arrived and went out.
     */
    public function test_catalogued_and_bought(): void {
        global $DB;
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $record = $this->gen->create_record(['entityid' => $this->library->entityid, 'title' => 'La isla del cometa']);
        $byhand = $this->gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id,
            'timecreated' => $this->now - 2 * HOURSECS]);
        $this->lend((int) $byhand->id, (int) $reader->id, $this->now - HOURSECS);
        $imported = $this->gen->create_item(['libraryid' => $this->library->id, 'timecreated' => $this->now - 3 * HOURSECS]);
        $DB->insert_record('local_oksigeniaayvurenda_source', ['entityid' => $this->library->entityid, 'origin' => 'abies',
            'tablename' => 'item', 'externalid' => 'x1', 'localid' => $imported->id, 'timecreated' => $this->now]);
        $this->lend((int) $imported->id, (int) $reader->id, $this->now - HOURSECS);
        $note = life::catalogued($this->library, $this->now);
        $this->assertSame([
            'What was catalogued this month has already been lent once.',
            '«La isla del cometa» went out the very day it arrived.',
        ], self::texts($note));
        // Only the title is a link; a sentence without a link is all text.
        $this->assertStringContainsString('/record.php', $note['lifeitems'][1]['lifehref']);
        $this->assertSame(['«', 'La isla del cometa', '» went out the very day it arrived.'], [$note['lifeitems'][1]['lifebefore'],
            $note['lifeitems'][1]['lifelinked'], $note['lifeitems'][1]['lifeafter']]);
        $this->assertSame('', $note['lifeitems'][0]['lifehref']);

        // Nothing this month: the copy added by hand this year that went out most, from twice on.
        $DB->delete_records('local_oksigeniaayvurenda_loan');
        $start = (int) $this->year->timestart;
        $DB->set_field('local_oksigeniaayvurenda_item', 'timecreated', $start + DAYSECS, ['id' => $byhand->id]);
        foreach ([3, 4, 5] as $days) {
            $this->lend((int) $byhand->id, (int) $reader->id, (int) $this->year->timestart + $days * DAYSECS);
        }
        $this->assertSame(
            ['«La isla del cometa», catalogued this school year, has already been lent 3 times.'],
            self::texts(life::catalogued($this->library, $this->now))
        );

        // A book that arrived because someone asked: two loans after it came, not the one before.
        $this->assertNull(life::purchases($this->library, $this->now));
        $DB->insert_record('local_oksigeniaayvurenda_purchase', ['libraryid' => $this->library->id, 'yearid' => $this->year->id,
            'title' => 'La isla del cometa', 'source' => 'reader', 'copies' => 1, 'status' => 'received',
            'recordid' => $record->id, 'timereceived' => (int) $this->year->timestart + 4 * DAYSECS - HOURSECS,
            'timecreated' => $this->now, 'timemodified' => $this->now]);
        $this->assertSame(
            ['What arrived this school year because someone asked for it has already been lent 2 times.'],
            self::texts(life::purchases($this->library, $this->now))
        );
    }

    /**
     * Weeding shows the oldest books still lent this year; the year, its busiest day and a clear most lent.
     */
    public function test_veterans_and_the_year(): void {
        $reader = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $copy = function (string $title, string $pubyear) {
            $record = $this->gen->create_record(['entityid' => $this->library->entityid, 'title' => $title,
                'pubyear' => $pubyear]);
            return $this->gen->create_item(['libraryid' => $this->library->id, 'recordid' => $record->id]);
        };
        $platero = $copy('Platero y yo', '1914');
        $atlas = $copy('Atlas viejo', '[1950]');
        $new = $copy('Un libro nuevo', '2020');
        $this->lend((int) $platero->id, (int) $reader->id, $this->now - 10 * DAYSECS);
        $this->lend((int) $platero->id, (int) $reader->id, $this->now - 5 * DAYSECS);
        $this->lend((int) $atlas->id, (int) $reader->id, $this->now - 4 * DAYSECS);
        $this->lend((int) $new->id, (int) $reader->id, $this->now - 4 * DAYSECS);
        $this->assertSame([
            '«Platero y yo», from 1914, has gone out 2 times this school year.',
            '«Atlas viejo», from 1950, has gone out once this school year.',
        ], self::texts(life::weed($this->library, $this->now)));

        // Nothing busy enough, and a tie: the year has nothing to say.
        $this->assertNull(life::year($this->library, $this->now));
        $day = zones::today($this->library, $this->now) - 20 * DAYSECS + 10 * HOURSECS;
        for ($i = 0; $i < 12; $i++) {
            $this->lend((int) $new->id, (int) $reader->id, $day + $i * 60);
        }
        $note = life::year($this->library, $this->now);
        $this->assertSame('The school year so far', $note['lifelead']);
        $this->assertSame([
            'The busiest day was ' . zones::date($day, 'strftimedateshort', $this->library) . ': 12 books lent.',
            'The most lent is «Un libro nuevo»: 13 times.',
        ], self::texts($note));
    }

    /**
     * The reports: this week, and the year ahead of the last at the same point; the cards of the libraries; and nothing at
     * all in a textbook bank.
     */
    public function test_reports_cards_and_quiet(): void {
        $item = $this->gen->create_item(['libraryid' => $this->library->id]);
        $old = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        // Last year, ten loans in its first weeks; this year, nine earlier and three this week, by two new readers.
        for ($i = 0; $i < 10; $i++) {
            $this->lend((int) $item->id, (int) $old->id, (int) $this->year->timestart - 365 * DAYSECS + ($i + 1) * DAYSECS);
        }
        for ($i = 0; $i < 9; $i++) {
            $this->lend((int) $item->id, (int) $old->id, (int) $this->year->timestart + ($i + 1) * DAYSECS);
        }
        $this->assertNull(life::pace($this->library, $this->now), 'behind last year: nothing to tell');
        $a = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $b = $this->gen->create_reader(['entityid' => $this->library->entityid]);
        $this->lend((int) $item->id, (int) $a->id, $this->now - 3 * DAYSECS);
        $this->lend((int) $item->id, (int) $a->id, $this->now - 2 * DAYSECS);
        $this->lend((int) $item->id, (int) $b->id, $this->now - DAYSECS);
        $note = life::reports($this->library, $this->now);
        $this->assertSame('These days', $note['lifelead']);
        $texts = self::texts($note);
        $this->assertSame('This week, 3 loans to 2 readers.', $texts[0]);
        $this->assertSame('That makes 12 loans this school year: 2 more than at this point last year.', $texts[1]);
        $this->assertContains('2 readers took their very first book from the library this week.', $texts, 'the moment');

        $other = $this->gen->create_library(['entityid' => $this->library->entityid, 'kind' => 'classroom']);
        $this->assertSame(
            [(int) $this->library->id => '3 loans this week'],
            life::weeks([$this->library, $other], $this->now)
        );

        $bank = $this->gen->create_library(['entityid' => $this->library->entityid, 'kind' => bank::KIND]);
        $copy = $this->gen->create_item(['libraryid' => $bank->id]);
        $this->gen->create_loan(['libraryid' => $bank->id, 'itemid' => $copy->id, 'readerid' => $a->id,
            'timeloaned' => $this->now - DAYSECS, 'timereturned' => $this->now - HOURSECS]);
        foreach (['late', 'holds', 'readers', 'catalogued', 'weed', 'year', 'purchases', 'reports'] as $screen) {
            $this->assertNull(call_user_func([life::class, $screen], $bank, $this->now), $screen . ' in a textbook bank');
        }
        $this->assertSame([], life::weeks([$bank], $this->now));
    }

    /**
     * Turns the test's library into a public library, with no school years.
     */
    private function public_skin(): void {
        global $DB;
        $entityid = (int) $this->library->entityid;
        $settings = json_decode((string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]), true)
            ?: [];
        $DB->set_field(
            'local_oksigeniaayvurenda_entity',
            'settings',
            json_encode(['skin' => 'public'] + $settings),
            ['id' => $entityid]
        );
        skins::forget($entityid);
    }
}
