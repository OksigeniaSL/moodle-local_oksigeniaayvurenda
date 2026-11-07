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
 * What was catalogued lately: the copies added by hand to a library today, this week, this fortnight or this month.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Books arrive in boxes and are catalogued a while at a time: the library looks back over what it added, in order, and
 * sees what may have gone in twice. What an import brought is not counted: it was not catalogued here.
 */
final class catalogued {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The spans, from the shortest. */
    public const SPANS = ['today', 'week', 'fortnight', 'month'];

    /** @var int Copies on a page. */
    public const PAGE = 120;

    /**
     * When each span starts where the centre is: today at midnight, Monday, the 1st or the 16th (the half of the month we
     * are in), the first of the month.
     *
     * @param int $entityid
     * @param int $now
     * @return array span => time.
     */
    public static function starts(int $entityid, int $now): array {
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(zones::of($entityid))->setTime(0, 0);
        return [
            'today' => $today->getTimestamp(),
            'week' => $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->getTimestamp(),
            'fortnight' => $today->setDate(
                (int) $today->format('Y'),
                (int) $today->format('n'),
                (int) $today->format('j') > 15 ? 16 : 1
            )->getTimestamp(),
            'month' => $today->modify('first day of this month')->getTimestamp(),
        ];
    }

    /**
     * How many copies were added by hand to a library since a time.
     *
     * @param \stdClass $library
     * @param int $since
     * @return int
     */
    public static function count(\stdClass $library, int $since): int {
        global $DB;
        return (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::T . 'item} i WHERE ' . self::WHERE,
            ['libraryid' => $library->id, 'since' => $since]
        );
    }

    /** @var string The copies of a library added by hand since a time. */
    private const WHERE = 'i.libraryid = :libraryid AND i.timecreated >= :since
        AND NOT EXISTS (SELECT 1 FROM {' . self::T . "source} s WHERE s.tablename = 'item' AND s.localid = i.id)";

    /**
     * The copies added by hand to a library since a time, the latest first, one line for each record added at once.
     *
     * @param \stdClass $library
     * @param int $since
     * @param int $from Where the page starts, in copies.
     * @return array[] recordid, title, isbn, codes, call (the spine), location, time; twice when the same record went in at
     *     two moments of the span, and twin when another record of the centre has its ISBN.
     */
    public static function lines(\stdClass $library, int $since, int $from = 0): array {
        global $DB;
        $items = $DB->get_records_sql(
            'SELECT i.id, i.recordid, i.barcode, i.callnumber1, i.callnumber2, i.callnumber3, i.locationid, i.timecreated,
                    r.title, r.isbn
               FROM {' . self::T . 'item} i
               JOIN {' . self::T . 'record} r ON r.id = i.recordid
              WHERE ' . self::WHERE . '
           ORDER BY i.timecreated DESC, i.id DESC',
            ['libraryid' => $library->id, 'since' => $since],
            $from,
            self::PAGE
        );
        $places = cataloguer::locations((int) $library->entityid);
        $lines = [];
        $moments = [];
        foreach ($items as $item) {
            $last = $lines ? count($lines) - 1 : -1;
            // Copies saved together are one addition (they share their second); the same book passed again a moment later
            // is another, and that is what may be a mistake.
            if (
                $last >= 0 && $lines[$last]['recordid'] === (int) $item->recordid
                    && $lines[$last]['time'] === (int) $item->timecreated
            ) {
                $lines[$last]['codes'][] = $item->barcode;
                continue;
            }
            $moments[(int) $item->recordid] = ($moments[(int) $item->recordid] ?? 0) + 1;
            $lines[] = [
                'recordid' => (int) $item->recordid,
                'title' => format_string($item->title, true, ['escape' => false]),
                'isbn' => (string) $item->isbn,
                'codes' => [$item->barcode],
                'call' => trim(implode(' ', array_filter(
                    [$item->callnumber1, $item->callnumber2, $item->callnumber3],
                    fn($line) => trim((string) $line) !== ''
                ))),
                'location' => $item->locationid ? (string) ($places[$item->locationid] ?? '') : '',
                'time' => (int) $item->timecreated,
            ];
        }
        $isbns = array_values(array_unique(array_filter(array_column($lines, 'isbn'))));
        $shared = [];
        if ($isbns) {
            [$in, $params] = $DB->get_in_or_equal($isbns, SQL_PARAMS_NAMED);
            $shared = $DB->get_records_sql_menu(
                'SELECT isbn, COUNT(1) FROM {' . self::T . "record} WHERE entityid = :entityid AND isbn $in GROUP BY isbn
                 HAVING COUNT(1) > 1",
                $params + ['entityid' => $library->entityid]
            );
        }
        foreach ($lines as $i => $line) {
            $lines[$i]['twice'] = $moments[$line['recordid']] > 1;
            $lines[$i]['twin'] = $line['isbn'] !== '' && isset($shared[$line['isbn']]);
        }
        return $lines;
    }
}
