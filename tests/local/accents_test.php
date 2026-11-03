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
 * Names with accents, found by a word typed without them, on every database.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\readers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(readers::class)]
final class accents_test extends \advanced_testcase {
    /**
     * The desk finds them with or without the accent, in any case.
     */
    public function test_desk_finds_accents(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $gen->create_reader(['entityid' => $library->entityid, 'firstname' => 'José', 'lastname' => 'Núñez', 'cardcode' => 'C1']);
        $desk = new desk($library, 0);
        foreach (['nunez', 'NÚÑEZ', 'jose nunez'] as $typed) {
            $this->assertCount(1, $desk->find_readers($typed), $typed);
        }
        $this->assertSame([], $desk->find_readers('zzqx'));
    }
}
