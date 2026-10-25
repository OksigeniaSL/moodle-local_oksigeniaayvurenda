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
 * Tests for the challenges of a class.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\challenges
 */
#[\PHPUnit\Framework\Attributes\CoversClass(challenges::class)]
final class challenges_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The terms are the thirds of the school year.
     */
    public function test_terms(): void {
        $year = (object) ['timestart' => 0, 'timeend' => 300];
        $this->assertSame(1, challenges::term($year, 10));
        $this->assertSame(2, challenges::term($year, 150));
        $this->assertSame(3, challenges::term($year, 299));
        $this->assertSame(3, challenges::term($year, 1000));
        $this->assertSame([100, 200], challenges::bounds($year, 2));
    }

    /**
     * «Everyone»: three in the class, two take a book; then the third, and the challenge is met and planted once.
     * Another kind chosen: corners, by the first digit of their class. Turned off: no challenge.
     */
    public function test_progress_garden_and_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = years::around($entityid, $now);
        [$start] = challenges::bounds($year, challenges::term($year, $now));
        $when = max($start, $now - 60);
        $readers = [];
        for ($i = 0; $i < 3; $i++) {
            $readers[$i] = $generator->create_reader(['entityid' => $entityid]);
            $generator->create_member(['readerid' => $readers[$i]->id, 'yearid' => $year->id, 'groupname' => '3.º B']);
        }
        $class = function (string $code) use ($entityid, $DB) {
            return $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'class', 'code' => $code,
                'label' => $code, 'timecreated' => time(), 'timemodified' => time()]);
        };
        $book = function (string $code) use ($generator, $entityid, $library, $DB, $class) {
            $record = $generator->create_record(['entityid' => $entityid]);
            $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $class($code), 'role' => 'class',
                'sortorder' => 0]);
            return $generator->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        };
        $lend = fn($item, $reader) => $generator->create_loan(['libraryid' => $library->id, 'itemid' => $item->id,
            'readerid' => $reader->id, 'timeloaned' => $when]);
        $lend($book('821.134'), $readers[0]);
        $lend($book('82-93'), $readers[1]);

        challenges::choose($entityid, '3.º B', 'everyone', $now);
        $challenge = challenges::of($entityid, '3.º B', $now);
        $this->assertSame(
            ['everyone', 3, 2, false],
            [$challenge->kind, $challenge->target, $challenge->done, $challenge->complete]
        );
        $view = challenges::view($challenge, $entityid);
        $this->assertCount(3, $view['marks']);
        $this->assertSame([true, true, false], array_column($view['marks'], 'lit'));
        $this->assertNotSame('', $view['left']);
        $this->assertSame([], challenges::garden($entityid, '3.º B', $now));

        $lend($book('5'), $readers[2]);
        $challenge = challenges::of($entityid, '3.º B', $now);
        $this->assertTrue($challenge->complete);
        challenges::of($entityid, '3.º B', $now);
        $this->assertCount(1, challenges::garden($entityid, '3.º B', $now));
        // Met, it is kept: choosing another does not undo it.
        challenges::choose($entityid, '3.º B', 'corners', $now);
        $this->assertSame('everyone', challenges::of($entityid, '3.º B', $now)->kind);

        // Another class: corners by the first digit (8, 8 and 5: two), and off.
        $other = $generator->create_reader(['entityid' => $entityid]);
        $generator->create_member(['readerid' => $other->id, 'yearid' => $year->id, 'groupname' => '4.º A']);
        $lend($book('821'), $other);
        $lend($book('53'), $other);
        $lend($book('5'), $other);
        challenges::choose($entityid, '4.º A', 'corners', $now);
        $corners = challenges::of($entityid, '4.º A', $now);
        $this->assertSame([6, 2], [$corners->target, $corners->done]);
        challenges::choose($entityid, '4.º A', 'off', $now);
        $this->assertNull(challenges::of($entityid, '4.º A', $now));
    }

    /**
     * A centre can leave a stage without challenges, and a tutor finds the groups they tutor this year.
     */
    public function test_stages_off_and_tutors(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $generator->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $year = years::around($entityid, $now);
        foreach (['Infantil' => 'Infantil 5 A', 'Primaria' => '1.º A'] as $stage => $group) {
            $reader = $generator->create_reader(['entityid' => $entityid]);
            $generator->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => $group,
                'stage' => $stage]);
        }
        $this->assertNotNull(challenges::of($entityid, 'Infantil 5 A', $now));

        libraries::update_entity($entityid, ['challengesoff' => ['Infantil', ' ']]);
        $this->assertSame(['Infantil'], challenges::stages_off($entityid));
        $this->assertSame('Infantil', challenges::stage_off($entityid, (int) $year->id, 'Infantil 5 A'));
        $this->assertNull(challenges::of($entityid, 'Infantil 5 A', $now));
        $this->assertNull(challenges::stage_off($entityid, (int) $year->id, '1.º A'));
        $this->assertNotNull(challenges::of($entityid, '1.º A', $now));
        libraries::update_entity($entityid, ['challengesoff' => []]);
        $this->assertSame([], challenges::stages_off($entityid));
        $this->assertNotNull(challenges::of($entityid, 'Infantil 5 A', $now));

        $teacher = $this->getDataGenerator()->create_user();
        $this->assertSame([], challenges::tutored($entityid, (int) $teacher->id, $now));
        tutors::set((int) $year->id, '1.º A', (int) $teacher->id);
        $this->assertSame(['1.º A'], challenges::tutored($entityid, (int) $teacher->id, $now));
        // Last year's tutoring is not this year's, and looking never opens a year.
        $years = $DB->count_records(self::T . 'year');
        $this->assertSame([], challenges::tutored($entityid, (int) $teacher->id, (int) $year->timeend + 10 * DAYSECS));
        $this->assertSame($years, $DB->count_records(self::T . 'year'));
    }
}
