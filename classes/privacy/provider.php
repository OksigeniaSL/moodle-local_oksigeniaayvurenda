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
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
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
            'timenotified' => 'privacy:metadata:loan:timenotified',
            'conditionout' => 'privacy:metadata:loan:conditionout',
            'conditionin' => 'privacy:metadata:loan:conditionin',
        ], 'privacy:metadata:loan');
        $collection->add_database_table(self::T . 'tutor', [
            'userid' => 'privacy:metadata:tutor:userid',
            'groupname' => 'privacy:metadata:tutor:groupname',
            'timenotified' => 'privacy:metadata:tutor:timenotified',
        ], 'privacy:metadata:tutor');
        $collection->add_database_table(self::T . 'hold', [
            'readerid' => 'privacy:metadata:hold:readerid',
            'recordid' => 'privacy:metadata:hold:recordid',
            'itemid' => 'privacy:metadata:hold:itemid',
            'status' => 'privacy:metadata:hold:status',
            'createdby' => 'privacy:metadata:hold:createdby',
            'timecreated' => 'privacy:metadata:hold:timecreated',
            'timeready' => 'privacy:metadata:hold:timeready',
            'timeexpires' => 'privacy:metadata:hold:timeexpires',
            'timenotified' => 'privacy:metadata:hold:timenotified',
            'timeclosed' => 'privacy:metadata:hold:timeclosed',
        ], 'privacy:metadata:hold');
        $collection->add_database_table(self::T . 'stocktake', [
            'createdby' => 'privacy:metadata:stocktake:createdby',
            'name' => 'privacy:metadata:stocktake:name',
            'timecreated' => 'privacy:metadata:stocktake:timecreated',
        ], 'privacy:metadata:stocktake');
        $collection->add_database_table(self::T . 'stockread', [
            'userid' => 'privacy:metadata:stockread:userid',
            'code' => 'privacy:metadata:stockread:code',
            'timeread' => 'privacy:metadata:stockread:timeread',
        ], 'privacy:metadata:stockread');
        $collection->add_database_table(self::T . 'withdrawal', [
            'createdby' => 'privacy:metadata:withdrawal:createdby',
            'reason' => 'privacy:metadata:withdrawal:reason',
            'timecreated' => 'privacy:metadata:withdrawal:timecreated',
        ], 'privacy:metadata:withdrawal');
        $collection->add_database_table(self::T . 'digital', [
            'createdby' => 'privacy:metadata:digital:createdby',
            'title' => 'privacy:metadata:digital:title',
            'signature' => 'privacy:metadata:digital:signature',
            'timecreated' => 'privacy:metadata:digital:timecreated',
        ], 'privacy:metadata:digital');
        $collection->add_database_table(self::T . 'digitalread', [
            'userid' => 'privacy:metadata:digitalread:userid',
            'position' => 'privacy:metadata:digitalread:position',
            'bookmarks' => 'privacy:metadata:digitalread:bookmarks',
            'timemodified' => 'privacy:metadata:digitalread:timemodified',
        ], 'privacy:metadata:digitalread');
        $collection->add_database_table(self::T . 'voiceconsent', [
            'readerid' => 'privacy:metadata:voiceconsent:readerid',
            'notedby' => 'privacy:metadata:voiceconsent:notedby',
            'timecreated' => 'privacy:metadata:voiceconsent:timecreated',
            'timerevoked' => 'privacy:metadata:voiceconsent:timerevoked',
        ], 'privacy:metadata:voiceconsent');
        $collection->add_database_table(self::T . 'voiceinvite', [
            'invitedby' => 'privacy:metadata:voiceinvite:invitedby',
            'readerid' => 'privacy:metadata:voiceinvite:readerid',
            'name' => 'privacy:metadata:voiceinvite:name',
            'timecreated' => 'privacy:metadata:voiceinvite:timecreated',
            'timeused' => 'privacy:metadata:voiceinvite:timeused',
        ], 'privacy:metadata:voiceinvite');
        $collection->add_database_table(self::T . 'lot', [
            'createdby' => 'privacy:metadata:lot:createdby',
            'name' => 'privacy:metadata:lot:name',
            'timecreated' => 'privacy:metadata:lot:timecreated',
        ], 'privacy:metadata:lot');
        $collection->add_database_table(self::T . 'relay', [
            'userid' => 'privacy:metadata:relay:userid',
            'code' => 'privacy:metadata:relay:code',
        ], 'privacy:metadata:relay');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_user_preference('local_oksigeniaayvurenda_cardlook', 'privacy:metadata:preference:cardlook');
        return $collection;
    }

    /**
     * Exports how the user chose to see their card.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $look = get_user_preferences('local_oksigeniaayvurenda_cardlook', null, $userid);
        if ($look !== null) {
            writer::export_user_preference(
                'local_oksigeniaayvurenda',
                'local_oksigeniaayvurenda_cardlook',
                $look,
                get_string('privacy:metadata:preference:cardlook', 'local_oksigeniaayvurenda')
            );
        }
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
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . self::T . 'tutor}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . self::T . 'relay}', []);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {' . self::T . 'hold} WHERE createdby IS NOT NULL', []);
        $userlist->add_from_sql(
            'createdby',
            'SELECT createdby FROM {' . self::T . 'stocktake} WHERE createdby IS NOT NULL',
            []
        );
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . self::T . 'stockread} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql(
            'createdby',
            'SELECT createdby FROM {' . self::T . 'withdrawal} WHERE createdby IS NOT NULL',
            []
        );
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {' . self::T . 'lot} WHERE createdby IS NOT NULL', []);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {' . self::T . 'digital} WHERE createdby IS NOT NULL', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . self::T . 'digitalread}', []);
        $userlist->add_from_sql('notedby', 'SELECT notedby FROM {' . self::T . 'voiceconsent} WHERE notedby IS NOT NULL', []);
        $userlist->add_from_sql('invitedby', 'SELECT invitedby FROM {' . self::T . 'voiceinvite} WHERE invitedby IS NOT NULL', []);
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
            $holds = [];
            foreach ($DB->get_records(self::T . 'hold', ['readerid' => $reader->id], 'timecreated') as $hold) {
                $holds[] = [
                    'title' => $DB->get_field(self::T . 'record', 'title', ['id' => $hold->recordid]),
                    'status' => $hold->status,
                    'created' => transform::datetime($hold->timecreated),
                    'ready' => $hold->timeready ? transform::datetime($hold->timeready) : null,
                    'expires' => $hold->timeexpires ? transform::datetime($hold->timeexpires) : null,
                    'notified' => $hold->timenotified ? transform::datetime($hold->timenotified) : null,
                    'closed' => $hold->timeclosed ? transform::datetime($hold->timeclosed) : null,
                ];
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
                    'holds' => $holds,
                    'voicepermission' => array_values(array_map(
                        fn($c) => ['noted' => transform::datetime($c->timecreated),
                        'revoked' => $c->timerevoked ? transform::datetime($c->timerevoked) : null],
                        $DB->get_records(self::T . 'voiceconsent', ['readerid' => $reader->id], 'timecreated')
                    )),
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
        $stocktakes = $DB->get_records_sql(
            'SELECT s.id, s.name, s.timecreated, s.createdby,
                    (SELECT COUNT(1) FROM {' . self::T . 'stockread} r WHERE r.stocktakeid = s.id AND r.userid = :a) AS codes
               FROM {' . self::T . 'stocktake} s
              WHERE s.createdby = :b OR EXISTS (SELECT 1 FROM {' . self::T . 'stockread} r2
                                                 WHERE r2.stocktakeid = s.id AND r2.userid = :c)
           ORDER BY s.timecreated',
            ['a' => $userid, 'b' => $userid, 'c' => $userid]
        );
        if ($stocktakes) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:stocktake', 'local_oksigeniaayvurenda')]),
                (object) ['stocktakes' => array_values(array_map(fn($s) => ['name' => $s->name,
                    'created' => transform::datetime($s->timecreated), 'started' => (int) $s->createdby === (int) $userid,
                    'codesread' => (int) $s->codes], $stocktakes))]
            );
        }
        $withdrawals = $DB->get_records(self::T . 'withdrawal', ['createdby' => $userid], 'timecreated', 'id, reason, timecreated');
        if ($withdrawals) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:withdrawal', 'local_oksigeniaayvurenda')]),
                (object) ['withdrawals' => array_values(array_map(fn($w) => ['reason' => $w->reason,
                    'created' => transform::datetime($w->timecreated)], $withdrawals))]
            );
        }
        $lots = $DB->get_records(self::T . 'lot', ['createdby' => $userid], 'timecreated', 'id, name, timecreated');
        if ($lots) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:lot', 'local_oksigeniaayvurenda')]),
                (object) ['lots' => array_values(array_map(fn($l) => ['name' => $l->name,
                    'created' => transform::datetime($l->timecreated)], $lots))]
            );
        }
        // The links they sent to families.
        $invites = $DB->get_records(
            self::T . 'voiceinvite',
            ['invitedby' => $userid],
            'timecreated',
            'id, name, timecreated, timeused'
        );
        if ($invites) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:voiceinvites', 'local_oksigeniaayvurenda')]),
                (object) ['invites' => array_values(array_map(fn($i) => ['name' => $i->name,
                    'created' => transform::datetime($i->timecreated),
                    'used' => $i->timeused ? transform::datetime($i->timeused) : null], $invites))]
            );
        }
        // The voices they recorded, with their files.
        foreach ($DB->get_records(self::T . 'digital', ['kind' => 'voice', 'createdby' => $userid], 'id') as $voice) {
            $path = array_merge($base, [get_string('privacy:path:voices', 'local_oksigeniaayvurenda'), (string) $voice->id]);
            writer::with_context($context)->export_data($path, (object) ['title' => $voice->title, 'status' => $voice->status,
                'audience' => $voice->audience, 'signature' => $voice->signature,
                'created' => transform::datetime($voice->timecreated)]);
            writer::with_context($context)->export_area_files($path, 'local_oksigeniaayvurenda', 'digital', $voice->id);
        }
        $places = $DB->get_records_sql('SELECT d.id, r.title, d.position, d.bookmarks, d.timemodified
            FROM {' . self::T . 'digitalread} d
            JOIN {' . self::T . 'digital} g ON g.id = d.digitalid
            JOIN {' . self::T . 'record} r ON r.id = g.recordid
           WHERE d.userid = :userid', ['userid' => $userid]);
        if ($places) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:digitalread', 'local_oksigeniaayvurenda')]),
                (object) ['reading' => array_values(array_map(fn($p) => ['title' => $p->title, 'position' => $p->position,
                    'bookmarks' => json_decode((string) $p->bookmarks, true) ?: [],
                    'modified' => transform::datetime($p->timemodified)], $places))]
            );
        }
        $relayed = $DB->get_records(self::T . 'relay', ['userid' => $userid], 'id', 'id, code, timecreated');
        if ($relayed) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:relay', 'local_oksigeniaayvurenda')]),
                (object) ['codes' => array_values(array_map(fn($r) => ['code' => $r->code,
                    'timecreated' => transform::datetime($r->timecreated)], $relayed))]
            );
        }
        $tutored = $DB->get_records_sql(
            'SELECT t.id, y.name AS year, t.groupname, t.timenotified
               FROM {' . self::T . 'tutor} t
               JOIN {' . self::T . 'year} y ON y.id = t.yearid
              WHERE t.userid = :userid
           ORDER BY y.timestart, t.groupname',
            ['userid' => $userid]
        );
        if ($tutored) {
            writer::with_context($context)->export_data(
                array_merge($base, [get_string('privacy:path:tutor', 'local_oksigeniaayvurenda')]),
                (object) ['groups' => array_values(array_map(fn($t) => [
                    'year' => $t->year,
                    'group' => $t->groupname,
                    'timenotified' => $t->timenotified ? transform::datetime($t->timenotified) : null,
                ], $tutored))]
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
        $DB->delete_records(self::T . 'tutor');
        $DB->delete_records(self::T . 'relay');
        $DB->set_field_select(self::T . 'hold', 'createdby', null, 'createdby IS NOT NULL');
        $DB->set_field_select(self::T . 'stocktake', 'createdby', null, 'createdby IS NOT NULL');
        $DB->set_field_select(self::T . 'stockread', 'userid', null, 'userid IS NOT NULL');
        $DB->set_field_select(self::T . 'withdrawal', 'createdby', null, 'createdby IS NOT NULL');
        $DB->set_field_select(self::T . 'lot', 'createdby', null, 'createdby IS NOT NULL');
        foreach ($DB->get_records(self::T . 'digital', ['kind' => 'voice']) as $voice) {
            \local_oksigeniaayvurenda\local\voices::remove($voice);
        }
        $DB->set_field_select(self::T . 'digital', 'createdby', null, 'createdby IS NOT NULL');
        $DB->delete_records(self::T . 'digitalread');
        $DB->delete_records(self::T . 'voiceconsent');
        $DB->delete_records(self::T . 'voiceinvite');
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
            $DB->delete_records(self::T . 'voiceconsent', ['readerid' => $reader->id]);
            // Their reservations go; a copy set aside for them goes back to the shelf or to the next.
            foreach ($DB->get_records(self::T . 'hold', ['readerid' => $reader->id]) as $hold) {
                if ($hold->status === 'ready' || $hold->status === 'waiting') {
                    \local_oksigeniaayvurenda\local\holds::cancel((int) $hold->id, $now);
                }
            }
            $DB->delete_records(self::T . 'hold', ['readerid' => $reader->id]);
        }
        $DB->set_field(self::T . 'loan', 'staffid', null, ['staffid' => $userid]);
        $DB->set_field(self::T . 'loan', 'returnstaffid', null, ['returnstaffid' => $userid]);
        $DB->delete_records(self::T . 'tutor', ['userid' => $userid]);
        $DB->delete_records(self::T . 'relay', ['userid' => $userid]);
        $DB->set_field(self::T . 'hold', 'createdby', null, ['createdby' => $userid]);
        $DB->set_field(self::T . 'stocktake', 'createdby', null, ['createdby' => $userid]);
        $DB->set_field(self::T . 'stockread', 'userid', null, ['userid' => $userid]);
        $DB->set_field(self::T . 'withdrawal', 'createdby', null, ['createdby' => $userid]);
        $DB->set_field(self::T . 'lot', 'createdby', null, ['createdby' => $userid]);
        // Their voices go, with their files; the permissions noted for their readers too.
        foreach ($DB->get_records(self::T . 'digital', ['kind' => 'voice', 'createdby' => $userid]) as $voice) {
            \local_oksigeniaayvurenda\local\voices::remove($voice);
        }
        $DB->set_field(self::T . 'digital', 'createdby', null, ['createdby' => $userid]);
        $DB->delete_records(self::T . 'digitalread', ['userid' => $userid]);
        $DB->set_field(self::T . 'voiceconsent', 'notedby', null, ['notedby' => $userid]);
        $DB->set_field(self::T . 'voiceinvite', 'invitedby', null, ['invitedby' => $userid]);
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
        if ($DB->record_exists(self::T . 'tutor', ['userid' => $userid])) {
            return true;
        }
        if ($DB->record_exists(self::T . 'relay', ['userid' => $userid])) {
            return true;
        }
        if ($DB->record_exists(self::T . 'hold', ['createdby' => $userid])) {
            return true;
        }
        if (
            $DB->record_exists(self::T . 'stocktake', ['createdby' => $userid]) ||
            $DB->record_exists(self::T . 'stockread', ['userid' => $userid]) ||
            $DB->record_exists(self::T . 'withdrawal', ['createdby' => $userid]) ||
            $DB->record_exists(self::T . 'lot', ['createdby' => $userid]) ||
            $DB->record_exists(self::T . 'digital', ['createdby' => $userid]) ||
            $DB->record_exists(self::T . 'digitalread', ['userid' => $userid]) ||
            $DB->record_exists(self::T . 'voiceconsent', ['notedby' => $userid]) ||
            $DB->record_exists(self::T . 'voiceinvite', ['invitedby' => $userid])
        ) {
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
            'conditionout' => $loan->conditionout ?? null,
            'conditionin' => $loan->conditionin ?? null,
        ];
    }
}
