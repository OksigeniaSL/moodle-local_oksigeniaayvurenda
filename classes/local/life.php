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
 * A little life on the staff's screens: on each one, what goes well in its own subject, summed and without names. Whoever
 * runs or heads the library sees that things happen there, not only the work left to do.
 *
 * Each screen gets its own note, never the same strip on all: the late books say how many came back on time, what was
 * catalogued says it is already read, weeding shows the old books that still go out. Nothing in a practice library nor in a
 * textbook bank, and nothing at all when there is nothing good to tell.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class life {
    /** @var string */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Below this many books back in the year, a share of them says nothing. */
    public const BACK = 20;

    /** @var int From this share of books back on time, in percent, it is good news worth telling. */
    public const ONTIME = 70;

    /** @var int How many years old a book is to count as a veteran on the weeding screen. */
    public const VETERAN = 25;

    /** @var int How many veterans the weeding screen names. */
    public const VETERANS = 3;

    /** @var int The fewest loans of a book to call it the most lent of the year. */
    public const TOP = 3;

    /** @var string Loans that came back, not given up for lost. */
    private const BACKNOTLOST = "l.timereturned IS NOT NULL AND (l.conditionin IS NULL OR l.conditionin <> 'lost')";

    /**
     * The late books: of what came back this year, the share on time when it is high, and how many came back this week.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null The note for the template life, or nothing to tell.
     */
    public static function late(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS back, SUM(CASE WHEN l.timereturned <= l.timedue THEN 1 ELSE 0 END) AS ontime
               FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timereturned >= :start AND l.timereturned <= :now AND " . self::BACKNOTLOST,
            ['libraryid' => $library->id, 'start' => $year->timestart, 'now' => $now]
        );
        $items = [];
        $back = (int) $row->back;
        if ($back >= self::BACK) {
            // Rounded down, so that it never says more than happened.
            $share = intdiv(100 * (int) $row->ontime, $back);
            if ($share >= self::ONTIME) {
                $items[] = self::item($entityid, 'lifelateshare', screens::number($share));
            }
        }
        $week = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timereturned >= :since AND l.timereturned <= :now AND " . self::BACKNOTLOST,
            ['libraryid' => $library->id, 'since' => $now - WEEKSECS, 'now' => $now]
        );
        if ($week) {
            $items[] = self::item($entityid, $week === 1 ? 'lifelateweekone' : 'lifelateweek', screens::number($week));
        }
        return self::note($entityid, 'lifelatelead', $items);
    }

    /**
     * The holds: how many books set aside this year reached whoever waited for them.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function holds(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $done = (int) $DB->count_records_select(
            self::T . 'hold',
            "libraryid = :libraryid AND status = 'done' AND timeclosed >= :start AND timeclosed <= :now",
            ['libraryid' => $library->id, 'start' => $year->timestart, 'now' => $now]
        );
        if (!$done) {
            return null;
        }
        return self::note($entityid, 'lifeholdslead', [
            self::item($entityid, $done === 1 ? 'lifeholdsone' : 'lifeholds', screens::number($done)),
        ]);
    }

    /**
     * The readers of a centre: how many have taken a book this year, and how many took their very first this week.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function readers(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $join = "FROM {" . self::T . "loan} l
                 JOIN {" . self::T . "library} b ON b.id = l.libraryid
                 JOIN {" . self::T . "reader} r ON r.id = l.readerid
                WHERE b.entityid = :entityid AND r.readertype <> :library AND l.timeloaned <= :now
                      AND " . bank::reading('l.libraryid');
        $params = ['entityid' => $entityid, 'library' => ill::READERTYPE, 'now' => $now];
        $came = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.readerid) $join AND l.timeloaned >= :start",
            $params + ['start' => $year->timestart]
        );
        $first = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM (SELECT l.readerid $join GROUP BY l.readerid HAVING MIN(l.timeloaned) >= :since) f",
            $params + ['since' => $now - WEEKSECS]
        );
        $items = [];
        if ($came) {
            $items[] = self::item($entityid, $came === 1 ? 'lifereadersyearone' : 'lifereadersyear', screens::number($came));
        }
        if ($first) {
            $items[] = self::item($entityid, $first === 1 ? 'lifereadersfirstone' : 'lifereadersfirst', screens::number($first));
        }
        return self::note($entityid, 'lifereaderslead', $items);
    }

    /**
     * What was catalogued by hand: how many times this month's copies already went out, and how soon the first did; with
     * nothing this month, the copy added by hand this year that has gone out most.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function catalogued(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $byhand = "i.libraryid = :libraryid AND i.timecreated >= :since
            AND NOT EXISTS (SELECT 1 FROM {" . self::T . "source} s WHERE s.tablename = 'item' AND s.localid = i.id)";
        $loans = "FROM {" . self::T . "loan} l
                  JOIN {" . self::T . "item} i ON i.id = l.itemid
                  JOIN {" . self::T . "record} r ON r.id = i.recordid
                 WHERE $byhand AND l.timeloaned >= i.timecreated AND l.timeloaned <= :now";
        $month = catalogued::starts($entityid, $now)['month'];
        $params = ['libraryid' => $library->id, 'since' => $month, 'now' => $now];
        $times = (int) $DB->count_records_sql("SELECT COUNT(1) $loans", $params);
        if ($times) {
            $items = [self::item($entityid, $times === 1 ? 'lifecataloguedone' : 'lifecatalogued', screens::number($times))];
            $first = $DB->get_records_sql(
                "SELECT l.id, l.timeloaned, i.timecreated, r.id AS recordid, r.title $loans ORDER BY l.timeloaned, l.id",
                $params,
                0,
                1
            );
            $first = reset($first);
            $days = self::days_between((int) $first->timecreated, (int) $first->timeloaned, $library);
            $key = $days === 0 ? 'lifecataloguedsameday' : ($days === 1 ? 'lifecataloguednextday' : 'lifecataloguedafter');
            $items[] = self::item(
                $entityid,
                $key,
                (object) ['title' => format_string($first->title), 'days' => screens::number($days)],
                self::record_url($library, (int) $first->recordid)
            );
            return self::note($entityid, 'lifecataloguedlead', $items);
        }
        $year = years::around($entityid, $now);
        $best = $DB->get_records_sql(
            "SELECT r.id, r.title, COUNT(1) AS times $loans GROUP BY r.id, r.title ORDER BY COUNT(1) DESC, r.id",
            ['libraryid' => $library->id, 'since' => $year->timestart, 'now' => $now],
            0,
            1
        );
        $best = reset($best);
        if (!$best || (int) $best->times < 2) {
            return null;
        }
        return self::note($entityid, 'lifecataloguedlead', [
            self::item(
                $entityid,
                'lifecataloguedyear',
                (object) ['title' => format_string($best->title), 'times' => screens::number((int) $best->times)],
                self::record_url($library, (int) $best->id)
            ),
        ]);
    }

    /**
     * Weeding: the oldest books that still went out this year, so that old is not taken for dead.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function weed(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.title, r.pubyear, COUNT(1) AS times
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :start AND l.timeloaned <= :now
                    AND r.pubyear IS NOT NULL AND r.pubyear <> ''
           GROUP BY r.id, r.title, r.pubyear",
            ['libraryid' => $library->id, 'start' => $year->timestart, 'now' => $now]
        );
        $last = (int) zones::date($now, '%Y', $library) - self::VETERAN;
        $old = [];
        foreach ($rows as $row) {
            // A year of four figures, bare or in brackets as cataloguers write one they worked out.
            if (!preg_match('/^\s*\[?(\d{4})\]?\.?\s*$/', (string) $row->pubyear, $m)) {
                continue;
            }
            $published = (int) $m[1];
            if ($published >= 1450 && $published <= $last) {
                $old[] = ['id' => (int) $row->id, 'title' => (string) $row->title, 'year' => $published,
                    'times' => (int) $row->times];
            }
        }
        // The oldest first and, of the same year, the one that went out most.
        usort($old, fn($a, $b) => [$a['year'], -$a['times'], $a['id']] <=> [$b['year'], -$b['times'], $b['id']]);
        $items = [];
        foreach (array_slice($old, 0, self::VETERANS) as $book) {
            $items[] = self::item(
                $entityid,
                $book['times'] === 1 ? 'lifeweedone' : 'lifeweed',
                (object) ['title' => format_string($book['title']), 'year' => $book['year'],
                    'times' => screens::number($book['times'])],
                self::record_url($library, $book['id'])
            );
        }
        return self::note($entityid, 'lifeweedlead', $items);
    }

    /**
     * The year so far: its busiest day and its most lent book, across the centre's reading libraries.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function year(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $where = "b.entityid = :entityid AND l.timeloaned >= :start AND l.timeloaned <= :now AND " . bank::reading('l.libraryid');
        $params = ['entityid' => $entityid, 'start' => $year->timestart, 'now' => $now];
        $items = [];
        // The day in the centre's own time: the offset of now is good enough for counting days.
        $offset = zones::of($entityid)->getOffset(new \DateTimeImmutable('@' . $now));
        $days = $DB->get_records_sql(
            "SELECT d.day, d.lent
               FROM (SELECT FLOOR((l.timeloaned + $offset) / 86400) AS day, COUNT(1) AS lent
                       FROM {" . self::T . "loan} l JOIN {" . self::T . "library} b ON b.id = l.libraryid
                      WHERE $where
                   GROUP BY FLOOR((l.timeloaned + $offset) / 86400)) d
           ORDER BY d.lent DESC, d.day DESC",
            $params,
            0,
            1
        );
        $day = reset($days);
        if ($day && (int) $day->lent >= moments::RECORD) {
            $items[] = self::item($entityid, 'lifeyearrecord', (object) [
                'day' => zones::date((int) $day->day * DAYSECS - $offset + 12 * HOURSECS, 'strftimedateshort', $library),
                'count' => screens::number((int) $day->lent),
            ]);
        }
        $top = $DB->get_records_sql(
            "SELECT r.id, r.title, COUNT(1) AS times
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE $where AND r.doctype <> 'device'
           GROUP BY r.id, r.title
           ORDER BY COUNT(1) DESC, r.id",
            $params,
            0,
            2
        );
        $top = array_values($top);
        // Only a clear first: a tie names nobody.
        if ($top && (int) $top[0]->times >= self::TOP && (count($top) < 2 || (int) $top[1]->times < (int) $top[0]->times)) {
            $items[] = self::item(
                $entityid,
                'lifeyeartop',
                (object) ['title' => format_string($top[0]->title), 'times' => screens::number((int) $top[0]->times)],
                self::record_url($library, (int) $top[0]->id)
            );
        }
        return self::note($entityid, 'lifeyearlead', $items);
    }

    /**
     * Purchases: how many times what arrived this year because someone asked for it has already gone out.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function purchases(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $times = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.id)
               FROM {" . self::T . "purchase} p
               JOIN {" . self::T . "item} i ON i.recordid = p.recordid AND i.libraryid = p.libraryid
               JOIN {" . self::T . "loan} l ON l.itemid = i.id AND l.timeloaned >= p.timereceived AND l.timeloaned <= :now
              WHERE p.libraryid = :libraryid AND p.yearid = :yearid AND p.status = 'received' AND p.recordid IS NOT NULL",
            ['libraryid' => $library->id, 'yearid' => $year->id, 'now' => $now]
        );
        if (!$times) {
            return null;
        }
        return self::note($entityid, 'lifepurchaseslead', [
            self::item($entityid, $times === 1 ? 'lifepurchasesone' : 'lifepurchases', screens::number($times)),
        ]);
    }

    /**
     * The reports: this week at the library, what there is to celebrate, and the year ahead of the last one at this point.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null
     */
    public static function reports(\stdClass $library, int $now): ?array {
        global $DB;
        if (self::quiet($library)) {
            return null;
        }
        $entityid = (int) $library->entityid;
        $items = [];
        $week = $DB->get_record_sql(
            "SELECT COUNT(1) AS lent, COUNT(DISTINCT l.readerid) AS readers FROM {" . self::T . "loan} l
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :since AND l.timeloaned <= :now",
            ['libraryid' => $library->id, 'since' => $now - WEEKSECS, 'now' => $now]
        );
        $lent = (int) $week->lent;
        if ($lent) {
            $a = (object) ['lent' => screens::number($lent), 'readers' => screens::number((int) $week->readers)];
            $items[] = self::item(
                $entityid,
                $lent === 1 ? 'lifereportsweekone' : ((int) $week->readers === 1 ? 'lifereportsweekreader' : 'lifereportsweek'),
                $a
            );
        }
        // Right after the week, which it counts on («with them»); then what there is to celebrate.
        $pace = self::pace($library, $now);
        if ($pace) {
            $items[] = self::item($entityid, $lent ? 'lifereportspace' : 'lifereportspacequiet', (object) [
                'now' => screens::number($pace['now']), 'more' => screens::number($pace['now'] - $pace['before']),
            ]);
        }
        foreach (array_slice(moments::of($library, $now, true), 0, 2) as $moment) {
            $items[] = self::split($moment['text'], $moment['url']);
        }
        return self::note($entityid, 'lifereportslead', $items);
    }

    /**
     * The loans of a library this year so far against the last year at the same point, only when this one is ahead.
     *
     * @param \stdClass $library
     * @param int $now
     * @return array|null now and before, the two counts.
     */
    public static function pace(\stdClass $library, int $now): ?array {
        global $DB;
        $entityid = (int) $library->entityid;
        $year = years::around($entityid, $now);
        $last = $DB->get_records_select(
            self::T . 'year',
            'entityid = :entityid AND timeend <= :start',
            ['entityid' => $entityid, 'start' => $year->timestart],
            'timeend DESC',
            'id, timestart, timeend',
            0,
            1
        );
        $last = reset($last);
        if (!$last) {
            return null;
        }
        $count = fn(int $from, int $to) => (int) $DB->count_records_select(
            self::T . 'loan',
            'libraryid = :libraryid AND timeloaned >= :a AND timeloaned < :b',
            ['libraryid' => $library->id, 'a' => $from, 'b' => $to]
        );
        $before = $count((int) $last->timestart, (int) $last->timestart + ($now - (int) $year->timestart));
        $sofar = $count((int) $year->timestart, $now + 1);
        // Against a year with almost nothing (the library was not here yet), being ahead means nothing.
        if ($before < moments::RECORD || $sofar <= $before) {
            return null;
        }
        return ['now' => $sofar, 'before' => $before];
    }

    /**
     * The loans of this week in each of some libraries, as a line for its card.
     *
     * @param \stdClass[] $libraries
     * @param int $now
     * @return string[] By library id; nothing for one without loans this week.
     */
    public static function weeks(array $libraries, int $now): array {
        global $DB;
        $ids = [];
        foreach ($libraries as $library) {
            if (!self::quiet($library)) {
                $ids[(int) $library->id] = $library;
            }
        }
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED);
        $counts = $DB->get_records_sql_menu(
            "SELECT libraryid, COUNT(1) FROM {" . self::T . "loan}
              WHERE libraryid $in AND timeloaned >= :since AND timeloaned <= :now
           GROUP BY libraryid",
            $params + ['since' => $now - WEEKSECS, 'now' => $now]
        );
        $out = [];
        foreach ($counts as $id => $lent) {
            $entityid = (int) $ids[(int) $id]->entityid;
            $out[(int) $id] = skins::say(
                $entityid,
                (int) $lent === 1 ? 'lifelibraryweekone' : 'lifelibraryweek',
                screens::number((int) $lent)
            );
        }
        return $out;
    }

    /**
     * Whether a library has nothing to tell: a practice one, or a textbook bank, whose loans are not reading.
     *
     * @param \stdClass $library
     * @return bool
     */
    private static function quiet(\stdClass $library): bool {
        return practice::is((int) $library->entityid) || bank::is($library);
    }

    /**
     * A note for the template life, or nothing without items.
     *
     * @param int $entityid
     * @param string $lead The key of the words in bold before it.
     * @param array[] $items
     * @return array|null
     */
    private static function note(int $entityid, string $lead, array $items): ?array {
        if (!$items) {
            return null;
        }
        return ['lifelead' => skins::say($entityid, $lead), 'lifeitems' => array_values($items)];
    }

    /**
     * One sentence of a note, in the words of the library's skin.
     *
     * @param int $entityid
     * @param string $key
     * @param mixed $a
     * @param string $href Where it leads, or nowhere.
     * @return array
     */
    private static function item(int $entityid, string $key, $a = null, string $href = ''): array {
        return self::split(skins::say($entityid, $key, $a), $href);
    }

    /**
     * A sentence with only its thing as a link, the title between «»: a whole sentence underlined weighs, most of all on a
     * phone. Without a title in it, no link at all.
     *
     * @param string $text
     * @param string $href
     * @return array lifetext, the whole sentence; lifebefore, lifelinked and lifeafter; lifehref, empty for no link.
     */
    public static function split(string $text, string $href): array {
        // The spaces French puts inside the guillemets stay outside the link.
        $space = '[\s\x{00A0}\x{202F}]';
        $pattern = '/^(.*?«' . $space . '*)([^»]*?[^»\s\x{00A0}\x{202F}])(' . $space . '*».*)$/su';
        if ($href !== '' && preg_match($pattern, $text, $m)) {
            return ['lifetext' => $text, 'lifebefore' => $m[1], 'lifelinked' => $m[2], 'lifeafter' => $m[3], 'lifehref' => $href];
        }
        return ['lifetext' => $text, 'lifebefore' => $text, 'lifelinked' => '', 'lifeafter' => '', 'lifehref' => ''];
    }

    /**
     * Where a record's page is, in a library.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @return string
     */
    private static function record_url(\stdClass $library, int $recordid): string {
        return (new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]))
            ->out(false);
    }

    /**
     * How many days of the centre's calendar lie between two moments: none on the same day.
     *
     * @param int $from
     * @param int $to
     * @param \stdClass $library
     * @return int
     */
    private static function days_between(int $from, int $to, \stdClass $library): int {
        $tz = zones::of($library);
        $a = (new \DateTimeImmutable('@' . $from))->setTimezone($tz)->setTime(0, 0);
        $b = (new \DateTimeImmutable('@' . $to))->setTimezone($tz)->setTime(0, 0);
        return max(0, (int) $a->diff($b)->days);
    }
}
