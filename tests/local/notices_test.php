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
 * Tests for notices of late books, tutors, the list of late books and the daily copy.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\notices
 * @covers     \local_oksigeniaayvurenda\local\tutors
 * @covers     \local_oksigeniaayvurenda\local\late
 * @covers     \local_oksigeniaayvurenda\local\copies
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notices::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tutors::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(late::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(copies::class)]
final class notices_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with a group, a tutor, two readers with accounts and one without; three late loans and one on time.
     *
     * @return array
     */
    private function library(): array {
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $year = $gen->create_year(['entityid' => $library->entityid]);
        $now = make_timestamp(2026, 11, 10, 12);
        $ana = $dg->create_user(['firstname' => 'Ana', 'lastname' => 'Inventada']);
        $leo = $dg->create_user(['firstname' => 'Leo', 'lastname' => 'Inventado']);
        $tutor = $dg->create_user(['firstname' => 'Irene', 'lastname' => 'Tutora']);
        $readers = [
            'ana' => $gen->create_reader(['entityid' => $library->entityid, 'userid' => $ana->id]),
            'leo' => $gen->create_reader(['entityid' => $library->entityid, 'userid' => $leo->id]),
            'noa' => $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'Noa', 'lastname' => 'Sin Cuenta']),
        ];
        foreach ($readers as $reader) {
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '3º A']);
        }
        $loan = fn($reader, $title, $due) => $gen->create_loan(['libraryid' => $library->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'itemid' => $gen->create_item(['libraryid' => $library->id, 'recordid' =>
            $gen->create_record(['entityid' => $library->entityid, 'title' => $title])->id])->id,
            'timeloaned' => $due - 14 * DAYSECS, 'timedue' => $due]);
        $loan($readers['ana'], 'El faro dormido', $now - 3 * DAYSECS);
        $loan($readers['ana'], 'La nube de tinta', $now - 1 * DAYSECS);
        $loan($readers['noa'], 'Mapas del sur', $now - 10 * DAYSECS);
        $loan($readers['leo'], 'A tiempo', $now + 3 * DAYSECS);
        tutors::set((int) $year->id, '3º A', (int) $tutor->id);
        return [$library, $year, $now, $readers, $tutor];
    }

    /**
     * The reader hears with the titles, the tutor without them; a day later nobody hears again, a week later they do.
     */
    public function test_notices(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [, , $now, , $tutor] = $this->library();
        $sink = $this->redirectMessages();
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($now));
        $messages = $sink->get_messages();
        $sink->clear();
        $this->assertCount(2, $messages);
        $byname = [];
        foreach ($messages as $message) {
            $byname[$message->eventtype] = $message;
        }
        $this->assertStringContainsString('El faro dormido', $byname['overdue']->fullmessage);
        $this->assertStringContainsString('La nube de tinta', $byname['overdue']->fullmessage);
        $this->assertStringNotContainsString('A tiempo', $byname['overdue']->fullmessage);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/index.php?library=', $byname['overdue']->contexturl);
        $tutormessage = $byname['overduetutor'];
        $this->assertEquals($tutor->id, $tutormessage->useridto);
        $this->assertStringContainsString('Noa Sin Cuenta', $tutormessage->fullmessage);
        $this->assertStringContainsString('/local/oksigeniaayvurenda/class.php?library=', $tutormessage->contexturl);
        $this->assertStringContainsString('Ana Inventada', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('faro', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('Mapas', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('Leo', $tutormessage->fullmessage);

        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($now + DAYSECS));
        // A week later, Ana hears again and Leo, whose book is now late, for the first time.
        $this->assertSame(['readers' => 2, 'tutors' => 1], notices::run($now + 7 * DAYSECS));
        $sink->close();
    }

    /**
     * Each centre says how often: only by hand, nothing goes by itself; once a month, a week later only Leo hears, late by
     * then for the first time, and a month later Ana and the tutor again.
     */
    public function test_rhythm(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , $now] = $this->library();
        $entityid = (int) $library->entityid;
        $save = fn(array $settings) => sanctions::save_settings($entityid, $settings + sanctions::settings($entityid));
        $sink = $this->redirectMessages();
        $save(['noticeevery' => 'hand']);
        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($now));
        $save(['noticeevery' => 'month']);
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($now));
        $this->assertSame(['readers' => 1, 'tutors' => 0], notices::run($now + 7 * DAYSECS));
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($now + 30 * DAYSECS));
        $sink->close();
    }

    /**
     * On the centre's days of the year: nothing before the day; on it, the books late enough that day (Ana's oldest, not
     * the one late for six days then; Noa's, to the tutor), once; past a week after it, nothing until the next one.
     */
    public function test_on_its_days(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , $now] = $this->library();
        $entityid = (int) $library->entityid;
        sanctions::save_settings($entityid, ['noticeevery' => 'dates', 'noticedates' => ['11-15'], 'noticeafter' => 7]
            + sanctions::settings($entityid));
        $sink = $this->redirectMessages();
        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($now));
        $day = make_timestamp(2026, 11, 15, 9);
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($day));
        $told = array_filter($sink->get_messages(), fn($message) => $message->eventtype === 'overdue');
        $this->assertStringContainsString('El faro dormido', reset($told)->fullmessage);
        $this->assertStringNotContainsString('La nube de tinta', reset($told)->fullmessage);
        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($day + DAYSECS));
        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($day + 10 * DAYSECS));
        $sink->close();
    }

    /**
     * Each centre says to whom: without the tutor, only the reader hears; without the reader and the family, only the
     * tutor.
     */
    public function test_to_whom(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , $now] = $this->library();
        $entityid = (int) $library->entityid;
        $save = fn(array $settings) => sanctions::save_settings($entityid, $settings + sanctions::settings($entityid));
        $sink = $this->redirectMessages();
        $save(['noticetutors' => false]);
        $this->assertSame(['readers' => 1, 'tutors' => 0], notices::run($now));
        $save(['noticereaders' => false, 'noticefamilies' => false, 'noticetutors' => true]);
        $this->assertSame(['readers' => 0, 'tutors' => 1], notices::run($now + 7 * DAYSECS));
        $sink->close();
    }

    /**
     * A week of grace: nobody hears of a book three days late; Noa's ten days go to the tutor; once Ana's oldest book is
     * a week late, she hears of it, and only of it. The staff's «Tell the tutor now» does not wait.
     */
    public function test_days_before_the_notice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, $year, $now, , $tutor] = $this->library();
        sanctions::save_settings((int) $library->entityid, ['noticeafter' => 7]);
        $this->assertSame(7, sanctions::settings((int) $library->entityid)['noticeafter']);
        $sink = $this->redirectMessages();
        $this->assertSame(['readers' => 0, 'tutors' => 1], notices::run($now));
        $tutormessage = $sink->get_messages()[0];
        $this->assertStringContainsString('Noa Sin Cuenta', $tutormessage->fullmessage);
        $this->assertStringNotContainsString('Ana Inventada', $tutormessage->fullmessage);
        $sink->clear();

        $this->assertSame(['readers' => 1, 'tutors' => 0], notices::run($now + 4 * DAYSECS));
        $message = $sink->get_messages()[0];
        $this->assertStringContainsString('El faro dormido', $message->fullmessage);
        $this->assertStringNotContainsString('La nube de tinta', $message->fullmessage);
        $sink->clear();

        $row = $DB->get_record(self::T . 'tutor', ['yearid' => $year->id, 'groupname' => '3º A'], '*', MUST_EXIST);
        $row->entityid = $library->entityid;
        $this->assertTrue(notices::tell_tutor($row, $now));
        $this->assertStringContainsString('Ana Inventada', $sink->get_messages()[0]->fullmessage);
        $sink->close();
    }

    /**
     * The days the library was closed after a book was due are not days late: past the days of grace counting them, the
     * reader is not told yet.
     */
    public function test_closed_days_are_not_late(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , $now] = $this->library();
        $entityid = (int) $library->entityid;
        sanctions::save_settings($entityid, ['noticeafter' => 7, 'noticetutors' => 0]);
        $tz = zones::of($entityid);
        $day = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->format('Y-m-d');
        $this->assertTrue(closures::add($entityid, $day($now - 20 * DAYSECS), $day($now + 3 * DAYSECS), 'Vacaciones'));
        $sink = $this->redirectMessages();
        $this->assertSame(0, notices::run($now + 4 * DAYSECS)['readers']);
        closures::remove($entityid, $day($now - 20 * DAYSECS), $day($now + 3 * DAYSECS));
        $this->assertSame(1, notices::run($now + 4 * DAYSECS)['readers']);
        $sink->close();
    }

    /**
     * The week counts by reader: two books late three days apart are one message with what is late, none three days
     * later, and one a week later with both; a reader who stopped coming but has books hears too.
     */
    public function test_by_reader(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, $year, $now, $readers] = $this->library();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $gen->create_record(['entityid' =>
            $library->entityid, 'title' => 'Tercer libro'])->id]);
        $gen->create_loan(['libraryid' => $library->id, 'readerid' => $readers['ana']->id, 'yearid' => $year->id,
            'itemid' => $item->id, 'timeloaned' => $now - 10 * DAYSECS, 'timedue' => $now + 2 * DAYSECS]);
        $sink = $this->redirectMessages();
        notices::run($now);
        $first = array_values(array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'));
        $this->assertCount(1, $first);
        $this->assertStringNotContainsString('Tercer libro', $first[0]->fullmessage);
        $sink->clear();
        // Three days later the third is late too, but Ana heard this week.
        notices::run($now + 3 * DAYSECS);
        $this->assertCount(0, array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'));
        $sink->clear();
        notices::run($now + 7 * DAYSECS);
        $week = array_values(array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'
            && str_contains($m->fullmessage, 'faro')));
        $this->assertCount(1, $week);
        $this->assertStringContainsString('Tercer libro', $week[0]->fullmessage);
        $sink->clear();
        // Ana stopped coming, with her books still out: she hears a week later all the same.
        $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $readers['ana']->id]);
        notices::run($now + 14 * DAYSECS);
        $this->assertCount(1, array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'
            && str_contains($m->fullmessage, 'faro')));
        $sink->close();
    }

    /**
     * A young reader's family hears of the late books too, through their account or to their e-mail, once each.
     */
    public function test_family(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\\local_oksigeniafamily\\api')) {
            require_once($CFG->dirroot . '/local/oksigeniaayvurenda/tests/fixtures/family_api.php');
        }
        [$library, $year, $now, $readers] = $this->library();
        $ana = $DB->get_record(self::T . 'reader', ['id' => $readers['ana']->id]);
        $this->assertSame('flower', cards::look_of($ana, $now));
        $mother = $this->getDataGenerator()->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Iván', 'email' => 'ivan.familia@example.org']);
        \local_oksigeniafamily\api::add_child($family, (int) $ana->userid);
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        notices::run($now);
        $tomother = array_values(array_filter($messages->get_messages(), fn($m) => (int) $m->useridto === (int) $mother->id));
        $this->assertCount(1, $tomother);
        $this->assertStringContainsString('El faro dormido', $tomother[0]->fullmessage);
        // Not a reader herself: no way to the library's pages, where she would only read «you are not a reader».
        $this->assertEmpty($tomother[0]->contexturl);
        $this->assertStringNotContainsString('index.php', $tomother[0]->fullmessagehtml);
        $toivan = array_values(array_filter($emails->get_messages(), fn($m) => $m->to === 'ivan.familia@example.org'));
        $this->assertCount(1, $toivan);
        $this->assertStringNotContainsString('index.php', $toivan[0]->body);
        $messages->close();
        $emails->close();
    }

    /**
     * The textbooks of the bank are not late reading: they have the bank's own reminders, and never the late notice.
     */
    public function test_bank_books_apart(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, $year, $now, $readers] = $this->library();
        $DB->set_field(self::T . 'library', 'kind', bank::KIND, ['id' => $library->id]);
        $sink = $this->redirectMessages();
        notices::run($now);
        $this->assertCount(0, array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'overdue'));
        $sink->close();
    }

    /**
     * A centre open only to its staff tells its readers nothing, whoever sends it, and tells its staff; what is set aside
     * there is told when it opens, not marked as told before.
     */
    public function test_closed_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , , $readers] = $this->library();
        $entityid = (int) $library->entityid;
        access::set_closed($entityid, true);
        $reader = \core_user::get_user($readers['ana']->userid);
        $sink = $this->redirectMessages();
        $this->assertFalse(notices::send('holdready', $reader, 'Asunto', 'Texto', '', '', '', ['library' => $library]));
        $librarian = $this->getDataGenerator()->create_user();
        $roleid = create_role('Biblioteca cerrada', 'ayvtestclosed', '');
        assign_capability('local/oksigeniaayvurenda:desk', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $librarian->id, \context_system::instance()->id);
        $this->assertTrue(notices::send('stafftodo', $librarian, 'Asunto', 'Texto', '', '', '', ['library' => $library]));

        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $item = $gen->create_item(['libraryid' => $library->id]);
        $now = time();
        $holdid = $DB->insert_record(self::T . 'hold', ['libraryid' => $library->id, 'recordid' => $item->recordid,
            'readerid' => $readers['ana']->id, 'itemid' => $item->id, 'status' => 'ready', 'timecreated' => $now,
            'timemodified' => $now, 'timeexpires' => $now + 5 * DAYSECS]);
        $this->assertFalse(holds::notify((int) $holdid));
        $this->assertNull($DB->get_field(self::T . 'hold', 'timenotified', ['id' => $holdid]));
        access::set_closed($entityid, false);
        $this->assertSame(1, holds::pending($now));
        $this->assertNotNull($DB->get_field(self::T . 'hold', 'timenotified', ['id' => $holdid]));
        $sink->close();
    }

    /**
     * Next year, the tutor of a group with the same name is proposed, not set.
     */
    public function test_tutor_proposed(): void {
        global $DB;
        $this->resetAfterTest();
        [$library, $year, , $readers, $tutor] = $this->library();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $next = $gen->create_year(['entityid' => $library->entityid, 'name' => '2027-2028',
            'timestart' => $year->timeend, 'timeend' => make_timestamp(2028, 9, 1)]);
        $gen->create_member(['readerid' => $readers['ana']->id, 'yearid' => $next->id, 'groupname' => '3º A']);
        $gen->create_member(['readerid' => $readers['leo']->id, 'yearid' => $next->id, 'groupname' => '4º A']);
        $groups = tutors::groups((int) $library->entityid, (int) $next->id);
        $this->assertSame([
            ['group' => '3º A', 'userid' => (int) $tutor->id, 'proposed' => true, 'stage' => 'Primaria'],
            ['group' => '4º A', 'userid' => 0, 'proposed' => false, 'stage' => 'Primaria'],
        ], $groups);
        $this->assertSame(0, $DB->count_records(self::T . 'tutor', ['yearid' => $next->id]));
        tutors::set((int) $next->id, '3º A', (int) $tutor->id);
        $this->assertFalse(tutors::groups((int) $library->entityid, (int) $next->id)[0]['proposed']);
        tutors::set((int) $next->id, '3º A', 0);
        $this->assertSame(0, $DB->count_records(self::T . 'tutor', ['yearid' => $next->id]));
    }

    /**
     * The staff list: late loans by group, with names and titles; all of them on request.
     */
    public function test_late_list(): void {
        $this->resetAfterTest();
        [$library, , $now] = $this->library();
        $groups = late::by_group($library, true, $now);
        $this->assertCount(1, $groups);
        $this->assertSame('3º A', $groups[0]['group']);
        $this->assertSame(['Noa Sin Cuenta', 'Ana Inventada', 'Ana Inventada'], array_column($groups[0]['lines'], 'name'));
        $this->assertSame('Mapas del sur', $groups[0]['lines'][0]['title']);
        $this->assertSame(10, $groups[0]['lines'][0]['days']);
        $this->assertCount(4, late::by_group($library, false, $now)[0]['lines']);
    }

    /**
     * The copy holds every table of the entity in CSV and only the last seven are kept.
     */
    public function test_copies(): void {
        $this->resetAfterTest();
        [$library, , $now] = $this->library();
        $other = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $file = copies::make((int) $library->entityid, $now);
        $path = make_request_directory() . '/copy.zip';
        $file->copy_content_to($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('LEEME.txt'));
        $readers = $zip->getFromName('reader.csv');
        $this->assertStringContainsString('Sin Cuenta', $readers);
        $this->assertSame(4, substr_count($readers, "\n"));
        $this->assertSame(5, substr_count($zip->getFromName('loan.csv'), "\n"));
        // The catalogue in MARC21 too, as ISO 2709 and MARCXML, each record with its copies.
        $records = \local_oksigeniaayvurenda\core\marc\iso2709::read($zip->getFromName('catalogo.mrc'));
        $this->assertCount(4, $records);
        $this->assertCount(4, \local_oksigeniaayvurenda\core\marc\marcxml::read($zip->getFromName('catalogo.xml')));
        $titles = array_map(fn($r) => $r->first(['245'], 'a'), $records);
        $this->assertContains('Mapas del sur.', $titles);
        $this->assertCount(1, $records[0]->data_fields('852'));
        // And in Dublin Core and RIS, a record each.
        $dc = new \DOMDocument();
        $this->assertTrue($dc->loadXML($zip->getFromName('catalogo-dc.xml')));
        $this->assertSame(4, $dc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'dc')->length);
        $this->assertSame(4, substr_count($zip->getFromName('catalogo.ris'), "ER  - \r\n"));
        $zip->close();
        copies::make((int) $other->entityid, $now);
        // A second copy in the same second takes the place of the first.
        copies::make((int) $other->entityid, $now);
        for ($i = 1; $i <= 8; $i++) {
            copies::make((int) $library->entityid, $now + $i * DAYSECS);
        }
        $list = copies::list((int) $library->entityid);
        $this->assertCount(copies::KEEP, $list);
        $this->assertSame($now + 8 * DAYSECS, (int) $list[0]->get_timecreated());
        $this->assertCount(1, copies::list((int) $other->entityid));
        // Copies by hand are kept apart: three of them leave the seven nightly ones, and only the last two of their own.
        for ($i = 1; $i <= 3; $i++) {
            copies::make((int) $library->entityid, $now + 9 * DAYSECS + $i, true);
        }
        $list = copies::list((int) $library->entityid);
        $this->assertCount(copies::KEEP + copies::KEEPHAND, $list);
        $this->assertCount(copies::KEEPHAND, array_filter($list, fn($f) => copies::by_hand($f)));
        $this->assertTrue(copies::by_hand($list[0]));
    }

    /**
     * A centre open only to its staff while it gets ready: its readers see no way in, not even in the block's summary,
     * hear nothing, and its public door is shut; its librarian works as before. Opened, its readers are told.
     */
    public function test_closed_to_readers(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$library, , $now, $readers] = $this->library();
        $entityid = (int) $library->entityid;
        $ana = (int) $readers['ana']->userid;
        $staff = $this->getDataGenerator()->create_user();
        role_assign(roles::ensure_librarian(), $staff->id, access::context($entityid)->id);
        $DB->set_field('local_oksigeniaayvurenda_library', 'settings', json_encode(['window' => ['open' => true,
            'catalogue' => true]]), ['id' => $library->id]);
        $library = $DB->get_record('local_oksigeniaayvurenda_library', ['id' => $library->id]);
        $this->assertTrue(access::can('local/oksigeniaayvurenda:view', $library, $ana));
        $this->assertNotEmpty(mine::summary($ana, $now));
        access::set_closed($entityid, true);
        $this->assertTrue(access::closed($entityid));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:view', $library, $ana));
        $this->assertTrue(access::can('local/oksigeniaayvurenda:view', $library, (int) $staff->id));
        $this->assertTrue(access::can('local/oksigeniaayvurenda:desk', $library, (int) $staff->id));
        $this->assertSame([], mine::summary($ana, $now));
        $this->assertNull(window::open($library->slug));
        $this->assertFalse(window::catalogue_open($library));
        $sink = $this->redirectMessages();
        $this->assertSame(['readers' => 0, 'tutors' => 0], notices::run($now));
        $this->assertCount(0, $sink->get_messages());
        access::set_closed($entityid, false);
        $this->assertTrue(access::can('local/oksigeniaayvurenda:view', $library, $ana));
        $this->assertNotNull(window::open($library->slug));
        $this->assertSame(['readers' => 1, 'tutors' => 1], notices::run($now));
        $sink->close();
    }

    /**
     * In a site that is only a library, its name signs the message; and whoever writes next is not signed with it.
     */
    public function test_the_library_signs(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('libraryonly', 1, 'local_oksigeniaayvurenda');
        $before = \core_user::get_noreply_user()->firstname;
        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEmails();
        $this->assertTrue(notices::send('overdue', $user, 'Asunto', 'Cuerpo', '', '', 'Biblioteca de Riosalto'));
        $this->assertSame($before, \core_user::get_noreply_user()->firstname);
        $sink->close();
    }
}
