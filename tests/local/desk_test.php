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
 * Tests for the desk.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\desk
 */
#[\PHPUnit\Framework\Attributes\CoversClass(desk::class)]
final class desk_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a rule of one book a week for students, Ana with her card and group, and three copies.
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
        $rules = [['student', 'normal', 7, 1, 1, 7], ['*', 'reference', 0, 0, 0, 0]];
        foreach ($rules as [$reader, $item, $days, $max, $renew, $rdays]) {
            $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => $reader, 'itemtype' => $item,
                'loandays' => $days, 'maxloans' => $max, 'renewals' => $renew, 'renewdays' => $rdays,
                'timecreated' => $now, 'timemodified' => $now]);
        }
        $w->ana = $gen->create_reader(['entityid' => $w->library->entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Montes']);
        $w->leo = $gen->create_reader(['entityid' => $w->library->entityid, 'firstname' => 'Leo', 'lastname' => 'Montes']);
        $year = \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', \core_date::get_server_timezone_object());
        $w->year = $gen->create_year(['entityid' => $w->library->entityid] + $year);
        $gen->create_member(['readerid' => $w->ana->id, 'yearid' => $w->year->id, 'groupname' => '3º Primaria A']);
        $w->faro = $gen->create_item(['libraryid' => $w->library->id, 'barcode' => '100000L']);
        $w->isla = $gen->create_item(['libraryid' => $w->library->id, 'barcode' => '100001C']);
        $w->atlas = $gen->create_item(['libraryid' => $w->library->id, 'barcode' => '100002K', 'itemtype' => 'reference']);
        $w->staff = $this->getDataGenerator()->create_user();
        return $w;
    }

    /**
     * The card opens the reader; a book without a reader asks for the card; with the reader, it is lent.
     */
    public function test_lend(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $reader = $desk->scan('10000h');
        $this->assertSame('reader', $reader['kind']);
        $this->assertSame('Ana Montes', $reader['reader']['name']);
        $this->assertSame('3º Primaria A', $reader['reader']['group']);
        $this->assertSame('needreader', $desk->scan('100000L')['kind']);

        $lent = $desk->scan('100000L', (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $loan = $DB->get_record(self::T . 'loan', ['id' => $lent['loanid']], '*', MUST_EXIST);
        $this->assertSame('3º Primaria A', $loan->groupname);
        $this->assertEquals($w->year->id, $loan->yearid);
        $this->assertEquals($w->staff->id, $loan->staffid);
        $this->assertGreaterThan(time() + 6 * DAYSECS, (int) $loan->timedue);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
        $this->assertCount(1, $lent['reader']['loans']);
    }

    /**
     * The limit stops the second book until the librarian says so; a book to read here never goes.
     */
    public function test_limit_and_force(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $desk->scan('100000L', (int) $w->ana->id);
        $stop = $desk->scan('100001C', (int) $w->ana->id);
        $this->assertSame('stop', $stop['kind']);
        $this->assertSame('limit', $stop['reason']);
        $this->assertTrue($stop['forceable']);
        $this->assertSame('lent', $desk->scan('100001C', (int) $w->ana->id, true)['kind']);
        $reference = $desk->scan('100002K', (int) $w->ana->id, true);
        $this->assertSame('notlendable', $reference['reason']);
        $this->assertFalse($reference['forceable']);
    }

    /**
     * A book on loan that comes back is returned, with its note; it can be renewed instead or undone.
     */
    public function test_return_note_renew_undo(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $lent = $desk->scan('100000L', (int) $w->ana->id);
        $back = $desk->scan('100000L');
        $this->assertSame('returned', $back['kind']);
        $this->assertSame($lent['loanid'], $back['loanid']);
        $this->assertTrue($back['canrenew']);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));

        $desk->note($back['loanid'], 'loved');
        $this->assertSame('loved', $DB->get_field(self::T . 'loan', 'returnnote', ['id' => $back['loanid']]));
        $desk->note($back['loanid'], 'nonsense');
        $this->assertNull($DB->get_field(self::T . 'loan', 'returnnote', ['id' => $back['loanid']]));

        $renewed = $desk->renew($back['loanid']);
        $this->assertSame('renewed', $renewed['kind']);
        $loan = $DB->get_record(self::T . 'loan', ['id' => $back['loanid']]);
        $this->assertNull($loan->timereturned);
        $this->assertSame(1, (int) $loan->renewals);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
        $this->assertSame('stop', $desk->renew($back['loanid'])['kind']);

        $desk->undo($back['loanid'], 'renewed', $renewed['previousdue']);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'loan', 'renewals', ['id' => $back['loanid']]));
        $desk->undo($back['loanid'], 'lent');
        $this->assertFalse($DB->record_exists(self::T . 'loan', ['id' => $back['loanid']]));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
    }

    /**
     * A return can be undone; a late loan stops the next one and shows in the reader and in today's counts.
     */
    public function test_late_and_undo_return(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $lent = $desk->scan('100000L', (int) $w->ana->id);
        $DB->set_field(self::T . 'loan', 'timedue', time() - 3 * DAYSECS, ['id' => $lent['loanid']]);
        $panel = $desk->reader_panel((int) $w->ana->id);
        $this->assertSame(1, $panel['latecount']);
        $this->assertSame(['out' => 1, 'today' => 0, 'late' => 1], $desk->today());
        $stop = $desk->scan('100001C', (int) $w->ana->id);
        $this->assertSame('limit', $stop['reason']);

        $back = $desk->scan('100000L');
        $this->assertSame('look', $back['sound']);
        $this->assertGreaterThan(0, $back['late']);
        $desk->undo($back['loanid'], 'returned');
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $back['loanid']]));
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
    }

    /**
     * A copy marked out without a loan, as an import may leave it, comes back too; unknown codes say so; names find
     * readers who came without their card.
     */
    public function test_unknown_and_names(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $DB->set_field(self::T . 'item', 'status', 'onloan', ['id' => $w->isla->id]);
        $back = $desk->scan('100001C');
        $this->assertSame('returned', $back['kind']);
        $this->assertSame(0, $back['loanid']);
        $this->assertSame('unknown', $desk->scan('999999X')['kind']);
        $this->assertSame('reader', $desk->scan('ana montes')['kind']);
        $choose = $desk->scan('montes');
        $this->assertSame('choose', $choose['kind']);
        $this->assertCount(2, $choose['readers']);
    }
}
