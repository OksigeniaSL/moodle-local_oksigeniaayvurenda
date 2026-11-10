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
 * What was catalogued lately, and the book passed or typed twice.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\catalogued
 * @covers     \local_oksigeniaayvurenda\local\cataloguer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalogued::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cataloguer::class)]
final class catalogued_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The spans count the copies added by hand, not what an import brought; a record added at two moments says so, and so
     * does one whose ISBN another record has.
     */
    public function test_spans_and_lines(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        // A Wednesday at noon, in the server's zone.
        $now = make_timestamp(2026, 11, 11, 12);
        $starts = catalogued::starts($entityid, $now);
        $this->assertSame(make_timestamp(2026, 11, 11), $starts['today']);
        $this->assertSame(make_timestamp(2026, 11, 9), $starts['week']);
        $this->assertSame(make_timestamp(2026, 11, 1), $starts['fortnight']);
        $later = catalogued::starts($entityid, make_timestamp(2026, 11, 20, 9));
        $this->assertSame(make_timestamp(2026, 11, 16), $later['fortnight']);
        $this->assertSame(make_timestamp(2026, 11, 1), $starts['month']);

        $copy = fn(int $recordid, string $code, int $time) => $gen->create_item(['libraryid' => $library->id,
            'recordid' => $recordid, 'barcode' => $code, 'timecreated' => $time]);
        $faro = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido', 'isbn' => '9780306406157']);
        $copy($faro->id, '030001', $now - HOURSECS);
        $copy($faro->id, '030002', $now - HOURSECS);
        $copy($faro->id, '030003', $now - 10 * MINSECS);
        $island = $gen->create_record(['entityid' => $entityid, 'title' => 'La isla', 'isbn' => '9781861972712']);
        $gen->create_record(['entityid' => $entityid, 'title' => 'La isla (otra ficha)', 'isbn' => '9781861972712']);
        $copy($island->id, '030004', $now - 2 * DAYSECS);
        $copy($island->id, '030006', $now - 4 * DAYSECS);
        $old = $copy($island->id, '030005', $now - DAYSECS);
        $DB->insert_record(self::T . 'source', ['entityid' => $entityid, 'origin' => 'abies', 'tablename' => 'item',
            'externalid' => '77', 'localid' => $old->id, 'hash' => sha1('x'), 'timecreated' => $now, 'timemodified' => $now]);

        $this->assertSame(3, catalogued::count($library, $starts['today']));
        $this->assertSame(3, catalogued::today($library, $now));
        $this->assertSame(4, catalogued::count($library, $starts['week']));
        $lines = catalogued::lines($library, $starts['week']);
        $this->assertCount(3, $lines);
        $this->assertSame(['030003'], $lines[0]['codes']);
        $this->assertSame(['030002', '030001'], $lines[1]['codes']);
        $this->assertTrue($lines[0]['twice']);
        $this->assertFalse($lines[0]['twin']);
        $this->assertSame('La isla', $lines[2]['title']);
        $this->assertFalse($lines[2]['twice']);
        $this->assertTrue($lines[2]['twin']);
    }

    /**
     * A record that had a copy added lately says when and which; one that only came with an import says nothing. A book
     * without an ISBN typed again finds the record with its title and an author in common, not another author's.
     */
    public function test_recent_and_similar(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $entityid = (int) $library->entityid;
        $now = time();
        $record = $gen->create_record(['entityid' => $entityid, 'title' => 'El faro dormido']);
        $this->assertNull(cataloguer::recent($entityid, (int) $record->id, $now));
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => '030001',
            'timecreated' => $now - 40 * DAYSECS]);
        $this->assertNull(cataloguer::recent($entityid, (int) $record->id, $now));
        $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id, 'barcode' => '030002',
            'timecreated' => $now - 5]);
        $said = cataloguer::recent($entityid, (int) $record->id, $now);
        $this->assertStringContainsString('030002', $said);
        $this->assertStringNotContainsString('030001', $said);

        $author = $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'author',
            'label' => 'Montes, Ana', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record(self::T . 'recauth', ['recordid' => $record->id, 'authorityid' => $author, 'sortorder' => 0]);
        $DB->set_field(self::T . 'record', 'searchtext', 'el faro dormido ana montes', ['id' => $record->id]);
        $this->assertSame([(int) $record->id], array_column(
            cataloguer::similar($entityid, 'El faro DORMIDO', ['Ana Montes']),
            'id'
        ));
        $this->assertSame([(int) $record->id], array_column(cataloguer::similar($entityid, 'El faro dormido', []), 'id'));
        $this->assertSame([], cataloguer::similar($entityid, 'El faro dormido', ['Luis Prado']));
        $this->assertSame([], cataloguer::similar($entityid, 'El faro', ['Ana Montes']));
    }
}
