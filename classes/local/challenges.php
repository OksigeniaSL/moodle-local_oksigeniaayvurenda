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
 * The challenges of a class: one each term, cooperative and anonymous, measured at the desk; the ones done are the
 * class's garden.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Three kinds, all from what happens at the desk, never from what a child says: books from six different corners
 * between them all; twenty returns with their one-tap note; everyone in the class takes a book home. The progress is
 * marks that light up; nobody is named, nobody is ranked, and what is done stays for the whole school year.
 */
final class challenges {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array Kinds of challenge and their target; «everyone» is the size of the group. */
    public const KINDS = ['corners' => 6, 'notes' => 20, 'everyone' => 0];

    /**
     * The term a moment falls in, of a school year: its thirds.
     *
     * @param \stdClass $year
     * @param int $time
     * @return int 1, 2 or 3.
     */
    public static function term(\stdClass $year, int $time): int {
        $span = max(1, (int) $year->timeend - (int) $year->timestart);
        return max(1, min(3, 1 + intdiv(3 * max(0, $time - (int) $year->timestart), $span)));
    }

    /**
     * When a term starts and ends.
     *
     * @param \stdClass $year
     * @param int $term
     * @return int[] start and end.
     */
    public static function bounds(\stdClass $year, int $term): array {
        $span = (int) $year->timeend - (int) $year->timestart;
        return [(int) $year->timestart + intdiv($span * ($term - 1), 3), (int) $year->timestart + intdiv($span * $term, 3)];
    }

    /**
     * The challenge of a group this term: the one chosen, or the next in turn; null when its tutor turned them off.
     *
     * @param int $entityid
     * @param string $group
     * @param int|null $now
     * @return \stdClass|null With kind, target, term, done (marks lit), complete and the record if there is one.
     */
    public static function of(int $entityid, string $group, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        $term = self::term($year, $now);
        $row = $DB->get_record(self::T . 'challenge', ['yearid' => $year->id, 'groupname' => $group, 'term' => $term]);
        if ($row && $row->kind === 'off') {
            return null;
        }
        $kinds = array_keys(self::KINDS);
        $kind = $row ? $row->kind : $kinds[($term - 1) % count($kinds)];
        [$start, $end] = self::bounds($year, $term);
        $members = $DB->get_fieldset_select(
            self::T . 'member',
            'readerid',
            'yearid = :yearid AND groupname = :groupname',
            ['yearid' => $year->id, 'groupname' => $group]
        );
        $target = self::KINDS[$kind] ?: count($members);
        $done = $members ? self::progress($kind, array_map('intval', $members), $start, min($end, $now)) : 0;
        $challenge = (object) ['yearid' => (int) $year->id, 'group' => $group, 'term' => $term, 'kind' => $kind,
            'target' => $target, 'done' => min($done, $target), 'complete' => $target > 0 && $done >= $target,
            'chosen' => (bool) $row, 'id' => $row ? (int) $row->id : 0];
        // A challenge met is kept, with when: it is the class's garden.
        if ($challenge->complete) {
            $challenge->id = self::keep($challenge, $row, $now);
        }
        return $challenge;
    }

    /**
     * How far a group has got.
     *
     * @param string $kind
     * @param int[] $readers
     * @param int $start
     * @param int $end
     * @return int
     */
    private static function progress(string $kind, array $readers, int $start, int $end): int {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal($readers, SQL_PARAMS_NAMED);
        $params += ['start' => $start, 'end' => $end];
        if ($kind === 'everyone') {
            return (int) $DB->count_records_sql("SELECT COUNT(DISTINCT l.readerid) FROM {" . self::T . "loan} l
                WHERE l.readerid $in AND l.timeloaned >= :start AND l.timeloaned < :end", $params);
        }
        if ($kind === 'notes') {
            return (int) $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::T . "loan} l
                WHERE l.readerid $in AND l.timereturned >= :start AND l.timereturned < :end
                  AND l.returnnote IS NOT NULL AND l.returnnote <> ''", $params);
        }
        $codes = $DB->get_fieldset_sql("SELECT DISTINCT a.code
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE l.readerid $in AND l.timeloaned >= :start AND l.timeloaned < :end", $params);
        $tops = [];
        foreach ($codes as $code) {
            if (preg_match('/^\s*(\d)/', (string) $code, $m)) {
                $tops[$m[1]] = true;
            }
        }
        return count($tops);
    }

    /**
     * Keeps a challenge met, once.
     *
     * @param \stdClass $challenge
     * @param \stdClass|false $row
     * @param int $now
     * @return int Its id.
     */
    private static function keep(\stdClass $challenge, $row, int $now): int {
        global $DB;
        if ($row) {
            if (!$row->timecompleted) {
                $DB->set_field(self::T . 'challenge', 'timecompleted', $now, ['id' => $row->id]);
            }
            return (int) $row->id;
        }
        return (int) $DB->insert_record(self::T . 'challenge', (object) ['yearid' => $challenge->yearid,
            'groupname' => $challenge->group, 'term' => $challenge->term, 'kind' => $challenge->kind,
            'timecompleted' => $now, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Chooses the challenge of a group this term, or none («off»).
     *
     * @param int $entityid
     * @param string $group
     * @param string $kind One of KINDS, or off.
     * @param int|null $now
     */
    public static function choose(int $entityid, string $group, string $kind, ?int $now = null): void {
        global $DB;
        if ($kind !== 'off' && !isset(self::KINDS[$kind])) {
            throw new \coding_exception('Unknown challenge');
        }
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        $term = self::term($year, $now);
        $row = $DB->get_record(self::T . 'challenge', ['yearid' => $year->id, 'groupname' => $group, 'term' => $term]);
        if ($row && $row->timecompleted) {
            return;
        }
        if ($row) {
            $DB->update_record(self::T . 'challenge', (object) ['id' => $row->id, 'kind' => $kind, 'timemodified' => $now]);
            return;
        }
        $DB->insert_record(self::T . 'challenge', (object) ['yearid' => $year->id, 'groupname' => $group, 'term' => $term,
            'kind' => $kind, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * What a challenge shows: its words and its marks; and when met, what the herald says.
     *
     * @param \stdClass $challenge
     * @param int $entityid
     * @param bool $staff For the staff: the term, not «your class».
     * @return array heading, text, marks (lit), progresslabel, left, complete, herald.
     */
    public static function view(\stdClass $challenge, int $entityid, bool $staff = false): array {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $text = match ($challenge->kind) {
            'corners' => $str('challengecorners', $challenge->target),
            'notes' => $str('challengenotes', $challenge->target),
            default => $str('challengeeveryone'),
        };
        $marks = [];
        for ($i = 0; $i < $challenge->target; $i++) {
            $marks[] = ['lit' => $i < $challenge->done];
        }
        $left = $challenge->kind === 'everyone' && !$challenge->complete && $challenge->target > $challenge->done
            ? $str('challengeeveryoneleft', $challenge->target - $challenge->done) : '';
        return [
            'heading' => $staff ? $str('challengeterm', $challenge->term) : $str('challengeheading'),
            'text' => $text,
            'marks' => $marks,
            'progresslabel' => $str('challengeprogress', (object) ['done' => $challenge->done, 'target' => $challenge->target]),
            'left' => $left,
            'complete' => $challenge->complete,
            'herald' => $challenge->complete
                ? characters::say($entityid, 'herald', 'challengeherald', ['group' => $challenge->group]) : null,
        ];
    }

    /**
     * The class's garden: the challenges it met this school year.
     *
     * @param int $entityid
     * @param string $group
     * @param int|null $now
     * @return array[] term, kind, time.
     */
    public static function garden(int $entityid, string $group, ?int $now = null): array {
        global $DB;
        $year = years::around($entityid, $now ?? time());
        $rows = $DB->get_records_select(self::T . 'challenge', 'yearid = :yearid AND groupname = :groupname
            AND timecompleted IS NOT NULL', ['yearid' => $year->id, 'groupname' => $group], 'term');
        return array_values(array_map(fn($r) => ['term' => (int) $r->term, 'kind' => $r->kind,
            'time' => (int) $r->timecompleted], $rows));
    }
}
