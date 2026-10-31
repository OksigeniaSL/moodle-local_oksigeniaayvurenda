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
 * The library's report of a school year, for the centre's reading plan.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * What a centre hands in at the end of the year about its library and its reading plan, without counting by hand: the
 * year's figures beside the year before, by stage and by group, what went out most and from which corners, what the
 * readers did, the textbook bank, and the team's own words. Always added up and anonymous: never minutes nor readings
 * of a pupil.
 */
final class memoir {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The report of a year of a centre.
     *
     * @param \stdClass $library A library of the centre (its corners name the classes).
     * @param \stdClass $year
     * @return array figures, before (the same of the year before, or null), stages, groups, top, corners, life, bank,
     *     notes.
     */
    public static function of(\stdClass $library, \stdClass $year): array {
        global $DB;
        $entityid = (int) $library->entityid;
        // The year just before, if the centre has one.
        $before = $DB->get_records_select(
            self::T . 'year',
            'entityid = :entityid AND timeend <= :start',
            ['entityid' => $entityid, 'start' => $year->timestart],
            'timeend DESC',
            '*',
            0,
            1
        );
        $before = reset($before) ?: null;
        return [
            'figures' => self::figures($entityid, $year),
            'before' => $before ? self::figures($entityid, $before) + ['name' => $before->name] : null,
            'stages' => self::stages($year),
            'groups' => year_change::summary($year)['groups'],
            'top' => self::top($entityid, $year),
            'corners' => self::corners($library, $year),
            'life' => self::life($entityid, $year),
            'bank' => self::bank($entityid, $year),
            'notes' => self::notes($entityid, (int) $year->id),
        ];
    }

    /**
     * The team's words of a year: what was done and what comes next.
     *
     * @param int $entityid
     * @param int $yearid
     * @return array done, next.
     */
    public static function notes(int $entityid, int $yearid): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $notes = (array) ($settings['memoir'][$yearid] ?? []);
        return ['done' => (string) ($notes['done'] ?? ''), 'next' => (string) ($notes['next'] ?? '')];
    }

    /**
     * Keeps the team's words of a year.
     *
     * @param int $entityid
     * @param int $yearid
     * @param string $done
     * @param string $next
     */
    public static function save_notes(int $entityid, int $yearid, string $done, string $next): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $settings['memoir'][$yearid] = ['done' => \core_text::substr(trim($done), 0, 5000),
            'next' => \core_text::substr(trim($next), 0, 5000)];
        $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
    }

    /**
     * The year's figures: loans, readers who came of those of the year, read in the room, copies come, gone and held.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @return array loans, borrowers, came (of the readers of the year), readers, room, acquired, withdrawn, holdings.
     */
    private static function figures(int $entityid, \stdClass $year): array {
        global $DB;
        $summary = year_change::summary($year);
        $range = ['entityid' => $entityid, 'a' => $year->timestart, 'b' => $year->timeend];
        $libraries = "SELECT id FROM {" . self::T . "library} WHERE entityid = :entityid";
        // Of the readers of the year (its groups), those who came: the staff borrow too, and are not of a group.
        $came = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.readerid)
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "member} m ON m.readerid = l.readerid AND m.yearid = l.yearid
              WHERE l.yearid = :yearid",
            ['yearid' => $year->id]
        );
        return [
            'loans' => $summary['loans'],
            'borrowers' => $summary['borrowers'],
            'came' => $came,
            'readers' => $summary['members'],
            'room' => $DB->count_records_select(
                self::T . 'roomread',
                "libraryid IN ($libraries) AND timecreated >= :a AND timecreated < :b",
                $range
            ),
            'acquired' => $DB->count_records_select(
                self::T . 'item',
                'entityid = :entityid AND timecreated >= :a AND timecreated < :b',
                $range
            ),
            'withdrawn' => (int) $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::T . "withdrawn} w
                JOIN {" . self::T . "withdrawal} d ON d.id = w.withdrawalid
                WHERE d.libraryid IN ($libraries) AND d.timecreated >= :a AND d.timecreated < :b
                    AND w.timerestored IS NULL", $range),
            'holdings' => $DB->count_records_select(
                self::T . 'item',
                "entityid = :entityid AND status <> 'withdrawn'",
                ['entityid' => $entityid]
            ),
        ];
    }

    /**
     * By stage: its readers, those who came and their loans.
     *
     * @param \stdClass $year
     * @return array[] stage, readers, borrowers, loans.
     */
    private static function stages(\stdClass $year): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT COALESCE(m.stage, '') AS stage, COUNT(DISTINCT m.readerid) AS readers,
                    COUNT(DISTINCT l.readerid) AS borrowers, COUNT(l.id) AS loans
               FROM {" . self::T . "member} m
          LEFT JOIN {" . self::T . "loan} l ON l.readerid = m.readerid AND l.yearid = m.yearid
              WHERE m.yearid = :yearid
           GROUP BY COALESCE(m.stage, '')
           ORDER BY COALESCE(m.stage, '')",
            ['yearid' => $year->id]
        );
        return array_values(array_map(fn($r) => ['stage' => $r->stage !== '' ? $r->stage : '—',
            'readers' => (int) $r->readers, 'borrowers' => (int) $r->borrowers, 'loans' => (int) $r->loans], $rows));
    }

    /**
     * What went out most in the year, in the whole centre.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @return array[] id, title, loans.
     */
    private static function top(int $entityid, \stdClass $year): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.title, COUNT(1) AS loans
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE r.entityid = :entityid AND l.yearid = :yearid
           GROUP BY r.id, r.title
           ORDER BY COUNT(1) DESC, r.title",
            ['entityid' => $entityid, 'yearid' => $year->id],
            0,
            10
        );
        return array_values(array_map(fn($r) => ['id' => (int) $r->id, 'title' => $r->title, 'loans' => (int) $r->loans], $rows));
    }

    /**
     * The corners books went out from, most first.
     *
     * @param \stdClass $library
     * @param \stdClass $year
     * @return array[] code, corner, loans.
     */
    private static function corners(\stdClass $library, \stdClass $year): array {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT l.id, a.code
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
              WHERE l.yearid = :yearid AND a.entityid = :entityid",
            ['yearid' => $year->id, 'entityid' => $library->entityid]
        );
        $counted = [];
        $seen = [];
        foreach ($rows as $row) {
            $top = catalogue::top_class((string) $row->code);
            if ($top !== null && !isset($seen[$row->id])) {
                $seen[$row->id] = true;
                $counted[$top] = ($counted[$top] ?? 0) + 1;
            }
        }
        $rows->close();
        $labels = array_column(catalogue::corners($library, true), 'label', 'code');
        arsort($counted);
        $out = [];
        foreach ($counted as $code => $loans) {
            $out[] = ['code' => (string) $code, 'corner' => $labels[(string) $code] ?? (string) $code, 'loans' => $loans];
        }
        return $out;
    }

    /**
     * What the readers did besides borrowing: class challenges met, reviews published, voices given to books.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @return array challenges, reviews, voices.
     */
    private static function life(int $entityid, \stdClass $year): array {
        global $DB;
        $range = ['entityid' => $entityid, 'a' => $year->timestart, 'b' => $year->timeend];
        return [
            'challenges' => $DB->count_records_select(
                self::T . 'challenge',
                'yearid = :yearid AND timecompleted IS NOT NULL',
                ['yearid' => $year->id]
            ),
            'reviews' => $DB->count_records_select(
                self::T . 'review',
                "entityid = :entityid AND status = 'published' AND timecreated >= :a AND timecreated < :b",
                $range
            ),
            'voices' => $DB->count_records_select(
                self::T . 'digital',
                "entityid = :entityid AND kind = 'voice' AND status = 'published' AND timecreated >= :a AND timecreated < :b",
                $range
            ),
        ];
    }

    /**
     * The textbook banks of the centre in the year: copies lent and pupils who got them.
     *
     * @param int $entityid
     * @param \stdClass $year
     * @return array|null loans, pupils; null without a bank.
     */
    private static function bank(int $entityid, \stdClass $year): ?array {
        global $DB;
        $banks = $DB->get_fieldset_select(
            self::T . 'library',
            'id',
            'entityid = :entityid AND kind = :kind',
            ['entityid' => $entityid, 'kind' => bank::KIND]
        );
        if (!$banks) {
            return null;
        }
        [$in, $params] = $DB->get_in_or_equal($banks, SQL_PARAMS_NAMED);
        $row = $DB->get_record_sql("SELECT COUNT(1) AS loans, COUNT(DISTINCT readerid) AS pupils
            FROM {" . self::T . "loan} WHERE yearid = :yearid AND libraryid $in", ['yearid' => $year->id] + $params);
        return ['loans' => (int) $row->loans, 'pupils' => (int) $row->pupils];
    }
}
