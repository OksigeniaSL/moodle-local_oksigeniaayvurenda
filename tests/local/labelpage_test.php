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

use local_oksigeniaayvurenda\core\label_sheets;

/**
 * Tests for the page of labels as one sample sheet.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\labelpage
 */
#[\PHPUnit\Framework\Attributes\CoversClass(labelpage::class)]
final class labelpage_test extends \advanced_testcase {
    /**
     * The days things came in are offered, oldest first and today last; the first sheet is drawn whole, the labels
     * before the start crossed; the sentence says how many labels and sheets.
     */
    public function test_page(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $zone = zones::of($library);
        $now = time();
        $at = fn(string $modify) => (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->modify($modify)->setTime(11, 0);
        $codes = [];
        foreach (['-20 days', '-9 days', '-9 days', '-2 days'] as $i => $when) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Libro ' . $i]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => '82-3',
                'callnumber2' => 'ROW', 'callnumber3' => 'abu']);
            $DB->set_field('local_oksigeniaayvurenda_item', 'timecreated', $at($when)->getTimestamp(), ['id' => $item->id]);
            $codes[] = $DB->get_field('local_oksigeniaayvurenda_item', 'barcode', ['id' => $item->id]);
        }
        $days = labelpage::days($library, $now);
        $this->assertSame([$at('-20 days')->format('Y-m-d'), $at('-9 days')->format('Y-m-d'), $at('-2 days')->format('Y-m-d'),
            $at('today')->format('Y-m-d')], array_column($days, 'date'));
        $this->assertSame(get_string('labelstoday', 'local_oksigeniaayvurenda'), end($days)['label']);
        $since = $at('-9 days')->format('Y-m-d');
        $items = labels::items($library, 'since', (new \DateTimeImmutable($since . ' 00:00', $zone))->getTimestamp());
        $this->assertCount(3, $items);
        $sheet = label_sheets::key('', 'spines');
        $per = label_sheets::placed($sheet)['per'];
        $page = labelpage::context($library, ['kind' => 'spines', 'which' => 'since', 'since' => $since, 'codes' => '',
            'sheet' => $sheet, 'start' => 3, 'adjust' => false], $items, false);
        $this->assertCount($per, $page['paper']['cells']);
        $this->assertSame([true, true, false], array_column(array_slice($page['paper']['cells'], 0, 3), 'used'));
        $this->assertTrue($page['paper']['cells'][2]['first']);
        $this->assertSame('82-3', $page['paper']['cells'][2]['spine']['lines'][0]['line']);
        $this->assertTrue($page['paper']['cells'][2]['spine']['lines'][0]['top']);
        $this->assertStringContainsString('<b>', $page['says']);
        $this->assertTrue($page['kinds'][1]['current']);
        $this->assertTrue($page['whiches'][1]['current']);
        $this->assertContains(true, array_column($page['days']['list'], 'current'));
        $this->assertNull($page['codes']);
        $this->assertNull($page['adjust']);
        // The chosen sheet is among the first ones; the rest wait under «Other sheets».
        $this->assertSame([true], array_column($page['sheets']['top'], 'current'));
        $this->assertCount(count(label_sheets::MODELS) - 1, $page['sheets']['others']);
        // A list of codes: its paper, and nothing to print when none is known.
        $page = labelpage::context($library, ['kind' => 'labels', 'which' => 'codes', 'since' => '', 'codes' => 'NADA',
            'sheet' => label_sheets::key('', 'labels'), 'start' => 1, 'adjust' => true], [], false);
        $this->assertFalse($page['haslabels']);
        $this->assertNotNull($page['codes']);
        $this->assertNotNull($page['adjust']);
        $this->assertSame('', $page['adjust']['reset']);
    }
}
