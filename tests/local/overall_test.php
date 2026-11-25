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
 * Tests for the sheet of the overall statistics.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\overall
 */
#[\PHPUnit\Framework\Attributes\CoversClass(overall::class)]
final class overall_test extends \advanced_testcase {
    /**
     * What is drawn and what is downloaded say the same; a library with nothing is drawn too.
     */
    public function test_overall(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        [$from, $to] = periods::resolve($library, $now, 'curso', '', '');
        $span = ['periodo' => 'curso'];
        $draw = fn() => overall::context($library, glance::of($library, $from, $to, $now), $from, $to, $now, $span);
        $empty = $draw();
        $this->assertNull($empty['shelf']);
        $this->assertNull($empty['top']);
        $this->assertCount(12, $empty['trend']['columns']);

        $item = $gen->create_item(['libraryid' => $library->id]);
        for ($i = 0; $i < 3; $i++) {
            $gen->create_item(['libraryid' => $library->id]);
        }
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id, 'timeloaned' => $now]);
        $drawn = $draw();
        $this->assertCount(100, $drawn['shelf']['spines']);
        $this->assertCount(25, array_filter($drawn['shelf']['spines'], fn($spine) => $spine['used']));
        $this->assertStringContainsString('25', $drawn['shelf']['lead']);
        $this->assertStringContainsString('periodo=curso', $drawn['top']['all']['url']);

        $sheet = overall::sheet($library, glance::of($library, $from, $to, $now), $from, $to);
        $this->assertCount(4, $sheet['columns']);
        foreach ($sheet['rows'] as $row) {
            $this->assertCount(4, $row);
        }
        $rows = array_column($sheet['rows'], 2, 1);
        $this->assertSame(4, $rows[get_string('overallcsvcopies', 'local_oksigeniaayvurenda')]);
        $this->assertSame(1, $rows[get_string('overallcsvused', 'local_oksigeniaayvurenda')]);
        $this->assertSame('25.0', $rows[get_string('overallcsvusedpct', 'local_oksigeniaayvurenda')]);
    }
}
