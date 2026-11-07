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
 * A reader without an account given their way into the library on the web.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Not everybody gets in through the app: a retired member of a town library, a child without an e-mail, any reader of a
 * centre whose plan has no app. The staff give them their way in: an account with a first password, which they change
 * the first time; by e-mail if the library has one, or on a sheet to print (or to hand over at the desk) if it has not.
 * The password is never kept: whoever gives it sees it once.
 */
final class entry {
    /** @var string[] What a first password starts with: plain words, for any age, the same in Spanish and in English. */
    private const WORDS = ['Faro', 'Luna', 'Nube', 'Lobo', 'Kiwi', 'Koala', 'Panda', 'Mango', 'Pino', 'Isla', 'Rayo', 'Duna',
        'Lince', 'Puma', 'Nido', 'Brisa', 'Lago', 'Atlas', 'Cometa', 'Mapa', 'Globo', 'Ancla', 'Tinta', 'Libro'];

    /**
     * Whether a reader can be given their way in: active, with no account, and whoever asks may make one (on a site of
     * its own, whoever may create accounts; on a shared one, the staff of the desk of their centre).
     *
     * @param \stdClass $reader
     * @return bool
     */
    public static function may(\stdClass $reader): bool {
        if ($reader->status !== 'active' || !empty($reader->userid)) {
            return false;
        }
        return has_capability('moodle/user:create', \context_system::instance())
            || (access::apart() && access::can('local/oksigeniaayvurenda:desk', (int) $reader->entityid));
    }

    /**
     * A first password easy to read aloud and to type: a word, a dash and four figures («Faro-4821»), which meets the
     * usual rules of Moodle (a capital, a small letter, a figure and a sign).
     *
     * @return string
     */
    public static function password(): string {
        return self::WORDS[random_int(0, count(self::WORDS) - 1)] . '-' . random_int(1000, 9999);
    }

    /**
     * Gives a reader their way in.
     *
     * @param \stdClass $reader
     * @return array state: mailed (to their e-mail), sheet (to print: with password), taken (another account has that
     *     e-mail) or none; and name, card, username, email and password as they apply.
     */
    public static function give(\stdClass $reader): array {
        if (!self::may($reader)) {
            return ['state' => 'none'];
        }
        $password = self::password();
        $email = applink::email($reader);
        $userid = applink::account($reader, $password, true);
        if (!$userid) {
            return ['state' => 'taken'];
        }
        $reader->userid = $userid;
        // The first password is only for the first time.
        set_user_preference('auth_forcepasswordchange', 1, $userid);
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $mailed = $email !== '' && self::mail($reader, $user, $password);
        \local_oksigeniaayvurenda\event\entry_given::fire(
            (int) $reader->entityid,
            (int) $reader->id,
            ['what' => $mailed ? 'mailed' : 'sheet'],
            $userid
        );
        $name = trim($reader->firstname . ' ' . $reader->lastname);
        return [
            'state' => $mailed ? 'mailed' : 'sheet',
            'name' => $name !== '' ? $name : (string) $reader->cardcode,
            'card' => (string) $reader->cardcode,
            'username' => (string) $user->username,
            'email' => $email,
            // Only on the sheet: in a message it has already gone.
            'password' => $mailed ? '' : $password,
        ];
    }

    /**
     * Gives their way in to every reader of a class who has none yet.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int $now
     * @return array mailed (how many), sheets (what give() said of each one to print), taken (how many).
     */
    public static function give_group(\stdClass $library, string $group, int $now): array {
        $done = ['mailed' => 0, 'sheets' => [], 'taken' => 0];
        foreach (self::waiting($library, $group, $now) as $reader) {
            $given = self::give($reader);
            if ($given['state'] === 'mailed') {
                $done['mailed']++;
            } else if ($given['state'] === 'sheet') {
                $done['sheets'][] = $given;
            } else if ($given['state'] === 'taken') {
                $done['taken']++;
            }
        }
        return $done;
    }

    /**
     * The readers of a class this school year who could be given their way in.
     *
     * @param \stdClass $library
     * @param string $group
     * @param int $now
     * @return \stdClass[]
     */
    public static function waiting(\stdClass $library, string $group, int $now): array {
        global $DB;
        if ($group === '') {
            return [];
        }
        $year = years::around((int) $library->entityid, $now);
        $readers = $DB->get_records_sql(
            "SELECT r.*
               FROM {local_oksigeniaayvurenda_reader} r
               JOIN {local_oksigeniaayvurenda_member} m ON m.readerid = r.id AND m.yearid = :yearid AND m.groupname = :groupname
              WHERE r.entityid = :entityid AND r.userid IS NULL AND r.status = 'active'
           ORDER BY r.lastname, r.firstname, r.id",
            ['yearid' => $year->id, 'groupname' => $group, 'entityid' => $library->entityid]
        );
        return array_values(array_filter($readers, [self::class, 'may']));
    }

    /**
     * Writes to a reader their user name and first password, with the look of their library.
     *
     * @param \stdClass $reader
     * @param \stdClass $user
     * @param string $password
     * @return bool
     */
    private static function mail(\stdClass $reader, \stdClass $user, string $password): bool {
        $language = force_current_language((string) $user->lang);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $library = mail::main_library((int) $reader->entityid);
        $url = (new \moodle_url('/login/index.php'))->out(false);
        $hello = $str('mailhello', $user->firstname);
        $lines = [$str('entrymailuser', $user->username), $str('entrymailpassword', $password)];
        $subject = $str('entrymailsubject');
        $text = $hello . "\n\n" . $str('entrymaillead') . "\n\n" . implode("\n", $lines) . "\n\n" . $url . "\n\n"
            . $str('entrymailhint');
        $html = mail::html([
            'library' => $library,
            'entityid' => (int) $reader->entityid,
            'hello' => $hello,
            'heading' => $str('entrymailheading'),
            'lead' => array_merge([$str('entrymaillead')], $lines),
            'button' => ['url' => $url, 'text' => $str('entrymailbutton')],
            'after' => [$str('entrymailhint')],
            'why' => $str('mailwhyreader'),
        ]);
        force_current_language($language);
        return (bool) email_to_user($user, mail::from($library ? (string) $library->name : ''), $subject, $text, $html);
    }
}
