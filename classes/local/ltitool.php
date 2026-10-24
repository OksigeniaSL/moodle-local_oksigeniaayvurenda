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
 * Ayvurenda as an LTI 1.3 tool: a learning platform (Moodle, Canvas, Brightspace…) opens a library without installing
 * anything, and whoever comes from it enters with their account of the platform.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\readers\card_codes;
use Packback\Lti1p3\JwksEndpoint;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

/**
 * Each platform belongs to one entity: whoever comes from it becomes a reader of that entity, with the type their role
 * on the platform says, and lands in its library, or in the book the link names. The account is Moodle's own LTI
 * account (auth_lti), bound to the platform's user; the library keeps no copy of their name.
 */
final class ltitool {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var \GuzzleHttp\Client|null How to ask the platforms; tests put their own. */
    public static $client = null;

    /** @var string[] Roles on the platform (the end of the URI) and the reader type they give, first match wins. */
    private const ROLES = [
        'Administrator' => 'teacher',
        'Instructor' => 'teacher',
        'Faculty' => 'teacher',
        'ContentDeveloper' => 'teacher',
        'Staff' => 'staff',
        'Mentor' => 'family',
        'Learner' => 'student',
        'Student' => 'student',
    ];

    /**
     * This site's key for signing what it sends to the platforms, made the first time.
     *
     * @return string[] Key id and private key in PEM.
     */
    public static function keys(): array {
        $kid = (string) get_config('local_oksigeniaayvurenda', 'ltikid');
        $private = (string) get_config('local_oksigeniaayvurenda', 'ltiprivatekey');
        if ($kid === '' || $private === '') {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            $kid = bin2hex(random_bytes(10));
            set_config('ltiprivatekey', $private, 'local_oksigeniaayvurenda');
            set_config('ltikid', $kid, 'local_oksigeniaayvurenda');
        }
        return [$kid, $private];
    }

    /**
     * The public key set the platforms check this site's messages with.
     *
     * @return array
     */
    public static function jwks(): array {
        [$kid, $private] = self::keys();
        return JwksEndpoint::new([$kid => $private])->getPublicJwks();
    }

    /**
     * The addresses a platform asks for when the tool is added to it.
     *
     * @return string[] login, launch, jwks, domain.
     */
    public static function urls(): array {
        $url = fn(string $page) => (new \moodle_url('/local/oksigeniaayvurenda/lti/' . $page))->out(false);
        return ['login' => $url('login.php'), 'launch' => $url('launch.php'), 'jwks' => $url('jwks.php'),
            'domain' => (string) parse_url((new \moodle_url('/'))->out(false), PHP_URL_HOST)];
    }

    /**
     * The platforms of an entity.
     *
     * @param int $entityid
     * @return \stdClass[]
     */
    public static function platforms(int $entityid): array {
        global $DB;
        return array_values($DB->get_records(self::T . 'ltiplatform', ['entityid' => $entityid], 'name, id'));
    }

    /**
     * The platform an LTI message comes from.
     *
     * @param string $issuer
     * @param string|null $clientid Without it, only if the issuer has a single client here.
     * @return \stdClass|null
     */
    public static function platform_by_issuer(string $issuer, ?string $clientid = null): ?\stdClass {
        global $DB;
        if ($clientid !== null && $clientid !== '') {
            return $DB->get_record(self::T . 'ltiplatform', ['issuer' => $issuer, 'clientid' => $clientid]) ?: null;
        }
        $all = $DB->get_records(self::T . 'ltiplatform', ['issuer' => $issuer]);
        return count($all) === 1 ? reset($all) : null;
    }

    /**
     * Whether a platform's deployment is accepted: the ones written, or any if none is.
     *
     * @param \stdClass $platform
     * @param string $deploymentid
     * @return bool
     */
    public static function accepts(\stdClass $platform, string $deploymentid): bool {
        $listed = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $platform->deployments))));
        return !$listed || in_array($deploymentid, $listed, true);
    }

    /**
     * Adds or changes a platform of an entity.
     *
     * @param int $entityid
     * @param array $data name, issuer, clientid, authloginurl, authtokenurl, keyseturl, deployments.
     * @param int $id 0 to add one.
     * @return int Its id.
     * @throws \moodle_exception When something is missing, or the platform and client are another library's.
     */
    public static function save_platform(int $entityid, array $data, int $id = 0): int {
        global $DB;
        $clean = ['name' => trim((string) ($data['name'] ?? ''))];
        foreach (['issuer', 'clientid', 'authloginurl', 'authtokenurl', 'keyseturl'] as $field) {
            $clean[$field] = trim((string) ($data[$field] ?? ''));
        }
        $clean['deployments'] = trim((string) ($data['deployments'] ?? ''));
        foreach (['name', 'issuer', 'clientid', 'authloginurl', 'keyseturl'] as $field) {
            if ($clean[$field] === '') {
                throw new \moodle_exception('ltimissing', 'local_oksigeniaayvurenda', '', get_string(
                    'lti' . $field,
                    'local_oksigeniaayvurenda'
                ));
            }
        }
        if (\core_text::strlen($clean['clientid']) > 64 || \core_text::strlen($clean['issuer']) > 255) {
            throw new \moodle_exception('ltitoolong', 'local_oksigeniaayvurenda');
        }
        foreach (['authloginurl', 'authtokenurl', 'keyseturl'] as $field) {
            if ($clean[$field] !== '' && !preg_match('#^https?://#i', $clean[$field])) {
                throw new \moodle_exception('ltinoturl', 'local_oksigeniaayvurenda', '', get_string(
                    'lti' . $field,
                    'local_oksigeniaayvurenda'
                ));
            }
        }
        $other = $DB->get_record(self::T . 'ltiplatform', ['issuer' => $clean['issuer'], 'clientid' => $clean['clientid']]);
        if ($other && (int) $other->id !== $id) {
            throw new \moodle_exception('ltitaken', 'local_oksigeniaayvurenda');
        }
        $now = time();
        if ($id) {
            $DB->get_record(self::T . 'ltiplatform', ['id' => $id, 'entityid' => $entityid], 'id', MUST_EXIST);
            $DB->update_record(self::T . 'ltiplatform', (object) (['id' => $id, 'timemodified' => $now] + $clean));
            return $id;
        }
        return (int) $DB->insert_record(self::T . 'ltiplatform', (object) (['entityid' => $entityid, 'timecreated' => $now,
            'timemodified' => $now] + $clean));
    }

    /**
     * Removes a platform: whoever came from it keeps their reader and their history.
     *
     * @param int $entityid
     * @param int $id
     */
    public static function delete_platform(int $entityid, int $id): void {
        global $DB;
        $DB->delete_records(self::T . 'ltiplatform', ['id' => $id, 'entityid' => $entityid]);
    }

    /**
     * Checks a launch the platform posts: signature, nonce, state, deployment.
     *
     * @param array $request What the platform posted (id_token and state).
     * @return LtiMessageLaunch
     */
    public static function launch(array $request): LtiMessageLaunch {
        $cache = new lti\cache();
        $connector = new LtiServiceConnector($cache, self::$client ?? new \core\http_client());
        return LtiMessageLaunch::new(new lti\store(), $cache, new lti\cookie(), $connector)->initialize($request);
    }

    /**
     * The reader type a launch's roles give.
     *
     * @param array $launchdata
     * @return string
     */
    public static function reader_type(array $launchdata): string {
        $ends = array_map(fn($role) => preg_replace('/^.*[#\/]/', '', (string) $role), $launchdata[LtiConstants::ROLES] ?? []);
        foreach (self::ROLES as $role => $type) {
            if (in_array($role, $ends, true)) {
                return $type;
            }
        }
        return 'student';
    }

    /**
     * The reader of an account in an entity, made if there is none; a reader who had left is back.
     *
     * @param int $entityid
     * @param int $userid
     * @param string $type For a new reader.
     * @param int|null $now
     * @return int The reader's id.
     */
    public static function reader(int $entityid, int $userid, string $type, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $reader = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid]);
        if ($reader) {
            if ($reader->status !== 'active') {
                $DB->update_record(self::T . 'reader', (object) ['id' => $reader->id, 'status' => 'active',
                    'timemodified' => $now]);
            }
            return (int) $reader->id;
        }
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $codes = $DB->get_fieldset_select(self::T . 'reader', 'cardcode', 'entityid = :entityid', ['entityid' => $entityid]);
        $code = card_codes::next($codes, 1, !empty($settings['controlletter']))[0];
        return (int) $DB->insert_record(self::T . 'reader', (object) ['entityid' => $entityid, 'userid' => $userid,
            'cardcode' => $code, 'readertype' => $type, 'status' => 'active', 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Where someone who comes from a platform lands, with their reader made: the library the link names (of the same
     * entity), or the entity's central one; the book, if the link names one of its own.
     *
     * @param array $launchdata
     * @param int $userid The account they entered with.
     * @return \moodle_url
     * @throws \moodle_exception When the platform is not registered here any more.
     */
    public static function arrive(array $launchdata, int $userid): \moodle_url {
        global $DB;
        $aud = $launchdata['aud'] ?? '';
        $clientid = is_array($aud) ? (string) ($launchdata['azp'] ?? reset($aud)) : (string) $aud;
        $platform = self::platform_by_issuer((string) ($launchdata['iss'] ?? ''), $clientid);
        if (!$platform) {
            throw new \moodle_exception('ltiunknown', 'local_oksigeniaayvurenda');
        }
        $entityid = (int) $platform->entityid;
        self::reader($entityid, $userid, self::reader_type($launchdata));
        $custom = $launchdata[LtiConstants::CUSTOM] ?? [];
        $library = null;
        if (!empty($custom['library'])) {
            $library = catalogue::library(clean_param($custom['library'], PARAM_ALPHANUMEXT));
            $library = $library && (int) $library->entityid === $entityid ? $library : null;
        }
        if (!$library) {
            $libraries = $DB->get_records(self::T . 'library', ['entityid' => $entityid], "CASE WHEN kind = 'central' THEN 0
                ELSE 1 END, id", '*', 0, 1);
            $library = reset($libraries) ?: null;
        }
        if (!$library) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        $recordid = (int) clean_param($custom['record'] ?? 0, PARAM_INT);
        if ($recordid && $DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid])) {
            return new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]);
        }
        return new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]);
    }
}
