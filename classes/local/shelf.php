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
 * The shelf of a classroom library: its teacher lends with two taps, without codes or dates.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The teacher is not a librarian and has no scanner: a Chromebook, twenty-five children and five minutes. The children
 * of its groups by name, the books of the shelf by cover: a name and a cover lend it, until the end of the school year;
 * a lent cover brings it back. What each child read comes out by itself, because it is the same history as the
 * central library's. The tutors of its groups use it; so does the library staff. A shelf may serve one group or several
 * (two classes that share it, a cycle).
 */
final class shelf {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The groups of a classroom library (one before 0.18.0 was kept as group).
     *
     * @param \stdClass $library
     * @return string[]
     */
    public static function groups(\stdClass $library): array {
        if ($library->kind !== 'classroom') {
            return [];
        }
        $settings = json_decode((string) $library->settings, true) ?: [];
        $groups = (array) ($settings['groups'] ?? [$settings['group'] ?? '']);
        return array_values(array_filter(array_map(fn($g) => trim((string) $g), $groups), fn($g) => $g !== ''));
    }

    /**
     * Whether someone may use the shelf: the library staff, or a tutor of one of its groups this year.
     *
     * @param \stdClass $library
     * @param int $userid
     * @param int|null $now
     * @return bool
     */
    public static function can_use(\stdClass $library, int $userid, ?int $now = null): bool {
        global $DB;
        if ($library->kind !== 'classroom') {
            return false;
        }
        if (access::can('local/oksigeniaayvurenda:desk', $library, $userid)) {
            return true;
        }
        $groups = self::groups($library);
        if (!$groups || !$userid) {
            return false;
        }
        $year = years::around((int) $library->entityid, $now ?? time());
        [$in, $params] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
        return $DB->record_exists_select(
            self::T . 'tutor',
            "yearid = :yearid AND userid = :userid AND groupname $in",
            $params + ['yearid' => $year->id, 'userid' => $userid]
        );
    }

    /**
     * The children of its groups this year, by group and name, with how many books of the shelf they have.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array[] id, name, group, has.
     */
    public static function children(\stdClass $library, ?int $now = null): array {
        global $DB;
        $groups = self::groups($library);
        if (!$groups) {
            return [];
        }
        $year = years::around((int) $library->entityid, $now ?? time());
        [$in, $params] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.userid, r.firstname, r.lastname, r.cardcode, MIN(m.groupname) AS groupname,
                    (SELECT COUNT(1) FROM {" . self::T . "loan} l
                      WHERE l.readerid = r.id AND l.libraryid = :libraryid AND l.timereturned IS NULL) AS has
               FROM {" . self::T . "member} m
               JOIN {" . self::T . "reader} r ON r.id = m.readerid
              WHERE m.yearid = :yearid AND m.groupname $in AND r.status = 'active'
           GROUP BY r.id, r.userid, r.firstname, r.lastname, r.cardcode",
            $params + ['libraryid' => $library->id, 'yearid' => $year->id]
        );
        // The same in Moodle 4.5 and 5.3, where user_get_users_by_id() is deprecated.
        $users = $DB->get_records_list('user', 'id', array_values(array_filter(array_map(fn($r) => (int) $r->userid, $rows))));
        $children = [];
        foreach ($rows as $row) {
            $user = $row->userid ? ($users[$row->userid] ?? null) : null;
            $name = $user ? fullname($user) : trim($row->firstname . ' ' . $row->lastname);
            $children[] = ['id' => (int) $row->id, 'name' => $name !== '' ? $name : $row->cardcode,
                'group' => (string) $row->groupname, 'has' => (int) $row->has];
        }
        usort($children, fn($a, $b) => strnatcasecmp($a['group'], $b['group']) ?: strcoll($a['name'], $b['name']));
        return $children;
    }

    /**
     * The books of the shelf: free first, then lent with whom.
     *
     * @param \stdClass $library
     * @return array[] itemid, recordid, title, cover, colour, lent (name of the child), loanid.
     */
    public static function books(\stdClass $library): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT i.id, i.recordid, i.status, r.title, l.id AS loanid, l.readerid
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
          LEFT JOIN {" . self::T . "loan} l ON l.itemid = i.id AND l.timereturned IS NULL
              WHERE i.libraryid = :libraryid AND i.status IN ('available', 'onloan', 'held')
           ORDER BY r.title, i.id",
            ['libraryid' => $library->id]
        );
        $covers = covers::urls(array_values(array_unique(array_map(fn($r) => (int) $r->recordid, $rows))));
        $desk = new desk($library, 0);
        $books = [];
        foreach ($rows as $row) {
            $books[] = [
                'itemid' => (int) $row->id,
                'recordid' => (int) $row->recordid,
                'title' => $row->title,
                'cover' => $covers[(int) $row->recordid] ?? null,
                'colour' => (int) $row->recordid % 6,
                'lent' => $row->loanid ? $desk->reader((int) $row->readerid)['name'] : '',
                'loanid' => (int) $row->loanid,
            ];
        }
        usort($books, fn($a, $b) => [$a['lent'] !== '', $a['title']] <=> [$b['lent'] !== '', $b['title']]);
        return $books;
    }

    /**
     * Lends a book of the shelf to a child of one of its groups, until the end of the school year.
     *
     * @param \stdClass $library
     * @param int $itemid
     * @param int $readerid
     * @param int $staffid
     * @param int|null $now
     * @return int The loan, or 0 if it could not be lent.
     */
    public static function lend(\stdClass $library, int $itemid, int $readerid, int $staffid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $library->id]);
        if (!$item || !in_array($item->status, ['available', 'held'], true)) {
            return 0;
        }
        $set = $item->status === 'held' ? holds::ready_for((int) $item->id) : null;
        if ($set && (int) $set->readerid !== $readerid) {
            return 0;
        }
        $year = years::around((int) $library->entityid, $now);
        $groups = self::groups($library);
        if (!$groups) {
            return 0;
        }
        [$in, $params] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
        $group = $DB->get_field_select(self::T . 'member', 'groupname', "readerid = :readerid AND yearid = :yearid
            AND groupname $in", $params + ['readerid' => $readerid, 'yearid' => $year->id], IGNORE_MULTIPLE);
        if ($group === false) {
            return 0;
        }
        $transaction = $DB->start_delegated_transaction();
        $loanid = (int) $DB->insert_record(self::T . 'loan', [
            'libraryid' => $library->id,
            'itemid' => $item->id,
            'readerid' => $readerid,
            'yearid' => $year->id,
            'groupname' => $group,
            'timeloaned' => $now,
            'timedue' => max($now + DAYSECS, (int) $year->timeend - 1),
            'renewals' => 0,
            'staffid' => $staffid ?: null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'onloan', 'timemodified' => $now]);
        holds::after_loan($item, $readerid, $loanid, $now);
        $transaction->allow_commit();
        return $loanid;
    }

    /**
     * Brings a lent book of the shelf back.
     *
     * @param \stdClass $library
     * @param int $itemid
     * @param int $staffid
     * @param int|null $now
     * @return int The loan it closed, or 0.
     */
    public static function give_back(\stdClass $library, int $itemid, int $staffid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $library->id]);
        $loan = $item ? $DB->get_record_select(
            self::T . 'loan',
            'itemid = :itemid AND timereturned IS NULL',
            ['itemid' => $item->id],
            '*',
            IGNORE_MULTIPLE
        ) : null;
        if (!$loan) {
            return 0;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'loan', (object) ['id' => $loan->id, 'timereturned' => $now,
            'returnstaffid' => $staffid ?: null, 'timemodified' => $now]);
        $DB->update_record(self::T . 'item', (object) ['id' => $item->id, 'status' => 'available', 'timemodified' => $now]);
        $item->status = 'available';
        lots::come_home($item, $now);
        holds::place($item, $now);
        $transaction->allow_commit();
        return (int) $loan->id;
    }

    /**
     * What each child of its groups read from the shelf this year, the newest first.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array[] name and books (titles).
     */
    public static function read(\stdClass $library, ?int $now = null): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now ?? time());
        $loans = $DB->get_records_sql(
            'SELECT l.id, l.readerid, r.title
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.yearid = :yearid
           ORDER BY l.timeloaned DESC, l.id DESC',
            ['libraryid' => $library->id, 'yearid' => $year->id]
        );
        $by = [];
        foreach ($loans as $loan) {
            $by[$loan->readerid][] = $loan->title;
        }
        $list = [];
        foreach (self::children($library, $now) as $child) {
            $list[] = ['name' => $child['name'], 'group' => $child['group'],
                'books' => array_values(array_unique($by[$child['id']] ?? []))];
        }
        return $list;
    }
}
