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
 * Tests for whoever keeps one library only.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\keepers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(keepers::class)]
final class keepers_test extends \advanced_testcase {
    /**
     * The class teacher keeps the library of her room: at its desk she lends its books to the readers of its group, and
     * nothing of the central library nor of other groups; the centre's librarian keeps the whole desk. Taken away, she
     * keeps nothing.
     */
    public function test_keeper_desk(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_oksigeniaayvurenda');
        $central = $gen->create_library(['slug' => 'central']);
        $room = $gen->create_library(['entityid' => $central->entityid, 'slug' => 'aula-3a', 'kind' => 'classroom',
            'parentid' => $central->id, 'settings' => json_encode(['groups' => ['3º A']])]);
        $now = time();
        $year = \local_oksigeniaayvurenda\core\school_year::around($now, '09-01', \core_date::get_server_timezone_object());
        $year = $gen->create_year(['entityid' => $central->entityid] + $year);
        $teacher = $dg->create_user();
        $gen->create_reader(['entityid' => $central->entityid, 'userid' => $teacher->id, 'readertype' => 'teacher']);
        $ana = $gen->create_reader(['entityid' => $central->entityid, 'cardcode' => '10000H', 'firstname' => 'Ana',
            'lastname' => 'Inventada']);
        $leo = $gen->create_reader(['entityid' => $central->entityid, 'cardcode' => '10001L', 'firstname' => 'Leo',
            'lastname' => 'Inventado']);
        $gen->create_member(['readerid' => $ana->id, 'yearid' => $year->id, 'groupname' => '3º A']);
        $gen->create_member(['readerid' => $leo->id, 'yearid' => $year->id, 'groupname' => '5º B']);
        $ours = $gen->create_item(['libraryid' => $room->id, 'barcode' => '100000L']);
        $gen->create_item(['libraryid' => $central->id, 'barcode' => '100001C']);
        $outsider = $dg->create_user();

        $this->assertFalse(keepers::add($room, (int) $outsider->id, 2));
        $this->assertTrue(keepers::add($room, (int) $teacher->id, 2));
        $this->assertSame(['userid' => (int) $teacher->id], array_intersect_key(keepers::of((int) $room->id)[0], ['userid' => 0]));
        $this->assertTrue(access::keeps($room, (int) $teacher->id));
        $this->assertFalse(access::keeps($central, (int) $teacher->id));
        $this->assertFalse(access::can('local/oksigeniaayvurenda:desk', $room, (int) $teacher->id));
        $this->assertSame(['aula-3a'], array_column(keepers::libraries((int) $teacher->id), 'slug'));

        $this->setUser($teacher);
        $desk = new desk($room, (int) $teacher->id, $now, true);
        $other = $desk->scan('100001C');
        $this->assertSame('unknown', $other['kind']);
        $this->assertStringEndsWith(
            explode('»', get_string('deskotherlibrary', 'local_oksigeniaayvurenda', 'x'))[1],
            $other['message']
        );
        $this->assertSame('reader', $desk->scan('10000H')['kind']);
        $this->assertSame(get_string('deskotherreader', 'local_oksigeniaayvurenda'), $desk->scan('10001L')['message']);
        $this->assertSame('unknown', $desk->scan('100000L', (int) $leo->id)['kind']);
        $this->assertSame('lent', $desk->scan('100000L', (int) $ana->id)['kind']);
        $this->assertSame((int) $room->id, (int) $DB->get_field(
            'local_oksigeniaayvurenda_loan',
            'libraryid',
            ['itemid' => $ours->id, 'readerid' => $ana->id]
        ));
        try {
            $desk->reader_panel((int) $leo->id);
            $this->fail('a reader of another group is shown');
        } catch (\moodle_exception $e) {
            $this->assertSame('deskotherreader', $e->errorcode);
        }

        // The centre's librarian is no keeper: the whole desk.
        $librarian = $dg->create_user();
        role_assign(roles::ensure_librarian(), $librarian->id, access::context($room)->id);
        $this->assertFalse(access::keeps($room, (int) $librarian->id));

        keepers::remove($room, (int) $teacher->id);
        $this->assertFalse(access::keeps($room, (int) $teacher->id));
        $this->assertSame([], keepers::of((int) $room->id));
    }
}
