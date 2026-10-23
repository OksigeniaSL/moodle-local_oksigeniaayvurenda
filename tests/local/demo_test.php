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

use local_oksigeniaayvurenda\core\control_letter;

/**
 * Tests for the made-up library.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\demo
 */
#[\PHPUnit\Framework\Attributes\CoversClass(demo::class)]
final class demo_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * It makes the entity, its library, a school year, loan rules, titles with copies and readers with groups.
     */
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $entityid = demo::create('Demo', 60, 30);

        $library = $DB->get_record(self::T . 'library', ['entityid' => $entityid], '*', MUST_EXIST);
        $this->assertSame('central', $library->kind);
        $this->assertSame(1, $DB->count_records(self::T . 'year', ['entityid' => $entityid]));
        $this->assertSame(4, $DB->count_records(self::T . 'rule', ['libraryid' => $library->id]));
        $this->assertSame(60, $DB->count_records(self::T . 'record', ['entityid' => $entityid]));
        $this->assertSame(30, $DB->count_records(self::T . 'reader', ['entityid' => $entityid]));

        $items = $DB->get_records(self::T . 'item', ['entityid' => $entityid]);
        $this->assertGreaterThanOrEqual(60, count($items));
        foreach ($items as $item) {
            $this->assertTrue(control_letter::is_valid($item->barcode), $item->barcode);
            $this->assertSame((int) $library->id, (int) $item->libraryid);
        }
        foreach ($DB->get_records(self::T . 'reader', ['entityid' => $entityid]) as $reader) {
            $this->assertTrue(control_letter::is_valid($reader->cardcode), $reader->cardcode);
            $members = $DB->count_records(self::T . 'member', ['readerid' => $reader->id]);
            $this->assertSame($reader->readertype === 'student' ? 1 : 0, $members);
        }
        $record = $DB->get_record_select(self::T . 'record', 'entityid = ?', [$entityid], '*', IGNORE_MULTIPLE);
        $this->assertNotEmpty($record->searchtext);
        $this->assertSame(4, $DB->count_records(self::T . 'recauth', ['recordid' => $record->id]));
    }

    /**
     * The same seed gives the same library.
     */
    public function test_seed(): void {
        global $DB;
        $this->resetAfterTest();
        $first = demo::create('One', 10, 5, 7);
        $second = demo::create('Two', 10, 5, 7);
        $titles = fn($entityid) => $DB->get_fieldset_select(self::T . 'record', 'title', 'entityid = ? ORDER BY id', [$entityid]);
        $this->assertSame($titles($first), $titles($second));
    }
}
