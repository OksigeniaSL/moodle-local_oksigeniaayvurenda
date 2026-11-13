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
 * «La cita a ciegas» (P7 of the review of 12 November): a book wrapped in paper with three clues.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The library wraps a copy in paper and writes three clues on it; it gets a number. Readers see the open dates with their
 * clues, never the book, and ask for one at the desk by its number; the desk knows it by the copy's code, which stays
 * outside the paper. Back home, the reader sees which book it was and says in one touch how it went. The staff see sums,
 * never names.
 */
final class blind {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Clues of a date. */
    public const CLUES = 3;

    /** @var int Letters of a clue. */
    public const CLUELENGTH = 80;

    /** @var string[] What a reader may say of their date, in one touch. */
    public const NOTES = ['loved', 'fine', 'notforme'];

    /** @var int Papers drawn for the wrapped books. */
    public const PAPERS = 6;

    /** @var int Days a date stays on the reader's card after it came back. */
    public const KEEP = 30;

    /**
     * Wraps a copy of the library: its clues, and the next number of the centre.
     *
     * @param \stdClass $library
     * @param int $itemid
     * @param string[] $clues
     * @param int $userid Who wrapped it.
     * @param int $now
     * @return int|string The date, or the key of what is wrong.
     */
    public static function wrap(\stdClass $library, int $itemid, array $clues, int $userid, int $now) {
        global $DB;
        $clues = array_map(
            fn($clue) => \core_text::substr(trim(preg_replace('/\s+/u', ' ', (string) $clue)), 0, self::CLUELENGTH),
            array_pad(array_slice(array_values($clues), 0, self::CLUES), self::CLUES, '')
        );
        // Three clues and no title: the shelf says so, and a date with fewer is not one.
        if (in_array('', $clues, true)) {
            return 'blinderrclues';
        }
        $item = $DB->get_record(self::T . 'item', ['id' => $itemid, 'libraryid' => $library->id]);
        if (!$item || $item->status !== 'available') {
            return 'blinderritem';
        }
        if (self::of_item($itemid)) {
            return 'blinderrwrapped';
        }
        $number = 1 + (int) $DB->get_field_sql(
            'SELECT MAX(tagno) FROM {' . self::T . 'blind} WHERE entityid = ?',
            [$library->entityid]
        );
        // Its paper: the first that no other date on the shelf wears, or, with all six worn, the least worn; so that a
        // shelf does not repeat one, and the same parcel looks the same on the home, the card and the book's page.
        $worn = array_fill(1, self::PAPERS, 0);
        foreach (
            $DB->get_fieldset_select(self::T . 'blind', 'paper', 'libraryid = :libraryid AND loanid IS NULL AND '
                . 'timeclosed IS NULL', ['libraryid' => $library->id]) as $paper
        ) {
            if (isset($worn[(int) $paper])) {
                $worn[(int) $paper]++;
            }
        }
        $paper = array_search(min($worn), $worn, true);
        return (int) $DB->insert_record(self::T . 'blind', (object) ['entityid' => $library->entityid,
            'libraryid' => $library->id, 'itemid' => $itemid, 'tagno' => $number, 'paper' => $paper,
            'clues' => json_encode($clues),
            'createdby' => $userid ?: null, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Unwraps a date nobody took home: it leaves the shelf of dates, and its number is not given again.
     *
     * @param int $id
     * @param int $libraryid
     * @param int $now
     * @return bool Whether it was open.
     */
    public static function unwrap(int $id, int $libraryid, int $now): bool {
        global $DB;
        $date = $DB->get_record(self::T . 'blind', ['id' => $id, 'libraryid' => $libraryid]);
        if (!$date || $date->loanid || $date->timeclosed) {
            return false;
        }
        $DB->update_record(self::T . 'blind', (object) ['id' => $id, 'timeclosed' => $now, 'timemodified' => $now]);
        return true;
    }

    /**
     * The date a copy is wrapped for now, waiting on the shelf (null if none).
     *
     * @param int $itemid
     * @return \stdClass|null
     */
    public static function of_item(int $itemid): ?\stdClass {
        global $DB;
        return $DB->get_record_select(
            self::T . 'blind',
            'itemid = :itemid AND loanid IS NULL AND timeclosed IS NULL',
            ['itemid' => $itemid]
        ) ?: null;
    }

    /**
     * The dates waiting on the shelf of a library, by number.
     *
     * @param \stdClass $library
     * @return array[] id, number, paper and clues.
     */
    public static function open(\stdClass $library): array {
        global $DB;
        $dates = $DB->get_records_select(
            self::T . 'blind',
            'libraryid = :libraryid AND loanid IS NULL AND timeclosed IS NULL',
            ['libraryid' => $library->id],
            'tagno'
        );
        return array_values(array_map(fn($date) => ['id' => (int) $date->id, 'number' => (int) $date->tagno,
            'paper' => (int) $date->paper, 'clues' => self::clues($date)], $dates));
    }

    /**
     * A loan just made at the desk: if its copy was wrapped, the date goes home with this reader.
     *
     * @param int $itemid
     * @param int $loanid
     * @param int $readerid
     * @param int $now
     * @return int|null The number of the date, or null if the copy was not wrapped.
     */
    public static function lent(int $itemid, int $loanid, int $readerid, int $now): ?int {
        global $DB;
        $date = self::of_item($itemid);
        if (!$date) {
            return null;
        }
        $DB->update_record(self::T . 'blind', (object) ['id' => $date->id, 'loanid' => $loanid, 'readerid' => $readerid,
            'timemodified' => $now]);
        return (int) $date->tagno;
    }

    /**
     * A reader's dates on their card: the one at home, and those back in the last KEEP days, with the book they were.
     *
     * @param int $readerid
     * @param int $now
     * @return array[] id, number, clues, out, recordid, title, note.
     */
    public static function mine(int $readerid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT b.id, b.tagno, b.paper, b.clues, b.note, l.timereturned, i.recordid, r.title
               FROM {" . self::T . "blind} b
               JOIN {" . self::T . "loan} l ON l.id = b.loanid
               JOIN {" . self::T . "item} i ON i.id = b.itemid
               JOIN {" . self::T . "record} r ON r.id = i.recordid
              WHERE b.readerid = :readerid AND (l.timereturned IS NULL OR l.timereturned >= :since)
           ORDER BY l.timeloaned DESC, b.id DESC",
            ['readerid' => $readerid, 'since' => $now - self::KEEP * DAYSECS]
        );
        return array_values(array_map(fn($row) => ['id' => (int) $row->id, 'number' => (int) $row->tagno,
            'paper' => (int) $row->paper,
            'clues' => self::clues($row), 'out' => empty($row->timereturned), 'recordid' => (int) $row->recordid,
            'title' => (string) $row->title, 'note' => (string) $row->note], $rows));
    }

    /**
     * What a reader says of their date once it is back, in one touch; touched again, it changes.
     *
     * @param int $id
     * @param int $readerid
     * @param string $note One of NOTES.
     * @param int $now
     * @return bool Whether it was kept.
     */
    public static function note(int $id, int $readerid, string $note, int $now): bool {
        global $DB;
        if (!in_array($note, self::NOTES, true)) {
            return false;
        }
        $back = $DB->record_exists_sql(
            "SELECT 1
               FROM {" . self::T . "blind} b
               JOIN {" . self::T . "loan} l ON l.id = b.loanid
              WHERE b.id = :id AND b.readerid = :readerid AND l.timereturned IS NOT NULL",
            ['id' => $id, 'readerid' => $readerid]
        );
        if (!$back) {
            return false;
        }
        $DB->update_record(self::T . 'blind', (object) ['id' => $id, 'note' => $note, 'timenoted' => $now,
            'timemodified' => $now]);
        return true;
    }

    /**
     * How the dates of a book went, added up, never with names: how many went home, and what their readers said.
     *
     * @param int $recordid
     * @return int[] dates, out, loved, fine, notforme.
     */
    public static function sums(int $recordid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT b.id, b.loanid, b.note
               FROM {" . self::T . "blind} b
               JOIN {" . self::T . "item} i ON i.id = b.itemid
              WHERE i.recordid = :recordid",
            ['recordid' => $recordid]
        );
        $sums = ['dates' => 0] + array_fill_keys(self::NOTES, 0);
        foreach ($rows as $row) {
            $sums['dates'] += $row->loanid ? 1 : 0;
            if (isset($sums[(string) $row->note])) {
                $sums[$row->note]++;
            }
        }
        return $sums;
    }

    /**
     * The wrapped book of a date as the pages draw it: Gaudí's drawing of its paper, or the one in CSS.
     *
     * @param int $number
     * @param int $paper One of PAPERS.
     * @param callable $str
     * @param bool $small
     * @return array number, art, label, small.
     */
    public static function wrapped(int $number, int $paper, callable $str, bool $small = false): array {
        $paper = $paper >= 1 && $paper <= self::PAPERS ? $paper : 1;
        return ['number' => $number, 'art' => screens::art('cita-' . $paper),
            'wrappedlabel' => $str('blindwrappedlabel', $number), 'small' => $small];
    }

    /**
     * The shelf of dates on the home of a library: none for a reader of Infantil (they do not read the clues yet).
     *
     * @param \stdClass $library
     * @param int $userid Who looks.
     * @param callable $str
     * @return array|null
     */
    public static function shelf(\stdClass $library, int $userid, callable $str): ?array {
        global $DB;
        if (bank::is($library)) {
            return null;
        }
        $dates = self::open($library);
        if (!$dates) {
            return null;
        }
        $reader = $userid ? $DB->get_record(self::T . 'reader', ['userid' => $userid, 'entityid' => $library->entityid,
            'status' => 'active'], '*', IGNORE_MULTIPLE) : null;
        if ($reader && cartilla::infant(quests::stage_of($reader, time()))) {
            return null;
        }
        // The memory with a secret, finger on her lips, where the art package draws her so; pointing, otherwise.
        $pose = screens::art('personajes/memoria-secreto') ? 'secreto' : 'senala';
        $memory = characters::say((int) $library->entityid, 'memory', 'blindwho', [], $pose);
        return [
            'heading' => $str('blindheading'),
            'hint' => $str('blindhint'),
            'who' => $memory['text'] ?? $str('blindwhonone'),
            'memoryfigure' => $memory ? ($memory['figure'] ?? $memory['portrait'] ?? null) : null,
            'blindask' => $str('blindask'),
            'blindnumberabbr' => $str('blindnumberabbr'),
            'dates' => array_map(fn($date) => self::wrapped($date['number'], $date['paper'], $str) + ['clues' => $date['clues'],
                'numbertext' => $str('blindnumber', $date['number'])], $dates),
        ];
    }

    /**
     * A reader's dates on their card: the one at home, wrapped, with its clues and the day it comes back; the one back,
     * the book it was and the three touches of what they say.
     *
     * @param \stdClass $reader
     * @param \stdClass $library Where its books are drawn from.
     * @param int $now
     * @param callable $str
     * @param bool $cannote Whether this page may keep a note (not in a preview of the staff).
     * @return array[]
     */
    public static function card(\stdClass $reader, \stdClass $library, int $now, callable $str, bool $cannote): array {
        global $DB;
        $views = [];
        foreach (self::mine((int) $reader->id, $now) as $date) {
            $view = ['id' => $date['id'], 'out' => $date['out'], 'clues' => $date['clues']]
                + self::wrapped($date['number'], $date['paper'], $str);
            if ($date['out']) {
                $due = (int) $DB->get_field_sql(
                    'SELECT l.timedue FROM {' . self::T . 'loan} l JOIN {' . self::T . 'blind} b ON b.loanid = l.id
                      WHERE b.id = ?',
                    [$date['id']]
                );
                $view['back'] = $str('blindback', zones::date($due, 'strftimedateshort', $reader->entityid));
            } else {
                $book = catalogue::cards_of([$date['recordid']], $library)[0] ?? [];
                $view += [
                    'title' => format_string($date['title'], true, ['escape' => false]),
                    'author' => (string) ($book['authors'] ?? ''),
                    'cover' => covers::url($date['recordid']),
                    'cannote' => $cannote,
                    'noted' => $date['note'] !== '',
                    'notes' => array_map(fn($note) => ['value' => $note, 'label' => $str('blindnote' . $note),
                        'pressed' => $date['note'] === $note ? 'true' : 'false', 'isloved' => $note === 'loved',
                        'isfine' => $note === 'fine', 'isnotforme' => $note === 'notforme'], self::NOTES),
                ];
            }
            $views[] = $view;
        }
        return $views;
    }

    /**
     * The card of a book's page for the staff: wrap a free copy with its clues; wrapped, its number, its label and how to
     * unwrap it; out, that it is out; and below, how its dates went, without names.
     *
     * @param \stdClass $library
     * @param int $recordid
     * @param int $chosen The copy touched, if any.
     * @param string[] $typed The clues written so far.
     * @param callable $str
     * @param bool $missing Whether wrapping failed for a clue missing: the empty ones say so.
     * @return array
     */
    public static function staff(
        \stdClass $library,
        int $recordid,
        int $chosen,
        array $typed,
        callable $str,
        bool $missing = false
    ): array {
        global $DB;
        $items = $DB->get_records_sql(
            "SELECT i.id, i.barcode, i.status, a.label AS place
               FROM {" . self::T . "item} i
          LEFT JOIN {" . self::T . "authority} a ON a.id = i.locationid
              WHERE i.recordid = :recordid AND i.libraryid = :libraryid
           ORDER BY i.barcode",
            ['recordid' => $recordid, 'libraryid' => $library->id]
        );
        $card = ['heading' => $str('blindcardheading')];
        foreach ($items as $item) {
            $date = $DB->get_record_select(
                self::T . 'blind',
                'itemid = :itemid AND timeclosed IS NULL AND (loanid IS NULL OR loanid IN (SELECT id FROM {' . self::T
                    . 'loan} WHERE timereturned IS NULL))',
                ['itemid' => $item->id],
                '*',
                IGNORE_MULTIPLE
            );
            if ($date && !$date->loanid) {
                $card['wrapped'] = self::wrapped((int) $date->tagno, (int) $date->paper, $str) + [
                    'id' => (int) $date->id,
                    'says' => $str('blindwrapped', (object) ['barcode' => $item->barcode, 'number' => (int) $date->tagno]),
                    'clues' => self::clues($date),
                    'printurl' => (new \moodle_url('/local/oksigeniaayvurenda/blindtags.php', ['library' => $library->slug,
                        'id' => $date->id]))->out(false),
                ];
                break;
            }
            if ($date) {
                $card['out'] = $str('blindout', (int) $date->tagno);
            }
        }
        if (empty($card['wrapped'])) {
            $free = array_values(array_filter(
                $items,
                fn($item) => $item->status === 'available' && !self::of_item((int) $item->id)
            ));
            $chosen = in_array($chosen, array_map(fn($item) => (int) $item->id, $free), true) ? $chosen
                : (int) ($free[0]->id ?? 0);
            $card['free'] = $free ? [
                'chosen' => $chosen,
                'copies' => array_map(fn($item) => ['id' => (int) $item->id, 'pressed' => (int) $item->id === $chosen
                    ? 'true' : 'false', 'label' => $item->barcode . ($item->place ? ' · ' . $item->place : '')], $free),
                'clues' => array_map(
                    fn($n) => ['n' => $n, 'label' => $str('blindclue' . $n), 'value' => (string) ($typed[$n - 1]
                    ?? ''), 'hint' => $n === 1 ? $str('blindcluehint') : '', 'idea' => $n === 3 ? $str('blindclueidea') : '',
                    // A clue left empty when wrapping, in coral, with what is missing under it.
                    'missing' => $missing && trim((string) ($typed[$n - 1] ?? '')) === '' ? $str('blindcluemissing' . $n) : ''],
                    range(1, self::CLUES)
                ),
            ] : null;
            if (!$free && empty($card['out'])) {
                $card['none'] = $str('blindnone');
            }
        }
        $sums = self::sums($recordid);
        $card['sums'] = $sums['dates'] ? [
            $sums['dates'] === 1 ? $str('blindsumone') : $str('blindsumdates', $sums['dates']),
            $str('blindsumloved', $sums['loved']),
            $str('blindsumfine', $sums['fine']),
            $str('blindsumnotforme', $sums['notforme']),
        ] : [];
        $card['hassums'] = (bool) $card['sums'];
        return $card;
    }

    /**
     * The clues of a date.
     *
     * @param \stdClass $date
     * @return string[]
     */
    private static function clues(\stdClass $date): array {
        $clues = json_decode((string) $date->clues, true);
        return is_array($clues) ? array_values(array_map('strval', $clues)) : [];
    }
}
