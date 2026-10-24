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
 * Tests for the people of a Cifra list, as readers.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\core\readers\cifra_people
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cifra_people::class)]
final class cifra_people_test extends \basic_testcase {
    /**
     * A made-up list: a repeated person counts once, who left is apart, a teacher has no group, two siblings with
     * the family's email are two readers.
     */
    public function test_readers(): void {
        $people = json_decode(file_get_contents(__DIR__ . '/../../fixtures/cifra/alumnos.json'), true);
        $found = cifra_people::readers($people, strtotime('2026-10-24 12:00:00 UTC'));
        $this->assertSame(['id:cifra:7003'], $found['gone']);
        $this->assertCount(4, $found['readers']);
        $this->assertSame('cifra:7005', $found['readers'][3]['externalid']);
        $this->assertEquals(['externalid' => 'cifra:7001', 'firstname' => 'Bruno', 'lastname' => 'Olmo Ríos',
            'email' => 'bruno.olmo@example.invalid', 'group' => 'ESO 1º A', 'stage' => stage_guesser::guess('ESO 1º A'),
            'type' => 'student'], $found['readers'][0]);
        $this->assertSame('Lago', $found['readers'][1]['lastname']);
        $this->assertSame('teacher', $found['readers'][2]['type']);
        $this->assertArrayNotHasKey('group', $found['readers'][2]);
        // Before the day they left, they were still there.
        $this->assertCount(5, cifra_people::readers($people, strtotime('2026-09-01 12:00:00 UTC'))['readers']);
    }
}
