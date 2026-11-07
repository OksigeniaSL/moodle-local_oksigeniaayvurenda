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
 * Tests for the collecting and the handing out of a textbook bank.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\bankround
 */
#[\PHPUnit\Framework\Attributes\CoversClass(bankround::class)]
final class bankround_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A bank with a set for 1º A: Ana (with an account) and Leo (without) in it, each with a book out, and Noa still waiting.
     *
     * @return \stdClass
     */
    private function world(): \stdClass {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->central = $gen->create_library(['slug' => 'central']);
        $w->entity = (int) $w->central->entityid;
        $w->bank = libraries::create_library($w->entity, ['name' => 'Banco', 'kind' => 'bank']);
        $w->now = time();
        $year = \local_oksigeniaayvurenda\core\school_year::around($w->now, '09-01', \core_date::get_server_timezone_object());
        $w->year = $gen->create_year(['entityid' => $w->entity] + $year);
        $w->user = $this->getDataGenerator()->create_user(['firstname' => 'Ana']);
        $w->ana = $gen->create_reader(['entityid' => $w->entity, 'firstname' => 'Ana', 'lastname' => 'Uno',
            'userid' => $w->user->id]);
        $w->leo = $gen->create_reader(['entityid' => $w->entity, 'firstname' => 'Leo', 'lastname' => 'Dos']);
        $w->noa = $gen->create_reader(['entityid' => $w->entity, 'firstname' => 'Noa', 'lastname' => 'Tres']);
        foreach ([$w->ana, $w->leo, $w->noa] as $reader) {
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $w->year->id, 'groupname' => '1º A']);
        }
        $w->record = $gen->create_record(['entityid' => $w->entity, 'title' => 'Matemáticas 1']);
        $set = bank::save_set($w->bank, 'Primero', ['1º A', '9º Z']);
        bank::add_title($w->bank, $set, (int) $w->record->id);
        $w->loans = [];
        foreach ([$w->ana, $w->leo] as $reader) {
            $item = $gen->create_item(['libraryid' => $w->bank->id, 'recordid' => $w->record->id, 'status' => 'onloan']);
            $w->loans[$reader->firstname] = $DB->insert_record(self::T . 'loan', ['libraryid' => $w->bank->id,
                'itemid' => $item->id, 'readerid' => $reader->id, 'yearid' => $w->year->id, 'groupname' => '1º A',
                'timeloaned' => $w->now - DAYSECS,
                'timedue' => (int) $w->year->timeend, 'renewals' => 0, 'timecreated' => $w->now, 'timemodified' => $w->now]);
            $w->item[$reader->firstname] = (int) $item->id;
        }
        return $w;
    }

    /**
     * The collecting counts what is out, by class, and who cannot be told; a book given up for lost leaves the count and can turn
     * up.
     */
    public function test_collection_and_losses(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->world();
        $c = bankround::collection($w->bank, $w->now);
        $this->assertSame(2, $c['out']);
        $this->assertSame(2, $c['holders']);
        $this->assertSame(1, $c['noaccount']);
        $this->assertSame('1º A', $c['classes'][0]['group']);

        $this->assertTrue(bankround::lost($w->bank, $w->loans['Leo'], 2, $w->now));
        $this->assertFalse(bankround::lost($w->bank, $w->loans['Leo'], 2, $w->now));
        $this->assertSame('lost', $DB->get_field(self::T . 'item', 'status', ['id' => $w->item['Leo']]));
        $this->assertSame(1, bankround::collection($w->bank, $w->now)['out']);
        $losses = bankround::losses($w->bank, $w->now);
        $this->assertCount(1, $losses);
        $this->assertSame('Leo Dos', $losses[0]['name']);
        $this->assertSame('Matemáticas 1', $losses[0]['title']);
        // The set counts it to replace.
        $this->assertSame(1, bank::needs($w->bank, $w->now)[0]['titles'][0]['lost']);

        $this->assertTrue(bankround::found($w->bank, $w->loans['Leo'], $w->now));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->item['Leo']]));
        $this->assertSame([], bankround::losses($w->bank, $w->now));
        $this->assertFalse(bankround::found($w->bank, $w->loans['Leo'], $w->now));
    }

    /**
     * Handing out: who waits, how many copies are short, and the groups that do not match this year's.
     */
    public function test_handout(): void {
        $this->resetAfterTest();
        $w = $this->world();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $eva = $gen->create_reader(['entityid' => $w->entity, 'firstname' => 'Eva', 'lastname' => 'Cuatro']);
        $gen->create_member(['readerid' => $eva->id, 'yearid' => $w->year->id, 'groupname' => '2º B']);
        $h = bankround::handout($w->bank, $w->now);
        $this->assertSame(1, $h['waiting']);
        // Three pupils, two copies out, none free.
        $this->assertSame(1, $h['short']);
        $this->assertSame(['2º B'], $h['nosets']);
        $this->assertSame(['9º Z'], $h['empty']);
    }

    /**
     * The reminder goes to whoever has an account, with their books and the date, and at most once a day; the tutor gets the names
     * and how many, without titles.
     */
    public function test_reminders(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->world();
        $deadline = $w->now + 20 * DAYSECS;
        bankround::save_state($w->bank, ['deadline' => $deadline]);
        $this->assertSame($deadline, bankround::state($w->bank)['deadline']);

        $sink = $this->redirectMessages();
        $this->assertSame(1, bankround::remind_readers($w->bank, $w->now));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('bankreturn', $messages[0]->eventtype);
        $this->assertEquals($w->user->id, $messages[0]->useridto);
        $this->assertStringContainsString('Matemáticas 1', $messages[0]->fullmessage);
        $date = userdate($deadline, get_string('strftimedateshort', 'langconfig'));
        $this->assertStringContainsString($date, $messages[0]->fullmessage);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/index.php?library=', $messages[0]->contexturl);
        $this->assertSame(1, bankround::state($w->bank)['readerssent']);
        $sink->clear();
        // The task tried again (it failed further on): whoever was told is not told twice.
        $this->assertSame(1, bankround::remind_readers($w->bank, $w->now));
        $this->assertCount(0, $sink->get_messages());

        $tutor = $this->getDataGenerator()->create_user();
        $DB->insert_record(self::T . 'tutor', ['yearid' => $w->year->id, 'groupname' => '1º A', 'userid' => $tutor->id,
            'timecreated' => $w->now, 'timemodified' => $w->now]);
        $this->assertSame(1, bankround::remind_tutors($w->bank, $w->now));
        $messages = $sink->get_messages();
        $this->assertEquals($tutor->id, $messages[0]->useridto);
        $this->assertStringContainsString('Leo Dos', $messages[0]->fullmessage);
        $this->assertStringNotContainsString('Matemáticas', $messages[0]->fullmessage);
        $sink->close();

        $this->assertTrue(bankround::queue_reminder($w->bank, 'readers', $w->now));
        $this->assertFalse(bankround::queue_reminder($w->bank, 'readers', $w->now + HOURSECS));
        $this->assertTrue(bankround::queue_reminder($w->bank, 'readers', $w->now + 2 * DAYSECS));
        // A new round starts with nobody told.
        $this->assertSame([], bankround::state($w->bank)['readerstold']);
        $this->assertFalse(bankround::queue_reminder($w->bank, 'nobody', $w->now));
    }
}
