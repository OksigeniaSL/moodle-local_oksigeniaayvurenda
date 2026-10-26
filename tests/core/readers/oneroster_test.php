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

namespace local_oksigeniaayvurenda\core\readers;

/**
 * Tests for the readers of a OneRoster export.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\oneroster
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oneroster::class)]
final class oneroster_test extends \basic_testcase {
    /**
     * The files of the made-up export (version 1.2, two schools).
     *
     * @return array<string, string>
     */
    private function files(): array {
        $files = [];
        foreach (glob(__DIR__ . '/../../fixtures/oneroster/*.csv') as $path) {
            $files[basename($path)] = file_get_contents($path);
        }
        return $files;
    }

    /**
     * Students with their homeroom, the teacher as its tutor, who is disabled apart, families only when asked, and
     * one school at a time.
     */
    public function test_readers(): void {
        $now = strtotime('2026-10-26 12:00:00 UTC');
        $found = oneroster::readers($this->files(), 'sch1', false, $now);
        $this->assertSame(['sch1' => 'Colegio Inventado Alfa', 'sch2' => 'Colegio Inventado Beta'], $found['schools']);
        $this->assertSame(['bulk', '1.2'], [$found['mode'], $found['version']]);
        $this->assertSame(['id:oneroster:u3'], $found['gone']);
        $this->assertSame(['3º A' => 'oneroster:t1'], $found['tutors']);
        $this->assertSame(['oneroster:u1', 'oneroster:u2', 'oneroster:t1'], array_column($found['readers'], 'externalid'));
        $this->assertEquals(['externalid' => 'oneroster:u1', 'firstname' => 'Ana', 'lastname' => 'Montes',
            'email' => 'ana@example.invalid', 'idnumber' => '1001', 'username' => 'ana.montes', 'group' => '3º A',
            'type' => 'student'], $found['readers'][0]);
        // A comma inside quotes is part of the name; the teacher has no group.
        $this->assertSame('Faro, del Mar', $found['readers'][1]['lastname']);
        $this->assertSame('teacher', $found['readers'][2]['type']);
        $this->assertArrayNotHasKey('group', $found['readers'][2]);

        $withfamilies = oneroster::readers($this->files(), 'sch1', true, $now);
        $this->assertContains('family', array_column($withfamilies['readers'], 'type'));
        $all = oneroster::readers($this->files(), '', false, $now);
        $this->assertContains('oneroster:b1', array_column($all['readers'], 'externalid'));
        $this->assertSame(['3º A' => 'oneroster:t1'], $all['tutors']);
    }

    /**
     * Version 1.1 keeps each user's role and schools in users.csv.
     */
    public function test_version_one_one(): void {
        $users = "sourcedId,status,dateLastModified,enabledUser,orgSourcedIds,role,username,userIds,givenName,familyName," .
            "middleName,identifier,email,sms,phone,agentSourcedIds,grades,password\n" .
            "x1,active,,true,\"sch1,sch2\",student,iker,,Iker,Brisa,,5001,,,,,04,\n" .
            "x2,active,,true,sch1,administrator,gael,,Gael,Mirto,,5002,,,,,,\n";
        $found = oneroster::readers(['users.csv' => $users], 'sch2', false, time());
        $this->assertSame(['oneroster:x1'], array_column($found['readers'], 'externalid'));
        $all = oneroster::readers(['users.csv' => $users], '', false, time());
        $this->assertSame(['student', 'staff'], array_column($all['readers'], 'type'));
    }

    /**
     * Without users.csv it is no export.
     */
    public function test_without_users(): void {
        $this->expectException(\InvalidArgumentException::class);
        oneroster::readers(['classes.csv' => "sourcedId\n"], '', false, time());
    }
}
