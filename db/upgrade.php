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

/**
 * Upgrade steps.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin from an older version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_oksigeniaayvurenda_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026102400) {
        // The fingerprint of each imported row, so that a new import skips what did not change.
        $table = new xmldb_table('local_oksigeniaayvurenda_source');
        $field = new xmldb_field('hash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'localid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026102400, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102500) {
        // The librarian role, for sites that had the plugin before it existed.
        \local_oksigeniaayvurenda\local\roles::ensure_librarian();
        upgrade_plugin_savepoint(true, 2026102500, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102503) {
        // When the reader last heard about a late loan.
        $table = new xmldb_table('local_oksigeniaayvurenda_loan');
        $field = new xmldb_field('timenotified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'returnnote');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // The tutor of each group in each school year.
        $table = new xmldb_table('local_oksigeniaayvurenda_tutor');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('yearid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('groupname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timenotified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('yearid', XMLDB_KEY_FOREIGN, ['yearid'], 'local_oksigeniaayvurenda_year', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('yeargroup', XMLDB_INDEX_UNIQUE, ['yearid', 'groupname']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102503, 'local', 'oksigeniaayvurenda');
    }

    return true;
}
