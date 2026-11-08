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
 * The curious things of a catalogue, which the memory tells when she has nothing of the reader's own to tell and the
 * blackboard of the visit writes up: the longest title, a title of one word, a title that is a question and the oldest
 * book still in service (jue-humor H37 to H39). Worked out once a day for each library, and kept.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curiosities {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Words a title needs to be worth telling as the longest. */
    public const LONG = 8;

    /** @var int Years a book needs to be worth telling as the oldest. */
    public const OLD = 50;

    /**
     * What is curious in a library's catalogue today: each one only if there is one.
     *
     * @param \stdClass $library
     * @param int|null $now
     * @return array long (id, title, words), short (id, title), question (id, title) and oldest (id, title, year), each
     *     present only if the catalogue has it.
     */
    public static function of(\stdClass $library, ?int $now = null): array {
        $now = $now ?? time();
        if (bank::is($library)) {
            return [];
        }
        $day = zones::date($now, '%Y%m%d', $library);
        $cache = \cache::make('local_oksigeniaayvurenda', 'curiosities');
        $key = 'l' . $library->id . 'd' . $day;
        $found = $cache->get($key);
        if ($found === false) {
            $found = self::find($library, (int) $day);
            $cache->set($key, $found);
        }
        return $found;
    }

    /**
     * Looks for them in the catalogue.
     *
     * @param \stdClass $library
     * @param int $day The day, as a number, so that the pick among equals changes from one day to the next.
     * @return array
     */
    private static function find(\stdClass $library, int $day): array {
        global $DB;
        // What is read in this library (books, e-books, audiobooks): not the material, nor what was withdrawn or lost.
        $here = "r.entityid = :entityid AND r.doctype IN ('book', 'electronic', 'audio') AND EXISTS (
                     SELECT 1 FROM {" . self::T . "item} i
                      WHERE i.recordid = r.id AND i.libraryid = :libraryid AND i.status NOT IN ('withdrawn', 'lost'))";
        $params = ['entityid' => $library->entityid, 'libraryid' => $library->id];
        $found = [];

        $longest = $DB->get_records_sql(
            "SELECT r.id, r.title FROM {" . self::T . "record} r
              WHERE $here
           ORDER BY " . $DB->sql_length('r.title') . " DESC, r.id",
            $params,
            0,
            20
        );
        $best = null;
        foreach ($longest as $record) {
            $title = self::tidy((string) $record->title);
            $words = count(self::words($title));
            if ($words >= self::LONG && (!$best || $words > $best['words'])) {
                $best = ['id' => (int) $record->id, 'title' => $title, 'words' => $words];
            }
        }
        if ($best) {
            $found['long'] = $best;
        }

        $short = $DB->get_records_sql(
            "SELECT r.id, r.title FROM {" . self::T . "record} r
              WHERE $here AND " . $DB->sql_like('r.title', ':space', true, true, true) . "
                    AND " . $DB->sql_length('r.title') . " >= 4
           ORDER BY r.id",
            $params + ['space' => '% %'],
            0,
            60
        );
        $short = array_values(array_filter(
            $short,
            fn($record) => count(self::words(self::tidy((string) $record->title))) === 1
                && preg_match('/\p{L}{4}/u', (string) $record->title)
        ));
        if ($short) {
            $one = $short[$day % count($short)];
            $found['short'] = ['id' => (int) $one->id, 'title' => self::tidy($one->title)];
        }

        $questions = $DB->get_records_sql(
            "SELECT r.id, r.title FROM {" . self::T . "record} r
              WHERE $here AND " . $DB->sql_like('r.title', ':question') . "
           ORDER BY r.id",
            $params + ['question' => '%?'],
            0,
            60
        );
        $questions = array_values($questions);
        if ($questions) {
            $one = $questions[$day % count($questions)];
            $found['question'] = ['id' => (int) $one->id, 'title' => self::tidy($one->title)];
        }

        // The oldest: a year of four figures, bare or in brackets as cataloguers write a year they worked out.
        $year = (int) date('Y');
        $old = [];
        foreach (['1%', $DB->sql_like_escape('[1') . '%'] as $starts) {
            $old += $DB->get_records_sql(
                "SELECT r.id, r.title, r.pubyear FROM {" . self::T . "record} r
                  WHERE $here AND " . $DB->sql_like('r.pubyear', ':starts') . "
               ORDER BY r.pubyear, r.id",
                $params + ['starts' => $starts],
                0,
                50
            );
        }
        $oldest = null;
        foreach ($old as $record) {
            if (!preg_match('/^\s*\[?(\d{4})\]?\.?\s*$/', (string) $record->pubyear, $m)) {
                continue;
            }
            $published = (int) $m[1];
            if ($published >= 1450 && $published <= $year - self::OLD && (!$oldest || $published < $oldest['year'])) {
                $oldest = ['id' => (int) $record->id, 'title' => self::tidy($record->title), 'year' => $published];
            }
        }
        if ($oldest) {
            $found['oldest'] = $oldest;
        }
        return $found;
    }

    /**
     * The words of a title.
     *
     * @param string $title
     * @return string[]
     */
    private static function words(string $title): array {
        return preg_split('/[\s\x{00A0}]+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A title as it is said: without the slash or the full stop a record may keep at its end.
     *
     * @param string $title
     * @return string
     */
    private static function tidy(string $title): string {
        return trim(preg_replace('/\s*[\/:;,.=]\s*$/u', '', trim($title)));
    }
}
