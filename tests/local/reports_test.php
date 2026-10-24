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
 * Tests for the six fixed reports.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\reports
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reports::class)]
final class reports_test extends \advanced_testcase {
    /**
     * On the practice library, with two more books lent at the desk: loans by group, the most lent, the never lent,
     * the holdings and the school year.
     */
    public function test_reports(): void {
        $this->resetAfterTest();
        $library = practice::library();
        $desk = new desk($library, (int) get_admin()->id);
        $ana = $desk->scan('10000H');
        $desk->scan('100000L', $ana['reader']['id']);
        $desk->scan('100001C', $ana['reader']['id']);
        $year = years::around((int) $library->entityid, time());

        $loans = reports::loans($library, (int) $year->timestart, time() + DAYSECS);
        $this->assertSame(4, $loans['total']);
        $this->assertSame(['3º A', '5º B'], array_column($loans['rows'], 'label'));
        $this->assertSame([3, 1], array_column($loans['rows'], 'value'));
        $this->assertSame([2, 1], array_column($loans['rows'], 'readers'));
        $this->assertSame([2, 1], array_column($loans['rows'], 'members'));

        $top = reports::top($library, (int) $year->timestart, time() + DAYSECS);
        $this->assertCount(4, $top);
        $this->assertSame(1, $top[0]['value']);

        $never = reports::never($library, 0, time() + DAYSECS);
        $this->assertSame(1, $never['total']);
        $this->assertSame('Atlas de los mares', $never['rows'][0]['label']);
        $this->assertNull($never['rows'][0]['lastloan']);
        // Not lent since yesterday: also the two lent days ago; a copy with no date of acquisition counts as old.
        $this->assertSame(3, reports::never($library, time() - DAYSECS, time() + DAYSECS)['total']);

        $description = ['record' => ['doctype' => 'book', 'title' => 'Volcanes'], 'authorities' => ['class' => ['551'],
            'audience' => ['A partir de 8 años']], 'extra' => []];
        cataloguer::add($library, $description, ['count' => 2]);
        $holdings = reports::holdings($library);
        $this->assertSame(2, $holdings['subjects'][0]['value']);
        $this->assertSame(0, $holdings['subjects'][0]['lent']);
        $this->assertSame([['label' => 'A partir de 8 años', 'value' => 2, 'lent' => 0]], $holdings['audiences']);

        $summary = reports::year($library, $year);
        $this->assertSame(4, $summary['loans']);
        // The five practice books, made today, and the two added.
        $this->assertSame(7, $summary['acquired']);
        $this->assertCount(4, $summary['top']);
    }
}
