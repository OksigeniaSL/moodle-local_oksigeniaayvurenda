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
 * Tests for the tales to read at home, by age, at a library's open door.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\window
 * @covers     \local_oksigeniaayvurenda\api
 */
#[\PHPUnit\Framework\Attributes\CoversClass(window::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_oksigeniaayvurenda\api::class)]
final class tales_test extends \advanced_testcase {
    /**
     * An age falls in its band; a public tale marked for some ages shows for a child of those ages and not of others;
     * one not for the public keeps no ages; and the door offers only the ages that have something.
     */
    public function test_tales_by_age(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $admin = (int) get_admin()->id;
        $this->assertSame(0, window::band(1));
        $this->assertSame(3, window::band(4));
        $this->assertSame(6, window::band(8));
        $this->assertSame(9, window::band(14));
        $this->assertSame('3,6', window::ages_of(['6', '3', '15']));
        $this->assertNull(window::ages_of([]));

        $moon = $gen->create_record(['entityid' => $library->entityid, 'title' => 'La luna que no quería dormir']);
        $sea = $gen->create_record(['entityid' => $library->entityid, 'title' => 'El mar de los mayores']);
        $kept = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Solo para el colegio']);
        digital::add_link($library, (int) $moon->id, 'https://example.org/luna.epub', ['licence' => 'cc-by',
            'audience' => ['public'], 'homeages' => [0, 3]], $admin);
        digital::add_link($library, (int) $sea->id, 'https://example.org/mar.epub', ['licence' => 'cc-by',
            'audience' => ['public'], 'homeages' => [9]], $admin);
        $closed = digital::add_link($library, (int) $kept->id, 'https://example.org/colegio.pdf', ['licence' => 'rights',
            'audience' => ['students'], 'homeages' => [3]], $admin);
        $this->assertNull(digital::get($closed, $library)->homeages, 'not for the public: not to read at home either');

        // What whoever looked at it wrote: minutes, pages, a line for each age it suits (not for others), bedtime.
        $faro = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Buenas noches, faro']);
        $tale = digital::add_link($library, (int) $faro->id, 'https://example.org/faro.epub', ['licence' => 'cc-by',
            'audience' => ['public'], 'homeages' => [3], 'homeminutes' => 4, 'homeparts' => 12, 'homenight' => 1,
            'homelines' => [3 => 'Corto y tranquilo.', 6 => 'No es de esta edad.'], 'homeorder' => 2], $admin);
        $row = digital::get($tale, $library);
        $this->assertSame(4, (int) $row->homeminutes);
        $this->assertSame(12, (int) $row->homeparts);
        $this->assertSame(1, (int) $row->homenight);
        $this->assertSame(2, (int) $row->homeorder);
        $this->assertSame(['3' => 'Corto y tranquilo.'], json_decode($row->homelines, true));
        $kept = digital::get($closed, $library);
        $this->assertNull($kept->homeminutes);
        $this->assertSame(0, (int) $kept->homenight);
        window::save($library, ['open' => 1]);

        $titles = fn(int $age) => array_column(window::records($library, '', 0, $age)['records'], 'title');
        $this->assertSame(['Buenas noches, faro', 'La luna que no quería dormir'], $titles(4));
        $this->assertSame(['La luna que no quería dormir'], $titles(2));
        $this->assertSame([], $titles(7));
        $this->assertSame(['El mar de los mayores'], $titles(11));
        $this->assertSame([0, 3, 9], window::tale_ages($library));
        // The door as always shows every public tale.
        $this->assertCount(3, window::records($library)['records']);

        // What admissions asks, without reading the library's tables.
        $mine = array_values(array_filter(\local_oksigeniaayvurenda\api::libraries(), fn($l) => $l['slug'] === $library->slug));
        $this->assertTrue($mine[0]['open']);
        $this->assertTrue($mine[0]['tales']);
        $url = \local_oksigeniaayvurenda\api::tales_url($library->slug, 4);
        $this->assertSame(['library' => $library->slug, 'tales' => '1', 'age' => '4'], $url->params());
        $this->assertNull(\local_oksigeniaayvurenda\api::tales_url('no-such-library', 4));
        window::save($library, ['open' => 0]);
        $this->assertNull(\local_oksigeniaayvurenda\api::tales_url($library->slug, 4), 'never to a closed door');
    }
}
