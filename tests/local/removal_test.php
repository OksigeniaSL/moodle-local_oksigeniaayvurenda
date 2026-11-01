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
 * A centre taken away whole.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\removal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(removal::class)]
final class removal_test extends \advanced_testcase {
    /**
     * All of it goes, and the other centre stays as it was.
     */
    public function test_only_that_centre(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('entitycategories', 1, 'local_oksigeniaayvurenda');
        $t = 'local_oksigeniaayvurenda_';
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $make = function (string $name) use ($gen) {
            $library = libraries::create_entity(['name' => $name, 'library' => $name]);
            $reader = $gen->create_reader(['entityid' => $library->entityid, 'firstname' => $name]);
            $record = $gen->create_record(['entityid' => $library->entityid, 'title' => $name]);
            $item = $gen->create_item(['libraryid' => $library->id, 'recordid' => $record->id]);
            $gen->create_loan(['libraryid' => $library->id, 'itemid' => $item->id, 'readerid' => $reader->id]);
            return $library;
        };
        $gone = $make('Se va');
        $stays = $make('Se queda');
        set_config('cifrakey' . $gone->entityid, 'llave', 'local_oksigeniaayvurenda');
        $category = (int) $DB->get_field($t . 'entity', 'categoryid', ['id' => $gone->entityid]);
        $this->assertSame(1, removal::count((int) $gone->entityid)['loan']);

        removal::remove((int) $gone->entityid);

        $this->assertFalse($DB->record_exists($t . 'entity', ['id' => $gone->entityid]));
        $this->assertFalse($DB->record_exists($t . 'library', ['id' => $gone->id]));
        $this->assertSame(0, $DB->count_records($t . 'reader', ['entityid' => $gone->entityid]));
        $this->assertSame(0, $DB->count_records($t . 'item', ['entityid' => $gone->entityid]));
        $this->assertSame(0, $DB->count_records($t . 'loan', ['libraryid' => $gone->id]));
        $this->assertFalse(get_config('local_oksigeniaayvurenda', 'cifrakey' . $gone->entityid));
        $this->assertFalse($DB->record_exists('course_categories', ['id' => $category]));
        $this->assertSame(1, $DB->count_records($t . 'loan', ['libraryid' => $stays->id]));
        $this->assertSame(1, $DB->count_records($t . 'reader', ['entityid' => $stays->entityid]));
    }
}
