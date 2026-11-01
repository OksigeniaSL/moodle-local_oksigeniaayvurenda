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
 * The way into the app for a reader.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Where Oksigenia Aty, the app of the hosting, is installed, the library staff can send a reader their way into it: an
 * e-mail with a link that signs them in once and lasts a week (Aty makes and sends it). A reader with no account yet gets
 * one first, from the e-mail the library has for them, if whoever asks may create accounts on the site.
 */
final class applink {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int The days the link lasts. */
    public const DAYS = 7;

    /**
     * Whether the app's plugin is there to make the links.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists('\local_oksigeniaaty\api');
    }

    /**
     * What can be done for a reader: send (they have an account), create (no account, an e-mail, and whoever asks may
     * create accounts, or works at the desk of a centre on a shared site, where nobody of a centre can have that permission
     * and the account gets nothing but being this reader), noemail (no account and no e-mail) or none (the app is not there,
     * or they are not active).
     *
     * @param \stdClass $reader
     * @return string
     */
    public static function state(\stdClass $reader): string {
        if (!self::available() || $reader->status !== 'active') {
            return 'none';
        }
        if (!empty($reader->userid)) {
            return 'send';
        }
        if (self::email($reader) === '') {
            return 'noemail';
        }
        $may = has_capability('moodle/user:create', \context_system::instance())
            || (access::apart() && access::can('local/oksigeniaayvurenda:desk', (int) $reader->entityid));
        return $may ? 'create' : 'none';
    }

    /**
     * Sends a reader their way into the app, making their account first if they have none and it may be made.
     *
     * @param \stdClass $reader
     * @return string What happened: appsent, appcreated, apptaken (another account has that e-mail), appnotallowed
     *     (an administrator's or a closed account), appnomail (the e-mail could not go) or appnone.
     */
    public static function send(\stdClass $reader): string {
        $state = self::state($reader);
        $created = false;
        if ($state === 'create') {
            $userid = self::account($reader);
            if (!$userid) {
                return 'apptaken';
            }
            $reader->userid = $userid;
            $created = true;
        } else if ($state !== 'send') {
            return 'appnone';
        }
        try {
            $sent = self::mail_link($reader);
        } catch (\moodle_exception $e) {
            return 'appnotallowed';
        }
        return !$sent ? 'appnomail' : ($created ? 'appcreated' : 'appsent');
    }

    /**
     * Sends a reader the link that opens the app, with the look of their library.
     *
     * @param \stdClass $reader
     * @return bool
     */
    private static function mail_link(\stdClass $reader): bool {
        $user = \core_user::get_user((int) $reader->userid, '*', MUST_EXIST);
        $link = \local_oksigeniaaty\api::issue_link((int) $user->id, self::DAYS);
        $language = force_current_language((string) $user->lang);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $until = userdate((int) $link['expires'], get_string('strftimedate', 'langconfig'), \core_date::get_user_timezone($user));
        $subject = $str('applinkmailsubject');
        $hello = $str('mailhello', $user->firstname);
        $library = mail::main_library((int) $reader->entityid);
        $text = $hello . "\n\n" . $str('applinkmaillead') . "\n\n" . $link['url'] . "\n\n" . $str('applinkmailuntil', $until) . ' '
            . $str('applinkmailhint');
        $html = mail::html([
            'library' => $library,
            'entityid' => (int) $reader->entityid,
            'hello' => $hello,
            'heading' => $str('applinkmailheading'),
            'lead' => [$str('applinkmaillead')],
            'button' => ['url' => $link['url'], 'text' => $str('applinkmailbutton')],
            'after' => [$str('applinkmailuntil', $until), $str('applinkmailhint')],
            'why' => $str('mailwhyreader'),
        ]);
        force_current_language($language);
        return (bool) email_to_user($user, mail::from($library ? (string) $library->name : ''), $subject, $text, $html);
    }

    /**
     * Makes a Moodle account for a reader who has none, from the name and e-mail the library has, and links it; nothing
     * when another account already has that e-mail (the staff link it in «Readers»).
     *
     * @param \stdClass $reader
     * @return int The new account, or 0.
     */
    public static function account(\stdClass $reader): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $email = self::email($reader);
        if (
            $email === '' || !empty($reader->userid) || $DB->record_exists_select(
                'user',
                $DB->sql_equal('email', ':email', false) . ' AND deleted = 0',
                ['email' => $email]
            )
        ) {
            return 0;
        }
        $username = clean_param(\core_text::strtolower($email), PARAM_USERNAME);
        if ($username === '' || $DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = clean_param(\core_text::strtolower('lector' . $reader->entityid . '-' . $reader->cardcode), PARAM_USERNAME);
        }
        $user = (object) [
            'username' => $username,
            'firstname' => trim((string) $reader->firstname) !== '' ? $reader->firstname : $reader->cardcode,
            'lastname' => trim((string) $reader->lastname),
            'email' => $email,
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'password' => generate_password(24),
        ];
        $userid = (int) (method_exists('\core\user', 'create_user') ? \core\user::create_user($user)
            : user_create_user($user));
        $DB->set_field(self::T . 'reader', 'userid', $userid, ['id' => $reader->id]);
        return $userid;
    }

    /**
     * The reader's e-mail as the library has it, or ''.
     *
     * @param \stdClass $reader
     * @return string
     */
    private static function email(\stdClass $reader): string {
        $email = trim((string) ($reader->email ?? ''));
        return $email !== '' && validate_email($email) ? $email : '';
    }
}
