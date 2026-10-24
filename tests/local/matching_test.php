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

use local_oksigeniaayvurenda\core\abies\export_reader;
use local_oksigeniaayvurenda\local\import\abies_import;
use local_oksigeniaayvurenda\local\sources\cohorts;

/**
 * Tests for readers and loans from Abies and for matching them with accounts.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\matching
 * @covers     \local_oksigeniaayvurenda\local\import\abies_import
 */
#[\PHPUnit\Framework\Attributes\CoversClass(matching::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(abies_import::class)]
final class matching_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The made-up export of the fixtures.
     *
     * @return export_reader
     */
    private function export(): export_reader {
        return new export_reader(__DIR__ . '/../fixtures/abies/small.xml');
    }

    /**
     * Readers come with their cards and groups, loans out and returned in their school years; a second run changes
     * nothing.
     */
    public function test_readers_and_loans(): void {
        global $DB;
        $this->resetAfterTest();
        $library = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $stats = (new abies_import($library->id, true, true))->run($this->export());
        $this->assertSame(3, $stats['reader']['created']);
        $this->assertSame(2, $stats['loan']['created']);
        $this->assertSame(1, $stats['loan']['skipped']);
        $first = $DB->get_record(self::T . 'reader', ['cardcode' => '10000H'], '*', MUST_EXIST);
        $this->assertSame('Inventada', $first->firstname);
        $this->assertSame('Lectora Uno', $first->lastname);
        $this->assertNull($first->userid);
        $this->assertSame('teacher', $DB->get_field(self::T . 'reader', 'readertype', ['cardcode' => '10001L']));
        $this->assertSame('1 PRI A', $DB->get_field(self::T . 'member', 'groupname', ['readerid' => $first->id]));
        $open = $DB->get_record(self::T . 'loan', ['readerid' => $first->id], '*', MUST_EXIST);
        $this->assertNull($open->timereturned);
        $returned = $DB->get_record_select(self::T . 'loan', 'timereturned IS NOT NULL', [], '*', MUST_EXIST);
        $this->assertSame(1, (int) $returned->renewals);
        $this->assertGreaterThanOrEqual(2, $DB->count_records(self::T . 'year', ['entityid' => $library->entityid]));

        $again = (new abies_import($library->id, true, true))->run($this->export());
        $this->assertSame(3, $again['reader']['unchanged']);
        $this->assertSame(2, $again['loan']['unchanged']);
        $this->assertSame(3, $DB->count_records(self::T . 'reader'));
    }

    /**
     * The three piles against the cohort accounts; linking merges the reader the cohort brought, which gives way with
     * its code, and the Abies card and loans stay.
     */
    public function test_match_and_merge(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $library = $dg->get_plugin_generator('local_oksigeniaayvurenda')->create_library();
        $DB->set_field(self::T . 'entity', 'settings', json_encode(['controlletter' => true]), ['id' => $library->entityid]);
        $cohort = $dg->create_cohort(['name' => '1 PRI A']);
        $staff = $dg->create_cohort(['name' => 'Claustro']);
        $inventada = $dg->create_user(['firstname' => 'Inventada', 'lastname' => 'Lectora Uno']);
        $profe = $dg->create_user(['firstname' => 'Profe', 'lastname' => 'De Prueba Otra']);
        cohort_add_member($cohort->id, $inventada->id);
        cohort_add_member($staff->id, $profe->id);
        cohorts::save((int) $library->entityid, [
            $cohort->id => ['type' => 'student', 'stage' => 'Primaria', 'group' => true],
            $staff->id => ['type' => 'teacher', 'stage' => '', 'group' => false],
        ]);
        cohorts::sync((int) $library->entityid);
        // The cohort reader of Inventada got the code 00001; Abies brings the printed card.
        (new abies_import($library->id, true, true))->run($this->export());

        $piles = matching::piles((int) $library->entityid);
        $abiesana = (int) $DB->get_field(self::T . 'reader', 'id', ['cardcode' => '10000H']);
        $this->assertSame([$abiesana => (int) $inventada->id], $piles['sure']);
        $profeabies = (int) $DB->get_field(self::T . 'reader', 'id', ['cardcode' => '10001L']);
        $this->assertSame([(int) $profe->id], $piles['doubt'][$profeabies]);
        $this->assertCount(1, $piles['none']);

        $cohortreader = $DB->get_record(self::T . 'reader', ['userid' => $inventada->id], '*', MUST_EXIST);
        matching::link((int) $library->entityid, $abiesana, (int) $inventada->id);
        $this->assertFalse($DB->record_exists(self::T . 'reader', ['id' => $cohortreader->id]));
        $linked = $DB->get_record(self::T . 'reader', ['id' => $abiesana], '*', MUST_EXIST);
        $this->assertEquals($inventada->id, $linked->userid);
        $this->assertSame('10000H', $linked->cardcode);
        $this->assertNull($linked->firstname);
        $this->assertSame(1, $DB->count_records(self::T . 'loan', ['readerid' => $abiesana, 'timereturned' => null]));

        matching::leave((int) $library->entityid, $piles['none']);
        $this->assertSame('left', $DB->get_field(self::T . 'reader', 'status', ['id' => $piles['none'][0]]));
        $this->assertSame(1, count(matching::piles((int) $library->entityid)['doubt']));
    }
}
