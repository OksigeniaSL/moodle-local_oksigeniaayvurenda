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
 * «No more mail, please», for the adults a library writes to without an account (a family from the families' file): who
 * has an account turns it off in their notification preferences; who has none gets a sealed link at the foot of each
 * mail. Only a hash of the address is kept, per centre.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailstop {
    /** @var string */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What is kept of an address: its hash, in lower case and without spaces.
     *
     * @param string $email
     * @return string
     */
    public static function hash(string $email): string {
        return hash('sha256', \core_text::strtolower(trim($email)));
    }

    /**
     * The seal of a centre's link for one address's hash.
     *
     * @param int $entityid
     * @param string $hash
     * @return string
     */
    public static function seal(int $entityid, string $hash): string {
        return mail::seal('mailstop', $entityid, $hash);
    }

    /**
     * The link at the foot of a mail to that address.
     *
     * @param int $entityid
     * @param string $email
     * @return string
     */
    public static function link(int $entityid, string $email): string {
        $hash = self::hash($email);
        return (new \moodle_url('/local/oksigeniaayvurenda/stop.php', ['e' => $entityid, 'h' => $hash,
            's' => self::seal($entityid, $hash)]))->out(false);
    }

    /**
     * Whether that address asked a centre for no more mail.
     *
     * @param int $entityid
     * @param string $email
     * @return bool
     */
    public static function stopped(int $entityid, string $email): bool {
        global $DB;
        return $DB->record_exists(self::T . 'mailstop', ['entityid' => $entityid, 'emailhash' => self::hash($email)]);
    }

    /**
     * Whether a hash is on a centre's list.
     *
     * @param int $entityid
     * @param string $hash
     * @return bool
     */
    public static function listed(int $entityid, string $hash): bool {
        global $DB;
        return $DB->record_exists(self::T . 'mailstop', ['entityid' => $entityid, 'emailhash' => $hash]);
    }

    /**
     * Puts a hash on a centre's list, or takes it off; twice is once.
     *
     * @param int $entityid
     * @param string $hash
     * @param bool $stop
     */
    public static function set(int $entityid, string $hash, bool $stop): void {
        global $DB;
        $where = ['entityid' => $entityid, 'emailhash' => $hash];
        if (!$stop) {
            $DB->delete_records(self::T . 'mailstop', $where);
        } else if (!$DB->record_exists(self::T . 'mailstop', $where)) {
            $DB->insert_record(self::T . 'mailstop', $where + ['timecreated' => time()]);
        }
    }

    /**
     * Sends a library's mail to an adult without an account, unless they asked for no more: with the link to ask it at
     * the foot, in the page and in the words.
     *
     * @param int $entityid
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param string $library The library's name, as the sender.
     * @param string $subject
     * @param string $text
     * @param array $design What mail::html() draws.
     * @return bool Whether it was sent.
     */
    public static function send(
        int $entityid,
        string $email,
        string $firstname,
        string $lastname,
        string $library,
        string $subject,
        string $text,
        array $design
    ): bool {
        if (!$entityid || self::stopped($entityid, $email)) {
            return false;
        }
        $to = clone \core_user::get_noreply_user();
        $to->email = $email;
        $to->firstname = $firstname;
        $to->lastname = $lastname;
        $to->mailformat = 1;
        $link = self::link($entityid, $email);
        $text .= "\n\n" . get_string('mailstoptext', 'local_oksigeniaayvurenda') . ': ' . $link;
        return (bool) email_to_user($to, mail::from($library), $subject, $text, mail::html($design + ['stopurl' => $link,
            'entityid' => $entityid]));
    }
}
