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
 * The whole library in figures.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * What a librarian asks first of the figures, together: how many books there are, what part of them is used, how many
 * readers came, whether it goes up or down, what goes out most and from where. What there is does not depend on the
 * period; the rest is of the period chosen, beside the same days of the year before.
 */
final class glance {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int How many of the most lent. */
    private const TOP = 10;

    /** @var int The days up to which the period is told day by day. */
    private const BY_DAY = 45;

    /** @var int The days up to which it is told month by month; from there on, year by year. */
    private const BY_MONTH = 400;

    /** @var string[] The kinds of document, in the order they are told. */
    private const KINDS = ['book', 'periodical', 'audio', 'video', 'electronic', 'map', 'game', 'device', 'other'];

    /**
     * The library in figures.
     *
     * @param \stdClass $library
     * @param int $from The start of the period.
     * @param int $to Its end (the start of the next day).
     * @param int $now
     * @param bool $always The whole history: there is nothing to set beside it.
     * @return array collection, use, readers, before (null when nothing is known of it), series, top, stages, corners.
     */
    public static function of(\stdClass $library, int $from, int $to, int $now, bool $always = false): array {
        $until = max($from, min($to, $now + 1));
        $span = $always ? null : self::before($library, $from, $until);
        $collection = self::collection($library, $from, $until);
        $use = self::usage($library, $from, $until, $collection['copies']);
        $readers = self::readers($library, $from, $until, $now);
        $use['perreader'] = $readers['borrowers'] ? round($use['loans'] / $readers['borrowers'], 1) : 0.0;
        return [
            'collection' => $collection,
            'use' => $use,
            'readers' => $readers,
            'before' => $span ? ['name' => $span['name'], 'loans' => self::loans($library, $span['from'], $span['to']),
                'readers' => self::readers($library, $span['from'], $span['to'], $now)] : null,
            'series' => self::series($library, $from, $to, $now, (bool) $span),
            'top' => reports::top($library, $from, $until, self::TOP),
            'stages' => skins::no_classes((int) $library->entityid) ? [] : self::stages($library, $from, $until),
            'corners' => self::corners($library, [$from, $until], $span ? [$span['from'], $span['to']] : null),
        ];
    }

    /**
     * The same days of the year before, when enough is known of that year to say something of this one.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $until
     * @return array|null from, to, name (of that year).
     */
    private static function before(\stdClass $library, int $from, int $until): ?array {
        $past = self::year_before($library, $from);
        $year = years::around((int) $library->entityid, $past);
        if (self::loans($library, (int) $year->timestart, (int) $year->timeend) < moments::RECORD) {
            return null;
        }
        return ['from' => $past, 'to' => self::year_before($library, $until), 'name' => (string) $year->name];
    }

    /**
     * The same day and hour of the year before, on the clock of the library.
     *
     * @param \stdClass $library
     * @param int $time
     * @return int
     */
    private static function year_before(\stdClass $library, int $time): int {
        return self::moved($library, $time, '-1 year');
    }

    /**
     * A moment moved on the clock of the library.
     *
     * @param \stdClass $library
     * @param int $time
     * @param string $by As DateTimeImmutable::modify().
     * @return int
     */
    private static function moved(\stdClass $library, int $time, string $by): int {
        return (new \DateTimeImmutable('@' . $time))->setTimezone(zones::of($library))->modify($by)->getTimestamp();
    }

    /**
     * The loans of a library in a stretch of time.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $to
     * @return int
     */
    private static function loans(\stdClass $library, int $from, int $to): int {
        global $DB;
        return $DB->count_records_select(
            self::T . 'loan',
            'libraryid = :libraryid AND timeloaned >= :a AND timeloaned < :b',
            ['libraryid' => $library->id, 'a' => $from, 'b' => $to]
        );
    }

    /**
     * What the library has now: copies and titles, by kind of document, those never lent and those that came in a period.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $until
     * @return array copies, titles, kinds (kind, copies), never, nevershare, acquired.
     */
    private static function collection(\stdClass $library, int $from, int $until): array {
        global $DB;
        $here = "FROM {" . self::T . "item} i WHERE i.libraryid = :libraryid AND i.status <> 'withdrawn'";
        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS copies, COUNT(DISTINCT i.recordid) AS titles,
                    SUM(CASE WHEN i.timeacquired >= :a AND i.timeacquired < :b THEN 1 ELSE 0 END) AS acquired $here",
            ['a' => $from, 'b' => $until, 'libraryid' => $library->id]
        );
        $never = (int) $DB->count_records_sql(
            "SELECT COUNT(1) $here AND NOT EXISTS (SELECT 1 FROM {" . self::T . "loan} l WHERE l.itemid = i.id)",
            ['libraryid' => $library->id]
        );
        $kinds = array_fill_keys(self::KINDS, 0);
        $rows = $DB->get_records_sql(
            "SELECT r.doctype, COUNT(1) AS copies
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE i.libraryid = :libraryid AND i.status <> 'withdrawn'
           GROUP BY r.doctype",
            ['libraryid' => $library->id]
        );
        foreach ($rows as $kind) {
            $kinds[isset($kinds[$kind->doctype]) ? $kind->doctype : 'other'] += (int) $kind->copies;
        }
        $copies = (int) $row->copies;
        return ['copies' => $copies, 'titles' => (int) $row->titles, 'kinds' => array_filter($kinds),
            'never' => $never, 'nevershare' => self::share($never, $copies), 'acquired' => (int) $row->acquired];
    }

    /**
     * How much of it is used: the loans of the period, the copies lent or read in the room in it, of those the library
     * has, those lent some other time, those read in the room and those out now.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $until
     * @param int $copies
     * @return array loans, used, usedshare, other, room, out, outshare, perhundred (loans of each hundred copies).
     */
    private static function usage(\stdClass $library, int $from, int $until, int $copies): array {
        global $DB;
        $items = "FROM {" . self::T . "item} i WHERE i.libraryid = :libraryid AND i.status <> 'withdrawn'";
        $inperiod = "(EXISTS (SELECT 1 FROM {" . self::T . "loan} l
                               WHERE l.itemid = i.id AND l.timeloaned >= :a AND l.timeloaned < :b)
                   OR EXISTS (SELECT 1 FROM {" . self::T . "roomread} r
                               WHERE r.itemid = i.id AND r.timecreated >= :c AND r.timecreated < :d))";
        $params = ['libraryid' => $library->id, 'a' => $from, 'b' => $until, 'c' => $from, 'd' => $until];
        $used = (int) $DB->count_records_sql("SELECT COUNT(1) $items AND $inperiod", $params);
        $other = (int) $DB->count_records_sql(
            "SELECT COUNT(1) $items AND NOT $inperiod
                AND EXISTS (SELECT 1 FROM {" . self::T . "loan} o WHERE o.itemid = i.id)",
            $params
        );
        $out = (int) $DB->count_records_sql(
            "SELECT COUNT(1) $items AND EXISTS (SELECT 1 FROM {" . self::T . "loan} l
                WHERE l.itemid = i.id AND l.timereturned IS NULL)",
            ['libraryid' => $library->id]
        );
        $room = $DB->count_records_select(
            self::T . 'roomread',
            'libraryid = :libraryid AND timecreated >= :a AND timecreated < :b',
            ['libraryid' => $library->id, 'a' => $from, 'b' => $until]
        );
        $loans = self::loans($library, $from, $until);
        return ['loans' => $loans, 'used' => $used, 'usedshare' => self::share($used, $copies), 'other' => $other,
            'room' => $room,
            'out' => $out, 'outshare' => self::share($out, $copies),
            'perhundred' => $copies ? round(100 * $loans / $copies, 1) : 0.0];
    }

    /**
     * The readers who took something home in a period and, of those in the groups of its year, how many came; where there
     * are no groups (a town, a company), of the active readers, who are only known for now. A period of more than a year
     * has no «of how many».
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $until
     * @param int $now
     * @return array borrowers (all of them), came and of (null when nobody knows), share (null).
     */
    private static function readers(\stdClass $library, int $from, int $until, int $now): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $took = fn(string $member, array $params) => (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.readerid) FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b $member",
            ['libraryid' => $library->id, 'a' => $from, 'b' => $until] + $params
        );
        $borrowers = $took('', []);
        $nobody = ['borrowers' => $borrowers, 'came' => $borrowers, 'of' => null, 'share' => null];
        $year = years::around($entityid, $from);
        if ($until > (int) $year->timeend) {
            return $nobody;
        }
        $ingroups = "%1\$s.yearid = :yearid AND %1\$s.groupname IS NOT NULL AND (%1\$s.source IS NULL OR %1\$s.source <> 'past')";
        $of = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT m.readerid) FROM {" . self::T . "member} m WHERE " . sprintf($ingroups, 'm'),
            ['yearid' => $year->id]
        );
        if ($of) {
            $came = $took(" AND EXISTS (SELECT 1 FROM {" . self::T . "member} m WHERE m.readerid = l.readerid AND "
                . sprintf($ingroups, 'm') . ')', ['yearid' => $year->id]);
        } else if ($until > $now) {
            $of = $DB->count_records(self::T . 'reader', ['entityid' => $entityid, 'status' => 'active']);
            $came = min($of, $borrowers);
        } else {
            return $nobody;
        }
        return ['borrowers' => $borrowers, 'came' => $came, 'of' => $of, 'share' => self::share($came, $of)];
    }

    /**
     * The loans of a period, piece by piece: day by day, month by month beside the same of the year before, or year by
     * year; what is still to come has none yet.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $to
     * @param int $now
     * @param bool $compare Whether the year before is told too.
     * @return array unit (day, month or year) and points: start, name (of a year; '' otherwise), loans (null: still to
     *     come), before (null: not told).
     */
    private static function series(\stdClass $library, int $from, int $to, int $now, bool $compare): array {
        global $DB;
        $days = ($to - $from) / DAYSECS;
        $unit = $days <= self::BY_DAY ? 'day' : ($days <= self::BY_MONTH ? 'month' : 'year');
        $compare = $compare && $unit === 'month';
        $at = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone(zones::of($library));
        $points = [];
        $start = $from;
        while ($start < $to) {
            $name = '';
            if ($unit === 'year') {
                $year = years::around((int) $library->entityid, $start);
                $name = (string) $year->name;
                $next = (int) $year->timeend;
            } else {
                $next = $at($start)->modify($unit === 'day' ? '+1 day' : 'first day of next month')->setTime(0, 0)->getTimestamp();
            }
            // A year that does not move on would never end.
            $next = min($to, max($next, $start + HOURSECS));
            $points[] = ['start' => $start, 'next' => $next, 'name' => $name];
            $start = $next;
        }
        $sums = [];
        $params = ['libraryid' => $library->id];
        foreach ($points as $i => $point) {
            foreach (['n' => 0, 'b' => 1] as $which => $past) {
                if ($past && !$compare) {
                    continue;
                }
                $params["f$which$i"] = $past ? self::year_before($library, $point['start']) : $point['start'];
                $params["t$which$i"] = $past ? self::year_before($library, $point['next']) : $point['next'];
                $sums[] = "SUM(CASE WHEN timeloaned >= :f$which$i AND timeloaned < :t$which$i THEN 1 ELSE 0 END) AS $which$i";
            }
        }
        $params += ['since' => $compare ? self::year_before($library, $from) : $from, 'till' => $to];
        $row = $sums ? $DB->get_record_sql(
            'SELECT ' . implode(', ', $sums) . ' FROM {' . self::T . 'loan}
              WHERE libraryid = :libraryid AND timeloaned >= :since AND timeloaned < :till',
            $params
        ) : null;
        $out = [];
        foreach ($points as $i => $point) {
            $out[] = ['start' => $point['start'], 'name' => $point['name'],
                'loans' => $point['start'] > $now ? null : (int) $row->{"n$i"},
                'before' => $compare ? (int) $row->{"b$i"} : null];
        }
        return ['unit' => $unit, 'points' => $out];
    }

    /**
     * The loans of each stage in a period, in the order of the school; the rest (the staff, who is in no group) last. Of a
     * year brought from another programme nobody may know who was in each stage: with loans of one of those, the stages
     * would say that almost everybody is in none, and say nothing.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $until
     * @return array[] stage ('' for the rest), loans, share; nothing without a single stage, or with a year without them.
     */
    private static function stages(\stdClass $library, int $from, int $until): array {
        global $DB;
        $params = ['libraryid' => $library->id, 'a' => $from, 'b' => $until];
        $unknown = $DB->record_exists_sql(
            "SELECT 1
               FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b
                    AND NOT EXISTS (SELECT 1 FROM {" . self::T . "member} m
                                     WHERE m.yearid = l.yearid AND m.stage IS NOT NULL AND m.stage <> '')",
            $params
        );
        if ($unknown) {
            return [];
        }
        $rows = $DB->get_records_sql(
            "SELECT s.stage, COUNT(1) AS loans
               FROM (SELECT l.id, COALESCE(MIN(m.stage), '') AS stage
                       FROM {" . self::T . "loan} l
                  LEFT JOIN {" . self::T . "member} m ON m.readerid = l.readerid AND m.yearid = l.yearid
                      WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b
                   GROUP BY l.id) s
           GROUP BY s.stage",
            $params
        );
        $total = array_sum(array_column($rows, 'loans'));
        $out = array_values(array_map(fn($r) => ['stage' => (string) $r->stage, 'loans' => (int) $r->loans,
            'share' => self::share((int) $r->loans, (int) $total)], $rows));
        if (!array_filter(array_column($out, 'stage'), fn($stage) => $stage !== '')) {
            return [];
        }
        usort($out, fn($a, $b) => [$a['stage'] === '', \local_oksigeniaayvurenda\core\group_order::stage($a['stage']), $a['stage']]
            <=> [$b['stage'] === '', \local_oksigeniaayvurenda\core\group_order::stage($b['stage']), $b['stage']]);
        return $out;
    }

    /**
     * The loans of each corner in a stretch of time and, if there is another, in it: the most lent first.
     *
     * @param \stdClass $library
     * @param int[] $now From and to.
     * @param int[]|null $before From and to.
     * @return array[] code, corner, loans, before, change (loans less before; null without a stretch before).
     */
    private static function corners(\stdClass $library, array $now, ?array $before): array {
        global $DB;
        $counted = [];
        foreach (['loans' => $now, 'before' => $before] as $which => $span) {
            if (!$span) {
                continue;
            }
            $rows = $DB->get_recordset_sql(
                "SELECT l.id, a.code
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                   JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                  WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b
               ORDER BY l.id, ra.id",
                ['libraryid' => $library->id, 'a' => $span[0], 'b' => $span[1]]
            );
            $last = 0;
            foreach ($rows as $row) {
                $top = catalogue::top_class((string) $row->code);
                // A loan counts once, in the first corner of its book.
                if ($top !== null && (int) $row->id !== $last) {
                    $last = (int) $row->id;
                    $counted['c' . $top][$which] = ($counted['c' . $top][$which] ?? 0) + 1;
                }
            }
            $rows->close();
        }
        $labels = array_column(catalogue::corners($library, true, false), 'label', 'code');
        $out = [];
        foreach ($counted as $key => $row) {
            $code = substr($key, 1);
            $loans = $row['loans'] ?? 0;
            $past = $row['before'] ?? 0;
            $out[] = ['code' => $code, 'corner' => (string) ($labels[$code] ?? $code), 'loans' => $loans, 'before' => $past,
                'change' => $before ? $loans - $past : null];
        }
        usort($out, fn($a, $b) => [$b['loans'], $a['corner']] <=> [$a['loans'], $b['corner']]);
        return $out;
    }

    /**
     * A part of a whole, as a percentage; a part that is there is never 0 %, nor all of it but one, 100 %.
     *
     * @param int $part
     * @param int $whole
     * @return float One decimal below ten, none from there on.
     */
    private static function share(int $part, int $whole): float {
        if ($whole <= 0 || $part <= 0) {
            return 0.0;
        }
        if ($part >= $whole) {
            return 100.0;
        }
        $share = 100 * $part / $whole;
        return $share < 10 ? max(0.1, round($share, 1)) : min(99.0, round($share));
    }
}
