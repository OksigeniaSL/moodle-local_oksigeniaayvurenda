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

    if ($oldversion < 2026102300) {
        // The 0.1.0 had no tables: a site that had it gets them all now, as they are today. The steps after this one
        // see what is already there and leave it.
        if (!$dbman->table_exists(new xmldb_table('local_oksigeniaayvurenda_entity'))) {
            $dbman->install_from_xmldb_file(__DIR__ . '/install.xml');
        }
        upgrade_plugin_savepoint(true, 2026102300, 'local', 'oksigeniaayvurenda');
    }

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

    if ($oldversion < 2026102601) {
        // Codes read with a phone, on their way to the desk.
        $table = new xmldb_table('local_oksigeniaayvurenda_relay');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102601, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102602) {
        // The state of material when it goes and when it comes back.
        $table = new xmldb_table('local_oksigeniaayvurenda_loan');
        foreach (['conditionout' => 'timenotified', 'conditionin' => 'conditionout'] as $name => $after) {
            $field = new xmldb_field($name, XMLDB_TYPE_CHAR, '20', null, null, null, null, $after);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026102602, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102711) {
        // Reservations: a reader waiting for a book, then a copy set aside.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_hold')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_hold');
        }
        upgrade_plugin_savepoint(true, 2026102711, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102713) {
        // Stocktakes: going along the shelves reading the copies.
        foreach (['local_oksigeniaayvurenda_stocktake', 'local_oksigeniaayvurenda_stockread'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026102713, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102714) {
        // Weeding: records of the copies taken out of the collection.
        foreach (['local_oksigeniaayvurenda_withdrawal', 'local_oksigeniaayvurenda_withdrawn'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026102714, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102717) {
        // Boxes of copies one library lends to another.
        foreach (['local_oksigeniaayvurenda_lot', 'local_oksigeniaayvurenda_lotitem'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026102717, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102722) {
        // The digital collection: files and links of a record, and where each reader is in them.
        foreach (['local_oksigeniaayvurenda_digital', 'local_oksigeniaayvurenda_digitalread'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026102722, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102723) {
        // Voices: who recorded each one, and the families' permissions to share a minor's voice.
        $table = new xmldb_table('local_oksigeniaayvurenda_digital');
        $field = new xmldb_field('readerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $dbman->add_key($table, new xmldb_key(
                'readerid',
                XMLDB_KEY_FOREIGN,
                ['readerid'],
                'local_oksigeniaayvurenda_reader',
                ['id']
            ));
        }
        if (!$dbman->table_exists('local_oksigeniaayvurenda_voiceconsent')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_voiceconsent');
        }
        upgrade_plugin_savepoint(true, 2026102723, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102724) {
        // One-time links for families to give a book a voice without an account.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_voiceinvite')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_voiceinvite');
        }
        upgrade_plugin_savepoint(true, 2026102724, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102727) {
        // Voices: a signature, one class as audience, a link to delete them, and the student a family's link is for.
        $table = new xmldb_table('local_oksigeniaayvurenda_digital');
        $fields = [
            new xmldb_field('signature', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'readerid'),
            new xmldb_field('classyearid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'signature'),
            new xmldb_field('classgroup', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'classyearid'),
            new xmldb_field('deletetoken', XMLDB_TYPE_CHAR, '48', null, null, null, null, 'classgroup'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('deletetoken', XMLDB_INDEX_NOTUNIQUE, ['deletetoken']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // The voices recorded before get their link too.
        $voices = $DB->get_fieldset_select('local_oksigeniaayvurenda_digital', 'id', "kind = 'voice' AND deletetoken IS NULL");
        foreach ($voices as $id) {
            $DB->set_field('local_oksigeniaayvurenda_digital', 'deletetoken', bin2hex(random_bytes(24)), ['id' => $id]);
        }
        $table = new xmldb_table('local_oksigeniaayvurenda_voiceinvite');
        $field = new xmldb_field('readerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'invitedby');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $dbman->add_key($table, new xmldb_key(
                'readerid',
                XMLDB_KEY_FOREIGN,
                ['readerid'],
                'local_oksigeniaayvurenda_reader',
                ['id']
            ));
        }
        upgrade_plugin_savepoint(true, 2026102727, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102811) {
        // The learning platforms that open a library by LTI 1.3.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_ltiplatform')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_ltiplatform');
        }
        upgrade_plugin_savepoint(true, 2026102811, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102816) {
        // The courses of a platform whose students a teacher brought.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_lticontext')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_lticontext');
        }
        upgrade_plugin_savepoint(true, 2026102816, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102820) {
        // The challenges of the classes.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_challenge')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_challenge');
        }
        upgrade_plugin_savepoint(true, 2026102820, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102833) {
        // What readers tell about books.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_review')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_review');
        }
        upgrade_plugin_savepoint(true, 2026102833, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102834) {
        // The books classes choose as the reward for a challenge met.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_wish')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_wish');
        }
        upgrade_plugin_savepoint(true, 2026102834, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102835) {
        // An older reader's review can be a recommendation for the young.
        $table = new xmldb_table('local_oksigeniaayvurenda_review');
        $field = new xmldb_field('forlittle', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026102835, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102841) {
        // The books read in the library without being taken home.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_roomread')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_roomread');
        }
        upgrade_plugin_savepoint(true, 2026102841, 'local', 'oksigeniaayvurenda');
    }

    return true;
}
