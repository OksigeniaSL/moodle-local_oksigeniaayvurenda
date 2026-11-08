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
 * Tests for the lines of humour written for each place (jue-humor).
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\screens
 */
#[\PHPUnit\Framework\Attributes\CoversClass(screens::class)]
final class humor_test extends \advanced_testcase {
    /**
     * A very old book says so on its record (a century for grown-ups); a recent one, or a year that is not a year, not.
     */
    public function test_old_book(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $lines = [];
        foreach (['1898', '1990', 'c. 1920', '[1910]'] as $year) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro ' . $year, 'pubyear' => $year]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $lines[$year] = screens::record(catalogue::record((int) $record->id, $library), $library)['oldline'];
        }
        $this->assertStringContainsString('1898', $lines['1898']);
        $this->assertSame('', $lines['1990']);
        $this->assertSame('', $lines['c. 1920']);
        $this->assertStringContainsString('1910', $lines['[1910]']);
    }
}
