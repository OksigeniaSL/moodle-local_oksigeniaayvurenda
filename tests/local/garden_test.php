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
 * Tests for the classic look of the card.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\garden
 */
#[\PHPUnit\Framework\Attributes\CoversClass(garden::class)]
final class garden_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Without counts: when they last came, the corners by name, what they said of a book, what they finished and the
     * series they have half read; and what it says before anything happened.
     */
    public function test_classic(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = $gen->create_year(['entityid' => $entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $reader = $gen->create_reader(['entityid' => $entityid]);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $this->assertSame(['back' => $str('cardnovisit'), 'explore' => $str('cardnocorner'), 'tell' => $str('cardnotold'),
            'stay' => $str('cardnofinished')], garden::classic($reader, $year, $now));

        $authority = fn(string $kind, string $code, string $label) => (int) $DB->insert_record(self::T . 'authority', [
            'entityid' => $entityid, 'kind' => $kind, 'code' => $code, 'label' => $label, 'timecreated' => $now,
            'timemodified' => $now]);
        $poetry = $authority('class', '8', 'Poesía y novela');
        $series = $authority('collection', '', 'Los misterios del faro');
        $book = function (string $title, int $class, ?int $collection) use ($gen, $entityid, $DB, $library) {
            $record = $gen->create_record(['entityid' => $entityid, 'title' => $title]);
            foreach (array_filter([$class, $collection]) as $sort => $authorityid) {
                $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $authorityid,
                    'sortorder' => $sort]);
            }
            return $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        };
        $one = $book('El faro dormido', $poetry, $series);
        $book('El faro despierto', $poetry, $series);
        $gen->create_loan(['libraryid' => $library->id, 'itemid' => $one->id, 'readerid' => $reader->id,
            'yearid' => $year->id, 'timeloaned' => $now - 3 * DAYSECS, 'timereturned' => $now - DAYSECS,
            'returnnote' => 'loved']);
        $classic = garden::classic($reader, $year, $now);
        $this->assertSame($str('cardlastvisit', $str('cardvisityesterday')), $classic['back']);
        $this->assertSame('Poesía y novela', $classic['explore']);
        $this->assertSame(
            $str('cardtold', (object) ['title' => 'El faro dormido', 'note' => $str('cardnoteloved')]),
            $classic['tell']
        );
        $this->assertSame('«El faro dormido». ' . $str('cardseries', 'Los misterios del faro'), $classic['stay']);
        $this->assertStringNotContainsString('3', implode(' ', $classic), 'no counts');
    }
}
