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
 * A centre signed up for the hosted service.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * Everything a new centre of a shared site needs, in one step that can be repeated without making anything twice: the
 * centre with its first library and its category, its plan, an account for whoever runs it with the manager's role in
 * that category and an e-mail to get in, and when it accepted the terms and the privacy policy.
 */
final class signup {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What would be done, without doing it.
     *
     * @param array $data name, email.
     * @return array centre (id or 0), account (id or 0).
     */
    public static function look(array $data): array {
        return ['centre' => (int) (self::centre_named((string) ($data['name'] ?? ''))->id ?? 0),
            'account' => (int) (self::account_of((string) ($data['email'] ?? ''))->id ?? 0)];
    }

    /**
     * Signs a centre up, or completes what is missing of it.
     *
     * @param array $data name, library (its first one's name; the centre's when empty), plan (free, small or medium),
     *     email, firstname, lastname, lang, role (a role's short name; the plugin's manager when empty), accepted (when it
     *     accepted the terms; now when empty), terms and privacy (where they are).
     * @param int|null $now
     * @return array entity, library (its slug), category, userid, created (centre, account, role), mailed.
     * @throws \invalid_parameter_exception When something is missing or wrong.
     */
    public static function centre(array $data, ?int $now = null): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $now = $now ?? time();
        $name = trim((string) ($data['name'] ?? ''));
        $email = \core_text::strtolower(trim((string) ($data['email'] ?? '')));
        $plan = plans::preset((string) ($data['plan'] ?? ''));
        if ($name === '' || !validate_email($email) || !$plan) {
            throw new \invalid_parameter_exception('A name, a valid e-mail and a plan (free, small or medium) are needed.');
        }
        if (!get_config('local_oksigeniaayvurenda', 'entitycategories')) {
            throw new \invalid_parameter_exception('This site is not shared by centres: «A category for each centre» is off.');
        }
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => trim((string) ($data['role'] ?? '')) ?: roles::MANAGER]);
        if (!$roleid) {
            throw new \invalid_parameter_exception('There is no role with that short name.');
        }
        $created = ['centre' => false, 'account' => false, 'role' => false];

        // The centre, with its first library and its category.
        $entity = self::centre_named($name);
        if (!$entity) {
            $library = libraries::create_entity(
                ['name' => $name, 'library' => trim((string) ($data['library'] ?? '')), 'closed' => true],
                $now
            );
            $entity = $DB->get_record(self::T . 'entity', ['id' => $library->entityid], '*', MUST_EXIST);
            $created['centre'] = true;
        }
        $categoryid = access::category((int) $entity->id);
        plans::save((int) $entity->id, $plan);
        $library = $DB->get_records(self::T . 'library', ['entityid' => $entity->id, 'parentid' => null], 'id', 'id, slug', 0, 1)
            ?: $DB->get_records(self::T . 'library', ['entityid' => $entity->id], 'id', 'id, slug', 0, 1);
        $library = reset($library);

        // When it accepted the terms of service, the privacy policy and the data processing agreement, ayvurenda.com's in
        // the language of whoever runs it unless others are given: the first time is the one that counts.
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entity->id]), true) ?: [];
        if (empty($settings['terms'])) {
            $accepted = trim((string) ($data['accepted'] ?? ''));
            $english = ($data['lang'] ?? '') === 'en';
            $site = 'https://ayvurenda.com/' . ($english ? 'en/' : '');
            $settings['terms'] = [
                'time' => $accepted !== '' && strtotime($accepted) ? (int) strtotime($accepted) : $now,
                'terms' => trim((string) ($data['terms'] ?? '')) ?: $site . ($english ? 'terms/' : 'condiciones/'),
                'privacy' => trim((string) ($data['privacy'] ?? '')) ?: $site . ($english ? 'privacy/' : 'privacidad/'),
                'dpa' => trim((string) ($data['dpa'] ?? '')) ?: $site . ($english ? 'data-processing/' : 'encargo/'),
            ];
            $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entity->id]);
        }

        // Whoever runs it: their account, or a new one that gets its password by e-mail and changes it on entering.
        $user = self::account_of($email);
        $mailed = false;
        if (!$user) {
            $lang = clean_param((string) ($data['lang'] ?? ''), PARAM_LANG) ?: $CFG->lang;
            $account = (object) [
                'auth' => 'manual',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username' => self::username($email),
                'email' => $email,
                'firstname' => trim((string) ($data['firstname'] ?? '')) ?: strtok($email, '@'),
                'lastname' => trim((string) ($data['lastname'] ?? '')) ?: $name,
                'lang' => $lang,
            ];
            // The same in Moodle 4.5 and 5.3, where user_create_user() is deprecated.
            $userid = (int) (method_exists('\core\user', 'create_user') ? \core\user::create_user($account, false, true)
                : user_create_user($account, false, true));
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            $mailed = (bool) setnew_password_and_mail($user);
            set_user_preference('auth_forcepasswordchange', 1, $user);
            $created['account'] = true;
        }
        $context = \context_coursecat::instance($categoryid);
        if (!$DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $user->id, 'contextid' => $context->id])) {
            role_assign($roleid, $user->id, $context->id);
            $created['role'] = true;
        }
        \cache::make('local_oksigeniaayvurenda', 'access')->purge();
        return ['entity' => (int) $entity->id, 'library' => (string) $library->slug, 'category' => $categoryid,
            'userid' => (int) $user->id, 'created' => $created, 'mailed' => $mailed];
    }

    /**
     * When a centre accepted the terms and the privacy policy, if it is known.
     *
     * @param int $entityid
     * @return array|null time, terms, privacy.
     */
    public static function terms(int $entityid): ?array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return !empty($settings['terms']['time']) ? $settings['terms'] : null;
    }

    /**
     * The centre with that very name, if there is one (not a practice library).
     *
     * @param string $name
     * @return \stdClass|null
     */
    private static function centre_named(string $name): ?\stdClass {
        global $DB;
        if (trim($name) === '') {
            return null;
        }
        $same = $DB->sql_equal('name', ':name', false);
        foreach ($DB->get_records_select(self::T . 'entity', $same, ['name' => trim($name)]) as $one) {
            if (!practice::is((int) $one->id)) {
                return $one;
            }
        }
        return null;
    }

    /**
     * The one account with that e-mail, if there is one.
     *
     * @param string $email
     * @return \stdClass|null
     * @throws \invalid_parameter_exception When several accounts share it.
     */
    private static function account_of(string $email): ?\stdClass {
        global $CFG, $DB;
        if ($email === '') {
            return null;
        }
        $found = $DB->get_records_select(
            'user',
            'deleted = 0 AND mnethostid = :host AND ' . $DB->sql_equal('email', ':email', false),
            ['host' => $CFG->mnet_localhost_id, 'email' => $email]
        );
        if (count($found) > 1) {
            throw new \invalid_parameter_exception('Several accounts have that e-mail: give the role by hand.');
        }
        return $found ? reset($found) : null;
    }

    /**
     * A user name made from the e-mail, free on this site.
     *
     * @param string $email
     * @return string
     */
    private static function username(string $email): string {
        global $DB;
        $base = \core_user::clean_field(\core_text::strtolower($email), 'username');
        $name = $base;
        for ($n = 2; $DB->record_exists('user', ['username' => $name]); $n++) {
            $name = $base . '-' . $n;
        }
        return $name;
    }
}
