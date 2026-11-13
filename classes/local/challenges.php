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

    /** @var int How long the herald celebrates a challenge met: two weeks. */
    public const CHEER = 14 * DAYSECS;

    /** @var int The months of classes a school year has, everywhere near enough: the terms are thirds of them. */
    private const CLASSMONTHS = 10;

    /**
     * When each term of a school year ends: the days the centre set in «Academic year», or else thirds of its ten months
     * of classes from the start (the third one is not the summer, D4).
     *
     * @param \stdClass $year
     * @return int[] Three moments, in order.
     */
    public static function ends(\stdClass $year): array {
        global $DB;
        $start = (int) $year->timestart;
        $settings = !empty($year->entityid) ? (json_decode((string) $DB->get_field(
            self::T . 'entity',
            'settings',
            ['id' => $year->entityid]
        ), true) ?: []) : [];
        $set = array_values(array_filter(
            (array) ($settings['termends'] ?? []),
            fn($d) => preg_match('/^\d{2}-\d{2}$/', (string) $d)
        ));
        if (count($set) === 3) {
            $zone = zones::of((int) $year->entityid);
            $ends = [];
            $after = $start;
            foreach ($set as $monthday) {
                // The first such day after the start (or after the term before), at the end of that day.
                $first = (new \DateTimeImmutable('@' . $after))->setTimezone($zone)->format('Y');
                foreach ([$first, (string) ((int) $first + 1)] as $y) {
                    $m = (int) substr($monthday, 0, 2);
                    $d = (int) substr($monthday, 3, 2);
                    if (!checkdate($m, $d, (int) $y)) {
                        continue;
                    }
                    $at = (new \DateTimeImmutable(sprintf('%s-%02d-%02d 23:59:59', $y, $m, $d), $zone))->getTimestamp();
                    if ($at > $after) {
                        $ends[] = $at;
                        $after = $at;
                        break;
                    }
                }
            }
            if (count($ends) === 3 && $ends[2] <= (int) $year->timeend + YEARSECS) {
                return $ends;
            }
        }
        $span = (int) round(min(max(1, (int) $year->timeend - $start), self::CLASSMONTHS * 30.44 * DAYSECS));
        return [$start + intdiv($span, 3), $start + intdiv(2 * $span, 3), $start + $span];
    }

    /**
     * The term a moment falls in, of a school year.
     *
     * @param \stdClass $year
     * @param int $time
     * @return int 1, 2 or 3.
     */
    public static function term(\stdClass $year, int $time): int {
        $ends = self::ends($year);
        foreach ($ends as $i => $end) {
            if ($time <= $end) {
                return $i + 1;
            }
        }
        return 3;
    }

    /** @var int Pennants a garland shows one for each step; past this many, ten, each a tenth (Jujol). */
    public const GARLAND = 12;

    /** @var int On the board, one for each step up to this many: «everyone» in a class of 25 reads well there. */
    public const GARLAND_BOARD = 30;

    /** @var string[] The herald's pose on the board, by the ship's state. */
    private const POSES = ['quedan' => 'senala', 'ultima' => 'senala', 'hecho' => 'celebra', 'zarpa-hecho' => 'celebra',
        'zarpa-sin' => 'piensa'];

    /**
     * A challenge's garland (Jujol): a pennant for each step, coloured when done; with more steps than fit, ten, each a
     * tenth of the way (18 of 25 lights 7), and the words beside it say the number.
     *
     * @param int $done
     * @param int $target
     * @param int $most The most pennants shown one for each step.
     * @return array[] lit, for each pennant.
     */
    public static function garland(int $done, int $target, int $most = self::GARLAND): array {
        $target = max(1, $target);
        if ($target <= $most) {
            return array_map(fn($i) => ['lit' => $i < $done], range(0, $target - 1));
        }
        $lit = (int) floor(min($done, $target) * 10 / $target);
        return array_map(fn($i) => ['lit' => $i < $lit], range(0, 9));
    }

    /**
     * The voyage of a class's ship this term (P5): how its challenge goes against the term's days.
     *
     * @param \stdClass|null $challenge This term's, as of() gives it.
     * @param int $entityid
     * @param int $now
     * @return array|null state (quedan, ultima, hecho, zarpa-hecho, zarpa-sin), days left (1 is the last day) and end;
     *     null without a challenge, or with its term over.
     */
    public static function voyage(?\stdClass $challenge, int $entityid, int $now): ?array {
        if (!$challenge) {
            return null;
        }
        $end = self::bounds(years::around($entityid, $now), (int) $challenge->term)[1];
        $days = (int) ceil(($end - $now) / DAYSECS);
        if ($days < 1) {
            return null;
        }
        $last = $days === 1;
        $open = $last ? 'zarpa-sin' : ($days <= 7 ? 'ultima' : 'quedan');
        $state = $challenge->complete ? ($last ? 'zarpa-hecho' : 'hecho') : $open;
        return ['state' => $state, 'days' => $days, 'end' => $end];
    }

    /**
     * How far a class is, in words: «5 of 6 corners», «18 of 25», «12 of 20 books with their note».
     *
     * @param \stdClass $challenge
     * @param bool $bold The number done in bold (HTML), for the board.
     * @return string
     */
    public static function march(\stdClass $challenge, bool $bold = false): string {
        $done = (int) $challenge->done;
        return get_string('boardmarch' . $challenge->kind, 'local_oksigeniaayvurenda', (object) [
            'done' => $bold ? '<b>' . $done . '</b>' : (string) $done, 'target' => (int) $challenge->target]);
    }

    /**
     * «Enseñar a la clase» (Jujol): the class's board, for the whiteboard of the classroom. The island, the class with
     * its challenge, its garland and how far it is, the harbour's board with the days, and the herald with a sentence
     * to hear. Never a pupil's name.
     *
     * @param \stdClass $challenge This term's.
     * @param string $group
     * @param int $entityid
     * @param int $now
     * @return array|null null without a voyage this term.
     */
    public static function board(\stdClass $challenge, string $group, int $entityid, int $now): ?array {
        $voyage = self::voyage($challenge, $entityid, $now);
        if (!$voyage) {
            return null;
        }
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $state = $voyage['state'];
        $day = zones::date($voyage['end'] - 1, '%A %e', $entityid);
        $text = match ($challenge->kind) {
            'corners' => $str('challengecorners', $challenge->target),
            'notes' => $str('challengenotes', $challenge->target),
            default => $str('challengeeveryone'),
        };
        $sex = (characters::of($entityid)['herald']['sex'] ?? 'm') === 'f' ? 'f' : 'm';
        $voice = screens::listen(['herald-' . $sex . '/{lang}/barco-' . $state]);
        $key = str_replace('-', '', $state);
        return [
            'state' => $state,
            'group' => $group,
            'challenge' => $text,
            'marks' => self::garland((int) $challenge->done, (int) $challenge->target, self::GARLAND_BOARD),
            'progresslabel' => self::march($challenge),
            'march' => self::march($challenge, true),
            // The harbour's board: the days, or the day it sails.
            'days' => in_array($state, ['quedan', 'ultima'], true) ? ['pre' => $str('boardleftpre'),
                'number' => $voyage['days'], 'post' => $str('boardleftpost')] : null,
            'note' => $state === 'ultima' ? $str('boardwaiting') : '',
            'phrase' => match ($state) {
                'hecho' => $str('boardmet'),
                'zarpa-hecho', 'zarpa-sin' => $str('boardsails'),
                default => '',
            },
            'small' => match ($state) {
                'hecho' => $str('boardsailson', $day),
                'zarpa-hecho' => $str('boardsailsmet'),
                default => '',
            },
            'herald' => characters::figure('herald', self::POSES[$state], characters::step($entityid)),
            'says' => $str('boardsays' . $key, $day),
            'voice' => $voice ? $voice['urls'] : '',
        ];
    }

    /**
     * The line of a reader's card about their class's ship (Jujol): how far the class is and when it sails.
     *
     * @param \stdClass|null $challenge The class's, this term.
     * @param int $entityid
     * @param int $now
     * @return array|null state, line (HTML: the numbers in bold), marks and progresslabel; null without a voyage.
     */
    public static function card_line(?\stdClass $challenge, int $entityid, int $now): ?array {
        $voyage = self::voyage($challenge, $entityid, $now);
        if (!$voyage) {
            return null;
        }
        $state = $voyage['state'];
        $a = (object) ['march' => s(self::march($challenge)), 'days' => $voyage['days'],
            'day' => s(zones::date($voyage['end'] - 1, '%A', $entityid))];
        $key = match ($state) {
            'quedan' => 'cardshipleft',
            'ultima' => 'cardshipsoon',
            'hecho' => 'cardshipmet',
            default => 'cardshiptoday',
        };
        return ['state' => $state, 'line' => get_string($key, 'local_oksigeniaayvurenda', $a),
            'marks' => self::garland((int) $challenge->done, (int) $challenge->target), 'progresslabel' => self::march($challenge)];
    }

    /**
     * Where the ship of the term is (P5): off the beach of the class island the last week of the term, waiting with its
     * sail furled, and setting sail its last day; the rest of the term, it is not there. The days are counted as the
     * challenge's clock counts them.
     *
     * @param int $entityid
     * @param int|null $now
     * @return string wait, sail, or nothing.
     */
    public static function ship(int $entityid, ?int $now = null): string {
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        for ($term = 1; $term <= 3; $term++) {
            [$start, $end] = self::bounds($year, $term);
            if ($now >= $start && $now < $end) {
                $days = (int) ceil(($end - $now) / DAYSECS);
                return $days <= 1 ? 'sail' : ($days <= 7 ? 'wait' : '');
            }
        }
        return '';
    }

    /**
     * When a term starts and ends.
     *
     * @param \stdClass $year
     * @param int $term
     * @return int[] start and end.
     */
    public static function bounds(\stdClass $year, int $term): array {
        $ends = self::ends($year);
        $term = max(1, min(3, $term));
        $start = $term === 1 ? (int) $year->timestart : $ends[$term - 2];
        return [$start, $term === 3 ? max($ends[2], (int) $year->timeend) : $ends[$term - 1]];
    }

    /**
     * Sets when each term ends, as month and day, or forgets them with an empty list.
     *
     * @param int $entityid
     * @param string[] $days Three 'Y-m-d' or 'm-d', in order; empty to go back to thirds.
     * @return bool Whether they were kept.
     */
    public static function set_ends(int $entityid, array $days): bool {
        global $DB;
        $monthdays = [];
        foreach ($days as $day) {
            if (!preg_match('/(\d{2})-(\d{2})$/', trim((string) $day), $m) || !checkdate((int) $m[1], (int) $m[2], 2028)) {
                return false;
            }
            $monthdays[] = $m[1] . '-' . $m[2];
        }
        if ($monthdays && count($monthdays) !== 3) {
            return false;
        }
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        if ($monthdays) {
            $settings['termends'] = $monthdays;
        } else {
            unset($settings['termends']);
        }
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
        return true;
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
        if (skins::no_classes($entityid)) {
            return null;
        }
        $now = $now ?? time();
        $year = years::around($entityid, $now);
        if (self::stage_off($entityid, (int) $year->id, $group) !== null) {
            return null;
        }
        $term = self::term($year, $now);
        $row = self::last((int) $year->id, $group, $term);
        if ($row && $row->kind === 'off') {
            return null;
        }
        $kinds = array_keys(self::KINDS);
        $kind = $row ? $row->kind : $kinds[($term - 1) % count($kinds)];
        [$start, $end] = self::bounds($year, $term);
        // Another one after the first was met counts from the day it started, not from the start of the term.
        $seq = $row ? max(1, (int) $row->seq) : 1;
        if ($seq > 1) {
            $start = max($start, (int) $row->timecreated);
        }
        // Those of the class who are here: one who stopped coming keeps their row (the year's history) but is no one to
        // wait for, or «Everyone» would miss one for ever.
        $members = $DB->get_fieldset_sql(
            'SELECT m.readerid FROM {' . self::T . 'member} m
               JOIN {' . self::T . "reader} r ON r.id = m.readerid AND r.status = 'active'
              WHERE m.yearid = :yearid AND m.groupname = :groupname",
            ['yearid' => $year->id, 'groupname' => $group]
        );
        $target = self::KINDS[$kind] ?: count($members);
        // Corners: as many as the library shows, six at most and three at least; a classroom's four can be met (D16).
        if ($kind === 'corners') {
            $target = max(3, min($target, garden::corners_shown($entityid)));
        }
        $done = $members ? self::progress($kind, array_map('intval', $members), $start, min($end, $now)) : 0;
        $challenge = (object) ['yearid' => (int) $year->id, 'group' => $group, 'term' => $term, 'kind' => $kind,
            'target' => $target, 'done' => min($done, $target), 'complete' => $target > 0 && $done >= $target,
            'chosen' => (bool) $row, 'id' => $row ? (int) $row->id : 0, 'seq' => $seq];
        // A challenge met is kept, with when: it is the class's garden.
        if ($challenge->complete) {
            $challenge->id = self::keep($challenge, $row, $now);
            $challenge->timecompleted = $row && $row->timecompleted ? (int) $row->timecompleted : $now;
        }
        return $challenge;
    }

    /**
     * The stages of an entity that play no class challenge, from its settings.
     *
     * @param int $entityid
     * @return string[]
     */
    public static function stages_off(int $entityid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return array_values(array_map('strval', (array) ($settings['challengesoff'] ?? [])));
    }

    /**
     * The stage of a group (that of most of its members this school year), if the centre turned its challenges off.
     *
     * @param int $entityid
     * @param int $yearid
     * @param string $group
     * @return string|null The stage, or null when the group plays.
     */
    public static function stage_off(int $entityid, int $yearid, string $group): ?string {
        global $DB;
        $off = self::stages_off($entityid);
        if (!$off) {
            return null;
        }
        $rows = $DB->get_records_sql('SELECT stage, COUNT(1) AS members
               FROM {' . self::T . 'member}
              WHERE yearid = :yearid AND groupname = :groupname AND stage IS NOT NULL
           GROUP BY stage
           ORDER BY members DESC, stage', ['yearid' => $yearid, 'groupname' => $group], 0, 1);
        $row = reset($rows);
        return $row && in_array((string) $row->stage, $off, true) ? (string) $row->stage : null;
    }

    /**
     * The groups someone is tutor of in an entity this school year. It only looks: it never opens a year.
     *
     * @param int $entityid
     * @param int $userid
     * @param int|null $now
     * @return string[]
     */
    public static function tutored(int $entityid, int $userid, ?int $now = null): array {
        global $DB;
        if (!$userid || skins::no_classes($entityid)) {
            return [];
        }
        $now = $now ?? time();
        $groups = $DB->get_fieldset_sql('SELECT t.groupname
               FROM {' . self::T . 'tutor} t
               JOIN {' . self::T . 'year} y ON y.id = t.yearid
              WHERE t.userid = :userid AND y.entityid = :entityid AND y.timestart <= :a AND y.timeend > :b
           ORDER BY t.groupname', ['userid' => $userid, 'entityid' => $entityid, 'a' => $now, 'b' => $now]);
        return array_values(array_unique(array_map('strval', $groups)));
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
                WHERE l.readerid $in AND l.timeloaned >= :start AND l.timeloaned < :end
                  AND " . bank::reading('l.libraryid'), $params);
        }
        if ($kind === 'notes') {
            return (int) $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::T . "loan} l
                WHERE l.readerid $in AND l.timereturned >= :start AND l.timereturned < :end
                  AND l.returnnote IS NOT NULL AND l.returnnote <> '' AND " . bank::reading('l.libraryid'), $params);
        }
        $codes = $DB->get_fieldset_sql("SELECT DISTINCT a.code
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE l.readerid $in AND l.timeloaned >= :start AND l.timeloaned < :end
                AND " . bank::reading('l.libraryid'), $params);
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
            'groupname' => $challenge->group, 'term' => $challenge->term, 'seq' => $challenge->seq ?? 1,
            'kind' => $challenge->kind, 'timecompleted' => $now, 'timecreated' => $now, 'timemodified' => $now]);
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
        $row = self::last((int) $year->id, $group, $term);
        if ($row && $row->timecompleted) {
            // Met: the class may play another one this term, counted from today (JV-D5); «none» leaves it as it is.
            if ($kind !== 'off') {
                $DB->insert_record(self::T . 'challenge', (object) ['yearid' => $year->id, 'groupname' => $group,
                    'term' => $term, 'seq' => (int) $row->seq + 1, 'kind' => $kind, 'timecreated' => $now,
                    'timemodified' => $now]);
            }
            return;
        }
        if ($row && (int) $row->seq > 1 && $kind === 'off') {
            // No other one after all: the class is back to the one it met.
            $DB->delete_records(self::T . 'challenge', ['id' => $row->id]);
            return;
        }
        if ($row) {
            $DB->update_record(self::T . 'challenge', (object) ['id' => $row->id, 'kind' => $kind, 'timemodified' => $now]);
            return;
        }
        $DB->insert_record(self::T . 'challenge', (object) ['yearid' => $year->id, 'groupname' => $group, 'term' => $term,
            'seq' => 1, 'kind' => $kind, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * The last challenge of a group in a term: the first one, or another one played after it was met.
     *
     * @param int $yearid
     * @param string $group
     * @param int $term
     * @return \stdClass|null
     */
    private static function last(int $yearid, string $group, int $term): ?\stdClass {
        global $DB;
        $rows = $DB->get_records(
            self::T . 'challenge',
            ['yearid' => $yearid, 'groupname' => $group, 'term' => $term],
            'seq DESC',
            '*',
            0,
            1
        );
        return $rows ? reset($rows) : null;
    }

    /**
     * What the staff choose from: the kinds, or, once the challenge is met, another one for the term.
     *
     * @param \stdClass|null $challenge As of() gives it.
     * @return array kinds (value, label, checked), again, choose, choosehint and save.
     */
    public static function choices(?\stdClass $challenge): array {
        $str = fn(string $key) => get_string($key, 'local_oksigeniaayvurenda');
        $all = array_keys(self::KINDS);
        $again = $challenge && $challenge->complete;
        $kinds = [];
        if ($again) {
            // The next in turn after the one met, ready.
            $next = $all[((int) array_search($challenge->kind, $all, true) + 1) % count($all)];
            foreach ($all as $kind) {
                $kinds[] = ['value' => $kind, 'label' => $str('challengekind' . $kind), 'checked' => $kind === $next];
            }
        } else {
            $followup = $challenge && ($challenge->seq ?? 1) > 1;
            foreach (array_merge($all, ['off']) as $kind) {
                $kinds[] = ['value' => $kind, 'checked' => $challenge ? $challenge->kind === $kind : $kind === 'off',
                    'label' => $str('challengekind' . $kind . ($kind === 'off' && $followup ? 'again' : ''))];
            }
        }
        return ['kinds' => $kinds, 'again' => $again,
            'choose' => $str($again ? 'challengeagain' : 'challengechoose'),
            'choosehint' => $str($again ? 'challengeagainhint' : 'challengechoosehint'),
            'save' => $str($again ? 'challengeagainsave' : 'challengesave')];
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
        // The garland: a pennant a step, or ten tenths for a long one (Jujol).
        $marks = self::garland((int) $challenge->done, (int) $challenge->target);
        $left = $challenge->kind === 'everyone' && !$challenge->complete && $challenge->target > $challenge->done
            ? $str('challengeeveryoneleft', $challenge->target - $challenge->done) : '';
        // Its clock, in sight (P5 of the review: the challenge had a deadline nobody saw): the days left in the term.
        $clock = '';
        $urgent = false;
        if (!$challenge->complete) {
            $now = time();
            $end = self::bounds(years::around($entityid, $now), (int) $challenge->term)[1];
            $days = (int) ceil(($end - $now) / DAYSECS);
            if ($days > 0) {
                $clock = $days === 1 ? $str('challengeclockone') : $str('challengeclock', (object) ['days' => $days,
                    'date' => zones::date($end - 1, 'strftimedaydate', $entityid)]);
                $urgent = $days <= 7;
            }
        }
        return [
            'heading' => $staff ? $str('challengeterm', $challenge->term) : $str('challengeheading'),
            'text' => $text,
            'marks' => $marks,
            'progresslabel' => $str('challengeprogress', (object) ['done' => $challenge->done, 'target' => $challenge->target]),
            'left' => $left,
            'clock' => $clock,
            'urgent' => $urgent,
            'complete' => $challenge->complete,
            'listen' => null,
            // The herald celebrates for two weeks, not every day until the next term (D5).
            'herald' => $challenge->complete && time() - (int) ($challenge->timecompleted ?? time()) <= self::CHEER
                ? characters::say($entityid, 'herald', 'challengeherald', ['group' => $challenge->group], 'celebra') : null,
            'meton' => $challenge->complete && time() - (int) ($challenge->timecompleted ?? time()) > self::CHEER
                ? $str('challengemeton', zones::date((int) $challenge->timecompleted, 'strftimedate', $entityid)) : '',
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
        if (skins::no_classes($entityid)) {
            return [];
        }
        $year = years::around($entityid, $now ?? time());
        $rows = $DB->get_records_select(self::T . 'challenge', 'yearid = :yearid AND groupname = :groupname
            AND timecompleted IS NOT NULL', ['yearid' => $year->id, 'groupname' => $group], 'term, seq');
        return array_values(array_map(fn($r) => ['term' => (int) $r->term, 'kind' => $r->kind,
            'time' => (int) $r->timecompleted], $rows));
    }
}
