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
 * Tests for the public window and its OPDS catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\window
 * @covers     \local_oksigeniaayvurenda\local\opds
 */
#[\PHPUnit\Framework\Attributes\CoversClass(window::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(opds::class)]
final class window_test extends \advanced_testcase {
    /**
     * Closed until the centre opens it. Open, it shows only what is meant for the public and licensed for it, the
     * public voices if the centre wants, and searches by title; its catalogue lists the same, with what can be
     * downloaded as open access.
     */
    public function test_window(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $faro = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El faro dormido', 'lang' => 'spa',
            'searchtext' => 'el faro dormido']);
        $mar = $gen->create_record(['entityid' => $library->entityid, 'title' => 'La sal del mar',
            'searchtext' => 'la sal del mar']);
        $path = make_request_directory() . '/libro.epub';
        file_put_contents($path, 'made-up book');
        $open = digital::add_file($library, (int) $faro->id, $path, 'faro.epub', ['title' => '', 'licence' => 'pd',
            'audience' => ['students', 'public'], 'download' => 1], 0);
        $students = digital::add_file($library, (int) $mar->id, $path, 'mar.epub', ['title' => '', 'licence' => 'cc-by',
            'audience' => ['students']], 0);
        $rights = digital::add_file($library, (int) $mar->id, $path, 'mar2.epub', ['title' => '', 'licence' => 'rights',
            'audience' => ['public']], 0);
        $voice = voices::record($library, (int) $mar->id, 0, 0, $path, 'Capítulo 1');
        voices::approve($voice, $library, ['licence' => 'pd', 'audience' => ['public']]);
        $get = fn(int $id) => digital::get($id, $library);

        $this->assertFalse(window::settings($library)['open']);
        $this->assertNull(window::open($library->slug));
        $this->assertFalse(window::shows($get($open)));

        window::save($library, ['open' => 1, 'text' => 'Bienvenidos', 'voices' => 1, 'index' => 0]);
        $this->assertNotNull(window::open($library->slug));
        $this->assertTrue(window::shows($get($open)));
        $this->assertFalse(window::shows($get($students)));
        $this->assertFalse(window::shows($get($rights)), 'someone else\'s rights never go out');
        $this->assertTrue(window::shows($get($voice)));
        $this->assertTrue(window::shows_record((int) $faro->id));
        $found = window::records($library);
        $this->assertSame(2, $found['total']);
        $this->assertSame(['La sal del mar', 'El faro dormido'], array_column($found['records'], 'title'));
        $this->assertSame(['El faro dormido'], array_column(window::records($library, 'faro')['records'], 'title'));

        window::save($library, ['open' => 1, 'voices' => 0]);
        $this->assertFalse(window::shows($get($voice)));
        $this->assertSame(['El faro dormido'], array_column(window::records($library)['records'], 'title'));

        $feed = new \DOMDocument();
        $this->assertTrue($feed->loadXML(opds::feed($library)));
        $xpath = new \DOMXPath($feed);
        $xpath->registerNamespace('a', 'http://www.w3.org/2005/Atom');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/terms/');
        $this->assertSame(1, $xpath->query('//a:entry')->length);
        $this->assertSame('El faro dormido', $xpath->evaluate('string(//a:entry/a:title)'));
        $this->assertSame('es', $xpath->evaluate('string(//a:entry/dc:language)'));
        $this->assertSame('application/epub+zip', $xpath->evaluate(
            'string(//a:entry/a:link[@rel="http://opds-spec.org/acquisition/open-access"]/@type)'
        ));
        $this->assertSame(1, $xpath->query('//a:feed/a:link[@rel="search"]')->length);
        $search = new \DOMDocument();
        $this->assertTrue($search->loadXML(opds::search($library)));
        $this->assertStringContainsString('{searchTerms}', $search->saveXML());

        window::save($library, ['open' => 0]);
        $this->assertFalse(window::shows($get($open)));
        $this->assertFalse(window::shows_record((int) $faro->id));
    }

    /**
     * The name a reader shows for the search: at most sixteen characters, cut between words, nothing hanging.
     */
    public function test_short_name(): void {
        $this->assertSame('Biblioteca', opds::short_name('Biblioteca de demostración'));
        $this->assertSame('Colegio Alfa', opds::short_name(' Colegio Alfa '));
        $this->assertSame('Colegio', opds::short_name('Colegio Inventado'));
        $this->assertSame('Biblioteca Alfa', opds::short_name('Biblioteca Alfa del barrio norte'));
        $this->assertSame('Supercalifragilí', opds::short_name('Supercalifragilístico'));
    }
}
