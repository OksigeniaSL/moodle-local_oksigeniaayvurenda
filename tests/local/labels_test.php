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

    /**
     * Every sheet fits its paper and says how many labels it has; the old keys still lead to their sheet; a library marks
     * its favourites of each kind, the first of them is the one the page starts with, and the print moves within its
     * limits only for that sheet.
     */
    public function test_sheets_favourites_and_nudge(): void {
        $this->resetAfterTest();
        foreach (\local_oksigeniaayvurenda\core\label_sheets::MODELS as $key => $model) {
            $this->assertTrue(\local_oksigeniaayvurenda\core\label_sheets::fits($key), $key);
        }
        $sheets = \local_oksigeniaayvurenda\core\label_sheets::class;
        $this->assertSame(40, $sheets::placed('apli-01286')['per']);
        $this->assertSame(21, $sheets::placed('apli-01276')['per']);
        $this->assertSame('apli-01273', $sheets::key('3x8'));
        $this->assertSame('avery-3666', $sheets::key('nada', 'spines'));

        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $this->assertSame('avery-3666', labels::chosen($library, 'spines', ''));
        $this->assertTrue(labels::favourite($library, 'spines', 'apli-01286'));
        $this->assertTrue(labels::favourite($library, 'labels', 'apli-01276'));
        $this->assertSame('apli-01286', labels::chosen($library, 'spines', ''));
        $this->assertSame('apli-01276', labels::chosen($library, 'labels', ''));
        $this->assertSame('apli-01273', labels::chosen($library, 'labels', '3x8'));
        $this->assertFalse(labels::favourite($library, 'spines', 'apli-01286'));
        $this->assertSame([], labels::own($library)['favs']['spines']);
        $this->assertFalse(labels::favourite($library, 'labels', 'otra-cosa'));

        labels::nudge($library, 'apli-01276', 1.5, -40);
        $this->assertSame([1.5, -10.0], labels::own($library)['nudge']['apli-01276']);
        $placed = $sheets::placed('apli-01276', 1.5, -10);
        $this->assertEquals(1.5, $placed['left']);
        labels::nudge($library, 'apli-01276', 0, 0);
        $this->assertArrayNotHasKey('apli-01276', labels::own($library)['nudge']);
        // Another library of the same centre keeps its own.
        $other = $gen->create_library(['entityid' => $library->entityid]);
        $this->assertSame([], labels::own($other)['favs']['labels']);
    }
}
