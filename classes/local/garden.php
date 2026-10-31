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
        $counts = flower::counts(self::loans((int) $reader->id, (int) $year->id));
        // A review the library published counts on the path of telling, as a note at the desk does.
        $counts['tell'] += reviews::told((int) $reader->id, $year);
        $levels = flower::levels($counts);
        return ['levels' => $levels, 'stage' => flower::stage($levels)];
    }

    /**
     * The album of a reader: this year's flower and books, and the flowers of the years before.
     *
     * @param \stdClass $reader
     * @param int|null $now
     * @return array year, flower, books (id, title, cover, note), past (year name, stage, the reader's group then and how
     *     many challenges that class met).
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
            'SELECT DISTINCT y.id, y.name, y.timestart, y.timeend
               FROM {' . self::T . 'loan} l
               JOIN {' . self::T . 'year} y ON y.id = l.yearid
              WHERE l.readerid = :readerid AND y.id <> :yearid
           ORDER BY y.timestart DESC',
            ['readerid' => $reader->id, 'yearid' => $year->id]
        );
        foreach ($years as $other) {
            // The class garden of that year stays in the album: what their group then met together.
            $group = (string) $DB->get_field_select(
                self::T . 'member',
                'groupname',
                'readerid = :readerid AND yearid = :yearid',
                ['readerid' => $reader->id, 'yearid' => $other->id],
                IGNORE_MULTIPLE
            );
            $met = $group === '' ? 0 : $DB->count_records_select(self::T . 'challenge', 'yearid = :yearid AND
                groupname = :groupname AND timecompleted IS NOT NULL', ['yearid' => $other->id, 'groupname' => $group]);
            $past[] = ['year' => $other->name, 'group' => $group, 'met' => $met] + self::flower($reader, $other);
        }
        return ['year' => $year, 'flower' => self::flower($reader, $year), 'books' => array_values($books), 'past' => $past];
    }

    /**
     * The album's collector cards: one for each corner of the library, won with the first book taken home from it, in
     * any school year, and kept for good. Only the corners the library shows (a card nobody could win is no game) and
     * the ones already won. Only with Gaudí's cards in the art package; without them, nothing.
     *
     * @param \stdClass $reader
     * @param \stdClass $library Whose corners name the cards.
     * @return array[]|null code, name, art, won (bool) and when (the day it was won, or ''), in the order of the classes.
     */
    public static function cromos(\stdClass $reader, \stdClass $library): ?array {
        $codes = [];
        foreach (range(0, 9) as $code) {
            $art = screens::art('cromos/cromo-' . $code);
            if ($art !== null) {
                $codes[(string) $code] = $art;
            }
        }
        if (!$codes) {
            return null;
        }
        $won = self::corners_won($reader);
        $names = [];
        $shown = [];
        foreach (catalogue::corners($library, true) as $corner) {
            $names[$corner['code']] = $corner['label'];
            $shown[$corner['code']] = !$corner['hidden'];
        }
        $cromos = [];
        foreach ($codes as $code => $art) {
            if (empty($shown[$code]) && !isset($won[$code])) {
                continue;
            }
            $name = $names[$code] ?? (get_string_manager()->string_exists('udc' . $code, 'local_oksigeniaayvurenda')
                ? get_string('udc' . $code, 'local_oksigeniaayvurenda') : (string) $code);
            $cromos[] = [
                'code' => $code,
                'name' => $name,
                'art' => $art,
                'won' => isset($won[$code]),
                'when' => isset($won[$code]) ? userdate($won[$code], get_string('strftimedateshort', 'langconfig')) : '',
            ];
        }
        return $cromos ?: null;
    }

    /**
     * The corners a reader has taken a book home from, in any school year, and when the first time: the first sign of
     * the class, as the catalogue's corners.
     *
     * @param \stdClass $reader
     * @return int[] Corner => the moment of its first loan.
     */
    public static function corners_won(\stdClass $reader): array {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT l.id, l.timeloaned,
                    (SELECT MIN(a.code)
                       FROM {" . self::T . "recauth} ra
                       JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'class'
                      WHERE ra.recordid = i.recordid) AS class
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "item} i ON i.id = l.itemid
              WHERE l.readerid = :readerid AND " . bank::reading('l.libraryid'),
            ['readerid' => $reader->id]
        );
        $won = [];
        foreach ($rows as $row) {
            $first = $row->class !== null ? \core_text::substr(trim($row->class), 0, 1) : '';
            if ($first !== '' && ctype_digit($first) && (!isset($won[$first]) || $row->timeloaned < $won[$first])) {
                $won[$first] = (int) $row->timeloaned;
            }
        }
        $rows->close();
        ksort($won);
        return $won;
    }

    /**
     * The classic look of the card, for those who want no picture and no counts: what each path means, concretely.
     * When they last came, which corners they went to, what they said of their books, what they finished and which
     * series they have half read.
     *
     * @param \stdClass $reader
     * @param \stdClass $year
     * @param int|null $now
     * @return string[] back, explore, tell and stay.
     */
    public static function classic(\stdClass $reader, \stdClass $year, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        // In the tone of the reader's library.
        $str = fn(string $key, $a = null) => get_string(
            skins::words((int) $reader->entityid, $key),
            'local_oksigeniaayvurenda',
            $a
        );
        $loans = self::loans((int) $reader->id, (int) $year->id);
        $params = ['readerid' => $reader->id, 'yearid' => $year->id];
        $reading = ' AND ' . bank::reading('libraryid');
        $last = max(
            (int) $DB->get_field_sql('SELECT MAX(timeloaned) FROM {' . self::T . 'loan}
                WHERE readerid = :readerid AND yearid = :yearid' . $reading, $params),
            (int) $DB->get_field_sql('SELECT MAX(timereturned) FROM {' . self::T . 'loan}
                WHERE readerid = :readerid AND yearid = :yearid' . $reading, $params)
        );
        $tz = \core_date::get_server_timezone_object();
        $day = fn(int $time) => (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->setTime(0, 0);
        $back = $str('cardnovisit');
        if ($last) {
            $ago = (int) $day($last)->diff($day($now))->days;
            $when = $ago === 0 ? $str('cardvisittoday') : ($ago === 1 ? $str('cardvisityesterday')
                : $str('cardvisiton', userdate($last, $ago < 7 ? '%A' : get_string('strftimedateshort', 'langconfig'))));
            $back = $str('cardlastvisit', $when);
        }
        // Corners by the first sign of the class, named as the catalogue names them.
        $codes = array_values(array_unique(array_filter(array_column($loans, 'corner'), fn($c) => $c !== '')));
        $labels = $DB->get_records_select_menu(self::T . 'authority', "entityid = :entityid AND kind = 'class' AND " .
            $DB->sql_length('code') . ' = 1', ['entityid' => $reader->entityid], '', 'code, label');
        $corners = array_map(fn($c) => $labels[$c] ?? (get_string_manager()->string_exists(
            'udc' . $c,
            'local_oksigeniaayvurenda'
        ) ? $str('udc' . $c) : $c), $codes);
        $told = [];
        $finished = [];
        foreach (array_reverse($loans) as $loan) {
            if (!empty($loan['note']) && count($told) < 4) {
                $told[] = $str('cardtold', (object) ['title' => $loan['title'], 'note' => $str('cardnote' . $loan['note'])]);
            }
            if (
                $loan['returned'] && $loan['note'] !== 'unfinished' && count($finished) < 4 &&
                    !in_array('«' . $loan['title'] . '»', $finished, true)
            ) {
                $finished[] = '«' . $loan['title'] . '»';
            }
        }
        $stay = $finished ? implode(', ', $finished) : $str('cardnofinished');
        $series = self::series_half_read($reader, array_values(array_unique(array_column($loans, 'recordid'))));
        if ($series) {
            $stay .= '. ' . $str('cardseries', implode(', ', $series));
        }
        return [
            'back' => $back,
            'explore' => $corners ? implode(', ', $corners) : $str('cardnocorner'),
            'tell' => $told ? implode(' · ', $told) : $str('cardnotold'),
            'stay' => $stay,
        ];
    }

    /**
     * Series the reader has started and not finished: the library has books of them they have not read.
     *
     * @param \stdClass $reader
     * @param int[] $read Records read this year.
     * @return string[] Names, two at most.
     */
    private static function series_half_read(\stdClass $reader, array $read): array {
        global $DB;
        if (!$read) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($read, SQL_PARAMS_NAMED);
        $collections = $DB->get_records_sql(
            "SELECT DISTINCT a.id, a.label
               FROM {" . self::T . "recauth} ra
               JOIN {" . self::T . "authority} a ON a.id = ra.authorityid AND a.kind = 'collection'
              WHERE ra.recordid $in",
            $params
        );
        $everread = $DB->get_fieldset_sql('SELECT DISTINCT i.recordid FROM {' . self::T . 'loan} l
            JOIN {' . self::T . 'item} i ON i.id = l.itemid WHERE l.readerid = ?', [$reader->id]);
        $names = [];
        foreach ($collections as $collection) {
            $all = $DB->get_fieldset_select(self::T . 'recauth', 'DISTINCT recordid', 'authorityid = ?', [$collection->id]);
            if (array_diff($all, $everread) && count($names) < 2) {
                $names[] = $collection->label;
            }
        }
        return $names;
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
              WHERE l.readerid = :readerid AND l.yearid = :yearid AND " . bank::reading('l.libraryid') . "
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
