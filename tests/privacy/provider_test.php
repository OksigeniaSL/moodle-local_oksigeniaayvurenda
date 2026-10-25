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

namespace local_oksigeniaayvurenda\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library where Ana borrowed a book that a librarian lent her, and Leo is a reader too.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        $dg = $this->getDataGenerator();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->ana = $dg->create_user(['firstname' => 'Ana']);
        $w->leo = $dg->create_user(['firstname' => 'Leo']);
        $w->librarian = $dg->create_user();
        $w->library = $gen->create_library();
        $w->year = $gen->create_year(['entityid' => $w->library->entityid]);
        $w->anareader = $gen->create_reader(['entityid' => $w->library->entityid, 'userid' => $w->ana->id]);
        $w->leoreader = $gen->create_reader(['entityid' => $w->library->entityid, 'userid' => $w->leo->id]);
        $gen->create_member(['readerid' => $w->anareader->id, 'yearid' => $w->year->id]);
        $w->item = $gen->create_item(['libraryid' => $w->library->id]);
        $w->loan = $gen->create_loan([
            'libraryid' => $w->library->id,
            'itemid' => $w->item->id,
            'readerid' => $w->anareader->id,
            'groupname' => '3º Primaria A',
            'staffid' => $w->librarian->id,
            'returnnote' => 'loved',
        ]);
        global $DB;
        $w->hold = $DB->insert_record(self::T . 'hold', ['libraryid' => $w->library->id, 'recordid' => $w->item->recordid,
            'readerid' => $w->anareader->id, 'status' => 'waiting', 'createdby' => $w->librarian->id,
            'timecreated' => time(), 'timemodified' => time()]);
        // Ana told about the book, and the librarian published it.
        $w->review = \local_oksigeniaayvurenda\local\reviews::write(
            (int) $w->library->entityid,
            (int) $w->item->recordid,
            (int) $w->anareader->id,
            'Me encantó.'
        );
        \local_oksigeniaayvurenda\local\reviews::decide($w->review, (int) $w->library->entityid, true, (int) $w->librarian->id);
        return $w;
    }

    /**
     * The metadata names the tables, the messages, the files of the daily copies and covers, and the look of the
     * card the reader chose.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('local_oksigeniaayvurenda'))->get_collection();
        $this->assertCount(18, $items);
    }

    /**
     * A tutor has data in the site context, exports the groups they tutored and loses them when deleted.
     */
    public function test_tutor(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $tutor = $this->getDataGenerator()->create_user();
        \local_oksigeniaayvurenda\local\tutors::set((int) $w->year->id, '3º Primaria A', (int) $tutor->id);
        $system = \context_system::instance();
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($tutor->id)->get_contextids());
        $this->export_context_data_for_user($tutor->id, $system, 'local_oksigeniaayvurenda');
        $data = writer::with_context($system)->get_data([get_string('pluginname', 'local_oksigeniaayvurenda'),
            get_string('privacy:path:tutor', 'local_oksigeniaayvurenda')]);
        $this->assertSame('3º Primaria A', $data->groups[0]['group']);
        provider::delete_data_for_user(new approved_contextlist($tutor, 'local_oksigeniaayvurenda', [$system->id]));
        $this->assertSame(0, $DB->count_records(self::T . 'tutor'));
    }

    /**
     * Readers and staff who lent have data in the site context; others do not.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $stranger = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($w->ana->id)->get_contextids());
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($w->librarian->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($stranger->id)->get_contextids());

        $userlist = new userlist($system, 'local_oksigeniaayvurenda');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();
        sort($ids);
        $expected = [(int) $w->ana->id, (int) $w->leo->id, (int) $w->librarian->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    /**
     * Ana gets her card, her group and her loan; the librarian gets the loans they handled.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $system = \context_system::instance();
        $this->export_context_data_for_user($w->ana->id, $system, 'local_oksigeniaayvurenda');
        $path = [get_string('pluginname', 'local_oksigeniaayvurenda'),
            get_string('privacy:path:reader', 'local_oksigeniaayvurenda', $w->anareader->cardcode)];
        $data = writer::with_context($system)->get_data($path);
        $this->assertSame($w->anareader->cardcode, $data->cardcode);
        $this->assertSame('3º Primaria A', $data->groups[0]['group']);
        $this->assertSame('loved', $data->loans[0]['note']);
        $this->assertSame('waiting', $data->holds[0]['status']);
        $this->assertSame('Me encantó.', $data->reviews[0]['text']);

        writer::reset();
        $this->export_context_data_for_user($w->librarian->id, $system, 'local_oksigeniaayvurenda');
        $desk = writer::with_context($system)->get_data([get_string('pluginname', 'local_oksigeniaayvurenda'),
            get_string('privacy:path:desk', 'local_oksigeniaayvurenda')]);
        $this->assertCount(1, $desk->loans);
    }

    /**
     * Deleting Ana anonymises her reader and keeps the loan, without her note; Leo stays as he was.
     */
    public function test_delete_user(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $system = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($w->ana, 'local_oksigeniaayvurenda', [$system->id]));
        $reader = $DB->get_record(self::T . 'reader', ['id' => $w->anareader->id]);
        $this->assertNull($reader->userid);
        $this->assertSame('deleted', $reader->status);
        $this->assertSame('deleted-' . $reader->id, $reader->cardcode);
        $loan = $DB->get_record(self::T . 'loan', ['id' => $w->loan->id]);
        $this->assertNull($loan->returnnote);
        $this->assertEquals($w->librarian->id, $loan->staffid);
        $this->assertEquals($w->leo->id, $DB->get_field(self::T . 'reader', 'userid', ['id' => $w->leoreader->id]));
        $this->assertFalse($DB->record_exists(self::T . 'hold', ['id' => $w->hold]));
        $this->assertFalse($DB->record_exists(self::T . 'review', ['id' => $w->review]));
    }

    /**
     * Deleting a list of users takes the librarian off the loan and anonymises Leo.
     */
    public function test_delete_users(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $system = \context_system::instance();
        provider::delete_data_for_users(new approved_userlist(
            $system,
            'local_oksigeniaayvurenda',
            [$w->librarian->id, $w->leo->id]
        ));
        $this->assertNull($DB->get_field(self::T . 'loan', 'staffid', ['id' => $w->loan->id]));
        $this->assertNull($DB->get_field(self::T . 'hold', 'createdby', ['id' => $w->hold]));
        $this->assertNull($DB->get_field(self::T . 'review', 'decidedby', ['id' => $w->review]));
        $this->assertSame('published', $DB->get_field(self::T . 'review', 'status', ['id' => $w->review]));
        $this->assertNull($DB->get_field(self::T . 'reader', 'userid', ['id' => $w->leoreader->id]));
        $this->assertEquals($w->ana->id, $DB->get_field(self::T . 'reader', 'userid', ['id' => $w->anareader->id]));
    }

    /**
     * Deleting everyone in the site context anonymises every reader and every staff mark.
     */
    public function test_delete_all(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records_select(self::T . 'reader', 'userid IS NOT NULL'));
        $this->assertSame(0, $DB->count_records_select(self::T . 'loan', 'staffid IS NOT NULL'));
        $this->assertSame(1, $DB->count_records(self::T . 'loan'));
        $this->assertSame(0, $DB->count_records(self::T . 'review'));
    }
}
