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
 * Tests for a reader's record, for the library staff.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\profile
 */
#[\PHPUnit\Framework\Attributes\CoversClass(profile::class)]
final class profile_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The record says who the reader is, with the photo of their account where the centre shows them, what they have
     * now in any library of the centre, what they took home this year (not the textbooks) and before, and keeps the
     * staff's notes; a reader of another centre has none here.
     */
    public function test_record(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $bank = libraries::create_library($entityid, ['name' => 'Banco de libros', 'kind' => 'bank']);
        $tz = \core_date::get_server_timezone_object();
        $now = time();
        $year = $gen->create_year(['entityid' => $entityid] +
            \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', $tz));
        $last = $gen->create_year(['entityid' => $entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            (int) $year->timestart - DAYSECS,
            '09-01',
            $tz
        ));
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Lucía', 'lastname' => 'Arenas']);
        $reader = $gen->create_reader(['entityid' => $entityid, 'userid' => $user->id, 'cardcode' => '12018N']);
        $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => '2º A', 'stage' => 'Primaria']);
        $novel = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $textbook = $gen->create_record(['entityid' => $entityid, 'title' => 'Matemáticas 2']);
        $copy = $gen->create_item(['libraryid' => $library->id, 'recordid' => $novel->id]);
        $old = $gen->create_item(['libraryid' => $library->id, 'recordid' => $novel->id]);
        $bankcopy = $gen->create_item(['libraryid' => $bank->id, 'recordid' => $textbook->id]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $copy->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $now - 20 * DAYSECS, 'timedue' => $now - 3 * DAYSECS]);
        $gen->create_loan(['libraryid' => $bank->id, 'itemid' => $bankcopy->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $now - 10 * DAYSECS, 'timedue' => $now + 200 * DAYSECS]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $old->id, 'readerid' => $reader->id,
            'yearid' => $last->id, 'timeloaned' => (int) $last->timestart + DAYSECS,
            'timereturned' => (int) $last->timestart + 9 * DAYSECS]);

        $record = profile::of($library, (int) $reader->id, $now);
        $this->assertSame('Lucía Arenas', $record['name']);
        $this->assertSame(
            ['2º A', 'Primaria', 'L', ''],
            [$record['group'], $record['stage'], $record['initial'], $record['photo']]
        );
        $this->assertSame(['El faro dormido', 'Matemáticas 2'], array_column($record['loans'], 'title'));
        $this->assertSame(3, $record['loans'][0]['late']);
        $this->assertSame(1, $record['read']['total']);
        $this->assertSame([['year' => $last->name, 'loans' => 1]], $record['past']);

        // The photo of their account, unless the centre turns photos off.
        $DB->set_field('user', 'picture', 1, ['id' => $user->id]);
        $this->assertStringContainsString('/user/icon/', profile::photo($reader));
        libraries::update_entity($entityid, ['photos' => false]);
        $this->assertSame('', profile::photo($reader));
        $this->assertSame('', (new desk($library, 2, $now))->reader((int) $reader->id)['photo']);
        $this->assertStringContainsString('reader.php', (new desk($library, 2, $now))->reader((int) $reader->id)['url']);

        profile::save_notes($library, (int) $reader->id, '  Necesita letra grande.  ');
        $this->assertSame('Necesita letra grande.', profile::of($library, (int) $reader->id, $now)['notes']);
        $other = $gen->create_library(['slug' => 'otra']);
        $this->assertNull(profile::of($other, (int) $reader->id, $now));
    }

    /**
     * A library with a year, two classes and someone from the platform: what the three cases below need.
     *
     * @return \stdClass
     */
    private function people(): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->gen = $gen;
        $w->library = $gen->create_library();
        $w->entityid = (int) $w->library->entityid;
        $w->now = time();
        $w->year = $gen->create_year(['entityid' => $w->entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $w->now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $w->user = $this->getDataGenerator()->create_user(['firstname' => 'Paula', 'lastname' => 'Arenas']);
        $w->teacher = $gen->create_reader(['entityid' => $w->entityid, 'userid' => $w->user->id, 'readertype' => 'teacher']);
        $w->old = $gen->create_reader(['entityid' => $w->entityid, 'firstname' => 'Raúl', 'lastname' => 'Abreu']);
        $w->class = $gen->create_reader(['entityid' => $w->entityid, 'firstname' => 'Ana', 'lastname' => 'Montes']);
        $gen->create_member(['readerid' => $w->class->id, 'yearid' => $w->year->id, 'groupname' => '2º A', 'stage' => 'Primaria',
            'source' => 'cohort']);
        $other = $gen->create_reader(['entityid' => $w->entityid, 'firstname' => 'Leo', 'lastname' => 'Sol']);
        $gen->create_member(['readerid' => $other->id, 'yearid' => $w->year->id, 'groupname' => '2º B', 'stage' => 'Primaria',
            'source' => 'cohort']);
        return $w;
    }

    /**
     * Someone without an account can have their name, address, kind and group changed, and what is wrong is not saved;
     * the name of an account and the group the platform brings stay as they are.
     */
    public function test_change(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->people();
        $form = ['firstname' => 'Raúl', 'lastname' => 'Abreu Jorge', 'email' => 'raul@example.com', 'type' => 'staff',
            'group' => '2º B'];
        $id = (int) $w->old->id;
        $this->assertSame('profiledatabademail', profile::change($w->library, $id, ['email' => 'no'] + $form, $w->now, false));
        $this->assertSame(
            'profiledatanoname',
            profile::change($w->library, $id, ['firstname' => ' ', 'lastname' => ''] + $form, $w->now, false)
        );
        $this->assertSame('student', $DB->get_field(self::T . 'reader', 'readertype', ['id' => $id]));

        $this->assertSame('profiledatasaved', profile::change($w->library, $id, $form, $w->now, false));
        $row = $DB->get_record(self::T . 'reader', ['id' => $id]);
        $this->assertSame(['Abreu Jorge', 'raul@example.com', 'staff'], [$row->lastname, $row->email, $row->readertype]);
        $member = $DB->get_record(self::T . 'member', ['readerid' => $id, 'yearid' => $w->year->id]);
        $this->assertSame(['2º B', 'Primaria', 'manual'], [$member->groupname, $member->stage, $member->source]);

        // No group again; and a group that is not one of this year's is none.
        profile::change($w->library, $id, ['group' => ''] + $form, $w->now, false);
        $this->assertFalse($DB->record_exists(self::T . 'member', ['readerid' => $id]));
        profile::change($w->library, $id, ['group' => 'Otro'] + $form, $w->now, false);
        $this->assertFalse($DB->record_exists(self::T . 'member', ['readerid' => $id]));

        // The account says the name; the platform, the group.
        profile::change($w->library, (int) $w->teacher->id, ['firstname' => 'Otra'] + $form, $w->now, false);
        $this->assertNull($DB->get_field(self::T . 'reader', 'email', ['id' => $w->teacher->id]) ?: null);
        $this->assertSame('Paula Arenas', profile::of($w->library, (int) $w->teacher->id, $w->now)['name']);
        profile::change($w->library, (int) $w->class->id, ['group' => '2º B'] + $form, $w->now, false);
        $this->assertSame('2º A', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $w->class->id]));
        $this->assertNotSame(
            '',
            profile::form($w->library, $DB->get_record(self::T . 'reader', ['id' => $w->class->id]), $w->now, true)['lockedgroup']
        );

        // Nobody of another centre is touched.
        $elsewhere = $w->gen->create_library(['slug' => 'lejos']);
        $this->assertSame('readernothere', profile::change($elsewhere, $id, $form, $w->now, false));
    }

    /**
     * The record says what a teacher tutors; whoever manages changes it from there, and only for teachers with an account.
     */
    public function test_tutor(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->people();
        $id = (int) $w->teacher->id;
        $form = ['type' => 'teacher', 'tutorof' => '2º A'];
        // Not without the right to.
        profile::change($w->library, $id, $form, $w->now, false);
        $this->assertSame(0, $DB->count_records(self::T . 'tutor'));

        profile::change($w->library, $id, $form, $w->now, true);
        $this->assertSame(['2º A'], profile::of($w->library, $id, $w->now)['tutoring']);
        $reader = $DB->get_record(self::T . 'reader', ['id' => $id]);
        $this->assertSame('2º A', profile::form($w->library, $reader, $w->now, true)['tutorof']);
        profile::change($w->library, $id, ['tutorof' => '2º B'] + $form, $w->now, true);
        $this->assertSame(['2º B'], profile::of($w->library, $id, $w->now)['tutoring']);
        profile::change($w->library, $id, ['tutorof' => ''] + $form, $w->now, true);
        $this->assertSame([], profile::of($w->library, $id, $w->now)['tutoring']);

        // A student has no account here, and tutors nothing.
        $old = $DB->get_record(self::T . 'reader', ['id' => $w->old->id]);
        $this->assertFalse(profile::form($w->library, $old, $w->now, true)['canteach']);
    }

    /**
     * Those without a group, by kind; those who stop coming leave the lists and come back as they were.
     */
    public function test_lists(): void {
        $this->resetAfterTest();
        $w = $this->people();
        $w->gen->create_reader(['entityid' => $w->entityid, 'firstname' => 'Aula', 'readertype' => 'classroom']);
        $kinds = readers::without_group($w->library, $w->now);
        $this->assertSame([1, 1, 1], [$kinds['teacher'], $kinds['student'], $kinds['other']]);
        $teachers = readers::in_group($w->library, '', $w->now, 0, 0, ['type' => 'teacher']);
        $this->assertSame([(int) $w->teacher->id], array_column($teachers, 'id'));
        $this->assertStringContainsString('type=teacher', $teachers[0]['url']);
        $this->assertSame(1, readers::in_group_count($w->library, '', $w->now, ['type' => 'other']));

        profile::set_gone($w->library, (int) $w->class->id, true);
        $this->assertSame(['id' => (int) $w->class->id], array_intersect_key(
            readers::in_group($w->library, '', $w->now, 0, 0, ['status' => 'left'])[0],
            ['id' => 1]
        ));
        $this->assertSame(0, readers::in_group_count($w->library, '2º A', $w->now));
        profile::set_gone($w->library, (int) $w->class->id, false);
        $this->assertSame(1, readers::in_group_count($w->library, '2º A', $w->now));
        $this->assertSame(0, readers::in_group_count($w->library, '', $w->now, ['status' => 'left']));
    }

    /**
     * The history has every loan, the last first, also those of the textbook bank; whoever only keeps a library sees what
     * it lent.
     */
    public function test_history(): void {
        $this->resetAfterTest();
        $w = $this->people();
        $bank = libraries::create_library($w->entityid, ['name' => 'Banco', 'kind' => 'bank']);
        $novel = $w->gen->create_record(['entityid' => $w->entityid, 'title' => 'El faro']);
        $text = $w->gen->create_record(['entityid' => $w->entityid, 'title' => 'Lengua 2']);
        $copy = $w->gen->create_item(['libraryid' => $w->library->id, 'recordid' => $novel->id]);
        $bankcopy = $w->gen->create_item(['libraryid' => $bank->id, 'recordid' => $text->id]);
        $id = (int) $w->old->id;
        $w->gen->create_loan(['libraryid' => $w->library->id, 'itemid' => $copy->id, 'readerid' => $id, 'yearid' => $w->year->id,
            'timeloaned' => $w->now - 20 * DAYSECS, 'timereturned' => $w->now - 9 * DAYSECS, 'returnnote' => 'loved']);
        $w->gen->create_loan(['libraryid' => $bank->id, 'itemid' => $bankcopy->id, 'readerid' => $id, 'yearid' => $w->year->id,
            'timeloaned' => $w->now - 5 * DAYSECS]);
        $this->assertSame(2, profile::history_total($w->library, $id));
        $this->assertSame(1, profile::history_total($w->library, $id, true));
        $all = profile::history($w->library, $id);
        $this->assertSame(['Lengua 2', 'El faro'], array_column($all, 'title'));
        $this->assertSame(0, $all[0]['returned']);
        $this->assertSame('loved', $all[1]['note']);
        $this->assertSame(['El faro'], array_column(profile::history($w->library, $id, 0, true), 'title'));
    }
}
