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
 * Tests for what the memory says to whoever works at the library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\memory
 * @covers     \local_oksigeniaayvurenda\local\memoir
 */
#[\PHPUnit\Framework\Attributes\CoversClass(memory::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(memoir::class)]
final class memory_staff_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Cataloguing: first, that a class asked for it; else what else there is by the author; else nothing.
     */
    public function test_cataloguing(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entity = (int) $library->entityid;
        $now = time();
        $this->assertSame('', memory::cataloguing($entity, 'Nadie lo pidió', ['Nadie, Ana']));

        $author = $DB->insert_record(self::T . 'authority', ['entityid' => $entity, 'kind' => 'author', 'label' => 'Montes, Ana',
            'timecreated' => $now, 'timemodified' => $now]);
        $record = $gen->create_record(['entityid' => $entity, 'title' => 'El faro dormido']);
        $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $author, 'sortorder' => 0]);
        $said = memory::cataloguing($entity, 'Otro de ella', ['Montes, Ana']);
        $this->assertStringContainsString('Ana Montes', $said);
        $this->assertStringNotContainsString('El faro dormido', $said);

        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $reader = $gen->create_reader(['entityid' => $entity]);
        $DB->insert_record(self::T . 'loan', ['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
            'timeloaned' => $now, 'timedue' => $now + DAYSECS, 'renewals' => 0, 'timecreated' => $now, 'timemodified' => $now]);
        $this->assertStringContainsString('El faro dormido', memory::cataloguing($entity, 'Otro de ella', ['Montes, Ana']));

        $year = years::around($entity, $now);
        $challenge = $DB->insert_record(self::T . 'challenge', ['yearid' => $year->id, 'groupname' => '3º A', 'term' => 1,
            'kind' => 'count', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record(self::T . 'wish', ['entityid' => $entity, 'challengeid' => $challenge, 'title' => ' Otro de ella ',
            'status' => 'asked', 'timecreated' => $now, 'timemodified' => $now]);
        $this->assertStringContainsString('3º A', memory::cataloguing($entity, 'otro de ELLA', ['Montes, Ana']));

        characters::save($entity, ['memory' => ['enabled' => false]]);
        $this->assertSame('', memory::cataloguing($entity, 'otro de ELLA', ['Montes, Ana']));
    }

    /**
     * The year as she tells it: from the figures, comparing with the year before, and nothing with no loans or with her off.
     */
    public function test_story(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entity = (int) $gen->create_library()->entityid;
        $report = [
            'figures' => ['loans' => 120, 'came' => 30, 'readers' => 40],
            'before' => ['loans' => 100, 'name' => '2025-2026'],
            'corners' => [['corner' => 'Novela']],
            'top' => [['title' => 'El faro dormido']],
            'life' => ['challenges' => 3, 'reviews' => 1, 'voices' => 0],
        ];
        $story = memoir::story($entity, $report);
        $text = implode(' ', $story['lines']);
        $this->assertStringContainsString('120', $text);
        $this->assertStringContainsString('2025-2026', $text);
        $this->assertStringContainsString('Novela', $text);
        $this->assertStringContainsString('El faro dormido', $text);
        // One review is not told: only what is more than one reads well.
        $this->assertCount(5, $story['lines']);
        $this->assertNull(memoir::story($entity, ['figures' => ['loans' => 0, 'came' => 0, 'readers' => 0]] + $report));
        characters::save($entity, ['memory' => ['enabled' => false]]);
        $this->assertNull(memoir::story($entity, $report));
    }

    /**
     * What the memory has to say is in a reader's summary, for the block and the app.
     */
    public function test_summary(): void {
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $user = $this->getDataGenerator()->create_user();
        $gen->create_reader(['entityid' => $library->entityid, 'userid' => $user->id]);
        $summary = mine::summary((int) $user->id);
        $this->assertArrayHasKey('remembers', $summary[0]);
        $this->assertNull($summary[0]['remembers']);
    }
}
