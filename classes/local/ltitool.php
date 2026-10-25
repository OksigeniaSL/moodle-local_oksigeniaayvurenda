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
        // A platform belongs to one centre. Moodle logs people in by the platform and their id there, so the same
        // platform in a second centre, or one another tool of the site already logs in from, could log in as someone
        // else.
        $ours = $DB->record_exists(self::T . 'ltiplatform', ['issuer' => $clean['issuer'], 'entityid' => $entityid]);
        if (
            $DB->record_exists_select(
                self::T . 'ltiplatform',
                'issuer = :issuer AND entityid <> :entityid',
                ['issuer' => $clean['issuer'], 'entityid' => $entityid]
            )
                || (!$ours && $DB->record_exists_select('auth_lti_linked_login', $DB->sql_compare_text('issuer', 255) .
                    ' = ' . $DB->sql_compare_text(':issuer', 255), ['issuer' => $clean['issuer']]))
        ) {
            throw new \moodle_exception('ltiissuertaken', 'local_oksigeniaayvurenda');
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
     * The address a platform opens to register itself in one go (LTI dynamic registration), for an entity; made, or
     * made again, on asking. It lasts a week and serves any number of platforms of that entity until then.
     *
     * @param int $entityid
     * @param bool $new A new one, which undoes the one before.
     * @return array url, expires.
     */
    public static function registration_url(int $entityid, bool $new = false): array {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $current = $settings['ltiregister'] ?? [];
        if ($new || empty($current['token']) || (int) ($current['expires'] ?? 0) < time()) {
            $current = ['token' => bin2hex(random_bytes(20)), 'expires' => time() + WEEKSECS];
            $settings['ltiregister'] = $current;
            $DB->set_field(self::T . 'entity', 'settings', json_encode($settings), ['id' => $entityid]);
        }
        $url = new \moodle_url('/local/oksigeniaayvurenda/lti/register.php', ['k' => $current['token']]);
        return ['url' => $url->out(false), 'expires' => (int) $current['expires']];
    }

    /**
     * The entity a registration address belongs to, while it lasts.
     *
     * @param string $token
     * @return int 0 when no entity has it.
     */
    public static function registration_entity(string $token): int {
        global $DB;
        if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
            return 0;
        }
        foreach (
            $DB->get_records_select(
                self::T . 'entity',
                $DB->sql_like('settings', ':token'),
                ['token' => '%' . $token . '%'],
                'id',
                'id, settings'
            ) as $entity
        ) {
            $register = (json_decode((string) $entity->settings, true) ?: [])['ltiregister'] ?? [];
            if (hash_equals((string) ($register['token'] ?? ''), $token) && (int) ($register['expires'] ?? 0) >= time()) {
                return (int) $entity->id;
            }
        }
        return 0;
    }

    /**
     * Registers this library in a platform that asks (LTI dynamic registration): reads the platform's configuration,
     * tells it about the tool, and keeps the platform with what it answers.
     *
     * @param int $entityid
     * @param string $configurl The platform's openid_configuration.
     * @param string $token The platform's registration_token, if it gave one.
     * @return \stdClass The platform kept.
     * @throws \moodle_exception When the platform does not answer as it should.
     */
    public static function register(int $entityid, string $configurl, string $token = ''): \stdClass {
        global $DB, $SITE;
        $client = self::$client ?? new \core\http_client();
        $fail = fn(string $why) => new \moodle_exception('ltiregisterfailed', 'local_oksigeniaayvurenda', '', $why);
        try {
            $config = json_decode((string) $client->get($configurl, ['timeout' => 20])->getBody(), true);
        } catch (\Throwable $e) {
            throw $fail($e->getMessage());
        }
        foreach (['issuer', 'authorization_endpoint', 'jwks_uri', 'registration_endpoint'] as $field) {
            if (!is_array($config) || empty($config[$field]) || !is_string($config[$field])) {
                throw $fail($field);
            }
        }
        // The configuration has to come from the platform it speaks for.
        if (!str_starts_with($configurl, rtrim($config['issuer'], '/'))) {
            throw $fail('issuer');
        }
        $libraries = $DB->get_records(self::T . 'library', ['entityid' => $entityid], "CASE WHEN kind = 'central' THEN 0
            ELSE 1 END, id", 'id, slug, name', 0, 1);
        $library = reset($libraries);
        if (!$library) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniaayvurenda');
        }
        $urls = self::urls();
        $name = get_string('ltitoolname', 'local_oksigeniaayvurenda', format_string($library->name));
        $request = [
            'application_type' => 'web',
            'response_types' => ['id_token'],
            'grant_types' => ['implicit', 'client_credentials'],
            'initiate_login_uri' => $urls['login'],
            'redirect_uris' => [$urls['launch']],
            'client_name' => $name,
            'jwks_uri' => $urls['jwks'],
            'token_endpoint_auth_method' => 'private_key_jwt',
            'scope' => 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly',
            'https://purl.imsglobal.org/spec/lti-tool-configuration' => [
                'domain' => $urls['domain'],
                'target_link_uri' => $urls['launch'],
                'claims' => ['iss', 'sub', 'name', 'given_name', 'family_name', 'email'],
                'messages' => [['type' => 'LtiResourceLinkRequest', 'label' => $name],
                    ['type' => 'LtiDeepLinkingRequest', 'label' => $name, 'target_link_uri' => $urls['launch']]],
                'description' => format_string($SITE->fullname),
            ],
        ];
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        try {
            $answer = json_decode((string) $client->post($config['registration_endpoint'], ['headers' => $headers,
                'body' => json_encode($request), 'timeout' => 20])->getBody(), true);
        } catch (\Throwable $e) {
            throw $fail($e->getMessage());
        }
        if (!is_array($answer) || empty($answer['client_id'])) {
            throw $fail('client_id');
        }
        $deployment = (string) ($answer['https://purl.imsglobal.org/spec/lti-tool-configuration']['deployment_id'] ?? '');
        $product = (string) ($config['https://purl.imsglobal.org/spec/lti-platform-configuration']['product_family_code'] ?? '');
        $existing = $DB->get_record(self::T . 'ltiplatform', ['issuer' => $config['issuer'],
            'clientid' => (string) $answer['client_id'], 'entityid' => $entityid]);
        $id = self::save_platform($entityid, [
            'name' => $existing->name ?? ($product !== '' ? ucfirst($product) . ' · ' : '') . $config['issuer'],
            'issuer' => $config['issuer'],
            'clientid' => (string) $answer['client_id'],
            'authloginurl' => $config['authorization_endpoint'],
            'authtokenurl' => (string) ($config['token_endpoint'] ?? ''),
            'keyseturl' => $config['jwks_uri'],
            'deployments' => $deployment,
        ], $existing ? (int) $existing->id : 0);
        return $DB->get_record(self::T . 'ltiplatform', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The course a teacher comes from, kept when the platform offers the list of its members (LTI names and roles).
     *
     * @param array $launchdata
     * @return \stdClass|null The course, or null when the platform does not offer the list.
     */
    public static function course(array $launchdata): ?\stdClass {
        global $DB;
        $url = (string) ($launchdata[LtiConstants::NRPS_CLAIM_SERVICE]['context_memberships_url'] ?? '');
        $context = $launchdata['https://purl.imsglobal.org/spec/lti/claim/context'] ?? [];
        $contextid = (string) ($context['id'] ?? '');
        if ($url === '' || $contextid === '') {
            return null;
        }
        $platform = self::platform_of($launchdata);
        $title = \core_text::substr((string) ($context['title'] ?? ''), 0, 255);
        $now = time();
        $course = $DB->get_record(self::T . 'lticontext', ['platformid' => $platform->id,
            'contextid' => \core_text::substr($contextid, 0, 255)]);
        if ($course) {
            if ($course->membershipsurl !== $url || (string) $course->title !== $title) {
                $DB->update_record(self::T . 'lticontext', (object) ['id' => $course->id, 'membershipsurl' => $url,
                    'title' => $title, 'timemodified' => $now]);
            }
            return $DB->get_record(self::T . 'lticontext', ['id' => $course->id]);
        }
        $id = $DB->insert_record(self::T . 'lticontext', (object) ['platformid' => $platform->id,
            'contextid' => \core_text::substr($contextid, 0, 255), 'title' => $title, 'membershipsurl' => $url,
            'timecreated' => $now, 'timemodified' => $now]);
        return $DB->get_record(self::T . 'lticontext', ['id' => $id]);
    }

    /**
     * The members of a course, as its platform lists them.
     *
     * @param \stdClass $course
     * @return array[]
     */
    public static function members(\stdClass $course): array {
        global $DB;
        $platform = $DB->get_record(self::T . 'ltiplatform', ['id' => $course->platformid], '*', MUST_EXIST);
        if (trim((string) $platform->authtokenurl) === '') {
            throw new \moodle_exception('ltinotokenurl', 'local_oksigeniaayvurenda');
        }
        $registration = (new lti\store())->findRegistrationByIssuer($platform->issuer, $platform->clientid);
        $cache = new lti\cache();
        $connector = new LtiServiceConnector($cache, self::$client ?? new \core\http_client());
        $service = new \Packback\Lti1p3\LtiNamesRolesProvisioningService(
            $connector,
            $registration,
            ['context_memberships_url' => $course->membershipsurl, 'service_versions' => ['2.0']]
        );
        try {
            return $service->getMembers();
        } catch (\Throwable $e) {
            throw new \moodle_exception('ltimembersfailed', 'local_oksigeniaayvurenda', '', $e->getMessage());
        }
    }

    /**
     * Brings the members of a course: each with their account, bound to the platform's user, as a reader of the
     * entity; the students, with the group the teacher said for this school year, unless another source already gave
     * them one. From then on, the night keeps them up to date.
     *
     * @param \stdClass $course
     * @param string|null $group The group of its students; null, only readers.
     * @param int|null $now
     * @return array created, existing, grouped, skipped (inactive), total, overlimit (past the plan).
     */
    public static function bring(\stdClass $course, ?string $group, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $platform = $DB->get_record(self::T . 'ltiplatform', ['id' => $course->platformid], '*', MUST_EXIST);
        $entityid = (int) $platform->entityid;
        $group = $group !== null ? \core_text::substr(trim($group), 0, 255) : null;
        $group = $group === '' ? null : $group;
        plans::require($entityid, 'lti');
        $members = self::members($course);
        $year = years::around($entityid, $now);
        $auth = get_auth_plugin('lti');
        $stats = ['created' => 0, 'existing' => 0, 'grouped' => 0, 'skipped' => 0, 'total' => 0, 'overlimit' => 0];
        foreach ($members as $member) {
            if (empty($member['user_id']) || strcasecmp((string) ($member['status'] ?? 'Active'), 'Active') !== 0) {
                $stats['skipped']++;
                continue;
            }
            $stats['total']++;
            $type = self::type_of_roles((array) ($member['roles'] ?? []));
            $user = $auth->find_or_create_user_from_membership($member, $platform->issuer);
            $had = $DB->record_exists(self::T . 'reader', ['entityid' => $entityid, 'userid' => $user->id]);
            try {
                $readerid = self::reader($entityid, (int) $user->id, $type, $now);
            } catch (plan_full $e) {
                $stats['overlimit']++;
                continue;
            }
            $stats[$had ? 'existing' : 'created']++;
            if (
                $type === 'student' && $group !== null &&
                    !$DB->record_exists(self::T . 'member', ['readerid' => $readerid, 'yearid' => $year->id])
            ) {
                $DB->insert_record(self::T . 'member', (object) ['readerid' => $readerid, 'yearid' => $year->id,
                    'groupname' => $group, 'stage' => \local_oksigeniaayvurenda\core\readers\stage_guesser::guess($group),
                    'source' => 'lti', 'timecreated' => $now]);
                $stats['grouped']++;
            }
        }
        $DB->update_record(self::T . 'lticontext', (object) ['id' => $course->id, 'groupname' => $group,
            'lastsync' => $now, 'timemodified' => $now]);
        return $stats;
    }

    /**
     * Brings again the courses already brought once, for the night.
     *
     * @return array[] Course id => what bring() says, or the error.
     */
    public static function bring_all(): array {
        global $DB;
        $done = [];
        foreach ($DB->get_records_select(self::T . 'lticontext', 'lastsync IS NOT NULL') as $course) {
            try {
                $done[$course->id] = self::bring($course, $course->groupname);
            } catch (\moodle_exception $e) {
                $done[$course->id] = ['error' => $e->getMessage()];
            }
        }
        return $done;
    }

    /**
     * Removes a platform: whoever came from it keeps their reader and their history.
     *
     * @param int $entityid
     * @param int $id
     */
    public static function delete_platform(int $entityid, int $id): void {
        global $DB;
        if ($DB->record_exists(self::T . 'ltiplatform', ['id' => $id, 'entityid' => $entityid])) {
            $DB->delete_records(self::T . 'lticontext', ['platformid' => $id]);
        }
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
        return self::type_of_roles($launchdata[LtiConstants::ROLES] ?? []);
    }

    /**
     * The reader type some roles give, as a launch or the list of a course's members says them.
     *
     * @param string[] $roles
     * @return string
     */
    public static function type_of_roles(array $roles): string {
        $ends = array_map(fn($role) => preg_replace('/^.*[#\/]/', '', (string) $role), $roles);
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
     * @throws plan_full When a new reader does not fit in the centre's plan.
     */
    public static function reader(int $entityid, int $userid, string $type, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $reader = $DB->get_record(self::T . 'reader', ['entityid' => $entityid, 'userid' => $userid]);
        if (!$reader || $reader->status !== 'active') {
            plans::check($entityid, 'readers');
        }
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
        $entityid = (int) self::platform_of($launchdata)->entityid;
        self::reader($entityid, $userid, self::reader_type($launchdata));
        $custom = $launchdata[LtiConstants::CUSTOM] ?? [];
        $library = self::library_of($entityid, $custom);
        $recordid = (int) clean_param($custom['record'] ?? 0, PARAM_INT);
        if ($recordid && $DB->record_exists(self::T . 'record', ['id' => $recordid, 'entityid' => $entityid])) {
            return new \moodle_url('/local/oksigeniaayvurenda/record.php', ['library' => $library->slug, 'id' => $recordid]);
        }
        return new \moodle_url('/local/oksigeniaayvurenda/index.php', ['library' => $library->slug]);
    }

    /**
     * The platform a launch comes from.
     *
     * @param array $launchdata
     * @return \stdClass
     * @throws \moodle_exception When it is not registered here any more.
     */
    public static function platform_of(array $launchdata): \stdClass {
        $aud = $launchdata['aud'] ?? '';
        $clientid = is_array($aud) ? (string) ($launchdata['azp'] ?? reset($aud)) : (string) $aud;
        $platform = self::platform_by_issuer((string) ($launchdata['iss'] ?? ''), $clientid);
        if (!$platform) {
            throw new \moodle_exception('ltiunknown', 'local_oksigeniaayvurenda');
        }
        return $platform;
    }

    /**
     * The library a link names, if it is of the entity, or the entity's central one.
     *
     * @param int $entityid
     * @param array $custom The link's custom parameters.
     * @return \stdClass
     * @throws \moodle_exception When the entity has no library.
     */
    public static function library_of(int $entityid, array $custom): \stdClass {
        global $DB;
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
        return $library;
    }

    /**
     * The link a teacher puts in a course (deep linking): to a book, or to the library itself.
     *
     * @param \stdClass $library
     * @param \stdClass|null $record
     * @return \Packback\Lti1p3\DeepLinkResources\Resource
     */
    public static function link_to(\stdClass $library, ?\stdClass $record): \Packback\Lti1p3\DeepLinkResources\Resource {
        $resource = \Packback\Lti1p3\DeepLinkResources\Resource::new()->setType(LtiConstants::DL_RESOURCE_LINK_TYPE)
            ->setUrl(self::urls()['launch']);
        if ($record) {
            return $resource->setTitle(\core_text::substr($record->title, 0, 250))
                ->setCustomParams(['library' => $library->slug, 'record' => (string) $record->id]);
        }
        return $resource->setTitle(format_string($library->name))->setCustomParams(['library' => $library->slug]);
    }

    /**
     * The page that takes the chosen link back to the platform: the signed answer in a form that sends itself.
     *
     * @param LtiMessageLaunch $launch A deep linking launch.
     * @param \Packback\Lti1p3\DeepLinkResources\Resource $resource
     * @return string
     */
    public static function link_back(LtiMessageLaunch $launch, \Packback\Lti1p3\DeepLinkResources\Resource $resource): string {
        $return = (string) ($launch->getLaunchData()[LtiConstants::DL_DEEP_LINK_SETTINGS]['deep_link_return_url'] ?? '');
        $jwt = $launch->getDeepLink()->getResponseJwt([$resource]);
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . s(get_string(
            'ltipicksending',
            'local_oksigeniaayvurenda'
        )) . '</title></head><body><form id="ayv-back" method="post" action="' . s($return)
            . '"><input type="hidden" name="JWT" value="' . s($jwt) . '"><noscript><button type="submit">'
            . s(get_string('ltipicksend', 'local_oksigeniaayvurenda')) . '</button></noscript></form>'
            . '<script>document.getElementById("ayv-back").submit();</script></body></html>';
    }

    /**
     * A launch kept between pages (the deep linking one, while the teacher chooses), only for whoever started it.
     *
     * @param string $launchid
     * @param int $userid
     * @return LtiMessageLaunch|null
     */
    public static function kept(string $launchid, int $userid): ?LtiMessageLaunch {
        $cache = new lti\cache();
        if (!$cache->getLaunchData($launchid)) {
            return null;
        }
        $connector = new LtiServiceConnector($cache, self::$client ?? new \core\http_client());
        $launch = LtiMessageLaunch::fromCache($launchid, new lti\store(), $cache, new lti\cookie(), $connector);
        $data = $launch->getLaunchData();
        $bound = get_auth_plugin('lti')->get_user_binding((string) ($data['iss'] ?? ''), (string) ($data['sub'] ?? ''));
        return (int) $bound === $userid ? $launch : null;
    }
}
