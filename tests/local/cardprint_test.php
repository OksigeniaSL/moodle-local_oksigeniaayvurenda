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
 * Tests for reader cards in print.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\cardprint
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cardprint::class)]
final class cardprint_test extends \advanced_testcase {
    /**
     * A centre with two classes of two stages, and a teacher.
     *
     * @return \stdClass
     */
    private function world(): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $w = new \stdClass();
        $w->library = $gen->create_library();
        $w->entityid = (int) $w->library->entityid;
        $w->now = time();
        $year = $gen->create_year(['entityid' => $w->entityid] + \local_oksigeniaayvurenda\core\school_year::around(
            $w->now,
            '09-01',
            \core_date::get_server_timezone_object()
        ));
        $reader = function (string $first, string $group, string $stage) use ($gen, $w, $year) {
            $reader = $gen->create_reader(['entityid' => $w->entityid, 'firstname' => $first, 'lastname' => 'Zeta']);
            $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => $group, 'stage' => $stage]);
            return $reader;
        };
        $w->ana = $reader('Ana', '1º A', 'Primaria');
        $w->leo = $reader('Leo', '1º A', 'Primaria');
        $w->iker = $reader('Iker', '3º A', 'Secundaria');
        $w->paula = $gen->create_reader(['entityid' => $w->entityid, 'firstname' => 'Paula', 'lastname' => 'Zeta',
            'readertype' => 'teacher']);
        return $w;
    }

    /**
     * Everyone, a stage, a class or one reader; and in another centre, nobody of this one.
     */
    public function test_people(): void {
        $this->resetAfterTest();
        $w = $this->world();
        $ids = fn(array $scope) => array_map(fn($row) => (int) $row->id, cardprint::people($w->library, $scope, $w->now));
        // A student without a class is not everyone's: they are asked for by name.
        $gone = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_reader([
            'entityid' => $w->entityid, 'firstname' => 'Raúl', 'lastname' => 'Zeta']);
        $this->assertCount(4, $ids(['who' => 'all']));
        $this->assertSame([(int) $gone->id], $ids(['who' => 'reader', 'reader' => $gone->id]));
        $this->assertSame([(int) $w->paula->id], $ids(['who' => 'type', 'type' => 'teacher']));
        $this->assertEqualsCanonicalizing(
            [(int) $w->ana->id, (int) $w->leo->id],
            $ids(['who' => 'stage', 'stage' => 'Primaria'])
        );
        $this->assertSame([(int) $w->iker->id], $ids(['who' => 'group', 'group' => '3º A']));
        $this->assertSame([(int) $w->paula->id], $ids(['who' => 'reader', 'reader' => $w->paula->id]));
        $other = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library(['slug' => 'lejos']);
        $this->assertSame([], cardprint::people($other, ['who' => 'reader', 'reader' => $w->ana->id], $w->now));
        $this->assertSame(['Primaria' => 2, 'Secundaria' => 1], array_intersect_key(
            cardprint::stages($w->library, $w->now),
            ['Primaria' => 1, 'Secundaria' => 1]
        ));
    }

    /**
     * The design follows the look of each reader, and only the school and the secondary school draw.
     */
    public function test_variant(): void {
        $this->assertSame('flor', cardprint::variant('school', 'flower'));
        $this->assertSame('cielo', cardprint::variant('secondary', 'sky'));
        $this->assertSame('cielo', cardprint::variant('school', 'skysober'));
        $this->assertSame('sobria', cardprint::variant('school', 'classic'));
        $this->assertSame('sobria', cardprint::variant('school', 'none'));
        $this->assertSame('sobria', cardprint::variant('university', 'flower'));
        $this->assertSame('sobria', cardprint::variant('public', 'sky'));
    }

    /**
     * Where a card falls on a sheet, with the margins of the centre; plastic is a page of its size; a printer has no sheet.
     */
    public function test_sheets(): void {
        $this->resetAfterTest();
        $w = $this->world();
        $this->assertNull(cardprint::sheet('press', $w->entityid));
        $this->assertTrue(cardprint::sheet('plain', $w->entityid)['cuts']);
        cardprint::save($w->entityid, ['left' => 15.5, 'gap' => 10, 'pwidth' => 85, 'nothing' => 1]);
        $sheet = cardprint::sheet('sheet', $w->entityid);
        $this->assertSame([15.5 + 85 + 10, 13.5 + 54 * 2], cardpdf::position($sheet, 1, 2));
        $this->assertSame(85.0, cardprint::sheet('plastic', $w->entityid)['width']);
        $this->assertArrayNotHasKey('nothing', cardprint::settings($w->entityid));
    }

    /**
     * What is made goes after its days; what was made today stays. With the design there, a PDF of each format.
     */
    public function test_make(): void {
        $this->resetAfterTest();
        $w = $this->world();
        $fs = get_file_storage();
        $base = ['contextid' => \context_system::instance()->id, 'component' => 'local_oksigeniaayvurenda',
            'filearea' => cardprint::AREA, 'itemid' => $w->entityid, 'filepath' => '/'];
        $fs->create_file_from_string($base + ['filename' => 'old.pdf', 'timecreated' => $w->now - 8 * DAYSECS], 'x');
        $fs->create_file_from_string($base + ['filename' => 'new.pdf', 'timecreated' => $w->now], 'x');
        $this->assertSame(1, cardprint::sweep($w->now));
        $this->assertSame(['new.pdf'], array_map(fn($f) => $f->get_filename(), array_values(cardprint::files($w->entityid))));
        if (!cardprint::ready()) {
            $this->markTestSkipped('The art package is not here.');
        }
        foreach (cardprint::FORMATS as $format) {
            $file = cardprint::make($w->library, ['who' => 'all'], $format, 'prueba', 2, $w->now);
            $this->assertStringStartsWith('%PDF', $file->get_content());
            $file->delete();
        }
    }
}
