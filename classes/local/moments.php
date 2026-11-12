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
 * The moments to celebrate in a centre: what happened this week that is worth a cheer, added up and without names.
 *
 * The library turning a month or a year online, a round number of books lent this year (and which book made it), a
 * book that has gone out a round number of times, the busiest day of the year at the desk, readers taking their very
 * first book, readers reaching the goal of a challenge of the library and classes meeting theirs. Whoever runs or keeps
 * the library sees them all, so that nobody with a high role is left with figures only (Enrique, 8 November 2026: «los
 * admins también tenemos derecho a saber cositas»); readers hear those of the whole library, from the herald.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moments {
    /** @var string Prefix of the plugin's tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int[] The round numbers of the books lent in a year, from a small library's to a big one's. */
    public const LOANS = [100, 250, 500, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 7500, 10000, 15000, 20000, 25000, 30000,
        40000, 50000, 75000, 100000, 150000, 200000, 250000, 500000];

    /** @var int[] The round numbers of the times one book has gone out. */
    public const BOOK = [10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 750, 1000];

    /** @var int How many days a moment is told: a week. */
    public const DAYS = 7;

    /** @var int The fewest loans in a day that make a record worth telling. */
    public const RECORD = 10;

    /** @var string[] The kinds readers hear too; the classes that met their challenge, only the staff. */
    public const PUBLIC = ['anniversary', 'loans', 'book', 'record', 'quest', 'first'];

    /** @var string[] Most worth telling first. */
    public const ORDER = ['anniversary', 'loans', 'book', 'record', 'quest', 'challenge', 'first'];

    /**
     * The moments of a centre this week, the most worth telling first.
     *
     * @param \stdClass $library The library someone looks at; the moments are of its whole centre.
     * @param int $now
     * @param bool $staff Whether whoever looks runs or keeps a library of the centre: then they see them all; a reader,
     *     only those of the whole library and, when they count people, only from several.
     * @return array[] Each with kind, key and a (to say it as the herald), text, url and recordid.
     */
    public static function of(\stdClass $library, int $now, bool $staff): array {
        $entityid = (int) $library->entityid;
        if (practice::is($entityid) || bank::is($library)) {
            return [];
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'moments');
        // Once an hour is enough: a moment lasts a week. In each language apart: its dates, figures and lists are written in
        // the language of whoever asked, and a reader in French read them in Spanish.
        $key = $entityid . '_' . intdiv($now, HOURSECS) . '_' . current_language();
        $found = $cache->get($key);
        if ($found === false) {
            $found = self::find($entityid, $now);
            $cache->set($key, $found);
        }
        $main = mail::main_library($entityid) ?? $library;
        $out = [];
        foreach ($found as $moment) {
            $few = ($moment['people'] ?? anonymity::FEW) < anonymity::FEW;
            if (!$staff && (!in_array($moment['kind'], self::PUBLIC, true) || $few)) {
                continue;
            }
            $key = $moment['key'];
            $page = $staff ? ($moment['staffpage'] ?? '') : ($moment['readerpage'] ?? '');
            $params = $moment['params'] ?? [];
            $out[] = [
                'kind' => $moment['kind'],
                'key' => $key,
                'a' => $moment['a'],
                'text' => skins::say($entityid, $key, (object) $moment['a']),
                'url' => $page !== '' ? (new \moodle_url(
                    '/local/oksigeniaayvurenda/' . $page,
                    ['library' => $main->slug] + $params
                ))->out(false) : '',
                'recordid' => (int) ($moment['recordid'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Looks for the moments of a centre this week.
     *
     * @param int $entityid
     * @param int $now
     * @return array[] In the order of ORDER.
     */
    public static function find(int $entityid, int $now): array {
        $since = $now - self::DAYS * DAYSECS;
        $moments = array_filter([
            self::anniversary($entityid, $now),
            self::loans($entityid, $now, $since),
            self::book($entityid, $now, $since),
            self::record($entityid, $now, $since),
            self::quest($entityid, $now, $since),
            self::challenge($entityid, $now),
            self::first($entityid, $now, $since),
        ]);
        return array_values($moments);
    }

    /**
     * When a centre began lending here: the day it was opened to its readers or, before that was written down, its first
     * loan at the desk (not the old ones a programme of before brought).
     *
     * @param int $entityid
     * @return int|null
     */
    public static function opened(int $entityid): ?int {
        global $DB;
        $entity = $DB->get_record(self::T . 'entity', ['id' => $entityid], 'id, settings, timecreated');
        if (!$entity) {
            return null;
        }
        $settings = json_decode((string) $entity->settings, true) ?: [];
        if (!empty($settings['opened'])) {
            return (int) $settings['opened'];
        }
        $first = $DB->get_field_sql(
            "SELECT MIN(l.timecreated)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
              WHERE b.entityid = :entityid AND l.timeloaned >= :created",
            ['entityid' => $entityid, 'created' => (int) $entity->timecreated - DAYSECS]
        );
        return $first ? (int) $first : null;
    }

    /**
     * The library online turning a month, a hundred days or a year, the week it does.
     *
     * @param int $entityid
     * @param int $now
     * @return array|null
     */
    private static function anniversary(int $entityid, int $now): ?array {
        $opened = self::opened($entityid);
        if (!$opened || $opened > $now) {
            return null;
        }
        $zone = zones::of($entityid);
        $day = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($zone)->setTime(0, 0);
        $start = $day($opened);
        $today = $day($now);
        $marks = [['month', 0, $start->modify('+1 month')], ['hundred', 0, $start->modify('+100 days')]];
        $years = (int) $start->diff($today)->y;
        for ($y = max(1, $years - 1); $y <= $years + 1; $y++) {
            $marks[] = [$y === 1 ? 'year' : 'years', $y, $start->modify('+' . $y . ' years')];
        }
        foreach ($marks as [$what, $years, $date]) {
            $since = $date->getTimestamp();
            if ($today->getTimestamp() >= $since && $today->getTimestamp() < $since + self::DAYS * DAYSECS) {
                return ['kind' => 'anniversary', 'key' => 'moment' . $what, 'a' => ['years' => $years,
                    'day' => zones::date($since, 'strftimedateshort', $entityid)],
                    'staffpage' => 'memoir.php', 'readerpage' => ''];
            }
        }
        return null;
    }

    /**
     * A round number of books lent this year, reached this week, and the book that made it.
     *
     * @param int $entityid
     * @param int $now
     * @param int $since
     * @return array|null
     */
    private static function loans(int $entityid, int $now, int $since): ?array {
        global $DB;
        $year = years::around($entityid, $now);
        $from = (int) $year->timestart;
        $where = "b.entityid = :entityid AND l.timeloaned >= :from AND l.timeloaned <= :now AND " . bank::reading('l.libraryid');
        $count = fn(int $until) => (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . self::T . "loan} l JOIN {" . self::T . "library} b ON b.id = l.libraryid WHERE $where",
            ['entityid' => $entityid, 'from' => $from, 'now' => $until]
        );
        $lent = $count($now);
        $reached = self::round($lent, self::LOANS);
        if (!$reached || self::round($since >= $from ? $count($since) : 0, self::LOANS) >= $reached) {
            return null;
        }
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timeloaned, i.recordid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE $where
           ORDER BY l.timeloaned, l.id",
            ['entityid' => $entityid, 'from' => $from, 'now' => $now],
            $reached - 1,
            1
        );
        $row = $rows ? reset($rows) : null;
        $title = $row ? self::title((int) $row->recordid) : '';
        return ['kind' => 'loans', 'key' => $title !== '' ? 'momentloans' : 'momentloansbare',
            'a' => ['count' => screens::number($reached), 'title' => $title],
            'recordid' => $row ? (int) $row->recordid : 0,
            'staffpage' => $row ? 'record.php' : 'reports.php', 'readerpage' => $row ? 'record.php' : '',
            'params' => $row ? ['id' => (int) $row->recordid] : []];
    }

    /**
     * A book that went out a round number of times this week, counting all the years of the library.
     *
     * @param int $entityid
     * @param int $now
     * @param int $since
     * @return array|null
     */
    private static function book(int $entityid, int $now, int $since): ?array {
        global $DB;
        $reading = bank::reading('l.libraryid');
        $recent = $DB->get_fieldset_sql(
            "SELECT DISTINCT i.recordid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE b.entityid = :entityid AND l.timeloaned >= :since AND l.timeloaned <= :now AND r.doctype <> 'device'
                    AND $reading",
            ['entityid' => $entityid, 'since' => $since, 'now' => $now]
        );
        if (!$recent) {
            return null;
        }
        $best = null;
        foreach (array_chunk($recent, 500) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'rec');
            $rows = $DB->get_records_sql(
                "SELECT i.recordid, COUNT(1) AS total,
                        SUM(CASE WHEN l.timeloaned >= :since THEN 1 ELSE 0 END) AS recent
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "library} b ON b.id = l.libraryid
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                  WHERE b.entityid = :entityid AND l.timeloaned <= :now AND i.recordid $in AND $reading
               GROUP BY i.recordid",
                $params + ['entityid' => $entityid, 'since' => $since, 'now' => $now]
            );
            foreach ($rows as $row) {
                $total = (int) $row->total;
                $reached = self::round($total, self::BOOK);
                if (
                    $reached && self::round($total - (int) $row->recent, self::BOOK) < $reached
                        && (!$best || $reached > $best['reached'])
                ) {
                    $best = ['recordid' => (int) $row->recordid, 'reached' => $reached];
                }
            }
        }
        if (!$best) {
            return null;
        }
        return ['kind' => 'book', 'key' => 'momentbook',
            'a' => ['count' => screens::number($best['reached']), 'title' => self::title($best['recordid'])],
            'recordid' => $best['recordid'], 'staffpage' => 'record.php', 'readerpage' => 'record.php',
            'params' => ['id' => $best['recordid']]];
    }

    /**
     * The busiest day of the year at the desk, when it was this week and beat every other, with some books.
     *
     * @param int $entityid
     * @param int $now
     * @param int $since
     * @return array|null
     */
    private static function record(int $entityid, int $now, int $since): ?array {
        global $DB;
        $year = years::around($entityid, $now);
        $from = (int) $year->timestart;
        // A record of the first weeks says little.
        if ($now - $from < 4 * WEEKSECS) {
            return null;
        }
        $offset = (int) zones::of($entityid)->getOffset(new \DateTimeImmutable('@' . $now));
        $best = function (int $a, int $b) use ($DB, $entityid, $offset): ?\stdClass {
            $rows = $DB->get_records_sql(
                "SELECT d.day, COUNT(1) AS n
                   FROM (SELECT FLOOR((l.timeloaned + $offset) / 86400) AS day
                           FROM {" . self::T . "loan} l
                           JOIN {" . self::T . "library} b ON b.id = l.libraryid
                          WHERE b.entityid = :entityid AND l.timeloaned >= :a AND l.timeloaned < :b
                                AND " . bank::reading('l.libraryid') . ") d
               GROUP BY d.day
               ORDER BY COUNT(1) DESC, d.day",
                ['entityid' => $entityid, 'a' => $a, 'b' => $b],
                0,
                1
            );
            return $rows ? reset($rows) : null;
        };
        $week = $best($since, $now + 1);
        if (!$week || (int) $week->n < self::RECORD) {
            return null;
        }
        $before = $best($from, $since);
        if ($before && (int) $before->n >= (int) $week->n) {
            return null;
        }
        $day = (int) $week->day * DAYSECS - $offset + 12 * HOURSECS;
        return ['kind' => 'record', 'key' => 'momentrecord',
            'a' => ['count' => screens::number((int) $week->n),
                'day' => zones::date($day, 'strftimedateshort', $entityid)],
            'staffpage' => 'reports.php', 'readerpage' => ''];
    }

    /**
     * Readers who reached the goal of a challenge of the library this week, or how one that just ended went.
     *
     * @param int $entityid
     * @param int $now
     * @param int $since
     * @return array|null
     */
    private static function quest(int $entityid, int $now, int $since): ?array {
        global $DB;
        $quests = $DB->get_records_sql(
            "SELECT q.id, q.name, q.timeend,
                    (SELECT COUNT(1) FROM {" . self::T . "questreader} r
                      WHERE r.questid = q.id AND r.timedone IS NOT NULL) AS done,
                    (SELECT COUNT(1) FROM {" . self::T . "questreader} r
                      WHERE r.questid = q.id AND r.timedone >= :since AND r.timedone <= :now) AS week
               FROM {" . self::T . "quest} q
               JOIN {" . self::T . "library} b ON b.id = q.libraryid
              WHERE b.entityid = :entityid AND q.timestart <= :now2 AND q.timeend >= :since2
           ORDER BY q.timeend DESC, q.id",
            ['entityid' => $entityid, 'since' => $since, 'now' => $now, 'now2' => $now, 'since2' => $since]
        );
        foreach ($quests as $quest) {
            $name = format_string($quest->name, true, ['escape' => false, 'context' => \context_system::instance()]);
            $ended = (int) $quest->timeend < $now;
            $count = $ended ? (int) $quest->done : (int) $quest->week;
            if ($count) {
                return ['kind' => 'quest', 'key' => ($ended ? 'momentquestended' : 'momentquest') . ($count === 1 ? 'one' : ''),
                    'a' => ['count' => screens::number($count), 'name' => $name], 'people' => $count,
                    'staffpage' => 'quests.php', 'readerpage' => 'card.php'];
            }
        }
        return null;
    }

    /**
     * The classes that met the challenge of their term in the last two weeks: for the staff, who did not see it happen.
     *
     * @param int $entityid
     * @param int $now
     * @return array|null
     */
    private static function challenge(int $entityid, int $now): ?array {
        global $DB;
        if (skins::no_classes($entityid)) {
            return null;
        }
        $names = $DB->get_fieldset_sql(
            "SELECT DISTINCT c.groupname
               FROM {" . self::T . "challenge} c
               JOIN {" . self::T . "year} y ON y.id = c.yearid
              WHERE y.entityid = :entityid AND c.timecompleted >= :since AND c.timecompleted <= :now
           ORDER BY c.groupname",
            ['entityid' => $entityid, 'since' => $now - challenges::CHEER, 'now' => $now]
        );
        if (!$names) {
            return null;
        }
        $names = array_map(
            fn($name) => format_string($name, true, ['escape' => false, 'context' => \context_system::instance()]),
            $names
        );
        // Never «and so many more»: up to four, by name; past four, how many, and the reports say which.
        $many = count($names) > 4;
        return ['kind' => 'challenge', 'key' => count($names) === 1 ? 'momentchallengeone'
                : ($many ? 'momentchallengemany' : 'momentchallenge'),
            'a' => ['count' => count($names), 'names' => $many ? '' : self::join($names)], 'staffpage' => 'reports.php'];
    }

    /**
     * Readers who took their very first book of the centre this week.
     *
     * @param int $entityid
     * @param int $now
     * @param int $since
     * @return array|null
     */
    private static function first(int $entityid, int $now, int $since): ?array {
        global $DB;
        $count = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (SELECT l.readerid
                       FROM {" . self::T . "loan} l
                       JOIN {" . self::T . "library} b ON b.id = l.libraryid
                       JOIN {" . self::T . "reader} r ON r.id = l.readerid
                      WHERE b.entityid = :entityid AND r.readertype <> :library AND l.timeloaned <= :now
                            AND " . bank::reading('l.libraryid') . "
                   GROUP BY l.readerid
                     HAVING MIN(l.timeloaned) >= :since) f",
            ['entityid' => $entityid, 'library' => ill::READERTYPE, 'now' => $now, 'since' => $since]
        );
        if ($count < 2) {
            return null;
        }
        return ['kind' => 'first', 'key' => 'momentfirst', 'a' => ['count' => screens::number($count)], 'people' => $count,
            'staffpage' => 'readers.php', 'readerpage' => ''];
    }

    /**
     * The biggest round number a count has passed.
     *
     * @param int $count
     * @param int[] $marks
     * @return int 0 below the first.
     */
    private static function round(int $count, array $marks): int {
        $reached = 0;
        foreach ($marks as $mark) {
            if ($count >= $mark) {
                $reached = $mark;
            }
        }
        return $reached;
    }

    /**
     * A book's title, as the library wrote it.
     *
     * @param int $recordid
     * @return string
     */
    private static function title(int $recordid): string {
        global $DB;
        $title = (string) $DB->get_field(self::T . 'record', 'title', ['id' => $recordid]);
        return format_string($title, true, ['escape' => false, 'context' => \context_system::instance()]);
    }

    /**
     * Names joined as a sentence: «3.º A, 3.º B y 4.º C».
     *
     * @param string[] $names
     * @return string
     */
    private static function join(array $names): string {
        if (count($names) < 2) {
            return (string) reset($names);
        }
        $last = array_pop($names);
        return get_string('momentand', 'local_oksigeniaayvurenda', (object) ['names' => implode(', ', $names), 'last' => $last]);
    }
}
