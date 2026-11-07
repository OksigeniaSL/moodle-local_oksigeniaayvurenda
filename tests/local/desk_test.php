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
        $this->assertSame(['out' => 1, 'today' => 0, 'late' => 1, 'held' => 0], $desk->today());
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

    /**
     * The ISBN of the back cover works as a copy: without a reader it stays in the hand, with one it goes and comes back;
     * when it is not clear which copy, the desk offers both lists; an ISBN not in the catalogue says so.
     */
    public function test_isbn(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $record = $gen->create_record(['entityid' => $w->library->entityid, 'title' => 'El gran Gatsby',
            'isbn' => '9780743273565']);
        $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '200000A']);
        $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '200001B']);
        $desk = new desk($w->library, (int) $w->staff->id);

        foreach (['9780743273565', '0743273567', '978-0-7432-7356-5', '978074327356551299'] as $read) {
            $hand = $desk->scan($read);
            $this->assertSame('needreader', $hand['kind'], $read);
            $this->assertSame('El gran Gatsby', $hand['title']);
        }
        $lent = $desk->scan('9780743273565', (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertEquals(1, $DB->get_field(self::T . 'loan', 'copyguessed', ['id' => $lent['loanid']]));

        // One out and one on the shelf: which one is in the hand is asked.
        $both = $desk->scan('9780743273565');
        $this->assertSame('choose', $both['kind']);
        $this->assertCount(1, $both['out']);
        $this->assertCount(1, $both['shelf']);

        // Ana brings it back by its ISBN.
        $this->assertSame('returned', $desk->scan('9780743273565', (int) $w->ana->id)['kind']);

        // Both out: the desk asks which comes back; only one out: it comes back at once.
        $desk->scan('9780743273565', (int) $w->ana->id);
        $desk->scan('9780743273565', (int) $w->leo->id);
        $two = $desk->scan('9780743273565');
        $this->assertSame('choose', $two['kind']);
        $this->assertCount(2, $two['out']);
        $this->assertSame([], $two['shelf']);
        $this->assertSame('returned', $desk->give_back_loan($two['out'][0]['loanid'])['kind']);
        $this->assertSame('returned', $desk->give_back_loan($two['out'][1]['loanid'])['kind']);
        $solo = $gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Solo', 'isbn' => '9781861972712']);
        $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $solo->id, 'barcode' => '200002C']);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'loan', 'copyguessed', ['id' => $desk->scan(
            '9781861972712',
            (int) $w->leo->id
        )['loanid']]));
        $this->assertSame('returned', $desk->scan('9781861972712')['kind']);

        // Not in the catalogue, and not a book number at all.
        $unknown = $desk->scan('9780306406157');
        $this->assertSame('unknown', $unknown['kind']);
        $this->assertStringContainsString('9780306406157', $unknown['message']);
        $this->assertSame(
            get_string('deskunknown', 'local_oksigeniaayvurenda', '8412345678901'),
            $desk->scan('8412345678901')['message']
        );
        $this->assertNull(desk::cover_isbn('100000L'));
        $this->assertSame('9780743273565', desk::cover_isbn('0-7432-7356-7'));
    }

    /**
     * A book a reader lost: the loan ends as lost and the copy is lost; «Undo» opens the loan again; lost again and then
     * passed at the desk, it is back on the shelf, or goes straight to the reader who is there.
     */
    public function test_lost_and_found(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $lent = $desk->scan('100000L', (int) $w->ana->id);
        $lost = $desk->lose((int) $lent['loanid'], (int) $w->ana->id);
        $this->assertSame('lost', $lost['kind']);
        $this->assertSame('lost', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
        $this->assertSame('lost', $DB->get_field(self::T . 'loan', 'conditionin', ['id' => $lent['loanid']]));
        $this->assertSame([], $lost['reader']['loans']);
        $this->assertSame('stop', $desk->lose((int) $lent['loanid'])['kind']);

        $desk->undo((int) $lent['loanid'], 'lost');
        $this->assertNull($DB->get_field(self::T . 'loan', 'timereturned', ['id' => $lent['loanid']]));
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));

        $desk->lose((int) $lent['loanid']);
        $found = $desk->scan('100000L');
        $this->assertSame('returned', $found['kind']);
        $this->assertStringContainsString('turned up', $found['message']);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->faro->id]));
        $this->assertNull($DB->get_field(self::T . 'loan', 'conditionin', ['id' => $lent['loanid']]));

        // Lost again; it turns up while Leo is at the desk: it goes to him.
        $again = $desk->scan('100000L', (int) $w->ana->id);
        $desk->lose((int) $again['loanid']);
        $this->assertSame('lent', $desk->scan('100000L', (int) $w->leo->id)['kind']);
    }

    /**
     * What changed since is not undone: a return undone, or a returned loan renewed, after another reader took the copy;
     * a loan undone after the book came back; a renewal undone with a date that was not this loan's.
     */
    public function test_what_changed_is_not_undone(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $desk = new desk($w->library, (int) $w->staff->id);
        $ana = $desk->scan('100000L', (int) $w->ana->id);
        $this->assertSame('returned', $desk->scan('100000L')['kind']);
        $leo = $desk->scan('100000L', (int) $w->leo->id);
        $this->assertSame('lent', $leo['kind']);
        $this->assertSame('stop', $desk->undo((int) $ana['loanid'], 'returned')['kind']);
        $this->assertSame('stop', $desk->renew((int) $ana['loanid'])['kind']);
        $this->assertNull($desk->renewable((int) $ana['loanid']));
        $this->assertSame(1, $DB->count_records_select(self::T . 'loan', 'itemid = ? AND timereturned IS NULL', [$w->faro->id]));
        $this->assertSame('returned', $desk->scan('100000L')['kind']);
        $this->assertSame('stop', $desk->undo((int) $leo['loanid'], 'lent')['kind']);
        $this->assertTrue($DB->record_exists(self::T . 'loan', ['id' => $leo['loanid']]));

        $isla = $desk->scan('100001C', (int) $w->ana->id);
        $this->assertSame('lent', $isla['kind']);
        $renewed = $desk->renew((int) $isla['loanid']);
        $this->assertSame('renewed', $renewed['kind']);
        $this->assertSame('stop', $desk->undo((int) $isla['loanid'], 'renewed', 1)['kind']);
        $this->assertSame('undone', $desk->undo((int) $isla['loanid'], 'renewed', (int) $renewed['previousdue'])['kind']);
    }

    /**
     * The limit counts what is out under the same rules: not the textbooks of the bank; a reader who left, or whose card
     * is on hold, is stopped (the librarian may go ahead); and a copy of a classroom lent at the central desk is the
     * classroom's loan.
     */
    public function test_limit_inactive_and_library(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $bank = $gen->create_library(['entityid' => $w->library->entityid, 'kind' => bank::KIND]);
        $textbook = $gen->create_item(['libraryid' => $bank->id, 'barcode' => 'BANK1']);
        $gen->create_loan(['libraryid' => $bank->id, 'itemid' => $textbook->id, 'readerid' => $w->ana->id,
            'yearid' => $w->year->id, 'timedue' => time() + 200 * DAYSECS]);
        $desk = new desk($w->library, (int) $w->staff->id);
        $this->assertSame('lent', $desk->scan('100000L', (int) $w->ana->id)['kind']);

        $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $w->leo->id]);
        $stopped = $desk->scan('100001C', (int) $w->leo->id);
        $this->assertSame('inactive', $stopped['reason']);
        $this->assertTrue($stopped['forceable']);
        $this->assertSame('lent', $desk->scan('100001C', (int) $w->leo->id, true)['kind']);

        $classroom = $gen->create_library(['entityid' => $w->library->entityid, 'kind' => 'classroom',
            'parentid' => $w->library->id]);
        $gen->create_item(['libraryid' => $classroom->id, 'barcode' => 'AULA1']);
        $DB->set_field(self::T . 'reader', 'status', 'active', ['id' => $w->leo->id]);
        $DB->delete_records(self::T . 'loan', ['readerid' => $w->leo->id]);
        $lent = $desk->scan('AULA1', (int) $w->leo->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertEquals($classroom->id, $DB->get_field(self::T . 'loan', 'libraryid', ['id' => $lent['loanid']]));
    }

    /**
     * Two desks read the same copy at once: the one that comes second, with the copy it read still free, does not lend it
     * again.
     */
    public function test_not_lent_twice(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $item = $DB->get_record(self::T . 'item', ['id' => $w->faro->id], '*', MUST_EXIST);
        $desk = new desk($w->library, (int) $w->staff->id);
        $this->assertSame('lent', $desk->scan('100000L', (int) $w->ana->id)['kind']);
        // The second desk had read it before the first lent it.
        $lend = new \ReflectionMethod(desk::class, 'lend');
        $second = $lend->invoke(new desk($w->library, (int) $w->staff->id), $item, (int) $w->leo->id, true);
        $this->assertSame('stop', $second['kind']);
        $this->assertSame(1, $DB->count_records(self::T . 'loan', ['itemid' => $w->faro->id]));
    }

    /**
     * Material: the unit is named by its serial number; its state is noted as it was when it goes, changed with a tap,
     * and offered as it is now when it comes back.
     */
    public function test_material(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $tablet = $gen->create_record(['entityid' => $w->library->entityid, 'doctype' => 'device', 'title' => 'Tableta']);
        $unit = $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $tablet->id, 'barcode' => '300000V',
            'itemtype' => 'device', 'serial' => 'SN-7']);
        $this->assertTrue(rules::cover((int) $w->library->id, 'device'));
        $this->assertFalse(rules::cover((int) $w->library->id, 'device'));
        $desk = new desk($w->library, (int) $w->staff->id);
        $lent = $desk->scan('300000V', (int) $w->leo->id);
        $this->assertSame('lent', $lent['kind']);
        $this->assertStringContainsString('Tableta (SN-7)', $lent['message']);
        $this->assertSame('good', $lent['material']['current']);
        $this->assertSame('out', $lent['material']['when']);
        $this->assertSame(desk::CONDITIONS, array_column($lent['material']['conditions'], 'value'));
        $this->assertSame('good', $DB->get_field(self::T . 'loan', 'conditionout', ['id' => $lent['loanid']]));
        $desk->condition($lent['loanid'], 'out', 'worn');
        $this->assertSame('worn', $DB->get_field(self::T . 'loan', 'conditionout', ['id' => $lent['loanid']]));
        $this->assertSame('worn', $DB->get_field(self::T . 'item', 'itemcondition', ['id' => $unit->id]));

        $back = $desk->scan('300000V');
        $this->assertSame('returned', $back['kind']);
        $this->assertSame(['current' => 'worn', 'when' => 'in'], array_intersect_key(
            $back['material'],
            ['current' => 1, 'when' => 1]
        ));
        $desk->condition($back['loanid'], 'in', 'missingpart');
        $loan = $DB->get_record(self::T . 'loan', ['id' => $back['loanid']]);
        $this->assertSame(['worn', 'missingpart'], [$loan->conditionout, $loan->conditionin]);
        $this->assertNull($desk->scan('100000L', (int) $w->ana->id)['material']);

        $this->expectException(\invalid_parameter_exception::class);
        $desk->condition($back['loanid'], 'in', 'lost');
    }
}
