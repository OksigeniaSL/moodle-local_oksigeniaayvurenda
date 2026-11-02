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
            "SELECT l.id, l.timedue, l.readerid, r.userid, b.name AS library, b.slug, b.entityid, rec.title, rec.id AS recordid
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
            $sent += self::tell_reader($list, $now) ? 1 : 0;
        }
        return $sent;
    }

    /**
     * Reminds a reader now of all their late books, as the nightly notice does (the staff's «Tell …» on late books).
     *
     * @param int $readerid
     * @param int $now
     * @param int $entityid The centre of whoever asks: nobody else's readers.
     * @return bool Whether a message went (a reader without an account, or with nothing late, gets none).
     */
    public static function remind(int $readerid, int $now, int $entityid): bool {
        global $DB;
        $tz = \core_date::get_server_timezone_object();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
        $list = array_values($DB->get_records_sql(
            "SELECT l.id, l.timedue, l.readerid, r.userid, b.name AS library, b.slug, b.entityid, rec.title, rec.id AS recordid
               FROM {" . self::T . "loan} l
               JOIN {" . self::T . "reader} r ON r.id = l.readerid
               JOIN {" . self::T . "library} b ON b.id = l.libraryid
               JOIN {" . self::T . "item} i ON i.id = l.itemid
               JOIN {" . self::T . "record} rec ON rec.id = i.recordid
              WHERE l.timereturned IS NULL AND l.timedue < :today AND r.userid IS NOT NULL AND r.status = 'active'
                    AND l.readerid = :readerid AND r.entityid = :entityid
           ORDER BY l.timedue",
            ['today' => $today, 'readerid' => $readerid, 'entityid' => $entityid]
        ));
        return $list && !access::closed((int) $list[0]->entityid) && self::tell_reader($list, $now);
    }

    /**
     * One message to a reader with their late books, in the tone of the centre's skin (by the herald, to the young).
     *
     * @param \stdClass[] $list The reader's late loans, the oldest first.
     * @param int $now
     * @return bool Whether it went.
     */
    private static function tell_reader(array $list, int $now): bool {
        global $DB;
        $user = \core_user::get_user($list[0]->userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        // In their language and at their hour, wherever the task runs; in the tone of the centre's skin.
        $language = force_current_language((string) $user->lang);
        $entityid = (int) $list[0]->entityid;
        $words = fn(string $key) => skins::words($entityid, $key);
        $str = fn(string $key, $a = null) => get_string($words($key), 'local_oksigeniaayvurenda', $a);
        $timezone = \core_date::get_user_timezone($user);
        $lines = [];
        $rows = [];
        foreach ($list as $loan) {
            $due = userdate((int) $loan->timedue, get_string('strftimedateshort', 'langconfig'), $timezone);
            $lines[] = $str('noticereaderline', (object) ['title' => $loan->title, 'due' => $due]);
            $rows[] = ['line' => $str('noticereaderdue', $due), 'urgent' => true] + mail::book((int) $loan->recordid, $loan->title);
        }
        $a = (object) ['name' => $user->firstname, 'library' => format_string($list[0]->library, true, ['escape' => false]),
            'books' => implode("\n", $lines)];
        $subject = get_string(
            $words(count($list) === 1 ? 'noticereadersubject' : 'noticereadersubjectmany'),
            'local_oksigeniaayvurenda',
            $a
        );
        $body = get_string($words('noticereaderbody'), 'local_oksigeniaayvurenda', $a);
        $herald = characters::of($entityid)['herald'];
        $one = count($list) === 1;
        // To a young reader (the card with the flower), the herald tells it her way: the book misses its shelf.
        $reader = $DB->get_record(self::T . 'reader', ['id' => $list[0]->readerid]);
        $young = $herald['enabled'] && $reader && cards::look_of($reader, $now) === 'flower'
            ? characters::say($entityid, 'herald', $one ? 'heraldoverdueone' : 'heraldoverdue', ['reader' => $user->firstname,
                'books' => $a->books]) : null;
        $heading = $subject;
        $after = $str('noticereaderafter');
        // Where a late book costs something, it is said before it does.
        $warning = $reader ? sanctions::warning($entityid, $reader, $now) : '';
        $body .= $warning !== '' ? ' ' . $warning : '';
        if ($young) {
            $body = $young['text'];
            $heading = $str($one ? 'heraldoverdueheading' : 'heraldoverdueheadingmany');
            $after = '';
        } else if ($herald['enabled'] && $herald['signs']) {
            $body .= "\n\n" . characters::say($entityid, 'herald', 'heraldsign')['text'];
        }
        // The library of the oldest one, where the reader sees what they have.
        $url = (new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $list[0]->slug]))->out(false);
        $library = format_string($list[0]->library, true, ['escape' => false]);
        $told = self::send('overdue', $user, $subject, $body, $url, $library, $library, [
            'library' => $DB->get_record(self::T . 'library', ['slug' => $list[0]->slug]),
            'young' => mail::young($reader ?: null),
            'hello' => get_string('mailhello', 'local_oksigeniaayvurenda', $user->firstname),
            'heading' => $heading,
            'lead' => $young ? [$str($one ? 'heraldoverduelead' : 'heraldoverdueleadmany')] : [],
            'books' => $rows,
            'button' => ['url' => $url, 'text' => get_string('noticereaderbutton', 'local_oksigeniaayvurenda')],
            'after' => array_values(array_filter([$warning, $after], fn($line) => $line !== '')),
            'sign' => mail::sign($entityid, $reader ?: null, (bool) $young),
            'why' => get_string('mailwhyreader', 'local_oksigeniaayvurenda'),
            'prefs' => true,
        ]);
        force_current_language($language);
        [$in, $params] = $DB->get_in_or_equal(array_map(fn($l) => (int) $l->id, $list));
        $DB->set_field_select(self::T . 'loan', 'timenotified', $now, 'id ' . $in, $params);
        return $told;
    }

    /**
     * Summaries to tutors: who in their group has books late, without titles, once a week.
     *
     * @param int $now
     * @return int Messages sent.
     */
    private static function tutors(int $now): int {
        global $DB;
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
            $sent += self::tell_tutor($tutor, $now) ? 1 : 0;
        }
        return $sent;
    }

    /**
     * Tells a tutor now who in the group has books to bring back, without their titles.
     *
     * @param \stdClass $tutor With id, yearid, groupname, userid and entityid.
     * @param int $now
     * @return bool Whether a message went.
     */
    public static function tell_tutor(\stdClass $tutor, int $now): bool {
        global $DB;
        $tz = \core_date::get_server_timezone_object();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();
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
            return false;
        }
        $user = \core_user::get_user($tutor->userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        $language = force_current_language((string) $user->lang);
        $readers = [];
        foreach ($late as $loan) {
            $days = loan_rules::days_late((int) $loan->timedue, $now, $tz);
            $entry = $readers[$loan->readerid] ?? ['name' => self::name($loan), 'books' => 0, 'days' => 0];
            $entry['books']++;
            $entry['days'] = max($entry['days'], $days);
            $readers[$loan->readerid] = $entry;
        }
        $lines = [];
        $people = [];
        foreach ($readers as $entry) {
            $row = get_string(
                'noticetutorrow' . ($entry['books'] === 1 ? 'one' : '') . ($entry['days'] === 1 ? 'day' : ''),
                'local_oksigeniaayvurenda',
                (object) $entry
            );
            $line = (object) ['name' => $entry['name'], 'row' => $row];
            $lines[] = get_string('noticetutorline', 'local_oksigeniaayvurenda', $line);
            $people[] = ['name' => $entry['name'], 'line' => $row];
        }
        $a = (object) ['name' => $user->firstname, 'group' => $tutor->groupname,
            'library' => format_string(reset($late)->library, true, ['escape' => false]), 'readers' => implode("\n", $lines)];
        $url = (new \moodle_url('/local/oksigeniaayvurenda/class.php', ['library' => reset($late)->slug,
            'group' => $tutor->groupname]))->out(false);
        $subject = get_string('noticetutorsubject', 'local_oksigeniaayvurenda', $a);
        $told = self::send(
            'overduetutor',
            $user,
            $subject,
            get_string('noticetutorbody', 'local_oksigeniaayvurenda', $a),
            $url,
            $tutor->groupname,
            $a->library,
            [
                'library' => $DB->get_record(self::T . 'library', ['slug' => reset($late)->slug]),
                'hello' => get_string('mailhello', 'local_oksigeniaayvurenda', $user->firstname),
                'heading' => $subject,
                'lead' => [get_string('noticetutorlead', 'local_oksigeniaayvurenda')],
                'people' => $people,
                'button' => ['url' => $url, 'text' => get_string('noticetutorbutton', 'local_oksigeniaayvurenda')],
                'after' => [get_string('noticetutorafter', 'local_oksigeniaayvurenda')],
                'why' => get_string('mailwhytutor', 'local_oksigeniaayvurenda'),
                'prefs' => true,
            ]
        );
        force_current_language($language);
        $DB->set_field(self::T . 'tutor', 'timenotified', $now, ['id' => $tutor->id]);
        return $told;
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
     * @param string $from The library that speaks: in a site that is only a library, it signs the message.
     * @param array|null $design What the e-mail shows, for mail::html(); without it, the words as they are.
     * @return bool
     */
    public static function send(
        string $provider,
        \stdClass $user,
        string $subject,
        string $body,
        string $url = '',
        string $urlname = '',
        string $from = '',
        ?array $design = null
    ): bool {
        $message = new \core\message\message();
        $message->component = 'local_oksigeniaayvurenda';
        $message->name = $provider;
        $message->userfrom = mail::from($from);
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = mail::html(($design ?? mail::plain($subject, $body)) + ['userid' => (int) $user->id]);
        $message->smallmessage = $subject;
        $message->notification = 1;
        if ($url !== '') {
            $message->contexturl = $url;
            $message->contexturlname = $urlname;
        }
        return (bool) message_send($message);
    }
}
