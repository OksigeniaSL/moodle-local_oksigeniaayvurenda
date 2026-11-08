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
 * Tests for the library's challenges with their own dates.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\quests
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quests::class)]
final class quests_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A challenge for one stage: who can join it, the stamps of the loans in its days (not those of the bank, nor those
     * before it), how it goes for all, and what goes when it is taken away or the reader is erased.
     */
    public function test_challenge(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $bank = $gen->create_library(['entityid' => $entityid, 'kind' => bank::KIND, 'slug' => 'banco']);
        $now = time();
        $year = years::around($entityid, $now);
        $young = $gen->create_reader(['entityid' => $entityid]);
        $older = $gen->create_reader(['entityid' => $entityid]);
        $gen->create_member(['readerid' => $young->id, 'yearid' => $year->id, 'stage' => 'Primaria']);
        $gen->create_member(['readerid' => $older->id, 'yearid' => $year->id, 'groupname' => '1º ESO A',
            'stage' => 'Secundaria']);
        $this->assertEqualsCanonicalizing(['Primaria', 'Secundaria'], quests::stages($library, $now));

        $day = fn(int $offset) => zones::date($now + $offset * DAYSECS, '%Y-%m-%d', $library);
        $this->assertSame('questerrname', quests::save($library, ['name' => ' ', 'start' => $day(-1), 'end' => $day(9),
            'goal' => 2], $now));
        $this->assertSame('questerrdates', quests::save($library, ['name' => 'Verano', 'start' => $day(9), 'end' => $day(-1),
            'goal' => 2], $now));
        $this->assertSame('questerrgoal', quests::save($library, ['name' => 'Verano', 'start' => $day(-1), 'end' => $day(9),
            'goal' => quests::MAXGOAL + 1], $now));
        $id = quests::save($library, ['name' => 'Verano lector', 'start' => $day(-1), 'end' => $day(9), 'goal' => 2,
            'stages' => ['Primaria', 'Nada']], $now);
        $this->assertIsInt($id);
        $quest = $DB->get_record(self::T . 'quest', ['id' => $id]);
        $this->assertSame(['Primaria'], json_decode($quest->stages, true), 'only stages of the centre');
        // All the stages ticked is the same as none: for everyone.
        quests::save($library, ['name' => 'Todos', 'start' => $day(-1), 'end' => $day(9), 'goal' => 1,
            'stages' => ['Primaria', 'Secundaria']], $now);
        $this->assertNull($DB->get_field(self::T . 'quest', 'stages', ['name' => 'Todos']));

        // For whom it is.
        $this->assertContains('Verano lector', array_column(quests::open($young, $now), 'name'));
        $this->assertNotContains('Verano lector', array_column(quests::open($older, $now), 'name'));
        $this->assertFalse(quests::join($id, $older, $now));
        $this->assertTrue(quests::join($id, $young, $now));
        $this->assertTrue(quests::join($id, $young, $now), 'twice is once');
        $this->assertSame(1, $DB->count_records(self::T . 'questreader', ['questid' => $id]));

        // The stamps: two loans in its days; one before it and one of the bank do not count.
        $book = fn($lib) => $gen->create_item(['libraryid' => $lib->id]);
        foreach (
            [[$library, $now - HOURSECS], [$library, $now - 2 * HOURSECS], [$library, $now - 5 * DAYSECS],
            [$bank, $now - HOURSECS]] as [$lib, $when]
        ) {
            $gen->create_loan(['libraryid' => $lib->id, 'itemid' => $book($lib)->id, 'readerid' => $young->id,
                'timeloaned' => $when]);
        }
        $quest = array_values(array_filter(quests::open($young, $now), fn($q) => (int) $q->id === $id))[0];
        $stamps = quests::stamps($quest, (int) $young->id, $now);
        $this->assertSame(2, $stamps);
        $this->assertSame(['readers' => 1, 'done' => 1, 'books' => 2], quests::totals($quest, $now));
        quests::note_done($quest, $stamps, $now);
        $this->assertEquals($now, $DB->get_field(self::T . 'questreader', 'timedone', ['questid' => $id]));

        // Over, it still shows a while to whoever played it, and nobody else can join.
        $later = (int) $quest->timeend + DAYSECS;
        $this->assertContains('Verano lector', array_column(quests::open($young, $later), 'name'));
        $this->assertFalse(quests::join($id, $older, $later));
        $this->assertNotContains('Verano lector', array_column(quests::open($young, $later + quests::AFTER * DAYSECS), 'name'));

        // Erased, the reader leaves the challenge; taken away, the challenge leaves nothing.
        erasure::reader((int) $young->id, $now);
        $this->assertSame(0, $DB->count_records(self::T . 'questreader', ['questid' => $id]));
        quests::join($DB->get_field(self::T . 'quest', 'id', ['name' => 'Todos']), $older, $now);
        quests::remove($library, (int) $DB->get_field(self::T . 'quest', 'id', ['name' => 'Todos']));
        $this->assertSame(0, $DB->count_records(self::T . 'questreader'));
        $this->assertSame(1, $DB->count_records(self::T . 'quest'));
    }
}
