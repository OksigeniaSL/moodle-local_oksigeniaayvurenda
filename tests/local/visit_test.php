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
 * Tests for the library one visits.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\visit
 */
#[\PHPUnit\Framework\Attributes\CoversClass(visit::class)]
final class visit_test extends \advanced_testcase {
    /**
     * Made-up scenes in a folder like the art package's: a hall with every kind of place (and some that are wrong), a
     * room of the corner 8, and a scene without its layers.
     *
     * @return array dir and url, as visit takes them.
     */
    private function art(): array {
        $dir = make_request_directory();
        mkdir($dir . '/pix/visita', 0777, true);
        mkdir($dir . '/sonido/visita', 0777, true);
        foreach (['vestibulo-fondo', 'vestibulo-fondo-noche', 'vestibulo-medio', 'vestibulo-frente', 'literatura-fondo'] as $file) {
            file_put_contents($dir . '/pix/visita/' . $file . '.webp', 'made-up');
        }
        file_put_contents($dir . '/sonido/visita/vestibulo.mp3', 'made-up');
        $box = fn($x, $y) => [[$x, $y], [$x + 200, $y], [$x + 200, $y + 400], [$x, $y + 400]];
        file_put_contents($dir . '/pix/visita/vestibulo.json', json_encode([
            'focus' => 1100,
            'layers' => [['name' => 'fondo', 'depth' => 0], ['name' => 'medio', 'depth' => .35],
                ['name' => 'frente', 'depth' => 1], ['name' => 'nada', 'depth' => .5]],
            'characters' => ['memory' => ['feet' => [1200, 1200], 'height' => 500, 'pose' => 'greet'],
                'herald' => ['feet' => [2000, 300], 'height' => 10]],
            'zones' => [
                ['kind' => 'exit', 'to' => '', 'shape' => $box(20, 400)],
                ['kind' => 'directory', 'quad' => [[300, 260], [700, 300], [700, 820], [300, 860]], 'rows' => 9,
                    'ink' => '#3A2A1C', 'inknight' => 'url(evil)'],
                ['kind' => 'voices', 'shape' => $box(400, 950)],
                ['kind' => 'new', 'slots' => [$box(700, 800), $box(950, 800), $box(1200, 800)]],
                ['kind' => 'room', 'to' => 'literatura', 'corner' => '8', 'shape' => $box(1500, 400)],
                ['kind' => 'pulse', 'quad' => [[1900, 300], [2280, 280], [2280, 620], [1900, 600]]],
                ['kind' => 'corner', 'corner' => '5', 'shape' => $box(2100, 600)],
                ['kind' => 'corner', 'corner' => '3', 'shape' => $box(1800, 600)],
                ['kind' => 'teleport', 'shape' => $box(0, 0)],
                ['kind' => 'corner', 'shape' => $box(0, 0)],
                ['kind' => 'pulse', 'quad' => [[0, 0], [10, 0], [20, 0], [30, 0]]],
                ['kind' => 'exit', 'shape' => [[0, 0], [9000, 0], [0, 10]]],
            ],
        ]));
        file_put_contents($dir . '/pix/visita/literatura.json', json_encode([
            'layers' => [['name' => 'fondo', 'depth' => 0]],
            'zones' => [
                ['kind' => 'stacks', 'corner' => '8', 'shape' => $box(200, 150)],
                ['kind' => 'new', 'slots' => [$box(1300, 800), $box(1500, 800), $box(1700, 800), $box(1900, 800)]],
                ['kind' => 'exit', 'to' => 'vestibulo', 'shape' => $box(2150, 420)],
            ],
        ]));
        file_put_contents($dir . '/pix/visita/roto.json', json_encode(['layers' => [['name' => 'fondo']]]));
        return ['dir' => $dir, 'url' => 'https://example.com/arte'];
    }

    /**
     * A scene is read as the art package paints it and nothing else gets in: no layer without its picture, no place
     * of a kind it does not know, no shape out of the painting, no board on three corners in a line.
     */
    public function test_scene(): void {
        $art = $this->art();
        $this->assertTrue(visit::available($art));
        $this->assertFalse(visit::available(['dir' => make_request_directory(), 'url' => '']));
        $this->assertNull(visit::scene('nada', $art));
        $this->assertNull(visit::scene('../pix/visita/vestibulo', $art));
        $this->assertNull(visit::scene('roto', $art), 'a scene without the picture of its layers');
        $hall = visit::scene('vestibulo', $art);
        $this->assertSame(['fondo', 'medio', 'frente'], array_column($hall['layers'], 'name'));
        $this->assertSame('https://example.com/arte/pix/visita/vestibulo-fondo-noche.webp', $hall['layers'][0]['night']);
        $this->assertSame($hall['layers'][1]['day'], $hall['layers'][1]['night'], 'without its night, the day');
        $this->assertSame(
            ['exit', 'directory', 'voices', 'new', 'room', 'pulse', 'corner', 'corner'],
            array_column($hall['zones'], 'kind')
        );
        $this->assertSame(['memory'], array_keys($hall['characters']), 'a herald of ten pixels stays out');
        $this->assertSame('saluda', $hall['characters']['memory']['pose'], 'a gesture of the puppet means its pose');
        $this->assertSame(1100.0, $hall['focus']);
        $this->assertSame('https://example.com/arte/sonido/visita/vestibulo.mp3', $hall['sound']);
        $this->assertSame('', $hall['door']);
        $this->assertSame('', $hall['touch']);
        $this->assertSame('8', visit::scene('literatura', $art)['corner'], 'a room is of the corner of its shelves');
    }

    /**
     * The library fills the scene: doors to its rooms or to its shelves, its corners on the board by its own names and
     * order, the books that came in last on the stand (of its corner, in a room), the blackboard, the way out. Seen
     * without an account, everything leads to the window.
     */
    public function test_page(): void {
        global $DB;
        $this->resetAfterTest();
        $art = $this->art();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $class = function (string $code) use ($DB, $library, $now): int {
            return (int) $DB->insert_record('local_oksigeniaayvurenda_authority', ['entityid' => $library->entityid,
                'kind' => 'class', 'code' => $code, 'label' => 'Class ' . $code, 'timecreated' => $now, 'timemodified' => $now]);
        };
        $classes = ['8' => $class('8'), '5' => $class('5')];
        $books = [];
        foreach ([['Cuento uno', '8', 30], ['Cuento dos', '8', 20], ['Las plantas', '5', 10]] as [$title, $code, $days]) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $title]);
            $DB->insert_record('local_oksigeniaayvurenda_recauth', ['recordid' => $record->id,
                'authorityid' => $classes[$code], 'sortorder' => 0]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'timeacquired' => $now - $days * DAYSECS,
                'callnumber1' => $code, 'callnumber2' => 'AUT', 'callnumber3' => \core_text::strtolower(substr($title, 0, 3))]);
            $books[$title] = (int) $record->id;
        }
        catalogue::save_corners($library, ['8' => ['label' => 'Cuentos', 'order' => 1], '5' => ['label' => '', 'order' => 2]]);
        $this->assertSame(['Cuento dos', 'Cuento uno'], array_column(catalogue::recent($library, 12, '8'), 'title'));

        $page = visit::page($library, 'vestibulo', false, $art);
        $this->assertSame(get_string('visithall', 'local_oksigeniaayvurenda'), $page['title']);
        $zones = array_column($page['zones'], null, 'kind');
        $this->assertSame(
            ['exit', 'voices', 'room', 'corner'],
            array_column($page['zones'], 'kind'),
            'the door of a corner the library does not have is not a door'
        );
        $this->assertStringContainsString('/local/oksigeniaayvurenda/index.php?library=' . $library->slug, $zones['exit']['url']);
        $this->assertSame(get_string('visitexit', 'local_oksigeniaayvurenda'), $zones['exit']['label']);
        $this->assertStringContainsString('visit.php?library=' . $library->slug . '&room=literatura', $zones['room']['url']);
        $this->assertSame('Cuentos', $zones['room']['label']);
        $this->assertTrue($zones['room']['enters']);
        $this->assertStringContainsString('stacks.php?library=' . $library->slug . '&corner=5', $zones['corner']['url']);
        $this->assertFalse($zones['corner']['enters']);
        $this->assertStringContainsString('ayvurande.php', $zones['voices']['url']);
        $this->assertSame('400,950 600,950 600,1350 400,1350', $zones['voices']['points']);
        $this->assertSame([500.0, 950.0, 1150.0], [$zones['voices']['tipx'], $zones['voices']['tipy'], $zones['voices']['cy']]);
        $board = array_values(array_filter($page['surfaces'], fn($s) => $s['directory']))[0];
        $this->assertSame(['Cuentos', get_string('udc5', 'local_oksigeniaayvurenda')], array_column($board['rows'], 'label'));
        $this->assertStringContainsString('room=literatura', $board['rows'][0]['url']);
        $this->assertStringContainsString('corner=5', $board['rows'][1]['url']);
        $this->assertStringStartsWith('matrix3d(', $board['transform']);
        $this->assertSame('--visit-ink: #3A2A1C;', $board['ink'], 'only a colour gets into the style');
        $this->assertEquals(93.3, $board['rowheight'], 'two corners on a board of nine spread as six would');
        $stand = array_values(array_filter($page['surfaces'], fn($s) => $s['book']));
        $this->assertSame(['Las plantas', 'Cuento dos', 'Cuento uno'], array_column($stand, 'title'));
        $this->assertStringContainsString(
            'record.php?library=' . $library->slug . '&id=' . $books['Las plantas'],
            $stand[0]['url']
        );
        $chalk = array_values(array_filter($page['surfaces'], fn($s) => $s['pulse']))[0];
        $this->assertNotEmpty($chalk['lines']);
        if (screens::art('personajes/memoria-saluda')) {
            $this->assertSame(['memory'], array_column($page['characters'], 'role'));
            $this->assertSame(950.0, (float) $page['characters'][0]['left']);
            $this->assertStringContainsString('search.php', $page['characters'][0]['search']['action']);
        } else {
            $this->assertSame([], $page['characters'], 'without Gaudí\'s drawings, nobody stands in the room');
        }
        $this->assertSame(
            [$page['back'][0]['day'], $page['back'][1]['day']],
            ['https://example.com/arte/pix/visita/vestibulo-fondo.webp', 'https://example.com/arte/pix/visita/vestibulo-medio.webp']
        );
        $this->assertSame(
            'https://example.com/arte/pix/visita/vestibulo-frente.webp',
            $page['front'][0]['day'],
            'the front goes over the characters'
        );
        $this->assertSame(.35, $page['depth']);

        $room = visit::page($library, 'literatura', false, $art);
        $this->assertSame('Cuentos', $room['title']);
        $this->assertSame(['stacks', 'exit'], array_column($room['zones'], 'kind'));
        $this->assertStringContainsString('corner=8', $room['zones'][0]['url']);
        $this->assertSame(get_string('visitbackhall', 'local_oksigeniaayvurenda'), $room['zones'][1]['label']);
        $this->assertStringContainsString('visit.php?library=' . $library->slug, $room['zones'][1]['url']);
        $this->assertTrue($room['zones'][1]['enters']);
        $this->assertSame(['Cuento dos', 'Cuento uno'], array_column($room['surfaces'], 'title'), 'only books of its corner');
        $this->assertContains($room['hunt']['target'], [$books['Cuento uno'], $books['Cuento dos']], 'the room hides a book');
        $this->assertStringContainsString('room=literatura&hunt=1#busca', $room['hunt']['againurl']);
        $this->assertNull($page['hunt'], 'the hall has no corner to hide a book in');
        $this->assertSame(
            ['Las plantas', 'Cuento dos', 'Cuento uno'],
            array_column($page['sort']['books'], 'title'),
            'the herald brings the new books to the hall'
        );
        $this->assertSame(['8', '5'], array_column($page['sort']['doors'], 'code'));
        $this->assertStringContainsString('visit.php?library=' . $library->slug . '&sort=1#coloca', $page['sort']['againurl']);
        $this->assertNull($room['sort']);

        // Without an account: to the window, and the voices only if it shows them.
        window::save($library, ['open' => 1, 'voices' => 0, 'catalogue' => 1]);
        $public = visit::page($library, 'vestibulo', true, $art);
        $zones = array_column($public['zones'], null, 'kind');
        $this->assertArrayNotHasKey('voices', $zones);
        $this->assertStringContainsString('public.php?library=' . $library->slug, $zones['exit']['url']);
        $stand = array_values(array_filter($public['surfaces'], fn($s) => $s['book']));
        $this->assertStringContainsString('public.php?library=' . $library->slug . '&record=', $stand[0]['url']);
        window::save($library, ['open' => 1, 'voices' => 1, 'catalogue' => 1]);
        $this->assertArrayHasKey('voices', array_column(visit::page($library, 'vestibulo', true, $art)['zones'], null, 'kind'));
    }

    /**
     * The way in, on the home always and at the door only when anyone may walk the catalogue; none without a hall.
     */
    public function test_card(): void {
        $this->resetAfterTest();
        $art = $this->art();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $card = visit::card($library, false, $art);
        $this->assertStringContainsString('visit.php?library=' . $library->slug, $card['url']);
        $this->assertSame('https://example.com/arte/pix/visita/vestibulo-fondo-noche.webp', $card['night']);
        $this->assertEquals(46, $card['position']);
        $this->assertNull(visit::card($library, true, $art));
        window::save($library, ['open' => 1, 'catalogue' => 1]);
        $this->assertNotNull(visit::card($library, true, $art));
        $this->assertNull(visit::card($library, false, ['dir' => make_request_directory(), 'url' => '']));
    }
}
