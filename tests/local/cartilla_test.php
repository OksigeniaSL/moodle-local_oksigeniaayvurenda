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
 * Tests for «La cartilla»: a library challenge of nine squares the desk measures.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cartilla
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cartilla::class)]
final class cartilla_test extends \advanced_testcase {
    /**
     * Saved with nine squares, its goal is the full card; a new book fills «new», one nobody took for years fills
     * «rescued», two new books fill only one square; the challenge counts squares, not books.
     */
    public function test_card(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $reader = $gen->create_reader(['entityid' => $library->entityid]);
        $now = time();
        $squares = ['new', 'voice', 'rescued', 'corner:0', 'corner:1', 'corner:2', 'corner:3', 'corner:5', 'corner:6'];
        $data = ['name' => 'La cartilla', 'start' => date('Y-m-d', $now - 5 * DAYSECS), 'end' => date('Y-m-d', $now + 5 * DAYSECS),
            'squares' => $squares];
        $this->assertSame('questerrsquares', quests::save($library, ['squares' => array_slice($squares, 0, 4)] + $data, $now));
        $id = quests::save($library, $data, $now);
        $quest = $DB->get_record('local_oksigeniaayvurenda_quest', ['id' => $id]);
        $this->assertSame([9, $squares], [(int) $quest->goal, cartilla::squares($quest)]);
        $item = fn($title, $acquired) => $gen->create_item(['libraryid' => $library->id,
            'recordid' => $gen->create_record(['entityid' => $library->entityid, 'title' => $title])->id,
            'timeacquired' => $acquired]);
        $loan = fn($it, $when) => $gen->create_loan(['libraryid' => $library->id, 'itemid' => $it->id,
            'readerid' => $reader->id, 'timeloaned' => $when]);
        $loan($item('Recién llegado', $now - 10 * DAYSECS), $now - 3 * DAYSECS);
        $loan($item('Otro recién llegado', $now - 10 * DAYSECS), $now - 2 * DAYSECS);
        $loan($item('Olvidado', $now - 5 * 365 * DAYSECS), $now - DAYSECS);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $card = cartilla::card($quest, $library, (int) $reader->id, $now, $str);
        $this->assertSame([2, 0, false], [$card['filled'], $card['lines'], $card['full']]);
        $this->assertSame(['Recién llegado', '', 'Olvidado'], array_column(array_slice($card['squares'], 0, 3), 'title'));
        $this->assertSame($str('cartillanew'), $card['squares'][0]['label']);
        $this->assertSame(2, quests::stamps($quest, (int) $reader->id, $now));
    }
}
