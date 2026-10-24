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
 * The flower and the album of a reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\flower;

/**
 * A reader's flower of this school year, by the four paths, and the album: the books of the year and the flowers of
 * other years. Everything comes from the desk (loans, returns and the note of one tap): nothing the reader declares.
 * Only for the reader: the tutor sees lateness, never what a child reads.
 */
final class garden {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The flower of a reader in a school year.
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @return array levels and stage.
     */
    public static function flower(\stdClass $reader, \stdClass $year): array {
        $levels = flower::levels(flower::counts(self::loans((int) $reader->id, (int) $year->id)));
        return ['levels' => $levels, 'stage' => flower::stage($levels)];
    }

    /**
     * The album of a reader: this year's flower and books, and the flowers of the years before.
     *
     * @param \stdClass $reader
     * @param int|null $now
     * @return array year, flower, books (id, title, cover, note), past (year name and stage).
     */
    public static function album(\stdClass $reader, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $year = years::around((int) $reader->entityid, $now);
        $loans = self::loans((int) $reader->id, (int) $year->id);
        $books = [];
        foreach (array_reverse($loans) as $loan) {
            if (!isset($books[$loan['recordid']])) {
                $books[$loan['recordid']] = ['id' => $loan['recordid'], 'title' => $loan['title'], 'note' => $loan['note']];
            }
        }
        $covers = covers::urls(array_keys($books));
        foreach ($books as $id => $book) {
            $books[$id]['cover'] = $covers[$id] ?? null;
        }
        $past = [];
        $years = $DB->get_records_sql(
            'SELECT DISTINCT y.id, y.name, y.timestart
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'year} y ON y.id = l.yearid
              WHERE l.readerid = :readerid AND y.id <> :yearid
           ORDER BY y.timestart DESC',
            ['readerid' => $reader->id, 'yearid' => $year->id]
        );
        foreach ($years as $other) {
            $past[] = ['year' => $other->name] + self::flower($reader, $other);
        }
        return ['year' => $year, 'flower' => self::flower($reader, $year), 'books' => array_values($books), 'past' => $past];
    }

    /**
     * The loans of a reader in a school year, as the flower counts them.
     *
     * @param int $readerid
     * @param int $yearid
     * @return array[]
     */
    private static function loans(int $readerid, int $yearid): array {
        global $DB;
        $tz = \core_date::get_server_timezone_object();
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.timeloaned, l.timereturned, l.returnnote, i.recordid, r.title,
                    (SELECT MIN(a.code)
                       FROM {" . self::T . "recauth} ra
                       JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                      WHERE ra.recordid = i.recordid) AS class
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE l.readerid = :readerid AND l.yearid = :yearid
           ORDER BY l.timeloaned, l.id",
            ['readerid' => $readerid, 'yearid' => $yearid]
        );
        $day = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->format('Y-m-d');
        $loans = [];
        foreach ($rows as $row) {
            $loans[] = [
                'day' => $day((int) $row->timeloaned),
                'returnday' => $row->timereturned ? $day((int) $row->timereturned) : null,
                // The corner of the library: the first sign of the class.
                'corner' => $row->class !== null ? \core_text::substr(trim($row->class), 0, 1) : '',
                'recordid' => (int) $row->recordid,
                'title' => $row->title,
                'note' => $row->returnnote,
                'returned' => (bool) $row->timereturned,
            ];
        }
        return $loans;
    }
}
