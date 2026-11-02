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
namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\desk\policy;

/**
 * What a late book leaves: off of itself, days without lending, fines, and all that can be done about them.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sanctions::class)]
final class sanctions_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a reader with an account and three copies.
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
        $w->entityid = (int) $w->library->entityid;
        $w->user = $this->getDataGenerator()->create_user();
        $w->ana = $gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '10000H', 'userid' => $w->user->id,
            'firstname' => 'Ana', 'lastname' => 'Montes']);
        foreach (['B1', 'B2', 'B3'] as $code) {
            $record = $gen->create_record(['entityid' => $w->entityid, 'title' => 'Libro ' . $code]);
            $gen->create_item(['libraryid' => $w->library->id, 'recordid' => $record->id, 'barcode' => $code]);
        }
        return $w;
    }

    /**
     * Lends a copy and brings it back so many days after its date.
     *
     * @param \stdClass $w
     * @param string $code
     * @param int $late
     * @return array The answer of the return, and when: at.
     */
    private function late(\stdClass $w, string $code, int $late): array {
        global $DB;
        $lent = (new desk($w->library, $w->staff, $w->now))->scan($code, (int) $w->ana->id, true);
        $this->assertSame('lent', $lent['kind']);
        $due = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $lent['loanid']]);
        $at = $due + $late * DAYSECS;
        return (new desk($w->library, $w->staff, $at))->scan($code) + ['at' => $at];
    }

    /**
     * Off of itself: a late book leaves nothing, and nobody is stopped for it afterwards.
     */
    public function test_off_of_itself(): void {
        global $DB;
        $w = $this->world();
        $this->assertFalse(sanctions::on($w->entityid));
        $back = $this->late($w, 'B1', 9);
        $this->assertNull($back['sanction']);
        $this->assertSame(0, $DB->count_records(self::T . 'sanction'));
        $next = (new desk($w->library, $w->staff, $back['at']))->scan('B2', (int) $w->ana->id);
        $this->assertSame('lent', $next['kind']);
    }

    /**
     * Days without lending: worked out as it comes back, up to the ceiling; the desk stops and can go on; they end by
     * themselves and do not add up; the librarian lifts them; a return undone takes them away.
     */
    public function test_days(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'days', 'perday' => 1, 'maxdays' => 10, 'grace' => 1]);
        $this->assertTrue(sanctions::on($w->entityid));

        // One day late, with one of grace: nothing.
        $this->assertNull($this->late($w, 'B1', 1)['sanction']);

        // Five late, one of grace: four days, today not counted.
        $back = $this->late($w, 'B2', 5);
        $this->assertNotNull($back['sanction']);
        $this->assertSame(['lift'], array_column($back['sanction']['actions'], 'do'));
        $row = $DB->get_record(self::T . 'sanction', ['id' => $back['sanction']['id']]);
        $this->assertSame('days', $row->kind);
        $this->assertSame(5, (int) $row->dayslate);
        $tz = \core_date::get_server_timezone_object();
        $start = (new \DateTimeImmutable('@' . $back['at']))->setTimezone($tz)->setTime(0, 0);
        $this->assertSame($start->modify('+5 days')->getTimestamp(), (int) $row->timeuntil);

        $at = $back['at'] + DAYSECS;
        $desk = new desk($w->library, $w->staff, $at);
        $panel = $desk->reader_panel((int) $w->ana->id);
        $this->assertStringContainsString(
            userdate((int) $row->timeuntil - 1, get_string('strftimedate', 'langconfig')),
            $panel['sanction']['text']
        );
        $stop = $desk->scan('B3', (int) $w->ana->id);
        $this->assertSame('stop', $stop['kind']);
        $this->assertSame(policy::SUSPENDED, $stop['reason']);
        $this->assertTrue($stop['forceable']);
        $this->assertSame('lent', $desk->scan('B3', (int) $w->ana->id, true)['kind'], 'the librarian may go on');
        $DB->delete_records(self::T . 'loan', ['readerid' => $w->ana->id, 'timereturned' => null]);
        $DB->set_field(self::T . 'item', 'status', 'available', []);

        // The last day still stops; the day after, no more.
        $last = new desk($w->library, $w->staff, (int) $row->timeuntil - 60);
        $this->assertSame(policy::SUSPENDED, $last->scan('B3', (int) $w->ana->id)['reason']);
        $free = new desk($w->library, $w->staff, (int) $row->timeuntil + 60);
        $this->assertSame('lent', $free->scan('B3', (int) $w->ana->id)['kind']);
        $DB->delete_records(self::T . 'loan', ['readerid' => $w->ana->id, 'timereturned' => null]);
        $DB->set_field(self::T . 'item', 'status', 'available', []);

        // Lifted, they end today, and it says who did it.
        $lift = $desk->sanction((int) $w->ana->id, 'lift');
        $this->assertSame('sanction', $lift['kind']);
        $this->assertNull($lift['reader']['sanction']);
        $this->assertSame((string) sanctions::LIFTED, $DB->get_field(self::T . 'sanction', 'status', ['id' => $row->id]));
        $this->assertSame($w->staff, (int) $DB->get_field(self::T . 'sanction', 'closedby', ['id' => $row->id]));
        $this->assertSame('lent', $desk->scan('B3', (int) $w->ana->id)['kind']);
        $this->assertTrue(sanctions::reopen($w->library, (int) $row->id, $at));
        $this->assertSame(policy::SUSPENDED, sanctions::stop($w->entityid, (int) $w->ana->id, $at)['reason']);
    }

    /**
     * Two books back late do not add up: the longest counts. A return undone takes its row away.
     */
    public function test_days_do_not_add_up_and_undo(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'days', 'maxdays' => 30]);
        $first = $this->late($w, 'B1', 3);
        $second = $this->late($w, 'B2', 2);
        $this->assertSame(2, $DB->count_records(self::T . 'sanction'));
        $state = sanctions::state($w->entityid, (int) $w->ana->id, $second['at']);
        $this->assertSame(
            (int) $DB->get_field(self::T . 'sanction', 'timeuntil', ['id' => $first['sanction']['id']]),
            $state['until']
        );
        (new desk($w->library, $w->staff, $first['at']))->undo($first['loanid'], 'returned');
        $this->assertFalse($DB->record_exists(self::T . 'sanction', ['id' => $first['sanction']['id']]));
    }

    /**
     * A fine: as it comes back, with its ceiling; the desk stops from what the centre says; paid, forgiven and opened
     * again; an amount written down by hand counts too.
     */
    public function test_fine(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10, 'maxcents' => 50, 'block' => 80,
            'currency' => 'EUR']);
        $first = $this->late($w, 'B1', 3);
        $this->assertSame(30, (int) $DB->get_field(self::T . 'sanction', 'cents', ['id' => $first['sanction']['id']]));
        $this->assertSame(['pay', 'waive'], array_column($first['sanction']['actions'], 'do'));
        $at = $first['at'];
        $this->assertNull(sanctions::stop($w->entityid, (int) $w->ana->id, $at), 'under what the centre stops at');
        $second = $this->late($w, 'B2', 40);
        $this->assertSame(50, (int) $DB->get_field(self::T . 'sanction', 'cents', ['id' => $second['sanction']['id']]));
        $at = $second['at'];
        $desk = new desk($w->library, $w->staff, $at);
        $stop = $desk->scan('B3', (int) $w->ana->id);
        $this->assertSame(policy::OWES, $stop['reason']);
        $this->assertTrue($stop['forceable']);
        $this->assertStringContainsString(sanctions::money(80, 'EUR'), $stop['message']);
        $this->assertSame(80, sanctions::state($w->entityid, (int) $w->ana->id, $at)['owes']);

        // One row paid, by the record; then all, at the desk.
        $one = (int) $first['sanction']['id'];
        $this->assertSame(1, sanctions::settle($w->library, (int) $w->ana->id, sanctions::PAID, $w->staff, $at, $one));
        $this->assertSame(50, sanctions::state($w->entityid, (int) $w->ana->id, $at)['owes']);
        $this->assertNull(sanctions::stop($w->entityid, (int) $w->ana->id, $at));
        $waive = $desk->sanction((int) $w->ana->id, 'waive');
        $this->assertNull($waive['reader']['sanction']);
        $now = sanctions::now($w->entityid, $at);
        $this->assertSame(0, $now['pending']);
        $this->assertSame([], $now['owing']);

        // Written down by hand, for a lost book.
        $charge = sanctions::charge($w->library, (int) $w->ana->id, 1250, 'Libro perdido', $w->staff, $at);
        $this->assertSame(1250, sanctions::state($w->entityid, (int) $w->ana->id, $at)['owes']);
        $rows = sanctions::of_reader($w->entityid, (int) $w->ana->id, $at);
        $this->assertSame($charge, $rows[0]['id']);
        $this->assertTrue($rows[0]['open']);
        $this->assertSame('Libro perdido', $rows[0]['why']);
        $paid = get_string('sanctionstatepaid', 'local_oksigeniaayvurenda');
        $this->assertCount(1, array_filter($rows, fn($row) => $row['state'] === $paid));
    }

    /**
     * Whoever answers for it: only the stages chosen, not the adults if the centre says so, never a library that borrows,
     * and nothing at a textbook bank.
     */
    public function test_who_answers(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'days', 'stages' => ['Secundaria'], 'adults' => false]);
        $this->assertNull($this->late($w, 'B1', 4)['sanction'], 'no stage this year, so not in those chosen');

        $year = years::around($w->entityid, $w->now);
        $w->gen->create_member(['readerid' => $w->ana->id, 'yearid' => $year->id, 'groupname' => '1A', 'stage' => 'Secundaria']);
        $this->assertNotNull($this->late($w, 'B2', 4)['sanction']);

        $bank = $w->gen->create_library(['entityid' => $w->entityid, 'kind' => bank::KIND]);
        $record = $w->gen->create_record(['entityid' => $w->entityid, 'title' => 'Matemáticas']);
        $w->gen->create_item(['libraryid' => $bank->id, 'recordid' => $record->id, 'barcode' => 'T1']);
        $DB->delete_records(self::T . 'sanction');
        $lent = (new desk($bank, $w->staff, $w->now))->scan('T1', (int) $w->ana->id);
        $this->assertSame('lent', $lent['kind']);
        $due = (int) $DB->get_field(self::T . 'loan', 'timedue', ['id' => $lent['loanid']]);
        $back = (new desk($bank, $w->staff, $due + 5 * DAYSECS))->scan('T1');
        $this->assertNull($back['sanction'], 'a textbook bank leaves nothing');

        $lib = $w->gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '30000B', 'readertype' => 'library',
            'firstname' => 'Otra', 'lastname' => 'Biblioteca']);
        sanctions::save_settings($w->entityid, ['mode' => 'days']);
        $this->assertNull(sanctions::after_return($w->library, $lib, (object) ['id' => 1], 8, $w->staff, $w->now));
    }

    /**
     * A textbook bank lends whatever is owed; a reader stopped cannot reserve or renew from home, and is told why.
     */
    public function test_what_stops_and_what_is_said(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'days']);
        holds::save_settings($w->entityid, ['online' => true, 'renewonline' => true]);
        $this->assertTrue(holds::online($w->library, (int) $w->ana->id, $w->now));
        $back = $this->late($w, 'B1', 5);
        $at = $back['at'];
        $this->assertFalse(holds::online($w->library, (int) $w->ana->id, $at));
        $this->assertFalse(holds::renew_online($w->library, (int) $w->ana->id, $at));
        $this->assertStringContainsString(
            userdate((int) $DB->get_field(self::T . 'sanction', 'timeuntil', []) - 1, get_string('strftimedate', 'langconfig')),
            sanctions::for_reader($w->entityid, (int) $w->ana->id, $at)
        );
        $summary = mine::summary((int) $w->user->id, $at);
        $this->assertNotSame('', $summary[0]['notice']);
        $cards = mine::home_cards((int) $w->user->id, $at);
        $this->assertTrue($cards[0]['items'][0]['urgent']);
        $this->assertSame($summary[0]['notice'], $cards[0]['items'][0]['title']);

        $bank = $w->gen->create_library(['entityid' => $w->entityid, 'kind' => bank::KIND]);
        $record = $w->gen->create_record(['entityid' => $w->entityid, 'title' => 'Matemáticas']);
        $w->gen->create_item(['libraryid' => $bank->id, 'recordid' => $record->id, 'barcode' => 'T1']);
        $this->assertSame('lent', (new desk($bank, $w->staff, $at))->scan('T1', (int) $w->ana->id)['kind']);

        // A centre that stops doing it stops stopping.
        sanctions::save_settings($w->entityid, ['mode' => 'none']);
        $this->assertNull(sanctions::stop($w->entityid, (int) $w->ana->id, $at));
        $this->assertSame('', sanctions::for_reader($w->entityid, (int) $w->ana->id, $at));
    }

    /**
     * Nothing crosses centres: another centre's staff cannot close a row of this one, and the desk does not reach it.
     */
    public function test_apart(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10]);
        $back = $this->late($w, 'B1', 3);
        $other = $w->gen->create_library();
        $this->assertSame(0, sanctions::settle($other, (int) $w->ana->id, sanctions::PAID, $w->staff, $back['at']));
        $this->assertSame(0, sanctions::lift($other, (int) $w->ana->id, $w->staff, $back['at']));
        $this->assertFalse(sanctions::reopen($other, (int) $back['sanction']['id'], $back['at']));
        $this->assertSame(sanctions::OPEN, $DB->get_field(self::T . 'sanction', 'status', ['id' => $back['sanction']['id']]));
    }

    /**
     * Each row is told once, in a message, and what is old is not told at all.
     */
    public function test_notify(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10]);
        $back = $this->late($w, 'B1', 3);
        $sink = $this->redirectMessages();
        $this->assertSame(1, sanctions::notify($back['at']));
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertSame('sanction', $messages[0]->eventtype);
        $this->assertStringContainsString('Libro B1', $messages[0]->fullmessage);
        $this->assertSame(0, sanctions::notify($back['at']), 'once');

        $late = $this->late($w, 'B2', 4);
        $this->assertSame(0, sanctions::notify($late['at'] + 10 * DAYSECS), 'not long after');
        $this->assertTrue($DB->record_exists_select(self::T . 'sanction', 'timetold IS NULL'));
    }

    /**
     * Two records of one person, joined: what they owe comes along.
     */
    public function test_joining_twins(): void {
        global $DB;
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10]);
        $back = $this->late($w, 'B1', 3);
        $twin = $w->gen->create_reader(['entityid' => $w->entityid, 'cardcode' => '20000X', 'firstname' => 'Ana',
            'lastname' => 'Montes']);
        twins::join($w->entityid, (int) $twin->id, (int) $w->ana->id);
        $owner = $DB->get_field(self::T . 'sanction', 'readerid', ['id' => $back['sanction']['id']]);
        $this->assertSame((int) $twin->id, (int) $owner);
    }

    /**
     * The file: every row, nothing a spreadsheet would run.
     */
    public function test_export(): void {
        $w = $this->world();
        sanctions::save_settings($w->entityid, ['mode' => 'fine', 'cents' => 10]);
        $back = $this->late($w, 'B1', 3);
        sanctions::charge($w->library, (int) $w->ana->id, 100, '=HYPERLINK("x")', $w->staff, $back['at']);
        $rows = sanctions::export($w->entityid, $back['at']);
        $this->assertCount(2, $rows);
        $this->assertSame('0.30', $rows[0][7]);
        $this->assertSame("'=HYPERLINK(\"x\")", $rows[1][4]);
    }
}
