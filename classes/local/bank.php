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
     * What each set needs: for each title, the pupils of its groups this year, the copies out with them, the copies that
     * can still go (free and not broken), and how many are short.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array[] Per set: id, name, pupils, titles (recordid, title, out, free, short).
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
                $titles[] = $title + ['out' => $lent, 'free' => $free, 'short' => max(0, $pupils - $lent - $free)];
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
