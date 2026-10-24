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
 * Tests for readers that are the same person.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\twins
 */
#[\PHPUnit\Framework\Attributes\CoversClass(twins::class)]
final class twins_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Two teachers with the same name are likely one; a student of that name is not with them. Joining moves loans,
     * groups and where they came from, and removes the other; two different accounts are never joined.
     */
    public function test_likely_and_join(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $teacher = ['entityid' => $entityid, 'firstname' => 'Jon', 'lastname' => 'Mar Prado', 'readertype' => 'teacher'];
        $one = $generator->create_reader($teacher);
        $two = $generator->create_reader(['firstname' => 'jon', 'lastname' => 'Prado Mar'] + $teacher);
        $student = $generator->create_reader(['readertype' => 'student'] + $teacher);
        $nickname = $generator->create_reader(['firstname' => 'Edu', 'lastname' => 'Sauce'] + $teacher);
        $likely = twins::likely($entityid);
        $this->assertCount(1, $likely);
        $this->assertEqualsCanonicalizing([(int) $one->id, (int) $two->id], $likely[0]);
        $this->assertCount(1, twins::likely($entityid, 'prado'));
        $this->assertSame([], twins::likely($entityid, 'prado nadie'));

        $record = $generator->create_record(['entityid' => $entityid]);
        $item = $generator->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $two->id]);
        $year = years::around($entityid, time());
        $generator->create_member(['readerid' => $one->id, 'yearid' => $year->id, 'groupname' => 'Claustro']);
        $generator->create_member(['readerid' => $two->id, 'yearid' => $year->id, 'groupname' => 'Otro']);
        $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'system' => 'abies', 'tablename' => 'reader',
            'externalid' => '88', 'localid' => $two->id, 'timecreated' => time()]);
        $described = twins::describe($entityid, [(int) $one->id, (int) $two->id]);
        $this->assertSame(1, $described[$two->id]['out']);
        $this->assertSame('Claustro', $described[$one->id]['group']);
        $this->assertSame((int) $one->id, twins::by_code($entityid, strtolower($one->cardcode)));

        twins::join($entityid, (int) $one->id, (int) $two->id);
        $this->assertFalse($DB->record_exists(self::T . 'reader', ['id' => $two->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'loan', ['readerid' => $one->id]));
        $this->assertSame(['Claustro'], $DB->get_fieldset_select(self::T . 'member', 'groupname', 'readerid = ?', [$one->id]));
        $this->assertSame((string) $one->id, $DB->get_field(self::T . 'source', 'localid', ['externalid' => '88']));
        $this->assertSame([], twins::likely($entityid));
        // By hand, with a nickname: the account of the other comes along.
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field(self::T . 'reader', 'userid', $user->id, ['id' => $nickname->id]);
        twins::join($entityid, (int) $one->id, (int) $nickname->id);
        $this->assertSame((string) $user->id, $DB->get_field(self::T . 'reader', 'userid', ['id' => $one->id]));
        $this->assertNull($DB->get_field(self::T . 'reader', 'firstname', ['id' => $one->id]));
        // Two accounts: two people.
        $other = $this->getDataGenerator()->create_user();
        $DB->set_field(self::T . 'reader', 'userid', $other->id, ['id' => $student->id]);
        $this->expectException(\moodle_exception::class);
        twins::join($entityid, (int) $one->id, (int) $student->id);
    }
}
