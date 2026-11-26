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
 * Tests for the places of a library and moving copies among them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\places
 * @covers     \local_oksigeniaayvurenda\local\moves
 */
#[\PHPUnit\Framework\Attributes\CoversClass(places::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(moves::class)]
final class places_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $library;

    /** @var \component_generator_base */
    private $gen;

    /**
     * A library.
     */
    private function prepare(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $this->library = $this->gen->create_library();
    }

    /**
     * A copy in a place, with its spine.
     *
     * @param int|null $place
     * @param string $class
     * @param string $author
     * @return \stdClass
     */
    private function copy(?int $place, string $class = '82', string $author = 'AAA'): \stdClass {
        global $DB;
        $item = $this->gen->create_item(['libraryid' => $this->library->id, 'locationid' => $place, 'callnumber1' => $class,
            'callnumber2' => $author, 'shelfkey' => \local_oksigeniaayvurenda\core\shelf_order::key($class, $author, '', 'T', 1)]);
        $code = $DB->insert_record('local_oksigeniaayvurenda_authority', ['entityid' => $this->library->entityid,
            'kind' => 'class', 'code' => $class, 'label' => $class, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('local_oksigeniaayvurenda_recauth', ['recordid' => $item->recordid, 'authorityid' => $code]);
        return $item;
    }

    /**
     * A place renamed keeps its old name; one renamed as another becomes it; the same name written otherwise is the same
     * place, and the same words in another order are offered as twins.
     */
    public function test_places(): void {
        global $DB;
        $this->prepare();
        $entityid = (int) $this->library->entityid;
        $a = places::create($entityid, 'Infantil Exterior, 4ª estantería, 4ª balda');
        $b = places::create($entityid, 'Infantil exterior, 4 estanteria, 4 balda.');
        $this->assertSame($a, $b);
        $inner = places::create($entityid, 'Interior Infantil');
        $other = places::create($entityid, 'Infantil Interior');
        $this->assertNotSame($inner, $other);
        foreach ([$a, $a, $inner, $other, null] as $place) {
            $this->copy($place);
        }
        $all = array_column(places::all($this->library), null, 'id');
        $this->assertSame(2, $all[$a]['copies']);
        $this->assertSame([$other], $all[$inner]['twins']);
        $this->assertSame(1, $all[0]['copies']);

        $this->assertSame($a, places::rename($entityid, $a, 'Estante redondo'));
        $place = array_column(places::all($this->library), null, 'id')[$a];
        $this->assertSame('Estante redondo', $place['label']);
        $this->assertSame(['Infantil Exterior, 4ª estantería, 4ª balda'], $place['old']);
        // The old name still finds it.
        $this->assertSame($a, places::find($entityid, 'infantil exterior, 4ª estantería, 4ª balda'));

        // Named as another, they are one.
        $this->assertSame($inner, places::rename($entityid, $other, 'interior infantil'));
        $this->assertFalse($DB->record_exists('local_oksigeniaayvurenda_authority', ['id' => $other]));
        $this->assertSame(2, array_column(places::all($this->library), null, 'id')[$inner]['copies']);
        $this->assertContains('Infantil Interior', array_column(places::all($this->library), null, 'id')[$inner]['old']);
    }

    /**
     * A whole place, a stretch of shelf, a corner and the reader, and each undone.
     */
    public function test_moves(): void {
        global $DB;
        $this->prepare();
        $entityid = (int) $this->library->entityid;
        $from = places::create($entityid, 'Estantería 7');
        $to = places::create($entityid, 'Estante redondo');
        $copies = [$this->copy($from, '82', 'AAA'), $this->copy($from, '82', 'MMM'), $this->copy($from, '82', 'ZZZ'),
            $this->copy($from, '5', 'BBB')];
        $where = fn(\stdClass $item) => (int) $DB->get_field('local_oksigeniaayvurenda_item', 'locationid', ['id' => $item->id]);

        $preview = moves::preview($this->library, ['place' => $from]);
        $this->assertSame(4, $preview['count']);
        $all = moves::move($this->library, ['place' => $from], $to);
        $this->assertSame(4, $all['count']);
        $this->assertSame($to, $where($copies[2]));
        $this->assertSame(4, moves::undo($this->library, $all['batch']));
        $this->assertSame($from, $where($copies[2]));

        // From the first to the second on the shelf: «5» goes before «82».
        $part = moves::move($this->library, ['place' => $from, 'first' => $copies[3]->id, 'last' => $copies[1]->id], $to);
        $this->assertSame(3, $part['count']);
        $this->assertSame($from, $where($copies[2]));
        moves::undo($this->library, $part['batch']);

        // From the letter A to the M of the spines: not the ZZZ.
        $letters = moves::move($this->library, ['place' => $from, 'letters' => ['M', 'A']], $to);
        $this->assertSame(3, $letters['count']);
        $this->assertSame($from, $where($copies[2]));
        moves::undo($this->library, $letters['batch']);
        $found = array_keys(moves::letters($this->library, $from));
        sort($found);
        $this->assertSame(['A', 'B', 'M', 'Z'], $found);

        $corner = moves::move($this->library, ['place' => $from, 'corner' => '5'], $to);
        $this->assertSame(1, $corner['count']);
        $this->assertSame($to, $where($copies[3]));

        // With the reader: one after another, the same batch; a code nobody has, nothing.
        $DB->set_field('local_oksigeniaayvurenda_item', 'barcode', 'Z-1', ['id' => $copies[0]->id]);
        $DB->set_field('local_oksigeniaayvurenda_item', 'barcode', 'Z-2', ['id' => $copies[1]->id]);
        $first = moves::one($this->library, 'z-1', $to, '');
        $this->assertTrue($first['found']);
        $this->assertSame('Estantería 7', $first['from']);
        $second = moves::one($this->library, 'Z-2', $to, $first['batch']);
        $this->assertSame(2, $second['count']);
        $this->assertFalse(moves::one($this->library, 'Z-9', $to, $first['batch'])['found']);
        $this->assertTrue(moves::one($this->library, 'Z-2', $to, $first['batch'])['already']);
        // One moved again since does not go back.
        moves::move($this->library, ['place' => $to, 'corner' => '8', 'first' => $copies[1]->id,
            'last' => $copies[1]->id], $from);
        $this->assertSame(1, moves::undo($this->library, $first['batch']));
        $this->assertSame($from, $where($copies[0]));

        $this->assertGreaterThan(0, moves::forget(time() + moves::KEEP + 1));
        $this->assertSame(0, $DB->count_records('local_oksigeniaayvurenda_move'));
    }
}
