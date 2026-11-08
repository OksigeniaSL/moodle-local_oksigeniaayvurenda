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
 * What a person has in the libraries, in a few lines, for places outside them: the block of the personal area.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * For each centre where someone is a reader: the books they have out and until when, the reservations set aside for
 * them or that they wait for, and the ways in (the library, the card, Ayvu rande). Nothing of anyone else.
 */
final class mine {
    /** @var int The most rows a reader's home in the app shows. */
    private const HOME_ROWS = 5;

    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What someone has, centre by centre.
     *
     * @param int $userid
     * @param int|null $now
     * @return array[] One per centre: name, cardcode, home, card, spring, remembers (what the memory has to say today: text,
     *     portrait, url, label, recordid, query; or null), loans (title, url, due, late) and holds
     *     (title, url, ready, until, ahead).
     */
    public static function summary(int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $out = [];
        if (!$userid) {
            return $out;
        }
        foreach ($DB->get_records(self::T . 'reader', ['userid' => $userid, 'status' => 'active'], 'id') as $reader) {
            if (access::closed((int) $reader->entityid) && !access::is_staff((int) $reader->entityid, $userid)) {
                continue;
            }
            // The centre's own library: a top one, not the bank of textbooks, the first made; the same one every time.
            $library = $DB->get_records_sql(
                "SELECT * FROM {" . self::T . "library}
                  WHERE entityid = :entityid
               ORDER BY CASE WHEN parentid IS NULL THEN 0 ELSE 1 END, CASE WHEN kind = :bank THEN 1 ELSE 0 END, id",
                ['entityid' => $reader->entityid, 'bank' => bank::KIND],
                0,
                1
            );
            $library = $library ? reset($library) : false;
            if (!$library) {
                continue;
            }
            $url = fn(string $script, \stdClass $where, array $params = []) => (new \moodle_url(
                '/local/oksigeniaayvurenda/' . $script,
                ['library' => $where->slug] + $params
            ))->out(false);
            $loans = [];
            $rows = $DB->get_records_sql(
                "SELECT l.id, l.timedue, l.libraryid, r.id AS recordid, r.title
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "item} i ON i.id = l.itemid
                   JOIN {" . self::T . "record} r ON r.id = i.recordid
                  WHERE l.readerid = :readerid AND l.timereturned IS NULL
               ORDER BY l.timedue, l.id",
                ['readerid' => $reader->id]
            );
            foreach ($rows as $row) {
                $where = $DB->get_record(self::T . 'library', ['id' => $row->libraryid]) ?: $library;
                // Whether they may give it more time themselves now, and until when.
                $until = renewals::until($where, (int) $reader->id, (int) $row->id, $userid, $now);
                $loans[] = [
                    'loanid' => (int) $row->id,
                    'recordid' => (int) $row->recordid,
                    // Not «slug»: inside the list, Mustache would find the library's first.
                    'where' => (string) $where->slug,
                    'canrenew' => $until !== null,
                    'renewuntil' => $until ? zones::date($until, 'strftimedateshort', $where) : '',
                    'title' => $row->title,
                    'url' => $url('record.php', $where, ['id' => $row->recordid]),
                    'due' => zones::date((int) $row->timedue, 'strftimedateshort', $where),
                    'late' => (int) $row->timedue < $now,
                    'timedue' => (int) $row->timedue,
                    // A textbook lent for the year is not something to remember on a phone's home.
                    'textbook' => bank::is($where),
                ];
            }
            // The textbooks lent for the year are not a list to read every day: one line says how many, unless one is late.
            $textbooks = count(array_filter($loans, fn($loan) => $loan['textbook'] && !$loan['late']));
            $loans = array_values(array_filter($loans, fn($loan) => !$loan['textbook'] || $loan['late']));
            $holds = array_map(
                fn($hold) => $hold + ['url' => $url('record.php', (object) ['slug' => $hold['slug']], ['id' => $hold['recordid']])],
                holds::of_reader((int) $reader->id, $now)
            );
            $remembers = memory::remembers($library, $reader, $now);
            $book = $remembers ? (int) $remembers['recordid'] : 0;
            $out[] = [
                'remembers' => $remembers ? [
                    'text' => $remembers['say']['text'],
                    'portrait' => (string) ($remembers['say']['portrait'] ?? ''),
                    'url' => $book ? $url('record.php', $library, ['id' => $book])
                        : $url('search.php', $library, ['q' => $remembers['query']]),
                    'label' => get_string($book ? 'memorygo' : 'memorysearchagain', 'local_oksigeniaayvurenda'),
                    'recordid' => $book,
                    'query' => (string) $remembers['query'],
                ] : null,
                'name' => format_string($library->name, true, ['escape' => false]),
                'slug' => (string) $library->slug,
                'cardcode' => (string) $reader->cardcode,
                'readerid' => (int) $reader->id,
                'entityid' => (int) $reader->entityid,
                'home' => $url('index.php', $library),
                'card' => $url('card.php', $library),
                'spring' => $url('ayvurande.php', $library),
                'loans' => $loans,
                'textbooks' => $textbooks ? get_string(
                    $textbooks === 1 ? 'minetextbooksone' : 'minetextbooks',
                    'local_oksigeniaayvurenda',
                    $textbooks
                ) : '',
                'holds' => $holds,
                'notice' => sanctions::for_reader((int) $reader->entityid, (int) $reader->id, $now),
                'none' => self::none((int) $reader->entityid),
            ];
        }
        return $out;
    }

    /**
     * What the app says when someone has nothing on loan: to the flower's readers, that the shelves are full and waiting;
     * to the sky's in a school, the same with a smile; to grown-ups, the plain fact (jue-humor H58).
     *
     * @param int $entityid
     * @return string
     */
    private static function none(int $entityid): string {
        $key = 'mobilenone';
        if (characters::young($entityid)) {
            $key = 'mobilenoneyoung';
        } else if (characters::step($entityid) === 'cielo' && in_array(skins::of($entityid), ['school', 'secondary'], true)) {
            $key = 'mobilenonesecondary';
        }
        return get_string($key, 'local_oksigeniaayvurenda');
    }

    /**
     * What a reader's home in Oksigenia Aty shows of the library: their books out and what is set aside for them, the
     * most pressing first, and the way into the library. Nothing for whoever is not a reader.
     *
     * @param int $userid
     * @param int|null $now
     * @return array A list with one card: tile and, when there is something to show, heading and items. The fields, in
     *     Aty's homecards class.
     */
    public static function home_cards(int $userid, ?int $now = null): array {
        $now = $now ?? time();
        $summary = self::summary($userid, $now);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        if (!$summary) {
            // Staff without a reader's card have nothing of theirs to list, and the library is still theirs to open.
            return access::can('local/oksigeniaayvurenda:desk', 0, $userid) ? [['tile' => ['label' => $str('mobilelibrary'),
                'icon' => 'fas-book-open-reader', 'method' => 'mobile_library_view',
                'pagetitle' => $str('mobilelibrary')]]] : [];
        }
        $items = [];
        foreach ($summary as $library) {
            // What keeps them from borrowing goes before the books: it is why the desk will stop them.
            if ($library['notice'] !== '') {
                $items[] = ['title' => $library['notice'], 'detail' => $library['name'], 'date' => 0, 'urgent' => true,
                    'method' => 'mobile_card_view', 'args' => []];
            }
            foreach ($library['loans'] as $loan) {
                if (!$loan['textbook']) {
                    // Late says so in words: a colour alone does not reach everyone, and a library's own may be close to it.
                    $items[] = ['title' => $loan['title'], 'detail' => $loan['late'] ? $str('mobilelate', $loan['due'])
                        : $library['name'], 'date' => self::noon((int) $loan['timedue'], $library['entityid']),
                        'urgent' => $loan['late'],
                        'method' => 'mobile_record_view',
                        'args' => ['library' => $loan['where'], 'id' => $loan['recordid']]];
                }
            }
            foreach ($library['holds'] as $hold) {
                if ($hold['ready']) {
                    $items[] = ['title' => $hold['title'], 'detail' => $str('mobileready', $hold['until']),
                        'date' => self::noon((int) $hold['expires'], $library['entityid']), 'urgent' => false,
                        'method' => 'mobile_record_view',
                        'args' => ['library' => $hold['slug'], 'id' => $hold['recordid']]];
                }
            }
        }
        usort($items, fn($a, $b) => [$b['urgent'], $a['date']] <=> [$a['urgent'], $b['date']]);
        $card = ['tile' => ['label' => $str('mobilelibrary'), 'icon' => 'fas-book-open-reader', 'method' => 'mobile_library_view',
            'pagetitle' => $str('mobilelibrary')]];
        if ($items) {
            $card += ['heading' => $str('mobileloans'), 'items' => array_slice($items, 0, self::HOME_ROWS)];
        }
        // The reader's card, a tap away, to show at the desk.
        $card2 = ['tile' => ['label' => $str('mobilecard'), 'icon' => 'fas-id-card', 'method' => 'mobile_card_view',
            'pagetitle' => $str('mobilecard')]];
        return array_filter(array_column($summary, 'cardcode'), fn($code) => $code !== '') ? [$card, $card2] : [$card];
    }

    /**
     * What a family sees of a child's library in Oksigenia Aty: what the child has out and what is set aside for them, and
     * until when, so that it gets back. See, not act: they do not renew or reserve for the child. What a child reads is theirs:
     * from the age the sky replaces the flower, the family sees that there is a book and its date, not which one, as a
     * tutor does, and its row opens nothing, since any page would be the adult's library and not the child's. A textbook
     * lent for the year is left out, as on the child's own home.
     *
     * @param int $childuserid
     * @param int|null $now
     * @return array A list of cards, as Aty's homecards class describes them.
     */
    public static function child_cards(int $childuserid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $items = [];
        foreach (self::summary($childuserid, $now) as $library) {
            $reader = $DB->get_record(
                self::T . 'reader',
                ['userid' => $childuserid, 'cardcode' => $library['cardcode'], 'status' => 'active'],
                '*',
                IGNORE_MULTIPLE
            );
            // A centre whose plan has no app shows nothing in it, not even of a child (int-aty-pixel 3).
            if (access::apart() && $reader && !plans::allows((int) $reader->entityid, 'app')) {
                continue;
            }
            $young = $reader && cards::look_of($reader, $now) === 'flower';
            $row = function (array $book, string $detail, int $date, bool $late, string $where) use ($young, $str) {
                return ['title' => $young ? $book['title'] : $str('mobilechildbook'), 'detail' => $detail, 'date' => $date,
                    'urgent' => $late] + ($young ? ['method' => 'mobile_record_view',
                    'args' => ['library' => $where, 'id' => (int) $book['recordid']]] : ['plain' => true]);
            };
            foreach ($library['loans'] as $loan) {
                if (!$loan['textbook']) {
                    $items[] = $row(
                        $loan,
                        $str($loan['late'] ? 'mobilechildlate' : 'mobilechilddue', $loan['due']),
                        self::noon((int) $loan['timedue'], $library['entityid']),
                        (bool) $loan['late'],
                        (string) $loan['where']
                    );
                }
            }
            foreach ($library['holds'] as $hold) {
                if ($hold['ready']) {
                    $items[] = $row(
                        $hold,
                        $str('mobilechildready', $hold['until']),
                        self::noon((int) $hold['expires'], $library['entityid']),
                        false,
                        (string) $hold['slug']
                    );
                }
            }
        }
        if (!$items) {
            return [];
        }
        usort($items, fn($a, $b) => [$b['urgent'], $a['date']] <=> [$a['urgent'], $b['date']]);
        return [['heading' => $str('mobilechildheading'), 'items' => array_slice($items, 0, self::HOME_ROWS)]];
    }

    /**
     * The noon of a moment's day at the centre: the app draws a chip's date in the phone's own zone, and noon falls on the
     * same day in any of them, as the line beside it says (int-aty-pixel 4).
     *
     * @param int $time
     * @param int $entityid
     * @return int
     */
    private static function noon(int $time, int $entityid): int {
        if (!$time) {
            return 0;
        }
        return (new \DateTimeImmutable('@' . $time))->setTimezone(zones::of($entityid))->setTime(12, 0)->getTimestamp();
    }
}
