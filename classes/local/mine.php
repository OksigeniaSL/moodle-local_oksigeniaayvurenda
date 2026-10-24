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
                $loans[] = [
                    'title' => $row->title,
                    'url' => $url('record.php', $where, ['id' => $row->recordid]),
                    'due' => userdate((int) $row->timedue, get_string('strftimedateshort', 'langconfig')),
                    'late' => (int) $row->timedue < $now,
                ];
            }
            $holds = array_map(
                fn($hold) => $hold + ['url' => $url('record.php', $library, ['id' => $hold['recordid']])],
                holds::of_reader((int) $reader->id, $now)
            );
            $out[] = [
                'name' => format_string($library->name),
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
}
