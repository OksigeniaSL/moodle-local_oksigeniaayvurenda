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
 * Tests for stocktakes.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\stocktake
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stocktake::class)]
final class stocktake_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * A library with two places, Narrativa and Poesía; copies on the shelves, one lent, one lost; another library.
     *
     * @return \stdClass
     */
    private function make_world(): \stdClass {
        global $DB;
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library();
        $w->other = $gen->create_library(['entityid' => $w->library->entityid]);
        $place = fn($label) => (int) $DB->insert_record(self::T . 'authority', ['entityid' => $w->library->entityid,
            'kind' => 'location', 'label' => $label, 'timecreated' => time(), 'timemodified' => time()]);
        $w->narrativa = $place('Narrativa');
        $w->poesia = $place('Poesía');
        $copy = fn(int $where, string $status = 'available', ?int $libraryid = null) => $gen->create_item([
            'libraryid' => $libraryid ?? $w->library->id, 'locationid' => $where, 'status' => $status]);
        $w->a1 = $copy($w->narrativa);
        $w->a2 = $copy($w->narrativa);
        $w->b1 = $copy($w->poesia);
        $w->lent = $copy($w->narrativa, 'onloan');
        $w->lost = $copy($w->narrativa, 'lost');
        $w->away = $copy($w->narrativa, 'available', (int) $w->other->id);
        $w->user = $this->getDataGenerator()->create_user();
        return $w;
    }

    /**
     * The whole library: each kind of read, what is missing, marking it lost, putting back what turned up.
     */
    public function test_whole_library(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $id = stocktake::start($w->library, 'Junio', null, (int) $w->user->id);
        $st = stocktake::get($id, $w->library);
        $kind = fn(string $code, ?int $here = null) => stocktake::read($st, $code, $here, (int) $w->user->id)['kind'];
        $this->assertSame('seen', $kind(strtolower($w->a1->barcode), $w->narrativa));
        $this->assertSame('again', $kind($w->a1->barcode, $w->narrativa));
        $this->assertSame('misplaced', $kind($w->b1->barcode, $w->narrativa));
        $this->assertSame('lent', $kind($w->lent->barcode));
        $this->assertSame('found', $kind($w->lost->barcode));
        $this->assertSame('otherlibrary', $kind($w->away->barcode));
        $this->assertSame('unknown', $kind('999999Z'));
        $this->assertSame('unknown', $kind('999999Z'));

        $this->assertSame(['expected' => 4, 'seen' => 3, 'missing' => 1, 'misplaced' => 1, 'lent' => 1, 'found' => 1,
            'unknown' => 1], stocktake::counts($st));
        $results = stocktake::results($st);
        $this->assertSame($w->a2->barcode, $results['missing'][0]['barcode']);
        $this->assertSame('Narrativa', $results['missing'][0]['where']);
        $this->assertSame(['Poesía', 'Narrativa'], [$results['misplaced'][0]['where'], $results['misplaced'][0]['readat']]);
        $this->assertSame(['999999Z'], $results['unknown']);

        $this->assertSame(1, stocktake::lose($st));
        $this->assertSame('lost', $DB->get_field(self::T . 'item', 'status', ['id' => $w->a2->id]));
        $this->assertSame(1, stocktake::recover($st));
        $this->assertSame('available', $DB->get_field(self::T . 'item', 'status', ['id' => $w->lost->id]));
        stocktake::close($st);
        $this->assertSame('closed', $DB->get_field(self::T . 'stocktake', 'status', ['id' => $id]));
    }

    /**
     * One place: only its copies count, and a copy of another place read there is out of its place without saying
     * where one is.
     */
    public function test_one_place(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $st = stocktake::get(stocktake::start($w->library, 'Poesía', $w->poesia, (int) $w->user->id), $w->library);
        $this->assertSame('misplaced', stocktake::read($st, $w->a1->barcode, null, (int) $w->user->id)['kind']);
        $this->assertSame('seen', stocktake::read($st, $w->b1->barcode, null, (int) $w->user->id)['kind']);
        $counts = stocktake::counts($st);
        $this->assertSame([1, 1, 0], [$counts['expected'], $counts['seen'], $counts['missing']]);
        $this->assertSame(
            [['id' => (int) $w->poesia, 'label' => 'Poesía', 'count' => 1]],
            array_values(array_filter(stocktake::places($w->library), fn($p) => $p['label'] === 'Poesía'))
        );
    }
}
