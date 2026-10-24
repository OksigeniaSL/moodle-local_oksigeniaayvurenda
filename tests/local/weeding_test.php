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
 * Tests for weeding.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\weeding
 * @covers     \local_oksigeniaayvurenda\local\catalogue
 */
#[\PHPUnit\Framework\Attributes\CoversClass(weeding::class)]
final class weeding_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Candidates by reason; taking the chosen out with a record; a copy lent is left alone; the catalogue no longer
     * shows what left; one goes back with a tap.
     */
    public function test_weeding(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $record = fn(string $year) => $gen->create_record(['entityid' => $library->entityid, 'pubyear' => $year]);
        $fresh = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record('2024')->id, 'timeacquired' => $now]);
        $old = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record('c1985')->id, 'timeacquired' => $now]);
        $worn = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record('2024')->id, 'timeacquired' => $now,
            'itemcondition' => 'broken']);
        $lost = $gen->create_item(['libraryid' => $library->id, 'status' => 'lost', 'timeacquired' => $now]);
        $lent = $gen->create_item(['libraryid' => $library->id, 'status' => 'onloan', 'recordid' => $record('1970')->id]);
        $filters = ['unused' => 5, 'before' => 2000, 'worn' => true, 'lost' => true];
        $found = weeding::candidates($library, $filters, $now);
        $this->assertSame(3, $found['total']);
        $this->assertSame(
            [[(int) $lost->id, 'lost'], [(int) $worn->id, 'worn'], [(int) $old->id, 'old']],
            array_map(fn($r) => [$r['id'], $r['why']], $found['rows'])
        );

        $id = weeding::withdraw(
            $library,
            [$old->id, $worn->id, $lent->id, $fresh->id],
            'Fin de curso',
            [$old->id => 'old', $worn->id => 'worn'],
            0,
            $now
        );
        $act = weeding::record($id, $library);
        $this->assertSame('Fin de curso', $act['record']->reason);
        $this->assertCount(3, $act['copies']);
        $this->assertSame('onloan', $DB->get_field(self::T . 'item', 'status', ['id' => $lent->id]));
        $this->assertSame('withdrawn', $DB->get_field(self::T . 'item', 'status', ['id' => $old->id]));
        $whys = array_column($act['copies'], 'why', 'barcode');
        $this->assertSame('chosen', $whys[$fresh->barcode]);
        $this->assertSame('none', catalogue::record((int) $old->recordid, $library)['card']['state']);
        $this->assertSame([], catalogue::record((int) $old->recordid, $library)['copies']);

        $back = array_values(array_filter($act['copies'], fn($c) => $c['barcode'] === $fresh->barcode))[0];
        weeding::restore($back['id'], $library);
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $fresh->id]));
        $this->assertSame(2, weeding::records($library)[0]['copies']);
        $this->assertSame(1, weeding::candidates($library, $filters, $now)['total'], 'only the lost one is left');
    }
}
