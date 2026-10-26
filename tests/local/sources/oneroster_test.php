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

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\oneroster as roster;

/**
 * Tests for readers from a OneRoster export.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\sources\oneroster
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oneroster::class)]
final class oneroster_test extends \advanced_testcase {
    /**
     * The made-up export as a zip, in a folder as some programs give it.
     *
     * @param array $change File name => text, to put in place of those of the fixtures.
     * @return string Its path.
     */
    private function zip(array $change = []): string {
        $path = make_request_directory() . '/oneroster.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach (glob(__DIR__ . '/../../fixtures/oneroster/*.csv') as $file) {
            $zip->addFromString('export/' . basename($file), $change[basename($file)] ?? file_get_contents($file));
        }
        $zip->close();
        return $path;
    }

    /**
     * The students come with their homeroom, the teacher with an account becomes its tutor, and a whole export without
     * someone makes them leave; an export of changes does not.
     */
    public function test_load(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $entityid = (int) $library->entityid;
        $teacher = $this->getDataGenerator()->create_user(['email' => 'hugo.lago@example.invalid']);
        $now = time();
        $found = roster::readers(oneroster::files($this->zip()), 'sch1', false, $now);
        $stats = oneroster::load($entityid, $found, $now);
        $this->assertSame([3, 0, 1], [$stats['created'], $stats['left'], $stats['tutors']]);
        $year = \local_oksigeniaayvurenda\local\years::around($entityid, $now);
        $this->assertEquals($teacher->id, $DB->get_field(
            'local_oksigeniaayvurenda_tutor',
            'userid',
            ['yearid' => $year->id, 'groupname' => '3º A']
        ));
        $this->assertSame(2, $DB->count_records('local_oksigeniaayvurenda_member', ['yearid' => $year->id,
            'groupname' => '3º A']));

        // Leo is no longer in the export: in a changes-only one he stays; in a whole one he leaves.
        $users = preg_replace('/^u2,.*\n/m', '', file_get_contents(__DIR__ . '/../../fixtures/oneroster/users.csv'));
        $manifest = str_replace(
            'file.users,bulk',
            'file.users,delta',
            file_get_contents(__DIR__ . '/../../fixtures/oneroster/manifest.csv')
        );
        $delta = roster::readers(
            oneroster::files($this->zip(['users.csv' => $users, 'manifest.csv' => $manifest])),
            'sch1',
            false,
            $now
        );
        $this->assertSame(0, oneroster::load($entityid, $delta, $now)['left']);
        $bulk = roster::readers(oneroster::files($this->zip(['users.csv' => $users])), 'sch1', false, $now);
        $again = oneroster::load($entityid, $bulk, $now);
        $this->assertSame([0, 1, 0], [$again['created'], $again['left'], $again['tutors']]);
        $this->assertSame('left', $DB->get_field('local_oksigeniaayvurenda_reader', 'status', ['entityid' => $entityid,
            'firstname' => 'Leo']));
    }

    /**
     * A zip without users.csv, or no zip, is said so.
     */
    public function test_not_an_export(): void {
        $path = make_request_directory() . '/other.zip';
        file_put_contents($path, 'not a zip');
        $this->expectException(\moodle_exception::class);
        oneroster::files($path);
    }
}
