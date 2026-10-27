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
 * The herald's shelf: a game in the hall of the library one visits.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The herald brings the books that came in last, and each goes to its corner: the corners as doors, by the names the
 * library gives them. A wrong door says what numbers it keeps and what number the book has, never the answer; after two,
 * the right one glows. It teaches the classification without explaining it. Nothing is kept.
 */
final class shelve {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Books a turn. */
    public const BOOKS = 4;

    /** @var int The newest books looked at. */
    private const LATEST = 60;

    /**
     * The books the herald brings this turn: the newest of the corners the library shows, four at a time, around again
     * when they run out.
     *
     * @param \stdClass $library
     * @param int $turn
     * @return array[] id, title, cover, colour, corner (its code), cornerlabel, topic (what its class says, or '') and
     *     lines (of its label); none when there are fewer than three, or fewer than two corners to choose from.
     */
    public static function books(\stdClass $library, int $turn): array {
        global $DB;
        $corners = [];
        foreach (catalogue::corners($library) as $corner) {
            $corners[$corner['code']] = $corner;
        }
        if (count($corners) < 2) {
            return [];
        }
        $latest = $DB->get_records_sql(
            "SELECT i.recordid, MAX(COALESCE(i.timeacquired, i.timecreated)) AS acquired
               FROM {" . self::T . "item} i
              WHERE i.libraryid = :libraryid AND i.status <> 'lost' AND i.status <> 'withdrawn'
           GROUP BY i.recordid
           ORDER BY acquired DESC, i.recordid DESC",
            ['libraryid' => $library->id],
            0,
            self::LATEST
        );
        if (!$latest) {
            return [];
        }
        $ids = array_map('intval', array_keys($latest));
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $classes = [];
        foreach (
            $DB->get_records_sql(
                "SELECT ra.id, ra.recordid, a.code, a.label
                   FROM {" . self::T . "recauth} ra
                   JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                  WHERE ra.recordid $in
               ORDER BY ra.sortorder, ra.id",
                $params
            ) as $link
        ) {
            if (!isset($classes[$link->recordid]) && preg_match('/^\s*(\d)/', (string) $link->code, $top)) {
                $classes[$link->recordid] = ['corner' => $top[1], 'code' => trim((string) $link->code),
                    'label' => catalogue::tidy_label((string) $link->label)];
            }
        }
        $kept = array_values(array_filter($ids, fn($id) => isset($classes[$id]) && isset($corners[$classes[$id]['corner']])));
        if (count($kept) < 3) {
            return [];
        }
        $at = ($turn * self::BOOKS) % count($kept);
        $chosen = array_slice(array_merge(array_slice($kept, $at), array_slice($kept, 0, $at)), 0, self::BOOKS);
        [$cin, $cparams] = $DB->get_in_or_equal($chosen, SQL_PARAMS_NAMED);
        $titles = $DB->get_records_select_menu(self::T . 'record', 'id ' . $cin, $cparams, '', 'id, title');
        $labels = [];
        foreach (
            $DB->get_records_select(
                self::T . 'item',
                'recordid ' . $cin . ' AND libraryid = :libraryid',
                $cparams + ['libraryid' => $library->id],
                'id',
                'id, recordid, callnumber1, callnumber2, callnumber3'
            ) as $item
        ) {
            $labels[$item->recordid] = $labels[$item->recordid] ?? array_values(array_filter(
                [$item->callnumber1, $item->callnumber2, $item->callnumber3],
                fn($line) => trim((string) $line) !== ''
            ));
        }
        $covers = covers::urls($chosen);
        $books = [];
        foreach ($chosen as $id) {
            $class = $classes[$id];
            $corner = $corners[$class['corner']];
            // What its class says, when it says more than its number.
            $topic = $class['label'] !== '' && $class['label'] !== $class['code'] ? $class['label'] : '';
            $books[] = ['id' => $id, 'title' => (string) ($titles[$id] ?? ''), 'cover' => $covers[$id] ?? null,
                'colour' => $corner['colour'], 'corner' => $class['corner'], 'cornerlabel' => $corner['label'],
                'topic' => $topic, 'lines' => array_map(
                    fn($line) => ['line' => trim((string) $line)],
                    ($labels[$id] ?? []) ?: [$class['code']]
                )];
        }
        return $books;
    }
}
