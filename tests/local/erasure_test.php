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
 * A reader erased by their library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(erasure::class)]
final class erasure_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A reader with everything that can say who they are, and a book out.
     *
     * @return \stdClass library, reader, user, other, item, loan.
     */
    private function world(): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library();
        $entityid = (int) $w->library->entityid;
        $w->user = $this->getDataGenerator()->create_user();
        $w->reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $w->user->id, 'firstname' => 'Lucía',
            'lastname' => 'Ríos', 'email' => 'lucia@example.org', 'notes' => 'Le encantan los cómics']);
        $w->other = $gen->create_reader(['entityid' => $entityid]);
        $now = time();
        $done = $gen->create_item(['libraryid' => $w->library->id]);
        $gen->create_loan(['libraryid' => $w->library->id, 'itemid' => $done->id, 'readerid' => $w->reader->id,
            'timereturned' => $now - DAYSECS, 'returnnote' => 'loved']);
        $w->item = $gen->create_item(['libraryid' => $w->library->id, 'status' => 'onloan']);
        $w->loan = $gen->create_loan(['libraryid' => $w->library->id, 'itemid' => $w->item->id, 'readerid' => $w->reader->id]);
        $common = ['entityid' => $entityid, 'libraryid' => $w->library->id, 'recordid' => $done->recordid,
            'readerid' => $w->reader->id, 'timecreated' => $now, 'timemodified' => $now];
        $DB->insert_record(self::T . 'review', $common + ['text' => 'Muy bonito', 'status' => 'approved', 'forlittle' => 0]);
        $DB->insert_record(self::T . 'hold', $common + ['status' => 'waiting']);
        $DB->insert_record(self::T . 'sanction', $common + ['kind' => 'days', 'dayslate' => 3, 'status' => 'open',
            'timeuntil' => $now + DAYSECS]);
        $DB->insert_record(self::T . 'formatask', $common + ['format' => 'audio', 'status' => 'waiting']);
        $DB->insert_record(self::T . 'voiceinvite', $common + ['token' => 'abc', 'name' => 'Familia de Lucía',
            'timeexpires' => $now + DAYSECS]);
        $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'system' => 'list', 'tablename' => 'reader',
            'externalid' => 'lucia-ríos', 'localid' => $w->reader->id, 'timecreated' => $now]);
        return $w;
    }

    /**
     * Not with a book out unless it is given up as lost; then nothing that says who they were is left, the loans stay as
     * numbers, and their account stays on a centre's own Moodle.
     */
    public function test_erase(): void {
        global $DB;
        $w = $this->world();
        $this->assertSame(['done' => false, 'open' => 1, 'account' => false], erasure::erase($w->library, (int) $w->reader->id));
        $this->assertSame('active', $DB->get_field(self::T . 'reader', 'status', ['id' => $w->reader->id]));

        $done = erasure::erase($w->library, (int) $w->reader->id, true, 2);
        $this->assertTrue($done['done']);
        $this->assertFalse($done['account'], 'a centre\'s own Moodle: the account is the person\'s');
        $reader = $DB->get_record(self::T . 'reader', ['id' => $w->reader->id]);
        $this->assertSame('deleted', $reader->status);
        $this->assertSame('deleted-' . $w->reader->id, $reader->cardcode);
        foreach (['userid', 'firstname', 'lastname', 'email', 'notes'] as $field) {
            $this->assertNull($reader->$field, $field);
        }
        foreach (['review', 'hold', 'sanction', 'formatask'] as $table) {
            $this->assertSame(0, $DB->count_records(self::T . $table, ['readerid' => $w->reader->id]), $table);
        }
        $this->assertSame(0, $DB->count_records(self::T . 'source', ['localid' => $w->reader->id, 'tablename' => 'reader']));
        $this->assertNull($DB->get_field(self::T . 'voiceinvite', 'name', ['token' => 'abc']));
        $this->assertNull($DB->get_field(self::T . 'voiceinvite', 'readerid', ['token' => 'abc']));
        // The loans stay, without their notes; the book out is lost.
        $this->assertSame(2, $DB->count_records(self::T . 'loan', ['readerid' => $w->reader->id]));
        $this->assertSame(0, $DB->count_records_select(
            self::T . 'loan',
            'readerid = ? AND returnnote IS NOT NULL',
            [$w->reader->id]
        ));
        $this->assertSame('lost', $DB->get_field(self::T . 'loan', 'conditionin', ['id' => $w->loan->id]));
        $this->assertSame('lost', $DB->get_field(self::T . 'item', 'status', ['id' => $w->item->id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'deleted', ['id' => $w->user->id]));
        // Once is enough; the other reader is untouched.
        $this->assertFalse(erasure::erase($w->library, (int) $w->reader->id)['done']);
        $this->assertSame('active', $DB->get_field(self::T . 'reader', 'status', ['id' => $w->other->id]));
    }

    /**
     * On a site shared by centres, an account with nothing else goes too; another centre cannot erase this reader.
     */
    public function test_shared_site(): void {
        global $DB;
        $w = $this->world();
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $elsewhere = $gen->create_library();
        $this->assertFalse(erasure::erase($elsewhere, (int) $w->reader->id, true)['done']);
        $done = erasure::erase($w->library, (int) $w->reader->id, true);
        $this->assertTrue($done['account']);
        $this->assertSame(1, (int) $DB->get_field('user', 'deleted', ['id' => $w->user->id]));
    }
}
