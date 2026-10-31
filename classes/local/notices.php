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
 * Notices of late books.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\loan_rules;

/**
 * Tells about late books, as Moodle messages, so they go by whatever the site and each person use (the web, email,
 * the app, or other channels a centre adds). The reader hears on the first day late and then once a week, kindly and
 * with the titles, which are theirs. The tutor gets once a week who in the group has books late and for how long,
 * without titles: the tutor sees the lateness, not what the child reads.
 */
final class notices {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Time between two notices to the same reader, or two summaries to the same tutor. */
    public const EVERY = 7 * DAYSECS;

    /**
     * Sends what is due.
     *
     * @param int|null $now
     * @return array{readers: int, tutors: int}
     */
    public static function run(?int $now = null): array {
        $now = $now ?? time();
        return ['readers' => self::readers($now), 'tutors' => self::tutors($now)];
    }

    /**
     * Notices to readers with an account: their late loans not told about for a week.
     *
     * @param int $now
     * @return int Messages sent.
     */
    private static function readers(int $now): int {
        global $DB;
        $tz = \core_date::get_server_timezone_object();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
        $loans = $DB->get_records_sql(
            "SELECT l.id, l.timedue, l.readerid, r.userid, b.name AS library, b.slug, b.entityid, rec.title
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
              WHERE l.timereturned IS NULL AND l.timedue < :today AND r.userid IS NOT NULL AND r.status = 'active'
                    AND (l.timenotified IS NULL OR l.timenotified <= :since)
           ORDER BY l.readerid, l.timedue",
            ['today' => $today, 'since' => $now - self::EVERY + HOURSECS]
        );
        $byreader = [];
        foreach ($loans as $loan) {
            $byreader[$loan->readerid][] = $loan;
        }
        $sent = 0;
        foreach ($byreader as $list) {
            // A centre open only to its staff tells its readers nothing yet.
            if (access::closed((int) $list[0]->entityid)) {
                continue;
            }
            $user = \core_user::get_user($list[0]->userid);
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            // In the tone of the centre's skin.
            $words = fn(string $key) => skins::words((int) $list[0]->entityid, $key);
            $lines = [];
            foreach ($list as $loan) {
                $lines[] = get_string($words('noticereaderline'), 'local_oksigeniaayvurenda', (object) ['title' => $loan->title,
                    'due' => userdate((int) $loan->timedue, get_string('strftimedateshort', 'langconfig'))]);
            }
            $a = (object) ['name' => $user->firstname, 'library' => format_string($list[0]->library),
                'books' => implode("\n", $lines)];
            $subject = get_string(
                $words(count($list) === 1 ? 'noticereadersubject' : 'noticereadersubjectmany'),
                'local_oksigeniaayvurenda',
                $a
            );
            $body = get_string($words('noticereaderbody'), 'local_oksigeniaayvurenda', $a);
            $herald = characters::of((int) $list[0]->entityid)['herald'];
            if ($herald['enabled'] && $herald['signs']) {
                $body .= "\n\n" . characters::say((int) $list[0]->entityid, 'herald', 'heraldsign')['text'];
            }
            // The library of the oldest one, where the reader sees what they have.
            $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $list[0]->slug]))->out(false);
            if (self::send('overdue', $user, $subject, $body, $url, format_string($list[0]->library))) {
                $sent++;
            }
            [$in, $params] = $DB->get_in_or_equal(array_map(fn($l) => (int) $l->id, $list));
            $DB->set_field_select(self::T . 'loan', 'timenotified', $now, 'id ' . $in, $params);
        }
        return $sent;
    }

    /**
     * Summaries to tutors: who in their group has books late, without titles, once a week.
     *
     * @param int $now
     * @return int Messages sent.
     */
    private static function tutors(int $now): int {
        global $DB;
        $tz = \core_date::get_server_timezone_object();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
        $tutors = $DB->get_records_sql(
            "SELECT t.id, t.yearid, t.groupname, t.userid, y.entityid
               FROM {" . self::T . "tutor} t
               JOIN {" . self::T . "year} y ON y.id = t.yearid
              WHERE y.timestart <= :a AND y.timeend > :b AND (t.timenotified IS NULL OR t.timenotified <= :since)",
            ['a' => $now, 'b' => $now, 'since' => $now - self::EVERY + HOURSECS]
        );
        $sent = 0;
        foreach ($tutors as $tutor) {
            if (access::closed((int) $tutor->entityid)) {
                continue;
            }
            $late = $DB->get_records_sql(
                "SELECT l.id, l.timedue, l.readerid, r.firstname, r.lastname, r.userid, b.name AS library, b.slug
                   FROM {" . self::T . "loan} l
                   JOIN {" . self::T . "reader} r ON r.id = l.readerid
                   JOIN {" . self::T . "member} m ON m.readerid = r.id AND m.yearid = :yearid AND m.groupname = :groupname
                   JOIN {" . self::T . "library} b ON b.id = l.libraryid
                  WHERE l.timereturned IS NULL AND l.timedue < :today AND r.status = 'active'
               ORDER BY r.lastname, r.firstname, l.timedue",
                ['yearid' => $tutor->yearid, 'groupname' => $tutor->groupname, 'today' => $today]
            );
            if (!$late) {
                continue;
            }
            $user = \core_user::get_user($tutor->userid);
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            $readers = [];
            foreach ($late as $loan) {
                $days = loan_rules::days_late((int) $loan->timedue, $now, $tz);
                $entry = $readers[$loan->readerid] ?? ['name' => self::name($loan), 'books' => 0, 'days' => 0];
                $entry['books']++;
                $entry['days'] = max($entry['days'], $days);
                $readers[$loan->readerid] = $entry;
            }
            $lines = [];
            foreach ($readers as $entry) {
                $lines[] = get_string(
                    $entry['books'] === 1 ? 'noticetutorlineone' : 'noticetutorline',
                    'local_oksigeniaayvurenda',
                    (object) $entry
                );
            }
            $a = (object) ['name' => $user->firstname, 'group' => $tutor->groupname,
                'library' => format_string(reset($late)->library), 'readers' => implode("\n", $lines)];
            if (
                self::send(
                    'overduetutor',
                    $user,
                    get_string('noticetutorsubject', 'local_oksigeniaayvurenda', $a),
                    get_string('noticetutorbody', 'local_oksigeniaayvurenda', $a),
                    (new \moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => reset($late)->slug,
                        'group' => $tutor->groupname]))->out(false),
                    $tutor->groupname
                )
            ) {
                $sent++;
            }
            $DB->set_field(self::T . 'tutor', 'timenotified', $now, ['id' => $tutor->id]);
        }
        return $sent;
    }

    /**
     * A reader's name for a tutor: the account's when there is one.
     *
     * @param \stdClass $loan With firstname, lastname and userid of the reader.
     * @return string
     */
    private static function name(\stdClass $loan): string {
        if ($loan->userid) {
            $user = \core_user::get_user($loan->userid);
            if ($user) {
                return fullname($user);
            }
        }
        return trim($loan->firstname . ' ' . $loan->lastname);
    }

    /**
     * Sends a message from the library.
     *
     * @param string $provider
     * @param \stdClass $user
     * @param string $subject
     * @param string $body Plain text.
     * @param string $url The exact page the message is about, which Moodle and its app open from it.
     * @param string $urlname What that page is.
     * @return bool
     */
    public static function send(
        string $provider,
        \stdClass $user,
        string $subject,
        string $body,
        string $url = '',
        string $urlname = ''
    ): bool {
        $message = new \core\message\message();
        $message->component = 'local_oksigeniaayvurenda';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;
        if ($url !== '') {
            $message->contexturl = $url;
            $message->contexturlname = $urlname;
        }
        return (bool) message_send($message);
    }
}
