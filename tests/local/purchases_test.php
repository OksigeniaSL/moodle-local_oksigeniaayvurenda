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
 * What a library buys: from the asking to the arrival, and what it costs.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(purchases::class)]
final class purchases_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library whose readers may ask, with two readers.
     *
     * @return \stdClass
     */
    private function world(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->gen = $gen;
        $w->library = $gen->create_library();
        $entityid = (int) $w->library->entityid;
        $w->ana = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Montes']);
        $w->leo = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10001L', 'firstname' => 'Leo',
            'lastname' => 'Faro']);
        $w->staff = (int) get_admin()->id;
        $w->now = time();
        return $w;
    }

    /**
     * Prices as people write them.
     */
    public function test_prices_and_isbns(): void {
        $this->assertNull(purchases::cents(''));
        $this->assertSame(1250, purchases::cents('12,50'));
        $this->assertSame(1250, purchases::cents('12.50 €'));
        $this->assertSame(1200, purchases::cents('12'));
        $this->assertSame(120000, purchases::cents('1.200'));
        $this->assertSame(120050, purchases::cents('1.200,50'));
        $this->assertSame(120000, purchases::cents('1,200.00'));
        $this->assertSame(1250, purchases::cents('12,5'));
        $this->assertSame('9780743273565', purchases::isbn('978-0-7432-7356-5'));
        $this->assertSame('9780743273565', purchases::isbn('0-7432-7356-7'));
        $this->assertSame('', purchases::isbn('nothing'));
    }

    /**
     * Off by default; on, by stage; adults always; never at a bank nor from another centre.
     */
    public function test_who_may_ask(): void {
        $w = $this->world();
        $this->assertFalse(purchases::may_ask($w->library, $w->ana, $w->now));
        $this->assertFalse(purchases::may_ask($w->library, null, $w->now));
        purchases::save_settings($w->library, ['suggest' => true, 'stages' => []]);
        $this->assertTrue(purchases::may_ask($w->library, $w->ana, $w->now));
        $year = years::around((int) $w->library->entityid, $w->now);
        $w->gen->create_member(['readerid' => $w->ana->id, 'yearid' => $year->id, 'groupname' => '3 A', 'stage' => 'Primaria']);
        purchases::save_settings($w->library, ['suggest' => true, 'stages' => ['Secundaria']]);
        $this->assertFalse(purchases::may_ask($w->library, $w->ana, $w->now));
        $teacher = $w->gen->create_reader(['entityid' => $w->library->entityid, 'cardcode' => '10002K', 'readertype' => 'teacher']);
        $this->assertTrue(purchases::may_ask($w->library, $teacher, $w->now));
        $other = $w->gen->create_library();
        purchases::save_settings($other, ['suggest' => true, 'stages' => []]);
        $this->assertFalse(purchases::may_ask($other, $teacher, $w->now), 'a reader of another centre');
    }

    /**
     * The same book asked twice is one, with both people; a reader has a limit; the same person asking again changes nothing.
     */
    public function test_asking(): void {
        global $DB;
        $w = $this->world();
        $this->assertSame('empty', purchases::ask($w->library, $w->ana, ['title' => ' ']));
        $this->assertSame('asked', purchases::ask($w->library, $w->ana, ['title' => 'El faro', 'note' => 'para clase']));
        $this->assertSame('known', purchases::ask($w->library, $w->ana, ['title' => 'el FARO']));
        $this->assertSame('joined', purchases::ask($w->library, $w->leo, ['title' => 'El faro']));
        $this->assertSame(1, $DB->count_records(self::T . 'purchase'));
        $this->assertSame(2, $DB->count_records(self::T . 'purchasewho'));
        $row = purchases::list($w->library, 'asked')[0];
        $this->assertSame(['Ana Montes', 'Leo Faro'], $row['names']);
        foreach (['b', 'c', 'd', 'e'] as $title) {
            $this->assertSame('asked', purchases::ask($w->library, $w->ana, ['title' => $title]));
        }
        $this->assertSame('full', purchases::ask($w->library, $w->ana, ['title' => 'one more']));
        // By ISBN too, written either way.
        $this->assertSame('asked', purchases::ask($w->library, $w->leo, ['title' => 'Otro', 'isbn' => '978-0-7432-7356-5']));
        $this->assertSame('joined', purchases::ask($w->library, $w->gen->create_reader(['entityid' => $w->library->entityid,
            'cardcode' => '10003H']), ['title' => 'Con otro nombre', 'isbn' => '0-7432-7356-7']));
    }

    /**
     * From yes to the order, to its sheet, to the budget.
     */
    public function test_from_yes_to_the_order(): void {
        global $DB;
        $w = $this->world();
        purchases::ask($w->library, $w->ana, ['title' => 'El faro']);
        $id = (int) $DB->get_field(self::T . 'purchase', 'id', ['title' => 'El faro']);
        $this->assertTrue(purchases::approve($w->library, $id, ['copies' => 2, 'price' => '12,50',
            'vendor' => 'Sur'], $w->staff, $w->now));
        $this->assertSame(0, purchases::budget($w->library, $w->now)['spent'], 'nothing spent until it is ordered');
        purchases::add($w->library, ['title' => 'Atlas', 'copies' => 1, 'price' => '30', 'vendor' => 'Norte'], $w->staff, $w->now);
        $this->assertSame(0, purchases::add($w->library, ['title' => ''], $w->staff, $w->now));

        $this->assertSame(0, purchases::order($w->library, 'Nadie', $w->staff, $w->now));
        $order = purchases::order($w->library, 'Sur', $w->staff, $w->now);
        $this->assertGreaterThan(0, $order);
        $this->assertSame('ordered', $DB->get_field(self::T . 'purchase', 'status', ['id' => $id]));
        $other = $DB->get_field(self::T . 'purchase', 'status', ['title' => 'Atlas']);
        $this->assertSame('approved', $other, 'the other vendor waits');
        $this->assertCount(1, purchases::order_of($w->library, $order)['rows']);
        $this->assertSame(2500, purchases::budget($w->library, $w->now)['spent']);

        purchases::save_budget($w->library, '1.200', 'USD', $w->now);
        $budget = purchases::budget($w->library, $w->now);
        $this->assertSame(120000, $budget['amount']);
        $this->assertSame('USD', $budget['currency']);
        purchases::save_budget($w->library, '', 'USD', $w->now);
        $this->assertNull(purchases::budget($w->library, $w->now)['amount']);

        // Nothing of another library's.
        $other = $w->gen->create_library();
        $this->assertNull(purchases::order_of($other, $order));
        $this->assertFalse(purchases::approve($other, $id, [], $w->staff));
        $this->assertFalse(purchases::decline($other, $id, $w->staff));
        $this->assertFalse(purchases::received($other, $id));
    }

    /**
     * When it arrives with its ISBN, or a copy's code, whoever asked is told, once; without either, not yet.
     */
    public function test_arriving(): void {
        global $DB;
        $w = $this->world();
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field(self::T . 'reader', 'userid', $user->id, ['id' => $w->ana->id]);
        $w->ana->userid = $user->id;
        $sink = $this->redirectMessages();
        purchases::ask($w->library, $w->ana, ['title' => 'El faro', 'isbn' => '9780743273565']);
        purchases::ask($w->library, $w->ana, ['title' => 'Sin ISBN']);
        $faro = (int) $DB->get_field(self::T . 'purchase', 'id', ['title' => 'El faro']);
        $bare = (int) $DB->get_field(self::T . 'purchase', 'id', ['title' => 'Sin ISBN']);
        purchases::approve($w->library, $faro, ['vendor' => 'Sur'], $w->staff, $w->now);
        purchases::approve($w->library, $bare, ['vendor' => 'Sur'], $w->staff, $w->now);
        purchases::order($w->library, 'Sur', $w->staff, $w->now);
        $this->assertTrue(purchases::received($w->library, $bare));
        $this->assertSame(0, $sink->count(), 'no record yet: nothing to tell');

        // Catalogued: told about the one with an ISBN, once.
        $added = cataloguer::add($w->library, ['record' => ['doctype' => 'book', 'title' => 'El faro', 'isbn' => '9780743273565'],
            'authorities' => [], 'extra' => []], ['count' => 1]);
        $this->assertSame('received', $DB->get_field(self::T . 'purchase', 'status', ['id' => $faro]));
        $this->assertSame((int) $added['recordid'], (int) $DB->get_field(self::T . 'purchase', 'recordid', ['id' => $faro]));
        $this->assertSame(1, $sink->count());
        $this->assertSame('purchasearrived', $sink->get_messages()[0]->eventtype);
        cataloguer::add($w->library, ['record' => ['doctype' => 'book', 'title' => 'El faro', 'isbn' => '9780743273565'],
            'authorities' => [], 'extra' => []], ['count' => 1], (int) $added['recordid']);
        $this->assertSame(1, $sink->count(), 'more copies tell nobody again');

        // One already arrived, without its ISBN: nothing can link it now but a new one with a code.
        purchases::ask($w->library, $w->ana, ['title' => 'Con código']);
        $code = (int) $DB->get_field(self::T . 'purchase', 'id', ['title' => 'Con código']);
        purchases::approve($w->library, $code, [], $w->staff, $w->now);
        $barcode = $DB->get_field(self::T . 'item', 'barcode', ['recordid' => $added['recordid']], IGNORE_MULTIPLE);
        $this->assertTrue(purchases::received($w->library, $code, $barcode));
        $this->assertSame((int) $added['recordid'], (int) $DB->get_field(self::T . 'purchase', 'recordid', ['id' => $code]));
        $this->assertSame(2, $sink->count());
        $sink->close();
    }

    /**
     * What runs short: readings aside, a book with a queue; off the list once it is on it.
     */
    public function test_what_runs_short(): void {
        global $DB;
        $w = $this->world();
        $record = $w->gen->create_record(['entityid' => $w->library->entityid, 'title' => 'Con cola', 'isbn' => '9780743273565']);
        $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '100000L']);
        foreach ([1, 2] as $i) {
            $DB->insert_record(self::T . 'hold', ['libraryid' => $w->library->id, 'recordid' => $record->id,
                'readerid' => $i === 1 ? $w->ana->id : $w->leo->id, 'status' => 'waiting', 'timecreated' => $w->now,
                'timemodified' => $w->now]);
        }
        $this->assertSame([], purchases::ideas($w->library), 'two is no queue');
        $third = $w->gen->create_reader(['entityid' => $w->library->entityid, 'cardcode' => '10003H']);
        $DB->insert_record(self::T . 'hold', ['libraryid' => $w->library->id, 'recordid' => $record->id, 'readerid' => $third->id,
            'status' => 'waiting', 'timecreated' => $w->now, 'timemodified' => $w->now]);
        $ideas = purchases::ideas($w->library);
        $this->assertCount(1, $ideas);
        $this->assertSame(3, $ideas[0]['waiting']);
        $this->assertSame(1, $ideas[0]['copies']);
        $this->assertSame('9780743273565', $ideas[0]['isbn']);
        purchases::add($w->library, ['title' => 'Con cola', 'recordid' => $record->id, 'source' => 'queue'], $w->staff, $w->now);
        $this->assertSame([], purchases::ideas($w->library));
    }

    /**
     * A centre that goes takes everything; a library that goes, its part.
     */
    public function test_going(): void {
        global $DB;
        $w = $this->world();
        $other = $w->gen->create_library(['entityid' => $w->library->entityid]);
        purchases::ask($other, $w->ana, ['title' => 'El faro']);
        purchases::save_budget($other, '100', 'EUR', $w->now);
        purchases::add($other, ['title' => 'Atlas', 'vendor' => 'Sur'], $w->staff, $w->now);
        purchases::order($other, 'Sur', $w->staff, $w->now);
        $this->assertTrue(libraries::delete_library((int) $other->id));
        foreach (['purchase', 'purchasewho', 'purchaseorder', 'budget'] as $table) {
            $this->assertSame(0, $DB->count_records(self::T . $table), $table);
        }
        purchases::ask($w->library, $w->ana, ['title' => 'El faro']);
        removal::remove((int) $w->library->entityid);
        foreach (['purchase', 'purchasewho'] as $table) {
            $this->assertSame(0, $DB->count_records(self::T . $table), $table);
        }
    }
}
