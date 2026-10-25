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
 * The six fixed reports of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Six reports that answer what libraries are asked, instead of a report builder: loans by period and group, late
 * books, the most lent, the never lent (to weed), the holdings by subject and age, and the school year for the
 * library's yearly report. Counts and titles, never what a child read: groups, not names.
 */
final class reports {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The reports, in their order. */
    public const ALL = ['loans', 'late', 'top', 'never', 'holdings', 'year'];

    /**
     * Loans in a period, by group: how many, how many readers of the group took something.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $to
     * @return array rows (label, value, readers, members), total.
     */
    public static function loans(\stdClass $library, int $from, int $to): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT COALESCE(l.groupname, \'\') AS groupname, COUNT(1) AS loans, COUNT(DISTINCT l.readerid) AS readers
               FROM {' . self::T . 'loan} l
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b
           GROUP BY COALESCE(l.groupname, \'\')',
            ['libraryid' => $library->id, 'a' => $from, 'b' => $to]
        );
        $year = years::around((int) $library->entityid, $to - 1);
        $members = $DB->get_records_sql_menu(
            'SELECT groupname, COUNT(1) FROM {' . self::T . 'member} WHERE yearid = :yearid AND groupname IS NOT NULL
           GROUP BY groupname',
            ['yearid' => $year->id]
        );
        $list = [];
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row->loans;
            $list[] = ['label' => $row->groupname, 'value' => (int) $row->loans, 'readers' => (int) $row->readers,
                'members' => (int) ($members[$row->groupname] ?? 0)];
        }
        usort($list, fn($a, $b) => strnatcmp($a['label'], $b['label']));
        return ['rows' => $list, 'total' => $total];
    }

    /**
     * The most lent titles in a period.
     *
     * @param \stdClass $library
     * @param int $from
     * @param int $to
     * @param int $limit
     * @return array[] label (title), value (loans), copies.
     */
    public static function top(\stdClass $library, int $from, int $to, int $limit = 25): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT r.id, r.title, COUNT(1) AS loans,
                    (SELECT COUNT(1) FROM {' . self::T . 'item} c WHERE c.recordid = r.id AND c.libraryid = :lib2) AS copies
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'item} i ON i.id = l.itemid
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE l.libraryid = :libraryid AND l.timeloaned >= :a AND l.timeloaned < :b
           GROUP BY r.id, r.title
           ORDER BY COUNT(1) DESC, r.title',
            ['libraryid' => $library->id, 'lib2' => $library->id, 'a' => $from, 'b' => $to],
            0,
            $limit
        );
        return array_values(array_map(fn($r) => ['label' => $r->title, 'value' => (int) $r->loans,
            'copies' => (int) $r->copies], $rows));
    }

    /**
     * Copies never lent, or not since a moment, acquired before another: the candidates to weed.
     *
     * @param \stdClass $library
     * @param int $since Not lent since this moment (0: never lent).
     * @param int $acquired Acquired before this moment.
     * @param int $limit 0 for all of them.
     * @return array rows (label, barcode, callnumber, acquired, lastloan), total.
     */
    public static function never(\stdClass $library, int $since, int $acquired, int $limit = 200): array {
        global $DB;
        $where = 'i.libraryid = :libraryid AND i.status <> :withdrawn AND (i.timeacquired IS NULL OR i.timeacquired < :acquired)
            AND NOT EXISTS (SELECT 1 FROM {' . self::T . 'loan} l WHERE l.itemid = i.id AND l.timeloaned >= :since)';
        $params = ['libraryid' => $library->id, 'withdrawn' => 'withdrawn', 'acquired' => $acquired, 'since' => $since];
        $total = $DB->count_records_sql('SELECT COUNT(1) FROM {' . self::T . 'item} i WHERE ' . $where, $params);
        $rows = $DB->get_records_sql(
            'SELECT i.id, i.barcode, i.callnumber1, i.callnumber2, i.callnumber3, i.timeacquired, r.title,
                    (SELECT MAX(l2.timeloaned) FROM {' . self::T . 'loan} l2 WHERE l2.itemid = i.id) AS lastloan
               FROM {' . self::T . 'item} i
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE ' . $where . '
           ORDER BY i.timeacquired, i.id',
            $params,
            0,
            $limit
        );
        return ['total' => $total, 'rows' => array_values(array_map(fn($r) => [
            'label' => $r->title,
            'barcode' => $r->barcode,
            'callnumber' => trim($r->callnumber1 . ' ' . $r->callnumber2 . ' ' . $r->callnumber3),
            'acquired' => $r->timeacquired ? (int) $r->timeacquired : null,
            'lastloan' => $r->lastloan ? (int) $r->lastloan : null,
        ], $rows))];
    }

    /**
     * The holdings by subject (the first sign of the class) and by audience, with how many were lent this year.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array subjects and audiences, rows of label, value (copies) and lent (copies lent this year).
     */
    public static function holdings(\stdClass $library, ?int $now = null): array {
        global $DB;
        $year = years::around((int) $library->entityid, $now ?? time());
        $by = function (string $kind, bool $first) use ($DB, $library, $year): array {
            $label = $first ? $DB->sql_substr('a.code', 1, 1) : 'a.label';
            $rows = $DB->get_records_sql(
                "SELECT $label AS label, COUNT(DISTINCT i.id) AS copies,
                        COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN i.id END) AS lent
                   FROM {" . self::T . "item} i
                   JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = :kind
              LEFT JOIN {" . self::T . "loan} l ON l.itemid = i.id AND l.yearid = :yearid
                  WHERE i.libraryid = :libraryid
               GROUP BY $label",
                ['kind' => $kind, 'yearid' => $year->id, 'libraryid' => $library->id]
            );
            $list = array_values(array_map(fn($r) => ['label' => (string) $r->label, 'value' => (int) $r->copies,
                'lent' => (int) $r->lent], $rows));
            usort($list, fn($a, $b) => strnatcmp($a['label'], $b['label']));
            return $list;
        };
        $corners = [];
        foreach (catalogue::corners($library) as $corner) {
            $corners[$corner['code']] = $corner['label'];
        }
        $subjects = array_map(fn($r) => ['label' => $corners[$r['label']] ?? $r['label']] + $r, $by('class', true));
        return ['subjects' => $subjects, 'audiences' => $by('audience', false)];
    }

    /**
     * The school year for the yearly report: loans, readers, new copies, the most lent and the groups.
     *
     * @param \stdClass $library
     * @param \stdClass $year
     * @return array
     */
    public static function year(\stdClass $library, \stdClass $year): array {
        global $DB;
        $summary = year_change::summary($year);
        $acquired = $DB->count_records_select(
            self::T . 'item',
            'libraryid = :libraryid AND timecreated >= :a AND timecreated < :b',
            ['libraryid' => $library->id, 'a' => $year->timestart, 'b' => $year->timeend]
        );
        // The books read here without going home.
        $room = $DB->count_records_select(
            self::T . 'roomread',
            'libraryid = :libraryid AND timecreated >= :a AND timecreated < :b',
            ['libraryid' => $library->id, 'a' => $year->timestart, 'b' => $year->timeend]
        );
        return $summary + [
            'acquired' => $acquired,
            'room' => $room,
            'top' => self::top($library, (int) $year->timestart, (int) $year->timeend, 10),
        ];
    }
}
