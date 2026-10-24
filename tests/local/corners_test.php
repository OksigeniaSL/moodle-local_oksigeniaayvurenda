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
 * Tests for the corners a library names, orders and hides, and for narrowing a search by corner or topic.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\catalogue
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogue::class)]
final class corners_test extends \advanced_testcase {
    /**
     * Named, ordered and hidden as the library says; a name left empty goes back to the classification's. A search
     * says how many of its results are in each corner and about each shared topic, and narrows by them.
     */
    public function test_corners_and_narrowing(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $book = fn(string $title, string $class, array $subjects) => cataloguer::create_record($entityid, [
            'record' => ['title' => $title],
            'authorities' => ['class' => [$class], 'subject' => $subjects],
        ], time());
        $book('El faro dormido', '821.134.2-34', ['Mar', 'Faros']);
        $book('El faro y la ballena', '821.134.2-1', ['Mar', 'Animales']);
        $book('Cómo funciona un faro', '62', ['Faros', 'Máquinas']);

        $corners = catalogue::corners($library);
        $this->assertSame(['6', '8'], array_column($corners, 'code'));
        catalogue::save_corners($library, ['8' => ['label' => 'Cuentos y poemas', 'order' => 1], '6' => ['label' => '',
            'order' => 2, 'hidden' => true]]);
        $this->assertSame(['Cuentos y poemas'], array_column(catalogue::corners($library), 'label'));
        $all = catalogue::corners($library, true);
        $this->assertSame(['8', '6'], array_column($all, 'code'));
        $this->assertSame($all[1]['default'], $all[1]['label'], 'an empty name goes back to the classification\'s');

        $found = catalogue::search($library, 'faro');
        $this->assertSame(3, $found['total']);
        $this->assertSame(['6' => 1, '8' => 2], $found['facets']['corners']);
        $topics = array_column($found['facets']['subjects'], 'count', 'label');
        $this->assertSame(2, $topics['Mar']);
        $this->assertSame(2, $topics['Faros']);
        $this->assertSame(2, catalogue::search($library, 'faro', 0, false, '8')['total']);
        $mar = array_search('Mar', array_column($found['facets']['subjects'], 'label', null), true);
        $this->assertNotFalse($mar);
        $subjectid = array_keys($found['facets']['subjects'])[$mar];
        $this->assertSame(1, catalogue::search($library, 'faro', 0, false, '6', 0)['total']);
        $this->assertSame(2, catalogue::search($library, 'faro', 0, false, '', (int) $subjectid)['total']);
    }
}
