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

namespace local_oksigeniaayvurenda\local;

/**
 * The reward of a class that meets its challenge: more reading. With its tutor it chooses the next book; the library gets
 * it (or says it could not), and the herald tells the class when it is here.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wishes {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int For how long the herald tells a class its book is here. */
    public const TELL = 30 * DAYSECS;

    /**
     * The challenges a group met this school year, each with the book it chose (or none yet).
     *
     * @param int $entityid
     * @param string $group
     * @param int|null $now
     * @return array[] challenge (id, term, kind) and wish (record or null).
     */
    public static function rewards(int $entityid, string $group, ?int $now = null): array {
        global $DB;
        $year = years::around($entityid, $now ?? time());
        $met = $DB->get_records_select(self::T . 'challenge', 'yearid = :yearid AND groupname = :groupname
            AND timecompleted IS NOT NULL', ['yearid' => $year->id, 'groupname' => $group], 'term');
        if (!$met) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($met), SQL_PARAMS_NAMED);
        $chosen = [];
        foreach ($DB->get_records_select(self::T . 'wish', "challengeid $in", $params) as $wish) {
            $chosen[(int) $wish->challengeid] = $wish;
        }
        return array_values(array_map(fn($c) => ['challenge' => $c, 'wish' => $chosen[(int) $c->id] ?? null], $met));
    }

    /**
     * The book a class chose for a challenge it met: a new choice replaces one the library could not get.
     *
     * @param int $entityid
     * @param int $challengeid
     * @param string $title
     * @param string $note
     * @param int $userid
     * @param int|null $now
     * @return int Its id, or 0 when there was no title or the challenge is not met.
     */
    public static function ask(int $entityid, int $challengeid, string $title, string $note, int $userid, ?int $now = null): int {
        global $DB;
        $title = \core_text::substr(trim($title), 0, 255);
        $met = $DB->record_exists_sql('SELECT 1 FROM {' . self::T . 'challenge} c
            JOIN {' . self::T . 'year} y ON y.id = c.yearid
           WHERE c.id = :id AND y.entityid = :entityid AND c.timecompleted IS NOT NULL', ['id' => $challengeid,
            'entityid' => $entityid]);
        if ($title === '' || !$met) {
            return 0;
        }
        $now = $now ?? time();
        $row = $DB->get_record(self::T . 'wish', ['challengeid' => $challengeid]);
        $data = ['title' => $title, 'note' => \core_text::substr(trim($note), 0, 255) ?: null, 'userid' => $userid,
            'status' => 'asked', 'decidedby' => null, 'timedecided' => null, 'timemodified' => $now];
        if ($row) {
            if ($row->status !== 'declined') {
                return (int) $row->id;
            }
            $DB->update_record(self::T . 'wish', (object) (['id' => $row->id] + $data));
            return (int) $row->id;
        }
        return (int) $DB->insert_record(self::T . 'wish', (object) ($data + ['entityid' => $entityid,
            'challengeid' => $challengeid, 'timecreated' => $now]));
    }

    /**
     * The books classes chose and the library has not got yet, oldest first.
     *
     * @param int $entityid
     * @return \stdClass[] With the group.
     */
    public static function asked(int $entityid): array {
        global $DB;
        return array_values($DB->get_records_sql('SELECT w.*, c.groupname
               FROM {' . self::T . 'wish} w
               JOIN {' . self::T . 'challenge} c ON c.id = w.challengeid
              WHERE w.entityid = :entityid AND w.status = :status
           ORDER BY w.timemodified, w.id', ['entityid' => $entityid, 'status' => 'asked']));
    }

    /**
     * How many books chosen by classes wait for the library.
     *
     * @param int $entityid
     * @return int
     */
    public static function waiting(int $entityid): int {
        global $DB;
        return $DB->count_records(self::T . 'wish', ['entityid' => $entityid, 'status' => 'asked']);
    }

    /**
     * The library got the book, or could not.
     *
     * @param int $id
     * @param int $entityid
     * @param bool $got
     * @param int $staffid
     * @param int|null $now
     */
    public static function decide(int $id, int $entityid, bool $got, int $staffid, ?int $now = null): void {
        global $DB;
        $row = $DB->get_record(self::T . 'wish', ['id' => $id, 'entityid' => $entityid], '*', MUST_EXIST);
        $now = $now ?? time();
        $DB->update_record(self::T . 'wish', (object) ['id' => $row->id, 'status' => $got ? 'got' : 'declined',
            'decidedby' => $staffid, 'timedecided' => $now, 'timemodified' => $now]);
    }

    /**
     * The book a group chose that arrived lately, for the herald to tell.
     *
     * @param int $entityid
     * @param string $group
     * @param int|null $now
     * @return string|null Its title.
     */
    public static function arrived(int $entityid, string $group, ?int $now = null): ?string {
        global $DB;
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        $rows = $DB->get_records_sql('SELECT w.id, w.title
               FROM {' . self::T . 'wish} w
               JOIN {' . self::T . 'challenge} c ON c.id = w.challengeid
              WHERE c.yearid = :yearid AND c.groupname = :groupname AND w.status = :status AND w.timedecided > :since
           ORDER BY w.timedecided DESC', ['yearid' => $year->id, 'groupname' => $group, 'status' => 'got',
            'since' => $now - self::TELL], 0, 1);
        $row = reset($rows);
        return $row ? (string) $row->title : null;
    }
}
