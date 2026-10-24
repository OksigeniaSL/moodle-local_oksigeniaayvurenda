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

namespace local_oksigeniaayvurenda;

/**
 * Tests for upgrading from the first version.
 *
 * @package    local_oksigeniaayvurenda
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class upgrade_test extends \advanced_testcase {
    /**
     * A site with the 0.1.0, which had no tables, upgrades to a complete database.
     */
    public function test_from_first_version(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $dbman = $DB->get_manager();
        $schema = new \xmldb_file($CFG->dirroot . '/local/oksigeniaayvurenda/db/install.xml');
        $schema->loadXMLStructure();
        $tables = $schema->getStructure()->getTables();
        foreach ($tables as $table) {
            if ($dbman->table_exists($table->getName())) {
                $dbman->drop_table($table);
            }
        }
        $this->assertFalse($dbman->table_exists('local_oksigeniaayvurenda_entity'));
        // The site is at the 0.1.0.
        set_config('version', 2026102000, 'local_oksigeniaayvurenda');
        $this->assertTrue(xmldb_local_oksigeniaayvurenda_upgrade(2026102000));
        foreach ($tables as $table) {
            $this->assertTrue($dbman->table_exists($table->getName()), $table->getName());
        }
        $this->assertTrue($dbman->field_exists('local_oksigeniaayvurenda_loan', 'conditionin'));
        $this->assertTrue($DB->record_exists('role', ['shortname' => 'ayvurendalibrarian']));
    }
}
