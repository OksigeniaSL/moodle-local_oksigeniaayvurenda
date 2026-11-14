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
 * Tests for the reader's booklet for the family.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\booklet
 */
#[\PHPUnit\Framework\Attributes\CoversClass(booklet::class)]
final class booklet_test extends \advanced_testcase {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A book taken home by a reader in a school year.
     *
     * @param \stdClass $library
     * @param \stdClass $reader
     * @param \stdClass $year
     * @param string $title
     * @param int $when
     * @return \stdClass The record.
     */
    private function lend(\stdClass $library, \stdClass $reader, \stdClass $year, string $title, int $when): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $when, 'timedue' => $when + WEEKSECS, 'timereturned' => $when + DAYSECS]);
        return $record;
    }

    /**
     * The year's edition has that year's books in their order, their voices, their group and the library's line; the whole
     * life's, every year with their first book ever. Nothing is made while the centre has it off; once on, when the
     * classes end, one for each young reader who took a book home that year, the whole life's for a group that leaves, and
     * never twice. The family hears of it once, the link opens only with its seal, and a reader who leaves during the year
     * gets the whole life's once.
     */
    public function test_editions_and_sending(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\\local_oksigeniafamily\\api')) {
            require_once($CFG->dirroot . '/local/oksigeniaayvurenda/tests/fixtures/family_api.php');
        }
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $tz = zones::of($entityid);
        $at = fn(string $day) => (new \DateTimeImmutable($day . ' 10:00:00', $tz))->getTimestamp();
        $old = $gen->create_year(['entityid' => $entityid, 'name' => '2025-2026',
            'timestart' => (new \DateTimeImmutable('2025-09-01 00:00:00', $tz))->getTimestamp(),
            'timeend' => (new \DateTimeImmutable('2026-09-01 00:00:00', $tz))->getTimestamp()]);
        $year = $gen->create_year(['entityid' => $entityid, 'name' => '2026-2027',
            'timestart' => (new \DateTimeImmutable('2026-09-01 00:00:00', $tz))->getTimestamp(),
            'timeend' => (new \DateTimeImmutable('2027-09-01 00:00:00', $tz))->getTimestamp()]);
        $this->assertTrue(challenges::set_ends($entityid, ['2026-12-22', '2027-03-26', '2027-06-18']));
        $kid = $dg->create_user(['firstname' => 'Leo']);
        $leo = $gen->create_reader(['entityid' => $entityid, 'userid' => $kid->id, 'band' => 'flower']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $old->id, 'groupname' => '5º A']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $year->id, 'groupname' => '6º A']);
        $ana = $gen->create_reader(['entityid' => $entityid, 'firstname' => 'Ana']);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $year->id, 'groupname' => '3º A']);
        $this->lend($library, $leo, $old, 'El primer cuento', $at('2025-10-01'));
        $this->lend($library, $leo, $year, 'La isla del cometa', $at('2026-10-05'));
        $comet = $this->lend($library, $leo, $year, 'El faro de papel', $at('2027-02-10'));
        $this->lend($library, $ana, $year, 'Las papas del abuelo', $at('2027-03-01'));
        $DB->insert_record(self::T . 'digital', ['entityid' => $entityid, 'recordid' => $comet->id, 'kind' => 'voice',
            'format' => 'audio', 'title' => 'Entero', 'status' => 'published', 'readerid' => $leo->id,
            'audience' => 'students', 'licence' => 'rights', 'createdby' => $kid->id, 'timecreated' => $at('2027-02-12'),
            'timemodified' => $at('2027-02-12')]);

        // The year's edition, and the whole life's.
        $page = booklet::year($leo, $year);
        $this->assertSame(['2026-2027', 'flower', '6º A'], [$page['year'], $page['look'], $page['group']]);
        $this->assertSame(['La isla del cometa', 'El faro de papel'], array_column($page['books'], 'title'));
        $this->assertSame(['El faro de papel'], array_column($page['voices'], 'title'));
        $this->assertSame('', $page['line']);
        booklet::save_line($entityid, (int) $year->id, '  Gracias por   llenar la biblioteca de vida. ');
        $this->assertSame('Gracias por llenar la biblioteca de vida.', booklet::year($leo, $year)['line']);
        $life = booklet::life($leo);
        $this->assertSame(['2025-2026', '2026-2027'], array_column($life['years'], 'year'));
        $this->assertSame(['El primer cuento', 3], [$life['first']['title'], $life['books']]);

        // Off, nothing; on, when the classes end, one each and the whole life's for the group that leaves.
        $end = booklet::classes_end($year);
        $this->assertSame((new \DateTimeImmutable('2027-06-19 00:00:00', $tz))->getTimestamp() - 1, $end);
        $this->assertSame(0, booklet::plan($end + HOURSECS));
        booklet::save($entityid, true);
        booklet::save_leaving($entityid, (int) $year->id, ['6º A', ' ', '6º A']);
        $this->assertSame(['6º A'], booklet::leaving($entityid, (int) $year->id));
        $this->assertSame(0, booklet::plan($end - HOURSECS));
        $this->assertSame(2, booklet::plan($end + HOURSECS));
        $this->assertSame(0, booklet::plan($end + 2 * HOURSECS));
        $kinds = $DB->get_records_menu(self::T . 'booklet', null, '', 'readerid, kind');
        $this->assertSame(['life', 'year'], [$kinds[$leo->id], $kinds[$ana->id]]);

        // The family hears of it once; the reader without an account has it printed.
        $mother = $dg->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Iván', 'email' => 'ivan.familia@example.org']);
        \local_oksigeniafamily\api::add_child($family, (int) $kid->id);
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $this->assertSame(2, booklet::run($end + HOURSECS));
        $tomother = array_values(array_filter($messages->get_messages(), fn($m) => (int) $m->useridto === (int) $mother->id));
        $this->assertCount(1, $tomother);
        $this->assertSame('booklet', $tomother[0]->eventtype);
        $this->assertStringContainsString('booklet.php', $tomother[0]->fullmessage);
        $this->assertCount(1, array_filter($emails->get_messages(), fn($m) => $m->to === 'ivan.familia@example.org'));
        $this->assertSame(0, $DB->count_records_select(self::T . 'booklet', 'timesent IS NULL'));
        $this->assertSame(0, booklet::run($end + DAYSECS));
        $messages->close();
        $emails->close();

        // The link opens with its seal only.
        $id = (int) $DB->get_field(self::T . 'booklet', 'id', ['readerid' => $leo->id]);
        $this->assertSame((int) $leo->id, (int) booklet::opened($id, booklet::seal($id))['reader']->id);
        $this->assertNull(booklet::opened($id, 'abc'));
        $this->assertStringContainsString('s=' . booklet::seal($id), booklet::url($id));

        // Leaving during the next year: the whole life's, once; the one who has it already, nothing.
        $next = $at('2027-10-10');
        $this->assertTrue(booklet::left($ana, $next));
        $this->assertFalse(booklet::left($ana, $next + DAYSECS));
        $this->assertFalse(booklet::left($leo, $next));
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
    }

    /**
     * On paper the covers take as many columns as the year needs to fit its sheet, as Jujol measured: Lucía's 22 books in
     * 11, Aitana's 64 in 16, Hugo's 7 big and with their titles; a year among many, at least six.
     */
    public function test_columns(): void {
        $this->assertSame(11, bookletview::columns(22, 250 - 49 - 11 - 5 - 62 - 40 - 24 - 8));
        $this->assertSame(16, bookletview::columns(64, 250 - 49 - 11 - 5 - 74 - 24 - 8));
        $this->assertLessThanOrEqual(8, bookletview::columns(7, 250 - 49 - 11 - 62 - 24 - 8));
        $this->assertGreaterThanOrEqual(6, bookletview::columns(3, 92, 6));
        // One book alone is no poster: at least four columns.
        $this->assertSame(4, bookletview::columns(1, 250 - 49 - 11 - 62 - 24 - 8));
    }

    /**
     * The sheet of a year and the whole life, as the pages show them; and a reader of the sky decides: without their yes
     * their family gets nothing, with it, the next morning; after a month without it, it is theirs alone.
     */
    public function test_sheet_life_and_theirs(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\\local_oksigeniafamily\\api')) {
            require_once($CFG->dirroot . '/local/oksigeniaayvurenda/tests/fixtures/family_api.php');
        }
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $tz = zones::of($entityid);
        $at = fn(string $day) => (new \DateTimeImmutable($day . ' 10:00:00', $tz))->getTimestamp();
        $old = $gen->create_year(['entityid' => $entityid, 'name' => '2025-2026',
            'timestart' => (new \DateTimeImmutable('2025-09-01 00:00:00', $tz))->getTimestamp(),
            'timeend' => (new \DateTimeImmutable('2026-09-01 00:00:00', $tz))->getTimestamp()]);
        $year = $gen->create_year(['entityid' => $entityid, 'name' => '2026-2027',
            'timestart' => (new \DateTimeImmutable('2026-09-01 00:00:00', $tz))->getTimestamp(),
            'timeend' => (new \DateTimeImmutable('2027-09-01 00:00:00', $tz))->getTimestamp()]);
        $this->assertTrue(challenges::set_ends($entityid, ['2026-12-22', '2027-03-26', '2027-06-18']));
        $kid = $dg->create_user(['firstname' => 'Iker Mar']);
        $iker = $gen->create_reader(['entityid' => $entityid, 'userid' => $kid->id, 'band' => 'sky']);
        $gen->create_member(['readerid' => $iker->id, 'yearid' => $old->id, 'groupname' => '1.º ESO B']);
        $gen->create_member(['readerid' => $iker->id, 'yearid' => $year->id, 'groupname' => '2.º ESO B']);
        $this->lend($library, $iker, $old, 'Ocho planetas', $at('2025-10-01'));
        $this->lend($library, $iker, $year, 'El puente de piedra', $at('2026-10-05'));
        booklet::save_line($entityid, (int) $year->id, 'Gracias por venir.');

        // The sheet: in the reader's voice, the first name, the year's books, the library's line.
        $sheet = bookletview::sheet($iker, $year, $library);
        $this->assertSame(['Iker', '2.º ESO B', 'sky', 1, 'Gracias por venir.'], [$sheet['readername'], $sheet['groupname'],
            $sheet['look'], $sheet['bookcount'], $sheet['note']['notetext']]);
        // The whole life: both years, from one class to the other, his first book first.
        $life = bookletview::life($iker, $library);
        $this->assertCount(2, $life['years']);
        $this->assertStringContainsString('1.º ESO B', $life['fromto']);
        $this->assertSame('Ocho planetas', $life['first']['firsttitle']);
        $this->assertSame('2025-2027', $life['span']);

        // His family gets it only if he says so.
        booklet::save($entityid, true);
        $mother = $dg->create_user(['firstname' => 'Rosa']);
        $family = \local_oksigeniafamily\api::create_family();
        \local_oksigeniafamily\api::add_adult($family, ['firstname' => 'Rosa', 'email' => $mother->email,
            'userid' => (int) $mother->id]);
        \local_oksigeniafamily\api::add_child($family, (int) $kid->id);
        $end = booklet::classes_end($year);
        $this->assertSame(1, booklet::plan($end + HOURSECS));
        $row = $DB->get_record(self::T . 'booklet', ['readerid' => $iker->id]);
        $this->assertTrue(booklet::theirs($iker, (int) $year->id));
        $this->assertSame((int) $row->id, (int) booklet::on_card($iker, $end + DAYSECS)->id);
        $this->assertSame(0, booklet::run($end + HOURSECS));
        $this->assertNull($DB->get_field(self::T . 'booklet', 'timesent', ['id' => $row->id]));
        booklet::wish((int) $row->id, true);
        $messages = $this->redirectMessages();
        $this->assertSame(1, booklet::run($end + DAYSECS));
        $this->assertCount(1, $messages->get_messages());
        $messages->close();
        // Without his yes, a month later it is his alone: done, and nothing sent.
        $DB->set_field(self::T . 'booklet', 'timesent', null, ['id' => $row->id]);
        booklet::wish((int) $row->id, false);
        $this->assertSame(0, booklet::run($end + 40 * DAYSECS));
        $this->assertNotNull($DB->get_field(self::T . 'booklet', 'timesent', ['id' => $row->id]));
        $this->assertNull(booklet::on_card($iker, $end + 90 * DAYSECS));
        if (method_exists('\\local_oksigeniafamily\\api', 'reset')) {
            \local_oksigeniafamily\api::reset();
        }
    }
}
