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
 * Privacy provider.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The library lives at site level: a reader with a Moodle account, their loans and their groups, and the staff who lent
 * and took back books. Deleting a reader's data anonymises the reader, so that the library keeps its counts (how many
 * loans a group had) without anyone in them; staff are taken off the loans they handled.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What the plugin stores about users.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::T . 'reader', [
            'userid' => 'privacy:metadata:reader:userid',
            'cardcode' => 'privacy:metadata:reader:cardcode',
            'readertype' => 'privacy:metadata:reader:readertype',
            'firstname' => 'privacy:metadata:reader:firstname',
            'lastname' => 'privacy:metadata:reader:lastname',
            'email' => 'privacy:metadata:reader:email',
            'status' => 'privacy:metadata:reader:status',
            'notes' => 'privacy:metadata:reader:notes',
        ], 'privacy:metadata:reader');
        $collection->add_database_table(self::T . 'member', [
            'readerid' => 'privacy:metadata:member:readerid',
            'groupname' => 'privacy:metadata:member:groupname',
            'stage' => 'privacy:metadata:member:stage',
        ], 'privacy:metadata:member');
        $collection->add_database_table(self::T . 'loan', [
            'readerid' => 'privacy:metadata:loan:readerid',
            'itemid' => 'privacy:metadata:loan:itemid',
            'groupname' => 'privacy:metadata:loan:groupname',
            'timeloaned' => 'privacy:metadata:loan:timeloaned',
            'timedue' => 'privacy:metadata:loan:timedue',
            'timereturned' => 'privacy:metadata:loan:timereturned',
            'renewals' => 'privacy:metadata:loan:renewals',
            'returnnote' => 'privacy:metadata:loan:returnnote',
            'staffid' => 'privacy:metadata:loan:staffid',
            'returnstaffid' => 'privacy:metadata:loan:returnstaffid',
        ], 'privacy:metadata:loan');
        return $collection;
    }

    /**
     * The site context, when the user is a reader or handled loans.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if (self::has_data($userid)) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Users with data in the site context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . self::T . 'reader} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql('staffid', 'SELECT staffid FROM {' . self::T . 'loan} WHERE staffid IS NOT NULL', []);
        $userlist->add_from_sql(
            'returnstaffid',
            'SELECT returnstaffid FROM {' . self::T . 'loan} WHERE returnstaffid IS NOT NULL',
            []
        );
    }

    /**
     * Exports the user's reader cards with their groups and loans, and the loans they handled at the desk.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::includes_system($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        $base = [get_string('pluginname', 'local_oksigeniaayvurenda')];
        foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid], 'id') as $reader) {
            $groups = [];
            foreach ($DB->get_records(self::T . 'member', ['readerid' => $reader->id], 'id') as $member) {
                $year = $DB->get_field(self::T . 'year', 'name', ['id' => $member->yearid]);
                $groups[] = ['year' => $year, 'group' => $member->groupname, 'stage' => $member->stage];
            }
            $loans = [];
            foreach ($DB->get_records(self::T . 'loan', ['readerid' => $reader->id], 'timeloaned') as $loan) {
                $loans[] = self::loan_export($loan);
            }
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:reader', 'local_oksigeniaayvurenda', $reader->cardcode)]),
                (object) [
                    'cardcode' => $reader->cardcode,
                    'readertype' => $reader->readertype,
                    'firstname' => $reader->firstname,
                    'lastname' => $reader->lastname,
                    'email' => $reader->email,
                    'status' => $reader->status,
                    'notes' => $reader->notes,
                    'groups' => $groups,
                    'loans' => $loans,
                ]
            );
        }
        $handled = $DB->get_records_select(
            self::T . 'loan',
            'staffid = :a OR returnstaffid = :b',
            ['a' => $userid, 'b' => $userid],
            'timeloaned'
        );
        if ($handled) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:desk', 'local_oksigeniaayvurenda')]),
                (object) ['loans' => array_values(array_map(fn($loan) => self::loan_export($loan), $handled))]
            );
        }
    }

    /**
     * Anonymises every reader with an account and takes every staff member off the loans.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach ($DB->get_fieldset_select(self::T . 'reader', 'DISTINCT userid', 'userid IS NOT NULL') as $userid) {
            self::delete_user((int) $userid);
        }
        $DB->set_field_select(self::T . 'loan', 'staffid', null, 'staffid IS NOT NULL');
        $DB->set_field_select(self::T . 'loan', 'returnstaffid', null, 'returnstaffid IS NOT NULL');
    }

    /**
     * Deletes one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (self::includes_system($contextlist)) {
            self::delete_user((int) $contextlist->get_user()->id);
        }
    }

    /**
     * Deletes the data of several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user((int) $userid);
        }
    }

    /**
     * Anonymises the user's readers and takes the user off the loans they handled.
     *
     * @param int $userid
     */
    private static function delete_user(int $userid): void {
        global $DB;
        $now = time();
        foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid], 'id', 'id') as $reader) {
            $DB->update_record(self::T . 'reader', (object) [
                'id' => $reader->id,
                'userid' => null,
                'cardcode' => 'deleted-' . $reader->id,
                'firstname' => null,
                'lastname' => null,
                'email' => null,
                'notes' => null,
                'status' => 'deleted',
                'timemodified' => $now,
            ]);
            $DB->set_field(self::T . 'loan', 'returnnote', null, ['readerid' => $reader->id]);
        }
        $DB->set_field(self::T . 'loan', 'staffid', null, ['staffid' => $userid]);
        $DB->set_field(self::T . 'loan', 'returnstaffid', null, ['returnstaffid' => $userid]);
    }

    /**
     * Whether the user is a reader or handled any loan.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_data(int $userid): bool {
        global $DB;
        if ($DB->record_exists(self::T . 'reader', ['userid' => $userid])) {
            return true;
        }
        return $DB->record_exists_select(
            self::T . 'loan',
            'staffid = :a OR returnstaffid = :b',
            ['a' => $userid, 'b' => $userid]
        );
    }

    /**
     * Whether the approved contexts include the site.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    private static function includes_system(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }

    /**
     * A loan as exported: the title and code of the copy, and its dates.
     *
     * @param \stdClass $loan
     * @return array
     */
    private static function loan_export(\stdClass $loan): array {
        global $DB;
        $item = $DB->get_record(self::T . 'item', ['id' => $loan->itemid], 'barcode, recordid');
        $title = $item ? $DB->get_field(self::T . 'record', 'title', ['id' => $item->recordid]) : '';
        return [
            'title' => $title,
            'barcode' => $item ? $item->barcode : '',
            'group' => $loan->groupname,
            'loaned' => transform::datetime($loan->timeloaned),
            'due' => transform::datetime($loan->timedue),
            'returned' => $loan->timereturned ? transform::datetime($loan->timereturned) : null,
            'renewals' => $loan->renewals,
            'note' => $loan->returnnote,
        ];
    }
}
