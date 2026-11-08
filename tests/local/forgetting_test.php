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
 * Tests for the loans of long ago, which stop saying who took them.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\forgetting
 */
#[\PHPUnit\Framework\Attributes\CoversClass(forgetting::class)]
final class forgetting_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * Two school years kept with the reader; before that, each reader's year goes to a stand-in of its own, with the same
     * group and stage, so the year's figures stay; what the reader won stays with them, the notes and what was asked go.
     */
    public function test_two_years(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        // This year and the three before it.
        $years = [years::around($entityid, $now)];
        for ($i = 1; $i <= 3; $i++) {
            $years[$i] = years::around($entityid, (int) $years[$i - 1]->timestart - 1);
        }
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $class = $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'class', 'code' => '821',
            'label' => '821', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $class, 'sortorder' => 0]);
        $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
        $ana = $gen->create_reader(['entityid' => $entityid]);
        $leo = $gen->create_reader(['entityid' => $entityid]);
        foreach ($years as $i => $year) {
            foreach ([$ana, $leo] as $reader) {
                $gen->create_member(['readerid' => $reader->id, 'yearid' => $year->id, 'groupname' => (4 - $i) . '.º A',
                    'stage' => 'Primaria']);
                $when = (int) $year->timestart + 30 * DAYSECS;
                $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id,
                    'yearid' => $year->id, 'groupname' => (4 - $i) . '.º A', 'timeloaned' => $when,
                    'timedue' => $when + WEEKSECS, 'timereturned' => $when + DAYSECS, 'returnnote' => 'loved']);
            }
        }
        $old = (int) $years[3]->timestart + 30 * DAYSECS;
        $DB->insert_record(self::T . 'hold', ['libraryid' => $library->id, 'recordid' => $record->id, 'readerid' => $ana->id,
            'status' => 'done', 'timecreated' => $old, 'timemodified' => $old]);
        $before = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT readerid) FROM {' . self::T . 'loan} WHERE yearid = ?',
            [$years[3]->id]
        );

        $this->assertSame(4, forgetting::entity($entityid, $now), 'two readers, two old years each');
        // Ana keeps this year's and last year's loans; the two before are not hers any more.
        $this->assertEqualsCanonicalizing(
            [(int) $years[0]->id, (int) $years[1]->id],
            array_map('intval', $DB->get_fieldset_select(self::T . 'loan', 'yearid', 'readerid = ?', [$ana->id]))
        );
        // The year's figures stay: as many readers as before, in the same group and stage.
        $this->assertSame($before, $DB->count_records_sql('SELECT COUNT(DISTINCT readerid) FROM {' . self::T . 'loan}
            WHERE yearid = ?', [$years[3]->id]));
        $standins = $DB->get_records_select(self::T . 'reader', "entityid = ? AND cardcode LIKE 'past-%'", [$entityid]);
        $this->assertCount(4, $standins);
        foreach ($standins as $standin) {
            $this->assertSame('deleted', $standin->status);
            $this->assertNull($standin->userid);
            $this->assertNull($standin->firstname);
            $this->assertSame('Primaria', $DB->get_field(self::T . 'member', 'stage', ['readerid' => $standin->id]));
        }
        $this->assertSame(0, $DB->count_records_select(
            self::T . 'loan',
            'yearid = ? AND returnnote IS NOT NULL',
            [$years[3]->id]
        ), 'the notes at the desk go');
        $this->assertFalse($DB->record_exists(self::T . 'hold', ['readerid' => $ana->id]), 'what was asked then goes');
        // What Ana won stays with her: the flowers of those years in her album, and the corner of her first book.
        $this->assertSame(2, $DB->count_records(self::T . 'keepsake', ['readerid' => $ana->id]));
        $album = garden::album($ana, $now);
        $this->assertSame(
            [$years[1]->name, $years[2]->name, $years[3]->name],
            array_column($album['past'], 'year')
        );
        $this->assertArrayHasKey('stage', $album['past'][2]);
        $this->assertSame(['8' => (int) $years[3]->timestart + 30 * DAYSECS], garden::corners_won($ana));

        // The next night, nothing more; a centre that keeps it always, nothing at all.
        $this->assertSame(0, forgetting::entity($entityid, $now));
        $other = $gen->create_library();
        libraries::update_entity((int) $other->entityid, ['historyyears' => 0]);
        $this->assertSame(0, forgetting::years((int) $other->entityid));
        $this->assertNull(forgetting::since((int) $other->entityid, $now));
        libraries::update_entity((int) $other->entityid, ['historyyears' => 2]);
        $this->assertSame(forgetting::KEEP, forgetting::years((int) $other->entityid));

        // Deleting Ana takes what she won with her.
        erasure::reader((int) $ana->id, $now);
        $this->assertSame(0, $DB->count_records(self::T . 'keepsake', ['readerid' => $ana->id]));
    }
}
