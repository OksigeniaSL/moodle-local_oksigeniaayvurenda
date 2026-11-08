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
 * The library's agenda: the storytelling, the book club, the author who comes, the school visit. Each activity with its
 * title, when (a day and an hour, and until when if it goes on), where, for whom, a line and how many places. It shows on
 * its page, this week's on the reader's home board and at the open door, and to the screens of the centre through the
 * plugin's API. Signing up, waiting lists and who came, after (docs/23).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class agenda {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Days ahead the agenda page shows. */
    public const AHEAD = 90;

    /**
     * The activities of the libraries of a centre from a moment on, the soonest first; one that goes on for days stays
     * until it ends.
     *
     * @param int $entityid
     * @param int $from
     * @param int $until
     * @param int|null $libraryid Only those of one library.
     * @return \stdClass[] Each with its library's name and slug.
     */
    public static function between(int $entityid, int $from, int $until, ?int $libraryid = null): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT a.*, b.name AS libraryname, b.slug AS libraryslug
               FROM {" . self::T . "activity} a
               JOIN {" . self::T . "library} b ON b.id = a.libraryid
              WHERE b.entityid = :entityid AND a.timestart <= :until
                    AND COALESCE(a.timeend, a.timestart) >= :from" . ($libraryid ? ' AND a.libraryid = :libraryid' : '') . "
           ORDER BY a.timestart, a.id",
            ['entityid' => $entityid, 'from' => $from, 'until' => $until, 'libraryid' => (int) $libraryid]
        ));
    }

    /**
     * Makes or changes an activity of a library.
     *
     * @param \stdClass $library
     * @param array $data title, day (Y-m-d), time (H:i), endday and endtime (both optional), place, audience, text, seats.
     * @param int $now
     * @param int $id The activity to change; 0 makes one.
     * @return int|string Its id, or the key of what is wrong (agendaerrtitle, agendaerrwhen).
     */
    public static function save(\stdClass $library, array $data, int $now, int $id = 0) {
        global $DB;
        $title = \core_text::substr(trim((string) ($data['title'] ?? '')), 0, 160);
        if ($title === '') {
            return 'agendaerrtitle';
        }
        $start = self::moment($library, (string) ($data['day'] ?? ''), (string) ($data['time'] ?? ''), '00:00');
        if (!$start) {
            return 'agendaerrwhen';
        }
        $end = null;
        if (trim((string) ($data['endday'] ?? '')) !== '' || trim((string) ($data['endtime'] ?? '')) !== '') {
            $end = self::moment(
                $library,
                (string) (($data['endday'] ?? '') ?: $data['day']),
                (string) ($data['endtime'] ?? ''),
                '23:59'
            );
            if (!$end || $end < $start) {
                return 'agendaerrwhen';
            }
        }
        $seats = (int) ($data['seats'] ?? 0);
        $row = ['libraryid' => $library->id, 'title' => $title, 'timestart' => $start, 'timeend' => $end,
            'place' => self::line($data['place'] ?? '', 160), 'audience' => self::line($data['audience'] ?? '', 160),
            'text' => self::line($data['text'] ?? '', 600), 'seats' => $seats > 0 ? min($seats, 9999) : null,
            'timemodified' => $now];
        if ($id && $DB->record_exists(self::T . 'activity', ['id' => $id, 'libraryid' => $library->id])) {
            $DB->update_record(self::T . 'activity', (object) (['id' => $id] + $row));
            return $id;
        }
        return (int) $DB->insert_record(self::T . 'activity', (object) ($row + ['timecreated' => $now]));
    }

    /**
     * Takes an activity away.
     *
     * @param \stdClass $library
     * @param int $id
     */
    public static function remove(\stdClass $library, int $id): void {
        global $DB;
        $DB->delete_records(self::T . 'activity', ['id' => $id, 'libraryid' => $library->id]);
    }

    /**
     * What an activity says of when it is, in the centre's zone and the page's language: «Saturday 14 November, 11:00»,
     * «until 13:00», «from 2 to 6 December».
     *
     * @param \stdClass $activity
     * @param int|\stdClass $where The centre.
     * @return string
     */
    public static function when(\stdClass $activity, $where): string {
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $day = fn(int $time) => zones::date($time, 'strftimedaydate', $where);
        $hour = fn(int $time) => zones::date($time, 'strftimetime', $where);
        $start = (int) $activity->timestart;
        $end = $activity->timeend !== null ? (int) $activity->timeend : null;
        $timed = $hour($start) !== $hour(zones::today($where, $start));
        if ($end === null) {
            return $timed ? $str('agendaat', (object) ['day' => $day($start), 'time' => $hour($start)]) : $day($start);
        }
        if (zones::today($where, $end) === zones::today($where, $start)) {
            return $str('agendafromto', (object) ['day' => $day($start), 'from' => $hour($start), 'to' => $hour($end)]);
        }
        return $str('agendadays', (object) ['from' => $day($start), 'to' => $day($end)]);
    }

    /**
     * A moment from a day and an hour written in the forms, in the centre's zone.
     *
     * @param \stdClass $library
     * @param string $day Y-m-d.
     * @param string $time H:i, or empty.
     * @param string $otherwise The hour of an empty one.
     * @return int 0 if the day is not one.
     */
    private static function moment(\stdClass $library, string $day, string $time, string $otherwise): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return 0;
        }
        $time = preg_match('/^\d{2}:\d{2}$/', trim($time)) ? trim($time) : $otherwise;
        return (new \DateTimeImmutable($day . ' ' . $time . ':00', zones::of($library)))->getTimestamp();
    }

    /**
     * A line of text cut to its field, or null when empty.
     *
     * @param mixed $text
     * @param int $length
     * @return string|null
     */
    private static function line($text, int $length): ?string {
        $text = \core_text::substr(trim((string) $text), 0, $length);
        return $text === '' ? null : $text;
    }
}
