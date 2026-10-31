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
 * The textbook bank: a library of its own whose books go to each pupil for the school year, by the set of their grade.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Each set of a bank is a grade's textbooks (its titles) for the groups it serves; a pupil gets the set of their group
 * this year, lent until the year ends, with the state of each copy noted when it goes and when it comes back. What the
 * design is, in docs/20-banco-de-libros.md of the project.
 */
final class bank {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string The kind of library a bank is. */
    public const KIND = 'bank';

    /**
     * Whether a library is a textbook bank.
     *
     * @param \stdClass|null $library
     * @return bool
     */
    public static function is(?\stdClass $library): bool {
        return $library !== null && ($library->kind ?? '') === self::KIND;
    }

    /**
     * A condition that leaves out the loans of the textbook banks: reading is counted without them (challenges, the
     * flower, the year's figures), since a pupil takes a whole set of textbooks at once.
     *
     * @param string $column The loan's library column, as the query names it.
     * @return string SQL without parameters.
     */
    public static function reading(string $column): string {
        return "$column NOT IN (SELECT bk.id FROM {" . self::T . "library} bk WHERE bk.kind = '" . self::KIND . "')";
    }

    /**
     * The titles of a centre that only its textbook banks have: they stay out of the reading catalogue.
     *
     * @param int $entityid
     * @return int[] Record ids.
     */
    public static function only(int $entityid): array {
        global $DB;
        if (!$DB->record_exists(self::T . 'library', ['entityid' => $entityid, 'kind' => self::KIND])) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT i.recordid
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "library} b ON b.id = i.libraryid
              WHERE b.entityid = :entityid AND b.kind = :kind
                AND NOT EXISTS (SELECT 1
                                  FROM {" . self::T . "item} o
                                  JOIN {" . self::T . "library} ol ON ol.id = o.libraryid
                                 WHERE o.recordid = i.recordid AND ol.kind <> :kind2)",
            ['entityid' => $entityid, 'kind' => self::KIND, 'kind2' => self::KIND]
        ));
    }

    /**
     * The sets of a bank, in order, each with its groups and titles.
     *
     * @param \stdClass $library
     * @return array[] id, name, groups, titles (recordid, title).
     */
    public static function sets(\stdClass $library): array {
        global $DB;
        $sets = [];
        foreach ($DB->get_records(self::T . 'bankset', ['libraryid' => $library->id], 'sortorder, name, id') as $set) {
            $titles = $DB->get_records_sql(
                "SELECT t.id, t.recordid, r.title
                   FROM {" . self::T . "banktitle} t
                   JOIN {" . self::T . "record} r ON r.id = t.recordid
                  WHERE t.setid = :setid
               ORDER BY t.sortorder, r.title",
                ['setid' => $set->id]
            );
            $sets[] = ['id' => (int) $set->id, 'name' => $set->name, 'groups' => self::groups_of($set),
                'titles' => array_values(array_map(fn($t) => ['recordid' => (int) $t->recordid, 'title' => $t->title], $titles))];
        }
        return $sets;
    }

    /**
     * Makes a set, or changes its name and its groups.
     *
     * @param \stdClass $library
     * @param string $name
     * @param string[] $groups
     * @param int $setid 0 for a new one.
     * @return int The set.
     */
    public static function save_set(\stdClass $library, string $name, array $groups, int $setid = 0): int {
        global $DB;
        $name = \core_text::substr(trim($name), 0, 100);
        $groups = array_values(array_unique(array_filter(array_map(fn($g) => trim((string) $g), $groups), fn($g) => $g !== '')));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        $now = time();
        if ($setid && $DB->record_exists(self::T . 'bankset', ['id' => $setid, 'libraryid' => $library->id])) {
            $DB->update_record(self::T . 'bankset', (object) ['id' => $setid, 'name' => $name !== '' ? $name : '—',
                'groups' => json_encode($groups), 'timemodified' => $now]);
            return $setid;
        }
        $order = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . self::T . 'bankset} WHERE libraryid = ?',
            [$library->id]
        );
        return (int) $DB->insert_record(self::T . 'bankset', ['libraryid' => $library->id, 'name' => $name !== '' ? $name : '—',
            'groups' => json_encode($groups), 'sortorder' => $order + 1, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Takes a set away (its copies and loans stay).
     *
     * @param \stdClass $library
     * @param int $setid
     */
    public static function remove_set(\stdClass $library, int $setid): void {
        global $DB;
        if ($DB->record_exists(self::T . 'bankset', ['id' => $setid, 'libraryid' => $library->id])) {
            $DB->delete_records(self::T . 'banktitle', ['setid' => $setid]);
            $DB->delete_records(self::T . 'bankset', ['id' => $setid]);
        }
    }

    /**
     * Puts a title of the centre's catalogue in a set, once.
     *
     * @param \stdClass $library
     * @param int $setid
     * @param int $recordid
     * @return bool Whether it went in.
     */
    public static function add_title(\stdClass $library, int $setid, int $recordid): bool {
        global $DB;
        if (
            !$DB->record_exists(self::T . 'bankset', ['id' => $setid, 'libraryid' => $library->id])
                || !$DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $library->entityid])
                || $DB->record_exists(self::T . 'banktitle', ['setid' => $setid, 'recordid' => $recordid])
        ) {
            return false;
        }
        $order = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::T . 'banktitle} WHERE setid = ?', [$setid]);
        $DB->insert_record(self::T . 'banktitle', ['setid' => $setid, 'recordid' => $recordid, 'sortorder' => $order + 1,
            'timecreated' => time()]);
        return true;
    }

    /**
     * Takes a title out of a set.
     *
     * @param \stdClass $library
     * @param int $setid
     * @param int $recordid
     */
    public static function remove_title(\stdClass $library, int $setid, int $recordid): void {
        global $DB;
        if ($DB->record_exists(self::T . 'bankset', ['id' => $setid, 'libraryid' => $library->id])) {
            $DB->delete_records(self::T . 'banktitle', ['setid' => $setid, 'recordid' => $recordid]);
        }
    }

    /**
     * What a pupil has of the set of their group this year: each title, and whether a copy of it is out with them.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @return array|null set (name), titles (title, out), missing (how many still to give); null without a set.
     */
    public static function of_reader(\stdClass $library, int $readerid, int $now): ?array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $groups = $DB->get_fieldset_select(
            self::T . 'member',
            'groupname',
            'readerid = :readerid AND yearid = :yearid',
            ['readerid' => $readerid, 'yearid' => $year->id]
        );
        foreach (self::sets($library) as $set) {
            if (!array_intersect($set['groups'], array_map('strval', $groups))) {
                continue;
            }
            $out = $DB->get_fieldset_sql(
                "SELECT i.recordid
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                  WHERE l.libraryid = :libraryid AND l.readerid = :readerid AND l.timereturned IS NULL",
                ['libraryid' => $library->id, 'readerid' => $readerid]
            );
            $out = array_map('intval', $out);
            $titles = array_map(fn($t) => ['title' => $t['title'], 'out' => in_array($t['recordid'], $out, true)], $set['titles']);
            return ['set' => $set['name'], 'titles' => $titles,
                'missing' => count(array_filter($titles, fn($t) => !$t['out']))];
        }
        return null;
    }

    /**
     * The classes of a bank, for handing out in September and collecting in June: the groups of its sets this year and
     * the groups that still have books from it (as they were called when they took them), each with its pupils, the
     * pupils who still hold books, the books out and the pupils still waiting for some of their set.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] group, pupils, holding, out, waiting.
     */
    public static function classes(\stdClass $library, int $now): array {
        global $DB;
        $groups = [];
        foreach (self::sets($library) as $set) {
            foreach ($set['groups'] as $group) {
                $groups[$group] = true;
            }
        }
        $held = $DB->get_records_sql(
            "SELECT l.groupname, COUNT(DISTINCT l.readerid) AS holding, COUNT(1) AS lent
               FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND l.groupname IS NOT NULL
           GROUP BY l.groupname",
            ['libraryid' => $library->id]
        );
        foreach (array_keys($held) as $group) {
            $groups[$group] = true;
        }
        $out = [];
        foreach (array_keys($groups) as $group) {
            $roll = self::roll($library, (string) $group, $now);
            $out[] = ['group' => (string) $group, 'pupils' => count(array_filter($roll, fn($p) => $p['member'])),
                'holding' => (int) ($held[$group]->holding ?? 0), 'out' => (int) ($held[$group]->lent ?? 0),
                'waiting' => count(array_filter($roll, fn($p) => $p['missing']))];
        }
        usort($out, fn($a, $b) => strnatcasecmp($a['group'], $b['group']));
        return $out;
    }

    /**
     * One class of a bank: its pupils this year and whoever still has books taken while in it, each with the books they
     * hold (with the state they went in) and the titles of their set still to give them.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int $now
     * @return array[] readerid, name, card, member, held (title, barcode, condition, due), missing (titles).
     */
    public static function roll(\stdClass $library, string $group, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $fields = 'r.id, r.firstname, r.lastname, r.cardcode, r.userid';
        $members = $DB->get_records_sql(
            "SELECT $fields
               FROM {" . self::T . "reader} r
               JOIN {" . self::T . "member} m ON m.readerid = r.id
              WHERE m.yearid = :yearid AND m.groupname = :groupname AND r.entityid = :entityid",
            ['yearid' => $year->id, 'groupname' => $group, 'entityid' => $library->entityid]
        );
        $holders = $DB->get_records_sql(
            "SELECT DISTINCT $fields
               FROM {" . self::T . "reader} r
               JOIN {" . self::T . "loan} l ON l.readerid = r.id
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND l.groupname = :groupname",
            ['libraryid' => $library->id, 'groupname' => $group]
        );
        $readers = $members + $holders;
        if (!$readers) {
            return [];
        }
        $titles = [];
        foreach (self::sets($library) as $set) {
            if (in_array($group, $set['groups'], true)) {
                $titles = $set['titles'];
                break;
            }
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($readers), SQL_PARAMS_NAMED);
        $loans = $DB->get_records_sql(
            "SELECT l.id, l.readerid, l.timedue, l.conditionout, i.recordid, i.barcode, r.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.timereturned IS NULL AND l.readerid $in
           ORDER BY r.title, l.id",
            ['libraryid' => $library->id] + $params
        );
        // The same in Moodle 4.5 and 5.3, where user_get_users_by_id() is deprecated.
        $users = $DB->get_records_list('user', 'id', array_values(array_filter(array_map(fn($r) => (int) $r->userid, $readers))));
        $roll = [];
        foreach ($readers as $reader) {
            $held = array_values(array_filter($loans, fn($l) => (int) $l->readerid === (int) $reader->id));
            $have = array_map(fn($l) => (int) $l->recordid, $held);
            $member = isset($members[$reader->id]);
            // By surname, as a class list goes; a reader with an account is called as Moodle calls them.
            $who = $users[$reader->userid] ?? $reader;
            $name = isset($users[$reader->userid]) ? fullname($who) : trim($reader->firstname . ' ' . $reader->lastname);
            $roll[] = ['readerid' => (int) $reader->id, 'name' => $name, 'sort' => \core_text::strtolower(
                trim($who->lastname . ' ' . $who->firstname) ?: $name
            ), 'card' => (string) $reader->cardcode,
                'member' => $member,
                'held' => array_map(fn($l) => ['title' => $l->title, 'barcode' => $l->barcode,
                    'condition' => (string) ($l->conditionout ?: 'good'), 'due' => (int) $l->timedue], $held),
                // Only a pupil of the class this year waits for its set.
                'missing' => $member ? array_values(array_map(
                    fn($t) => $t['title'],
                    array_filter($titles, fn($t) => !in_array((int) $t['recordid'], $have, true))
                )) : []];
        }
        usort($roll, fn($a, $b) => strnatcasecmp($a['sort'], $b['sort']));
        return array_map(function ($p) {
            unset($p['sort']);
            return $p;
        }, $roll);
    }

    /**
     * What each set needs: for each title, the pupils of its groups this year, the copies out with them, the copies that
     * can still go (free and not broken), and how many are short.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] Per set: id, name, pupils, titles (recordid, title, out, free, short, broken, lost).
     */
    public static function needs(\stdClass $library, int $now): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now);
        $out = [];
        foreach (self::sets($library) as $set) {
            $pupils = 0;
            if ($set['groups']) {
                [$in, $params] = $DB->get_in_or_equal($set['groups'], SQL_PARAMS_NAMED);
                $pupils = (int) $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT m.readerid)
                       FROM {" . self::T . "member} m
                       JOIN {" . self::T . "reader} r ON r.id = m.readerid AND r.status = 'active'
                      WHERE m.yearid = :yearid AND m.groupname $in",
                    ['yearid' => $year->id] + $params
                );
            }
            $titles = [];
            foreach ($set['titles'] as $title) {
                $params = ['libraryid' => $library->id, 'recordid' => $title['recordid']];
                $lent = $DB->count_records_select(self::T . 'item', "libraryid = :libraryid AND recordid = :recordid AND
                    status = 'onloan'", $params);
                $free = $DB->count_records_select(self::T . 'item', "libraryid = :libraryid AND recordid = :recordid AND
                    status = 'available' AND (itemcondition IS NULL OR " . $DB->sql_compare_text('itemcondition') .
                    " <> 'broken')", $params);
                // What has to be replaced: copies broken (or being mended) and copies lost.
                $broken = $DB->count_records_select(
                    self::T . 'item',
                    "libraryid = :libraryid AND recordid = :recordid AND
                    (status = 'repair' OR (status = 'available' AND " . $DB->sql_compare_text('itemcondition') . " = 'broken'))",
                    $params
                );
                $lost = $DB->count_records_select(self::T . 'item', "libraryid = :libraryid AND recordid = :recordid AND
                    status = 'lost'", $params);
                $titles[] = $title + ['out' => $lent, 'free' => $free, 'short' => max(0, $pupils - $lent - $free),
                    'broken' => $broken, 'lost' => $lost];
            }
            $out[] = ['id' => $set['id'], 'name' => $set['name'], 'pupils' => $pupils, 'titles' => $titles];
        }
        return $out;
    }

    /**
     * What the family signs: the pupil, their group and year, and each copy out with them from this bank, with its
     * state when it went.
     *
     * @param \stdClass $library
     * @param int $readerid
     * @param int $now
     * @return array name, group, year, copies (title, barcode, condition, date).
     */
    public static function contract(\stdClass $library, int $readerid, int $now): array {
        global $DB;
        $reader = $DB->get_record(self::T . 'reader', ['id' => $readerid, 'entityid' => $library->entityid], '*', MUST_EXIST);
        $year = years::around((int) $library->entityid, $now);
        $name = trim(($reader->firstname ?? '') . ' ' . ($reader->lastname ?? ''));
        if ($reader->userid && ($user = \core_user::get_user($reader->userid))) {
            $name = fullname($user);
        }
        $group = (string) $DB->get_field(
            self::T . 'member',
            'groupname',
            ['readerid' => $readerid, 'yearid' => $year->id],
            IGNORE_MULTIPLE
        );
        $rows = $DB->get_records_sql(
            "SELECT l.id, r.title, i.barcode, l.conditionout, l.timeloaned
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.readerid = :readerid AND l.timereturned IS NULL
           ORDER BY r.title",
            ['libraryid' => $library->id, 'readerid' => $readerid]
        );
        return ['name' => $name, 'group' => $group, 'year' => $year->name, 'copies' => array_values(array_map(fn($r) => [
            'title' => $r->title, 'barcode' => $r->barcode, 'condition' => (string) ($r->conditionout ?: 'good'),
            'date' => (int) $r->timeloaned], $rows))];
    }

    /**
     * The groups of a set.
     *
     * @param \stdClass $set
     * @return string[]
     */
    private static function groups_of(\stdClass $set): array {
        return array_values(array_filter((array) (json_decode((string) $set->groups, true) ?: []), 'is_string'));
    }
}
