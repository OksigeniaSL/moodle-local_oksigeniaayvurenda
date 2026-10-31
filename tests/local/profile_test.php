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
}
