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
 * A reader erased by their library, when they ask for it.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The right to erasure, at the library: the library answers for its readers, and many of them are children with only a
 * card. Everything that says who the reader is goes: their name, card, e-mail and notes, what they wrote, drew and
 * recorded, their reservations, fines, asks and the invitations to their family, and the key their origin knew them by.
 * What stays is anonymous: the loans as numbers, under a reader with no name. On a site shared by centres, their account
 * goes too when it has nothing else there.
 */
final class erasure {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * How many books a reader still has: they come back (or are written off as lost) before the reader goes.
     *
     * @param int $readerid
     * @return int
     */
    public static function open_loans(int $readerid): int {
        global $DB;
        return $DB->count_records_select(self::T . 'loan', 'readerid = ? AND timereturned IS NULL', [$readerid]);
    }

    /**
     * Erases a reader of a library's centre: with no books out, or with the books they have given up as lost.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param bool $lost Whether the books they still have are given up as lost.
     * @param int $staffid Who does it.
     * @param int|null $now
     * @return array done, open (books out), account (whether their account went too).
     */
    public static function erase(\stdClass $library, int $readerid, bool $lost = false, int $staffid = 0, ?int $now = null): array {
        global $CFG, $DB;
        $now = $now ?? time();
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid]);
        if (!$reader || $reader->status === 'deleted') {
            return ['done' => false, 'open' => 0, 'account' => false];
        }
        $open = self::open_loans($readerid);
        if ($open && !$lost) {
            return ['done' => false, 'open' => $open, 'account' => false];
        }
        // The books they still have, given up as lost: the loan closes and the copy is lost.
        foreach ($DB->get_records_select(self::T . 'loan', 'readerid = ? AND timereturned IS NULL', [$readerid]) as $loan) {
            $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => $now,
                'returnstaffid' => $staffid ?: null, 'conditionin' => 'lost', 'timemodified' => $now]);
            $DB->set_field(self::T . 'item', 'status', 'lost', ['id' => $loan->itemid]);
        }
        $userid = (int) $reader->userid;
        self::reader($readerid, $now);
        if ($userid && !$DB->record_exists(self::T . 'reader', ['userid' => $userid])) {
            self::account_traces($userid);
        }
        $account = false;
        if ($userid && self::alone($userid)) {
            require_once($CFG->dirroot . '/user/lib.php');
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            $account = $user && delete_user($user);
        }
        \local_oksigeniaayvurenda\event\reader_erased::fire((int) $library->entityid, $readerid);
        access::forget();
        return ['done' => true, 'open' => 0, 'account' => $account];
    }

    /**
     * Takes out of a reader everything that says who they are; their loans stay as numbers. Also what Moodle's privacy
     * requests do with each reader of an account.
     *
     * @param int $readerid
     * @param int|null $now
     */
    public static function reader(int $readerid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $was = $DB->get_record(self::T . 'reader', ['id' => $readerid], 'id, entityid, userid');
        $DB->update_record(self::T . 'reader', (object) [
            'id' => $readerid,
            'userid' => null,
            'cardcode' => 'deleted-' . $readerid,
            'firstname' => null,
            'lastname' => null,
            'email' => null,
            'notes' => null,
            'accessneeds' => 0,
            'status' => 'deleted',
            'timemodified' => $now,
        ]);
        $DB->set_field(self::T . 'loan', 'returnnote', null, ['readerid' => $readerid]);
        $DB->delete_records(self::T . 'voiceconsent', ['readerid' => $readerid]);
        // The invitations to their family carried their name («Family of …»): the name and the link to them go.
        $DB->set_field(self::T . 'voiceinvite', 'name', null, ['readerid' => $readerid]);
        $DB->set_field(self::T . 'voiceinvite', 'readerid', null, ['readerid' => $readerid]);
        // Their voices go, with their files.
        foreach ($DB->get_records(self::T . 'digital', ['kind' => 'voice', 'readerid' => $readerid]) as $voice) {
            voices::remove($voice);
        }
        // The key their origin knew them by (a file number, an email, a name) goes too. Only a fingerprint of it stays,
        // and one of their account, so that a list, a program or a cohort that still has them does not bring them back.
        foreach ($DB->get_records(self::T . 'source', ['tablename' => 'reader', 'localid' => $readerid]) as $source) {
            $DB->update_record(self::T . 'source', (object) ['id' => $source->id, 'localid' => 0, 'hash' => null,
                'localhash' => null, 'externalid' => import\abies_import::erased((string) $source->externalid)]);
        }
        if ($was && $was->userid) {
            $DB->insert_record(self::T . 'source', ['entityid' => $was->entityid, 'origin' => 'account', 'tablename' => 'reader',
                'externalid' => self::fingerprint((int) $was->userid), 'localid' => 0, 'timecreated' => $now]);
        }
        $drawn = $DB->get_fieldset_select(self::T . 'review', 'id', 'readerid = :readerid', ['readerid' => $readerid]);
        foreach ($drawn as $id) {
            foreach (['reviewdrawing', 'reviewvoice'] as $area) {
                get_file_storage()->delete_area_files(
                    \context_system::instance()->id,
                    'local_oksigeniaayvurenda',
                    $area,
                    (int) $id
                );
            }
        }
        $DB->delete_records(self::T . 'review', ['readerid' => $readerid]);
        // Their reservations go; a copy set aside for them goes back to the shelf or to the next.
        foreach ($DB->get_records(self::T . 'hold', ['readerid' => $readerid]) as $hold) {
            if ($hold->status === 'ready' || $hold->status === 'waiting') {
                holds::cancel((int) $hold->id, $now);
            }
        }
        $DB->delete_records(self::T . 'hold', ['readerid' => $readerid]);
        // What they asked the library to buy stays as a book on a list, without them or what they wrote.
        $asked = $DB->get_fieldset_select(self::T . 'purchasewho', 'purchaseid', 'readerid = ?', [$readerid]);
        if ($asked) {
            [$in, $params] = $DB->get_in_or_equal($asked);
            $DB->set_field_select(self::T . 'purchase', 'note', null, "source = 'reader' AND id $in", $params);
        }
        $DB->delete_records(self::T . 'purchasewho', ['readerid' => $readerid]);
        $DB->delete_records(self::T . 'sanction', ['readerid' => $readerid]);
        $DB->delete_records(self::T . 'formatask', ['readerid' => $readerid]);
        $DB->execute(
            'UPDATE {' . self::T . "ill} SET readerid = NULL, note = NULL WHERE readerid = ? AND direction = 'in'",
            [$readerid]
        );
    }

    /**
     * The fingerprint an erased reader's account leaves.
     *
     * @param int $userid
     * @return string
     */
    public static function fingerprint(int $userid): string {
        return import\abies_import::erased('user:' . $userid);
    }

    /**
     * The fingerprints of who was erased from a centre.
     *
     * @param int $entityid
     * @return array Fingerprint => its key.
     */
    public static function fingerprints(int $entityid): array {
        global $DB;
        return array_flip($DB->get_fieldset_select(
            self::T . 'source',
            'externalid',
            "entityid = ? AND tablename = 'reader' AND localid = 0",
            [$entityid]
        ));
    }

    /**
     * What an account leaves in the library besides its readers: their place in a book, the code of their phone, their
     * choices.
     *
     * @param int $userid
     */
    public static function account_traces(int $userid): void {
        global $DB;
        $DB->delete_records(self::T . 'relay', ['userid' => $userid]);
        $DB->delete_records(self::T . 'digitalread', ['userid' => $userid]);
        foreach (['cardlook', 'stafftold', 'newbooks', 'newbookslast', 'goal', 'lastsearch'] as $name) {
            unset_user_preference('local_oksigeniaayvurenda_' . $name, $userid);
        }
        foreach (array_keys((array) get_user_preferences(null, null, $userid)) as $name) {
            if (str_starts_with($name, 'local_oksigeniaayvurenda_stafftold_')) {
                unset_user_preference($name, $userid);
            }
        }
    }

    /**
     * Whether an account is only this reader's, on a site shared by centres: no other card, no role, no course. On a
     * centre's own Moodle the account is the person's, for everything else they do there, and it stays.
     *
     * @param int $userid
     * @return bool
     */
    private static function alone(int $userid): bool {
        global $CFG, $DB;
        if (!access::apart() || is_siteadmin($userid) || $userid === (int) $CFG->siteguest) {
            return false;
        }
        return !$DB->record_exists_select(self::T . 'reader', "userid = ? AND status <> 'deleted'", [$userid])
            && !$DB->record_exists('role_assignments', ['userid' => $userid])
            && !$DB->record_exists('user_enrolments', ['userid' => $userid]);
    }
}
