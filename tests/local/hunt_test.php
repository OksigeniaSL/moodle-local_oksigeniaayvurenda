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
 * Tests for the game of the hidden book.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\hunt
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hunt::class)]
final class hunt_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The book hidden is one of the corner that is free to take home, with its label: never one out, one only to read
     * in the library, one of another corner. The same all day for the same turn; better one the reader has not taken
     * home. A corner with nothing to hide has no game.
     */
    public function test_target(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $place = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'location', 'code' => '',
            'label' => 'Rojo', 'timecreated' => $now, 'timemodified' => $now]);
        $classes = [];
        foreach (['8', '5'] as $code) {
            $classes[$code] = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'class',
                'code' => $code, 'label' => 'Class ' . $code, 'timecreated' => $now, 'timemodified' => $now]);
        }
        $books = [];
        foreach (
            [
                ['Las brujas', '8', ['82', 'DAH', 'bru'], 'available', 'normal'],
                ['El faro', '8', ['82-3', 'MON', 'far'], 'available', 'normal'],
                ['Marianela', '8', ['821', 'ALC', 'mar'], 'onloan', 'normal'],
                ['Diccionario', '8', ['81', 'DIC', 'dic'], 'available', 'reference'],
                ['El agua', '5', ['5', 'LAU', 'agu'], 'available', 'normal'],
            ] as [$title, $code, $call, $status, $type]
        ) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => $title, 'extent' => '96 p. ; 22 cm']);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $classes[$code],
                'sortorder' => 0]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => $call[0],
                'callnumber2' => $call[1], 'callnumber3' => $call[2], 'locationid' => $place, 'status' => $status,
                'itemtype' => $type]);
            $books[$title] = ['record' => (int) $record->id, 'item' => (int) $item->id];
        }
        $free = [$books['Las brujas']['record'], $books['El faro']['record']];

        $seen = [];
        for ($turn = 0; $turn < 12; $turn++) {
            $id = hunt::target($library, '8', 0, $turn, $now);
            $this->assertContains($id, $free);
            $this->assertSame($id, hunt::target($library, '8', 0, $turn, $now), 'the same all day');
            $seen[$id] = true;
        }
        $this->assertCount(2, $seen, 'another turn, another book');
        $this->assertSame($books['El agua']['record'], hunt::target($library, '5', 0, 0, $now));
        $this->assertNull(hunt::target($library, '3', 0, 0, $now));
        $this->assertNull(hunt::target($library, 'x', 0, 0, $now));

        // A reader who took one home gets the other; once they took both, any.
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $books['Las brujas']['item'], 'readerid' => $reader->id,
            'timeloaned' => $now - 20 * DAYSECS, 'timereturned' => $now - 5 * DAYSECS]);
        for ($turn = 0; $turn < 6; $turn++) {
            $this->assertSame($books['El faro']['record'], hunt::target($library, '8', (int) $reader->id, $turn, $now));
        }
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $books['El faro']['item'], 'readerid' => $reader->id,
            'timeloaned' => $now - 4 * DAYSECS, 'timereturned' => $now - DAYSECS]);
        $this->assertContains(hunt::target($library, '8', (int) $reader->id, 0, $now), $free);
    }

    /**
     * The game: the hidden book among the books really beside it, not told apart; its label as the clue; the cover only
     * for the young; the links to the window when seen without an account. A book not on a shelf here has no game.
     */
    public function test_game(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $now = time();
        $place = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'location',
            'code' => '', 'label' => 'Rojo', 'timecreated' => $now, 'timemodified' => $now]);
        $ids = [];
        foreach ([['82', 'DAH', 'bru'], ['82-3', 'MON', 'far'], ['821', 'ALC', 'mar']] as $call) {
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => implode(' ', $call)]);
            $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'callnumber1' => $call[0],
                'callnumber2' => $call[1], 'callnumber3' => $call[2], 'locationid' => $place]);
            $ids[] = (int) $record->id;
        }
        $game = hunt::game($library, $ids[1], false, false);
        $this->assertSame($ids[1], $game['target']);
        $this->assertSame($ids, array_column($game['spines'], 'id'));
        $this->assertSame([false, false, false], array_column($game['spines'], 'here'), 'the hidden one is not marked');
        $this->assertSame(['82-3', 'MON', 'far'], array_column($game['lines'], 'line'));
        $this->assertSame('Rojo', $game['place']);
        $this->assertNull($game['cover']);
        $this->assertFalse($game['young']);
        $this->assertNotEmpty($game['spines'][0]['label'], 'what a screen reader hears of each spine');
        $this->assertStringContainsString('record.php', $game['spines'][0]['url']);
        $public = hunt::game($library, $ids[1], true, true);
        $this->assertTrue($public['young']);
        $this->assertStringContainsString('public.php?library=' . $library->slug . '&record=', $public['spines'][0]['url']);
        $this->assertNull(hunt::game($library, $gen->create_record(['entityid' => $library->entityid])->id, false, false));
    }
}
