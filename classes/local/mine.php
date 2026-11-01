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
     * @return array[] One per centre: name, cardcode, home, card, spring, loans (title, url, due, late) and holds
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
            $library = $DB->get_record(
                self::T . 'library',
                ['entityid' => $reader->entityid, 'parentid' => null],
                '*',
                IGNORE_MULTIPLE
            ) ?: $DB->get_record(self::T . 'library', ['entityid' => $reader->entityid], '*', IGNORE_MULTIPLE);
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
                    'renewuntil' => $until ? userdate($until, get_string('strftimedateshort', 'langconfig')) : '',
                    'title' => $row->title,
                    'url' => $url('record.php', $where, ['id' => $row->recordid]),
                    'due' => userdate((int) $row->timedue, get_string('strftimedateshort', 'langconfig')),
                    'late' => (int) $row->timedue < $now,
                    'timedue' => (int) $row->timedue,
                    // A textbook lent for the year is not something to remember on a phone's home.
                    'textbook' => bank::is($where),
                ];
            }
            $holds = array_map(
                fn($hold) => $hold + ['url' => $url('record.php', $library, ['id' => $hold['recordid']])],
                holds::of_reader((int) $reader->id, $now)
            );
            $out[] = [
                'name' => format_string($library->name, true, ['escape' => false]),
                'slug' => (string) $library->slug,
                'cardcode' => (string) $reader->cardcode,
                'home' => $url('index.php', $library),
                'card' => $url('card.php', $library),
                'spring' => $url('ayvurande.php', $library),
                'loans' => $loans,
                'holds' => $holds,
            ];
        }
        return $out;
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
            foreach ($library['loans'] as $loan) {
                if (!$loan['textbook']) {
                    // Late says so in words: a colour alone does not reach everyone, and a library's own may be close to it.
                    $items[] = ['title' => $loan['title'], 'detail' => $loan['late'] ? $str('mobilelate', $loan['due'])
                        : $library['name'], 'date' => $loan['timedue'], 'urgent' => $loan['late'],
                        'method' => 'mobile_record_view',
                        'args' => ['library' => $loan['where'], 'id' => $loan['recordid']]];
                }
            }
            foreach ($library['holds'] as $hold) {
                if ($hold['ready']) {
                    $items[] = ['title' => $hold['title'], 'detail' => $str('mobileready', $hold['until']),
                        'date' => $hold['expires'], 'urgent' => false, 'method' => 'mobile_record_view',
                        'args' => ['library' => $library['slug'], 'id' => $hold['recordid']]];
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
}
