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
 * The copy a centre takes with it has every table, or says why not.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniaayvurenda\local\copies
 */
#[\PHPUnit\Framework\Attributes\CoversClass(copies::class)]
final class copies_tables_test extends \advanced_testcase {
    /**
     * Each table of install.xml is in the copy or in the list of what it leaves out on purpose; never both, never neither
     * (twelve tables were missing until 0.173.0, among them the readings of the courses).
     */
    public function test_every_table(): void {
        global $CFG;
        $file = new \xmldb_file($CFG->dirroot . '/local/oksigeniaayvurenda/db/install.xml');
        $file->loadXMLStructure();
        $tables = array_map(
            fn($table) => str_replace('local_oksigeniaayvurenda_', '', $table->getName()),
            $file->getStructure()->getTables()
        );
        $copied = array_keys(copies::TABLES);
        $out = array_keys(copies::LEFT_OUT);
        $this->assertSame([], array_values(array_intersect($copied, $out)));
        $this->assertSame([], array_values(array_diff($tables, $copied, $out)), 'tables nobody decided about');
        $this->assertSame([], array_values(array_diff(array_merge($copied, $out), $tables)), 'tables that do not exist');
    }

    /**
     * A course's readings, its reserve and who keeps a library go into the copy of the centre, not another's.
     */
    public function test_readings_in_the_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $other = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid]);
        $now = time();
        foreach ([$library, $other] as $lib) {
            // One list a course.
            $course = $this->getDataGenerator()->create_course();
            $list = $DB->insert_record('local_oksigeniaayvurenda_readinglist', ['courseid' => $course->id, 'libraryid' => $lib->id,
                'timecreated' => $now, 'timemodified' => $now]);
            $DB->insert_record('local_oksigeniaayvurenda_readingitem', ['listid' => $list, 'recordid' => $record->id,
                'required' => 1, 'sortorder' => 1, 'timecreated' => $now]);
        }
        $count = function (string $table) use ($DB, $library) {
            return $DB->count_records_select(
                'local_oksigeniaayvurenda_' . $table,
                copies::TABLES[$table],
                ['entityid' => $library->entityid]
            );
        };
        $this->assertSame(1, $count('readinglist'));
        $this->assertSame(1, $count('readingitem'));
    }
}
