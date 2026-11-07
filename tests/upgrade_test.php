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

    /**
     * A centre whose bridge was on the server's own network keeps reaching it after the 0.97.0.
     */
    public function test_bridge_stays_allowed(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $entity = $gen->create_entity();
        $DB->set_field('local_oksigeniaayvurenda_entity', 'settings', json_encode(['cifra' => ['enabled' => true,
            'url' => 'http://Puente-Interno:8080', 'mode' => 'key']]), ['id' => $entity->id]);
        set_config('version', 2026103135, 'local_oksigeniaayvurenda');
        $this->assertTrue(xmldb_local_oksigeniaayvurenda_upgrade(2026103135));
        $this->assertSame('puente-interno', get_config('local_oksigeniaayvurenda', 'internalhosts'));
    }

    /**
     * A site at the 0.149.0, whose sources still had their origin as «system», upgrades to the end in one go: the step that
     * takes Abies as the starting point used the new name before the step that gave it (app.ayvurenda.com, 7 November).
     */
    public function test_from_0149(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $dbman = $DB->get_manager();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Desde Abies']);
        $now = time();
        $DB->insert_record('local_oksigeniaayvurenda_source', ['entityid' => $library->entityid, 'origin' => 'abies',
            'tablename' => 'record', 'externalid' => '1001', 'localid' => $record->id, 'hash' => sha1('x'),
            'timecreated' => $now, 'timemodified' => $now]);

        // The table as the 0.149.0 left it.
        $table = new \xmldb_table('local_oksigeniaayvurenda_source');
        $dbman->drop_index($table, new \xmldb_index('external', XMLDB_INDEX_UNIQUE, ['entityid', 'origin', 'tablename',
            'externalid']));
        $dbman->rename_field($table, new \xmldb_field(
            'origin',
            XMLDB_TYPE_CHAR,
            '30',
            null,
            XMLDB_NOTNULL,
            null,
            null,
            'entityid'
        ), 'system');
        $dbman->add_index($table, new \xmldb_index('external', XMLDB_INDEX_UNIQUE, ['entityid', 'system', 'tablename',
            'externalid']));
        $dbman->drop_field($table, new \xmldb_field('localhash'));
        $dbman->drop_field(new \xmldb_table('local_oksigeniaayvurenda_loan'), new \xmldb_field('copyguessed'));
        $this->assertTrue($dbman->field_exists('local_oksigeniaayvurenda_source', 'system'));

        set_config('version', 2026110316, 'local_oksigeniaayvurenda');
        $this->assertTrue(xmldb_local_oksigeniaayvurenda_upgrade(2026110316));
        $this->assertFalse($dbman->field_exists('local_oksigeniaayvurenda_source', 'system'));
        $this->assertTrue($dbman->field_exists('local_oksigeniaayvurenda_source', 'origin'));
        $this->assertTrue($dbman->index_exists($table, new \xmldb_index('external', XMLDB_INDEX_UNIQUE, ['entityid', 'origin',
            'tablename', 'externalid'])));
        $this->assertTrue($dbman->field_exists('local_oksigeniaayvurenda_loan', 'copyguessed'));
        $this->assertNotEmpty($DB->get_field('local_oksigeniaayvurenda_source', 'localhash', ['localid' => $record->id]));
    }

    /**
     * The stored search text takes the letters as the search now writes them.
     */
    public function test_letters(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/oksigeniaayvurenda/db/upgrade.php');
        $this->resetAfterTest();
        /** @var \local_oksigeniaayvurenda_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniaayvurenda');
        $library = $gen->create_library();
        $record = $gen->create_record(['entityid' => $library->entityid, 'title' => 'Straße']);
        $DB->set_field('local_oksigeniaayvurenda_record', 'searchtext', 'straße œuvres lodz', ['id' => $record->id]);
        local_oksigeniaayvurenda_upgrade_letters();
        local_oksigeniaayvurenda_upgrade_letters();
        $this->assertSame('strasse oeuvres lodz', $DB->get_field(
            'local_oksigeniaayvurenda_record',
            'searchtext',
            ['id' => $record->id]
        ));
    }
}
