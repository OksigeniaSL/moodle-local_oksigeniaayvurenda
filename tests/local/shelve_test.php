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
 * Tests for the herald's shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\shelve
 */
#[\PHPUnit\Framework\Attributes\CoversClass(shelve::class)]
final class shelve_test extends \advanced_testcase {
    /**
     * The herald brings the newest books of the corners the library shows, four a turn and around again, each with its
     * corner as the library names it, what its class says and its label; never a book of a hidden corner or without a
     * class. With one corner only there is nothing to choose.
     */
    public function test_books(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $class = function (string $code, string $label) use ($DB, $entityid, $now): int {
            return (int) $DB->insert_record('local_oksigeniaayvurenda_authority', ['entityid' => $entityid, 'kind' => 'class',
                'code' => $code, 'label' => $label, 'timecreated' => $now, 'timemodified' => $now]);
        };
        $classes = ['52' => $class('52', 'Astronomía'), '821' => $class('821', '821'), '3' => $class('3', 'Sociedad')];
        $ids = [];
        foreach (
            [
                ['Las estrellas', '52', 1, ['52', 'SAG', 'est']],
                ['El faro', '821', 2, ['821', 'MON', 'far']],
                ['Los oficios', '3', 3, ['3', 'PER', 'ofi']],
                ['El cometa', '52', 4, ['52', 'HAL', 'com']],
                ['La playa', '821', 5, []],
                ['Sin clase', '', 6, ['N', 'XXX', 'sin']],
            ] as [$title, $code, $days, $call]
        ) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => $title]);
            if ($code !== '') {
                $DB->insert_record('local_oksigeniaayvurenda_recauth', ['recordid' => $record->id,
                    'authorityid' => $classes[$code], 'sortorder' => 0]);
            }
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'timeacquired' => $now - $days * DAYSECS,
                'callnumber1' => $call[0] ?? null, 'callnumber2' => $call[1] ?? null, 'callnumber3' => $call[2] ?? null]);
            $ids[$title] = (int) $record->id;
        }
        catalogue::save_corners($library, ['3' => ['label' => '', 'order' => 0, 'hidden' => 1]]);

        $books = shelve::books($library, 0);
        $this->assertSame(
            ['Las estrellas', 'El faro', 'El cometa', 'La playa'],
            array_column($books, 'title'),
            'the newest, never of a hidden corner nor without a class'
        );
        $this->assertSame(['5', '8', '5', '8'], array_column($books, 'corner'));
        $this->assertSame(get_string('udc5', 'local_oksigeniaayvurenda'), $books[0]['cornerlabel']);
        $this->assertSame('Astronomía', $books[0]['topic']);
        $this->assertSame('', $books[1]['topic'], 'a class that only says its number says no more');
        $this->assertSame(['52', 'SAG', 'est'], array_column($books[0]['lines'], 'line'));
        $this->assertSame(['821'], array_column($books[3]['lines'], 'line'), 'without a label, its class');
        $this->assertNull($books[0]['cover']);
        $this->assertSame(
            ['Las estrellas', 'El faro', 'El cometa', 'La playa'],
            array_column(shelve::books($library, 1), 'title'),
            'four of four: around again'
        );

        catalogue::save_corners($library, ['3' => ['hidden' => 1], '8' => ['hidden' => 1]]);
        $this->assertSame([], shelve::books($library, 0), 'one corner only is no choice');
    }
}
