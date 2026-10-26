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
 * Tests for walking along the shelves.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\stacks
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stacks::class)]
final class stacks_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var \local_oksigeniaayvurenda_generator */
    private $gen;

    /**
     * A place of an entity.
     *
     * @param int $entityid
     * @param string $label
     * @return int
     */
    private function place(int $entityid, string $label): int {
        global $DB;
        return (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'location', 'code' => '',
            'label' => $label, 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * A book with one copy in a library.
     *
     * @param \stdClass $library
     * @param string $title
     * @param array $call The three lines of its label.
     * @param int|null $place
     * @param string $status
     * @return int The record id.
     */
    private function book(\stdClass $library, string $title, array $call, ?int $place = null, string $status = 'available'): int {
        $record = $this->gen->create_record(['entityid' => $library->entityid, 'title' => $title, 'extent' => '96 p. ; 22 cm']);
        $this->gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => $call[0],
            'callnumber2' => $call[1], 'callnumber3' => $call[2], 'locationid' => $place, 'status' => $status]);
        return (int) $record->id;
    }

    /**
     * The books of a place come in the order of their labels, the one that is out as a gap, and never the books of
     * another place or another library.
     */
    public function test_order_and_gaps(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $this->gen->create_library();
        $red = $this->place((int) $library->entityid, 'Rojo');
        $blue = $this->place((int) $library->entityid, 'Azul');
        $ids = [];
        $ids['82-3 MON far'] = $this->book($library, 'El faro dormido', ['82-3', 'MON', 'far'], $red);
        $ids['821 ALC mar'] = $this->book($library, 'Marianela', ['821', 'ALC', 'mar'], $red, 'onloan');
        $ids['5 LAU agu'] = $this->book($library, 'El agua', ['5', 'LAU', 'agu'], $red);
        $ids['82 DAH bru'] = $this->book($library, 'Las brujas', ['82', 'DAH', 'bru'], $red);
        $this->book($library, 'Otro sitio', ['1', 'AAA', 'otr'], $blue);
        $other = $this->gen->create_library(['entityid' => $library->entityid]);
        $this->book($other, 'Otra biblioteca', ['0', 'AAA', 'otr'], $red);

        $page = stacks::page($library, ['place' => $red]);
        $this->assertSame(
            [$ids['5 LAU agu'], $ids['82 DAH bru'], $ids['82-3 MON far'], $ids['821 ALC mar']],
            array_column($page['spines'], 'id')
        );
        $this->assertSame(['free', 'free', 'free', 'out'], array_column($page['spines'], 'state'));
        $this->assertTrue($page['spines'][3]['out']);
        $this->assertSame('82-3|MON|far', $page['spines'][2]['joined']);
        $this->assertSame('Rojo', $page['spines'][0]['place']);
        $this->assertNotNull($page['spines'][0]['placecolour']);
        $this->assertSame(22.0, $page['spines'][0]['cm']);
        $this->assertSame(['', ''], [$page['prev'], $page['next']]);

        // A corner is the first digit of the class; the whole library is everything of this library, in order.
        $this->assertSame(
            [$ids['82 DAH bru'], $ids['82-3 MON far'], $ids['821 ALC mar']],
            array_column(stacks::page($library, ['corner' => '8'])['spines'], 'id')
        );
        $this->assertCount(5, stacks::page($library, [])['spines']);

        // The places, by name, with their colour.
        $places = stacks::places($library);
        $this->assertSame(['Azul', 'Rojo'], array_column($places, 'label'));
        $this->assertSame([1, 4], array_column($places, 'count'));
    }

    /**
     * A long shelf goes by stretches, forwards and backwards, and opens around a book.
     */
    public function test_stretches(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $this->gen->create_library();
        $ids = [];
        for ($i = 1; $i <= stacks::PAGE + 5; $i++) {
            $ids[] = $this->book($library, 'Libro ' . $i, ['82', 'AAA', sprintf('%03d', $i)]);
        }
        $first = stacks::page($library, []);
        $this->assertCount(stacks::PAGE, $first['spines']);
        $this->assertSame('', $first['prev']);
        $this->assertNotSame('', $first['next']);
        $second = stacks::page($library, [], $first['next']);
        $this->assertSame(array_slice($ids, stacks::PAGE), array_column($second['spines'], 'id'));
        $this->assertSame('', $second['next']);
        $this->assertNotSame('', $second['prev']);
        $back = stacks::page($library, [], '', $second['prev']);
        $this->assertSame(array_slice($ids, 0, stacks::PAGE), array_column($back['spines'], 'id'));
        $this->assertSame('', $back['prev']);

        $around = stacks::page($library, [], '', '', $ids[40]);
        $this->assertSame($ids[34], $around['spines'][0]['id']);
        $this->assertContains($ids[40], array_column($around['spines'], 'id'));
        $this->assertNotSame('', $around['prev']);
    }

    /**
     * The books beside one are the ones before and after it in its place; not of another place.
     */
    public function test_neighbours(): void {
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $this->gen->create_library();
        $red = $this->place((int) $library->entityid, 'Rojo');
        $blue = $this->place((int) $library->entityid, 'Azul');
        $ids = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'] as $letter) {
            $ids[$letter] = $this->book($library, 'Libro ' . $letter, ['82', 'AAA', $letter . 'aa'], $red);
        }
        $this->book($library, 'Libro dd', ['82', 'AAA', 'daa'], $blue);
        $beside = stacks::neighbours($library, $ids['d']);
        $this->assertSame($red, $beside['place']);
        $this->assertSame(
            [$ids['a'], $ids['b'], $ids['c'], $ids['d'], $ids['e'], $ids['f'], $ids['g']],
            array_column($beside['spines'], 'id')
        );
        $this->assertSame([false, false, false, true, false, false, false], array_column($beside['spines'], 'here'));
        $this->assertCount(4, stacks::neighbours($library, $ids['a'])['spines']);
        $other = $this->gen->create_library(['entityid' => $library->entityid]);
        $this->assertNull(stacks::neighbours($other, $ids['a']));
    }

    /**
     * Copies keep their place in the order: filled when they come without it, changed when their label changes.
     */
    public function test_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $this->gen->create_library();
        $one = $this->book($library, 'Uno', ['82', 'AAA', 'uno']);
        $this->assertNull($DB->get_field(self::T . 'item', 'shelfkey', ['recordid' => $one]));
        stacks::fill((int) $library->id);
        $key = $DB->get_field(self::T . 'item', 'shelfkey', ['recordid' => $one]);
        $this->assertMatchesRegularExpression('/^[0-9a-z]+$/', $key);
        $this->assertSame(0, stacks::refresh_keys((int) $library->entityid));
        $DB->set_field(self::T . 'item', 'callnumber1', '9', ['recordid' => $one]);
        $this->assertSame(1, stacks::refresh_keys((int) $library->entityid));
        $this->assertNotSame($key, $DB->get_field(self::T . 'item', 'shelfkey', ['recordid' => $one]));
    }

    /**
     * A cover's main colour is its strongest hue, not the grey around it.
     */
    public function test_dominant(): void {
        $image = imagecreatetruecolor(40, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 140, 140, 140));
        imagefilledrectangle($image, 8, 10, 32, 50, imagecolorallocate($image, 200, 40, 40));
        ob_start();
        imagepng($image);
        [$r, $g, $b] = stacks::dominant((string) ob_get_clean());
        $this->assertGreaterThan(150, $r);
        $this->assertLessThan(90, $g);
        $this->assertLessThan(90, $b);
        $this->assertNull(stacks::dominant('not a picture'));
    }
}
