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
 * Tests for reservations, at the desk and online.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\holds
 * @covers     \local_oksigeniaayvurenda\local\desk
 */
#[\PHPUnit\Framework\Attributes\CoversClass(holds::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(desk::class)]
final class holds_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with one book in two copies, both out (to Ana and Leo), and Hugo and Marta, who want it.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library(['slug' => 'central']);
        $now = time();
        $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => '*', 'itemtype' => 'normal',
            'loandays' => 14, 'maxloans' => 3, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $now,
            'timemodified' => $now]);
        $year = \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', \core_date::get_server_timezone_object());
        $w->year = $gen->create_year(['entityid' => $w->library->entityid] + $year);
        $w->user = $this->getDataGenerator()->create_user();
        foreach (['ana' => 'Ana', 'leo' => 'Leo', 'hugo' => 'Hugo', 'marta' => 'Marta'] as $key => $name) {
            $w->$key = $gen->create_reader(['entityid' => $w->library->entityid, 'firstname' => $name,
                'lastname' => 'Prueba', 'userid' => $key === 'hugo' ? $w->user->id : null]);
            $gen->create_member(['readerid' => $w->$key->id, 'yearid' => $w->year->id, 'groupname' => '5º A',
                'stage' => $key === 'marta' ? 'Infantil' : 'Primaria']);
        }
        $w->record = $gen->create_record(['entityid' => $w->library->entityid, 'title' => 'El faro dormido']);
        $w->staff = $this->getDataGenerator()->create_user();
        $desk = new desk($w->library, (int) $w->staff->id);
        foreach (['one' => 'ana', 'two' => 'leo'] as $copy => $reader) {
            $w->$copy = $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $w->record->id]);
            $desk->scan($w->$copy->barcode, (int) $w->{$reader}->id);
        }
        return $w;
    }

    /**
     * The whole way: the queue, the copy set aside at the desk, the red card for anyone else, the loan to the reader
     * who waited, undoing each step, and the copy that waits past its date.
     */
    public function test_desk(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $lib = $w->library;
        $first = holds::add($lib, (int) $w->record->id, (int) $w->hugo->id, (int) $w->staff->id);
        $this->assertTrue($first['ok']);
        $this->assertSame('has', holds::add($lib, (int) $w->record->id, (int) $w->hugo->id, null)['reason']);
        $this->assertSame('has', holds::add($lib, (int) $w->record->id, (int) $w->ana->id, null)['reason']);
        $second = holds::add($lib, (int) $w->record->id, (int) $w->marta->id, null);
        $this->assertSame(1, holds::ahead($second['hold']));

        $desk = new desk($lib, (int) $w->staff->id);
        $analoan = $DB->get_field(self::T . 'loan', 'id', ['itemid' => $w->one->id, 'timereturned' => null]);
        $this->assertSame('stop', $desk->renew((int) $analoan)['kind'], 'others wait: no renewal');

        // Ana brings it back: set it aside for Hugo.
        $back = $desk->scan($w->one->barcode);
        $this->assertSame('returned', $back['kind']);
        $this->assertTrue($back['held']);
        $this->assertFalse($back['canrenew']);
        $this->assertSame('look', $back['sound']);
        $this->assertStringContainsString(fullname($w->user) . ' (5º A)', $back['message']);
        $this->assertSame('held', $DB->get_field(self::T . 'item', 'status', ['id' => $w->one->id]));
        $hold = $DB->get_record(self::T . 'hold', ['id' => $first['hold']->id]);
        $this->assertSame('ready', $hold->status);
        $this->assertEquals($w->one->id, $hold->itemid);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks('\local_oksigeniaayvurenda\task\notify_hold'));
        $this->assertSame('held', catalogue::record((int) $w->record->id, $lib)['copies'][0]['state'] === 'held'
            ? 'held' : catalogue::record((int) $w->record->id, $lib)['copies'][1]['state']);

        // Undoing the return: Hugo waits again; returning it again sets it aside again.
        $desk->undo((int) $back['loanid'], 'returned');
        $this->assertSame('waiting', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->one->id]));
        $desk->scan($w->one->barcode);

        // Nobody open: it says whose it is. Leo: the red card, which the librarian may override.
        $this->assertSame('needreader', $desk->scan($w->one->barcode)['kind']);
        $stop = $desk->scan($w->one->barcode, (int) $w->leo->id);
        $this->assertSame('stop', $stop['kind']);
        $this->assertSame('held', $stop['reason']);
        $this->assertTrue($stop['forceable']);

        // Hugo takes it: his reservation is fulfilled; undoing the loan sets it aside again.
        $lent = $desk->scan($w->one->barcode, (int) $w->hugo->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertSame('done', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $desk->undo((int) $lent['loanid'], 'lent');
        $this->assertSame('ready', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $this->assertSame('held', $DB->get_field(self::T . 'item', 'status', ['id' => $w->one->id]));
        $this->assertSame([['id' => (int) $hold->id, 'recordid' => (int) $w->record->id, 'title' => 'El faro dormido',
            'ready' => true]], array_map(
                fn($h) => array_intersect_key($h, array_flip(['id', 'recordid', 'title', 'ready'])),
                $desk->reader_panel((int) $w->hugo->id)['holds']
            ));

        // Lent to Leo anyway: Hugo goes back to the queue, first.
        $forced = $desk->scan($w->one->barcode, (int) $w->leo->id, true);
        $this->assertSame('lent', $forced['kind']);
        $this->assertSame('waiting', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $this->assertSame(0, holds::ahead($DB->get_record(self::T . 'hold', ['id' => $hold->id])));

        // Leo's first copy comes back: Hugo's. He does not come: past its date, to Marta.
        $desk->scan($w->two->barcode);
        $this->assertSame('ready', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $this->assertSame(1, holds::expire(time() + 30 * DAYSECS));
        $this->assertSame('expired', $DB->get_field(self::T . 'hold', 'status', ['id' => $hold->id]));
        $this->assertSame('ready', $DB->get_field(self::T . 'hold', 'status', ['id' => $second['hold']->id]));
        $this->assertSame('held', $DB->get_field(self::T . 'item', 'status', ['id' => $w->two->id]));

        // Marta changes her mind: the copy goes back to the shelf, and the book can no longer be reserved.
        holds::cancel((int) $second['hold']->id);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->two->id]));
        $this->assertSame('free', holds::add($lib, (int) $w->record->id, (int) $w->marta->id, null)['reason']);
        $this->assertSame([], holds::lists($lib, time())['ready']);
    }

    /**
     * Online by stage; the reader with an account hears once that it is set aside; a limit of reservations.
     */
    public function test_online_and_message(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $lib = $w->library;
        $this->assertFalse(holds::online($lib, (int) $w->hugo->id, time()));
        holds::save_settings((int) $lib->entityid, ['online' => true, 'stages' => ['Primaria'], 'max' => 1]);
        $this->assertTrue(holds::online($lib, (int) $w->hugo->id, time()));
        $this->assertFalse(holds::online($lib, (int) $w->marta->id, time()));
        $this->assertSame(['Infantil', 'Primaria'], holds::stages((int) $lib->entityid, time()));

        $hold = holds::add($lib, (int) $w->record->id, (int) $w->hugo->id, (int) $w->user->id)['hold'];
        $other = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_item([
            'libraryid' => $lib->id, 'status' => 'onloan']);
        $this->assertSame('max', holds::add($lib, (int) $other->recordid, (int) $w->hugo->id, null)['reason']);

        (new desk($lib, (int) $w->staff->id))->scan($w->one->barcode);
        $sink = $this->redirectMessages();
        $this->assertTrue(holds::notify((int) $hold->id));
        $this->assertFalse(holds::notify((int) $hold->id));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('holdready', $messages[0]->eventtype);
        $this->assertEquals($w->user->id, $messages[0]->useridto);
        $this->assertNotEmpty($DB->get_field(self::T . 'hold', 'timenotified', ['id' => $hold->id]));
    }
}
