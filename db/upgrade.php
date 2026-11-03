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

    if ($oldversion < 2026102846) {
        // Each centre can have a course category of its own, where the roles of its staff are given.
        $table = new xmldb_table('local_oksigeniaayvurenda_entity');
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'settings');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // The librarian role can then be given in a category, not only on the whole site.
        \local_oksigeniaayvurenda\local\roles::ensure_librarian();
        upgrade_plugin_savepoint(true, 2026102846, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026102855) {
        // Each copy keeps its place in the order of the shelf, for walking along the shelves.
        $table = new xmldb_table('local_oksigeniaayvurenda_item');
        $field = new xmldb_field('shelfkey', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'timeacquired');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('libraryshelfkey', XMLDB_INDEX_NOTUNIQUE, ['libraryid', 'shelfkey']);
        if ($dbman->table_exists($table) && !$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        foreach ($DB->get_fieldset_select('local_oksigeniaayvurenda_entity', 'id', '1 = 1') as $entityid) {
            \local_oksigeniaayvurenda\local\stacks::refresh_keys((int) $entityid);
        }
        upgrade_plugin_savepoint(true, 2026102855, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103104) {
        // Who keeps one library only, at its desk.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_keeper')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_keeper');
        }
        upgrade_plugin_savepoint(true, 2026103104, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103109) {
        // The sets of a textbook bank and their titles.
        foreach (['local_oksigeniaayvurenda_bankset', 'local_oksigeniaayvurenda_banktitle'] as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $table);
            }
        }
        upgrade_plugin_savepoint(true, 2026103109, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103122) {
        // The reading lists of the courses and their books.
        foreach (['local_oksigeniaayvurenda_readinglist', 'local_oksigeniaayvurenda_readingitem'] as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $table);
            }
        }
        upgrade_plugin_savepoint(true, 2026103122, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103129) {
        // Copies on reserve for a course.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_reserve')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_reserve');
        }
        upgrade_plugin_savepoint(true, 2026103129, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103131) {
        // Digital items only for the students of a course.
        if (!$dbman->table_exists('local_oksigeniaayvurenda_fragment')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oksigeniaayvurenda_fragment');
        }
        upgrade_plugin_savepoint(true, 2026103131, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103136) {
        // Since 0.97.0 a connector reaches the server's own network only at the hosts the administrator allows. Where a
        // centre's bridge already worked that way (a site that is not shared), its host is allowed, so nothing stops.
        if (!get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            $hosts = array_filter(array_map('trim', explode(',', strtolower((string) get_config(
                'local_oksigeniaayvurenda',
                'internalhosts'
            )))));
            foreach ($DB->get_records('local_oksigeniaayvurenda_entity', null, 'id', 'id, settings') as $entity) {
                $cifra = (json_decode((string) $entity->settings, true) ?: [])['cifra'] ?? [];
                $host = strtolower((string) parse_url((string) ($cifra['url'] ?? ''), PHP_URL_HOST));
                if ($host !== '' && ($cifra['mode'] ?? 'key') === 'key' && !in_array($host, $hosts, true)) {
                    $hosts[] = $host;
                }
            }
            if ($hosts) {
                set_config('internalhosts', implode(', ', $hosts), 'local_oksigeniaayvurenda');
            }
        }
        upgrade_plugin_savepoint(true, 2026103136, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026103137) {
        // Indexes for what is asked every day: the pulse and the new books, the notices, the tutors and the challenges.
        $indexes = [
            ['local_oksigeniaayvurenda_loan', 'libraryloaned', ['libraryid', 'timeloaned']],
            ['local_oksigeniaayvurenda_loan', 'returneddue', ['timereturned', 'timedue']],
            ['local_oksigeniaayvurenda_item', 'librarycreated', ['libraryid', 'timecreated']],
            ['local_oksigeniaayvurenda_member', 'yeargroup', ['yearid', 'groupname']],
        ];
        foreach ($indexes as [$tablename, $name, $fields]) {
            $table = new xmldb_table($tablename);
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026103137, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110116) {
        // What a reader waits for rings on their phone too. Moodle does not change the defaults of a message that exists.
        foreach (['overdue', 'holdready', 'newbooks', 'overduetutor'] as $name) {
            $key = 'message_provider_local_oksigeniaayvurenda_' . $name . '_enabled';
            $outputs = array_filter(explode(',', (string) get_config('message', $key)));
            if (!in_array('airnotifier', $outputs, true)) {
                $outputs[] = 'airnotifier';
                set_config($key, implode(',', $outputs), 'message');
            }
        }
        upgrade_plugin_savepoint(true, 2026110116, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110204) {
        // The passwords and keys of the centres, encrypted from now on.
        foreach (get_config('local_oksigeniaayvurenda') as $name => $value) {
            $secret = preg_match('/^(offsite\d+_|cifrakey\d|cifrasecret\d)/', $name);
            if ($secret && !preg_match('/^(sodium|openssl):/', (string) $value)) {
                \local_oksigeniaayvurenda\local\secrets::put($name, (string) $value);
            }
        }
        upgrade_plugin_savepoint(true, 2026110204, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110211) {
        // The self-loan posts, and which of them a loan went through.
        $table = new xmldb_table('local_oksigeniaayvurenda_selfpost');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('libraryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('keyhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('lang', XMLDB_TYPE_CHAR, '30');
            $table->add_field('tries', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timetries', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('hourtries', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timehour', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timestopped', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('timelast', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('libraryid', XMLDB_KEY_FOREIGN, ['libraryid'], 'local_oksigeniaayvurenda_library', ['id']);
            $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $table->add_index('keyhash', XMLDB_INDEX_UNIQUE, ['keyhash']);
            $dbman->create_table($table);
        }
        foreach (
            [new xmldb_field('hourtries', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0'),
                new xmldb_field('timehour', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
                new xmldb_field('timestopped', XMLDB_TYPE_INTEGER, '10')] as $field
        ) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $table = new xmldb_table('local_oksigeniaayvurenda_loan');
        foreach (['postid', 'returnpostid'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $key = new xmldb_key($name, XMLDB_KEY_FOREIGN, [$name], 'local_oksigeniaayvurenda_selfpost', ['id']);
                $dbman->add_key($table, $key);
            }
        }
        upgrade_plugin_savepoint(true, 2026110211, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110212) {
        // What a library buys: all from the plugin's own description, which says the same as these tables.
        $xml = new xmldb_file(__DIR__ . '/install.xml');
        $xml->loadXMLStructure();
        foreach (['purchaseorder', 'purchase', 'purchasewho', 'budget'] as $name) {
            $table = $xml->getStructure()->getTable('local_oksigeniaayvurenda_' . $name);
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }
        upgrade_plugin_savepoint(true, 2026110212, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110215) {
        // Lending between libraries.
        $xml = new xmldb_file(__DIR__ . '/install.xml');
        $xml->loadXMLStructure();
        $table = $xml->getStructure()->getTable('local_oksigeniaayvurenda_ill');
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110215, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110217) {
        // What a late book leaves, for a centre that wants it to leave something.
        $xml = new xmldb_file(__DIR__ . '/install.xml');
        $xml->loadXMLStructure();
        $table = $xml->getStructure()->getTable('local_oksigeniaayvurenda_sanction');
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110217, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110219) {
        // A post may be a machine that speaks SIP2.
        $table = new xmldb_table('local_oksigeniaayvurenda_selfpost');
        $field = new xmldb_field('machine', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'keyhash');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110219, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110224) {
        // A link may be to something the centre subscribes to.
        $table = new xmldb_table('local_oksigeniaayvurenda_digital');
        $field = new xmldb_field('subscribed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'url');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110224, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110302) {
        global $CFG;
        // Accessible formats: who needs them, the items made for them, and asking for a book in another format.
        $fields = ['reader' => new xmldb_field('accessneeds', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status'),
            'digital' => new xmldb_field('accessformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'subscribed')];
        foreach ($fields as $name => $field) {
            $table = new xmldb_table('local_oksigeniaayvurenda_' . $name);
            if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        if (!$dbman->table_exists('local_oksigeniaayvurenda_formatask')) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/oksigeniaayvurenda/db/install.xml',
                'local_oksigeniaayvurenda_formatask'
            );
        }
        upgrade_plugin_savepoint(true, 2026110302, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110304) {
        // Whoever runs the library of a centre has a role of the plugin's, given in the centre's category.
        \local_oksigeniaayvurenda\local\roles::ensure_manager();
        upgrade_plugin_savepoint(true, 2026110304, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110316) {
        // In a site with one centre, its library manager is given on the site.
        \local_oksigeniaayvurenda\local\roles::ensure_manager();
        upgrade_plugin_savepoint(true, 2026110316, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110319) {
        // What came from Abies, as it is now, is where what the library changes here starts: a new import leaves it.
        $table = new xmldb_table('local_oksigeniaayvurenda_source');
        $field = new xmldb_field('localhash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'hash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        \local_oksigeniaayvurenda\local\import\abies_import::settle();
        upgrade_plugin_savepoint(true, 2026110319, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110320) {
        // A loan written on paper whose copy nobody wrote down.
        $table = new xmldb_table('local_oksigeniaayvurenda_loan');
        $field = new xmldb_field('copyguessed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'conditionin');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110320, 'local', 'oksigeniaayvurenda');
    }

    if ($oldversion < 2026110402) {
        // Two words MySQL 8 reserves, and Moodle does not quote its columns.
        $table = new xmldb_table('local_oksigeniaayvurenda_source');
        $index = new xmldb_index('external', XMLDB_INDEX_UNIQUE, ['entityid', 'system', 'tablename', 'externalid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $field = new xmldb_field('system', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, 'entityid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'origin');
        }
        $index = new xmldb_index('external', XMLDB_INDEX_UNIQUE, ['entityid', 'origin', 'tablename', 'externalid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $table = new xmldb_table('local_oksigeniaayvurenda_bankset');
        $field = new xmldb_field('groups', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'groupnames');
        }
        upgrade_plugin_savepoint(true, 2026110402, 'local', 'oksigeniaayvurenda');
    }

    return true;
}
