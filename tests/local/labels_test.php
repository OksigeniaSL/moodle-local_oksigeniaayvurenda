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
 * Tests for labels and spine labels.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\labels
 */
#[\PHPUnit\Framework\Attributes\CoversClass(labels::class)]
final class labels_test extends \advanced_testcase {
    /**
     * The copies added today or since a day, or a list of codes in its order; a started sheet leaves its first labels.
     */
    public function test_items_and_sheets(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $old = $gen->create_item(['libraryid' => $library->id, 'barcode' => '100000L', 'callnumber1' => 'N',
            'timecreated' => $now - 10 * DAYSECS]);
        $gen->create_item(['libraryid' => $library->id, 'barcode' => '100001C', 'callnumber1' => '91',
            'callnumber2' => 'EST', 'timecreated' => $now]);
        $this->assertSame(['100001C'], array_column(labels::items($library, 'today'), 'barcode'));
        $this->assertSame(['100000L', '100001C'], array_column(
            labels::items($library, 'since', $now - 11 * DAYSECS),
            'barcode'
        ));
        $listed = labels::items($library, 'codes', 0, ['100001C', ' 100000L ', 'nada']);
        $this->assertSame(['100001C', '100000L'], array_column($listed, 'barcode'));
        $this->assertSame(['91', 'EST'], $listed[0]['callnumber']);
        $this->assertStringContainsString('>100001C</text>', $listed[0]['svg']);

        $sheets = labels::sheets($listed, '3x8', 24);
        $this->assertCount(2, $sheets);
        $this->assertCount(24, $sheets[0]['labels']);
        $this->assertSame(['blank' => true], $sheets[0]['labels'][0]);
        $this->assertSame('100001C', $sheets[0]['labels'][23]['barcode']);
        $this->assertSame('100000L', $sheets[1]['labels'][0]['barcode']);
        $this->assertCount(1, labels::sheets($listed, '5x13', 1));
        $this->assertNotNull($old);
    }
}
