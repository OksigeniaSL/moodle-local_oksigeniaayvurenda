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
 * Lending between libraries: both ways, and what stays apart from the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ill::class)]
final class ill_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a reader who has an account, and a copy.
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
        $w->staff = (int) get_admin()->id;
        $w->now = time();
        $entityid = (int) $w->library->entityid;
        $w->user = $this->getDataGenerator()->create_user();
        $w->ana = $gen->create_reader(['entityid' => $entityid, 'cardcode' => '10000H', 'userid' => $w->user->id,
            'firstname' => 'Ana', 'lastname' => 'Montes']);
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro']);
        $w->copy = $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => '100000L']);
        return $w;
    }

    /**
     * Asking: what is wrong says so; from the asking to going home, in order, with a notice when it comes.
     */
    public function test_asking(): void {
        global $DB;
        $w = $this->world();
        $this->assertSame('notitle', ill::ask($w->library, ['partner' => 'Central'], $w->staff));
        $this->assertSame('nopartner', ill::ask($w->library, ['title' => 'Atlas'], $w->staff));
        $this->assertSame('nocard', ill::ask($w->library, ['title' => 'Atlas', 'partner' => 'Central',
            'card' => 'NADA'], $w->staff));
        $id = ill::ask($w->library, ['title' => 'Atlas', 'partner' => 'Central', 'card' => $w->ana->cardcode], $w->staff, $w->now);
        $this->assertIsInt($id);
        $this->assertSame(['Central'], ill::partners($w->library));
        $row = ill::rows($w->library, 'in', $w->now)['open'][0];
        $this->assertSame($w->user->firstname . ' ' . $w->user->lastname, $row['who']);
        $this->assertFalse($DB->record_exists(self::T . 'record', ['title' => 'Atlas']), 'it stays out of the catalogue');

        $this->assertFalse(ill::move($w->library, $id, 'done', null, $w->now), 'not out of order');
        $sink = $this->redirectMessages();
        $this->assertTrue(ill::move($w->library, $id, 'arrived', $w->now + DAYSECS, $w->now));
        $this->assertSame(1, $sink->count());
        $this->assertSame('illarrived', $sink->get_messages()[0]->eventtype);
        $sink->close();
        $this->assertSame(0, ill::overdue($w->library, $w->now));
        $this->assertSame(1, ill::overdue($w->library, $w->now + 2 * DAYSECS));
        $this->assertTrue(ill::rows($w->library, 'in', $w->now + 2 * DAYSECS)['open'][0]['late']);
        foreach (['lent', 'back', 'done'] as $state) {
            $this->assertTrue(ill::move($w->library, $id, $state, null, $w->now), $state);
        }
        $this->assertSame(0, ill::overdue($w->library, $w->now + 2 * DAYSECS));
        $this->assertCount(1, ill::rows($w->library, 'in', $w->now)['closed']);

        // Nothing of another library's.
        $other = $w->gen->create_library();
        $this->assertFalse(ill::move($other, $id, 'cancelled'));
    }

    /**
     * Lending: it goes out at the desk to a reader that stands for that library, the same one each time, and when it comes
     * back at the desk it has ended.
     */
    public function test_lending(): void {
        global $DB;
        $w = $this->world();
        // With rules only for pupils, as a library usually has: another library is no pupil.
        $DB->insert_record(self::T . 'rule', ['libraryid' => $w->library->id, 'readertype' => 'student', 'itemtype' => '*',
            'loandays' => 14, 'maxloans' => 1, 'renewals' => 1, 'renewdays' => 7, 'timecreated' => $w->now,
            'timemodified' => $w->now]);
        $this->assertSame('nopartner', ill::lend($w->library, $w->copy->barcode, ' ', null, $w->staff));
        $this->assertSame('nocopy', ill::lend($w->library, 'NADA', 'Central', null, $w->staff));
        $due = $w->now + 30 * DAYSECS;
        $id = ill::lend($w->library, $w->copy->barcode, 'Central', $due, $w->staff, $w->now);
        $this->assertIsInt($id);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $w->copy->id]));
        $loan = $DB->get_record(self::T . 'loan', ['itemid' => $w->copy->id, 'timereturned' => null], '*', MUST_EXIST);
        $this->assertSame($due, (int) $loan->timedue);
        $this->assertSame(ill::READERTYPE, $DB->get_field(self::T . 'reader', 'readertype', ['id' => $loan->readerid]));
        $this->assertSame('stop', ill::lend($w->library, $w->copy->barcode, 'Central', null, $w->staff), 'it is out');
        $this->assertCount(1, ill::rows($w->library, 'out', $w->now)['open']);

        (new desk($w->library, $w->staff, $w->now))->scan($w->copy->barcode);
        $rows = ill::rows($w->library, 'out', $w->now);
        $this->assertSame([], $rows['open']);
        $this->assertSame('done', $rows['closed'][0]['status']);

        // The same library, the same reader; another centre's copy, no.
        ill::lend($w->library, $w->copy->barcode, 'central', null, $w->staff, $w->now);
        $this->assertSame(1, $DB->count_records(self::T . 'reader', ['readertype' => ill::READERTYPE]));
        $other = $w->gen->create_library();
        $this->assertSame('nocopy', ill::lend($other, $w->copy->barcode, 'Central', null, $w->staff));
    }

    /**
     * What a reader asked to buy can be asked of another library instead: for them, and off the list of what to decide.
     */
    public function test_from_a_purchase(): void {
        global $DB;
        $w = $this->world();
        purchases::ask($w->library, $w->ana, ['title' => 'Atlas', 'isbn' => '9780743273565']);
        $id = (int) $DB->get_field(self::T . 'purchase', 'id', ['title' => 'Atlas']);
        $this->assertFalse(ill::from_purchase($w->library, $id, '', $w->staff));
        $this->assertTrue(ill::from_purchase($w->library, $id, 'Central', $w->staff));
        $this->assertSame('borrowed', $DB->get_field(self::T . 'purchase', 'status', ['id' => $id]));
        $this->assertSame([], purchases::list($w->library, 'asked'));
        $row = ill::rows($w->library, 'in')['open'][0];
        $this->assertSame($w->user->firstname . ' ' . $w->user->lastname, $row['who']);
        $this->assertSame('9780743273565', $row['isbn']);
        $this->assertFalse(ill::from_purchase($w->library, $id, 'Central', $w->staff), 'once');
    }

    /**
     * A date from a form, and a library that goes.
     */
    public function test_dates_and_going(): void {
        global $DB;
        $w = $this->world();
        $this->assertNull(ill::date('', $w->library));
        $this->assertNull(ill::date('nothing', $w->library));
        $date = new \DateTime('2030-03-04 23:59:59', \core_date::get_server_timezone_object());
        $this->assertSame($date->getTimestamp(), ill::date('2030-03-04', $w->library));
        $other = $w->gen->create_library(['entityid' => $w->library->entityid]);
        ill::ask($other, ['title' => 'Atlas', 'partner' => 'Central'], $w->staff);
        $this->assertTrue(libraries::delete_library((int) $other->id));
        $this->assertSame(0, $DB->count_records(self::T . 'ill'));
    }
}
