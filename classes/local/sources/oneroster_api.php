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
 * Readers from the OneRoster REST API of a management program.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\oneroster as roster;
use local_oksigeniaayvurenda\core\readers\oneroster_rest as rest;
use local_oksigeniaayvurenda\local\plans;
use local_oksigeniaayvurenda\local\secrets;

/**
 * An optional connector: a management program that speaks OneRoster 1.1 or 1.2 over its REST API gives the readers
 * of a centre, each student with their homeroom group and each homeroom with its tutor, and the night keeps them up to
 * date, as an export of the same program would. It reads only. It enters with OAuth 2 (client credentials, what 1.2
 * asks) or with the OAuth 1.0a signature of 1.1. The readers are the same as a OneRoster zip brings (the same keys):
 * a centre may start with a zip and go on with the API. The secret lives in the plugin's configuration, encrypted, not
 * with the entity, so the daily copies never carry it.
 */
final class oneroster_api {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string Where its settings are, in the entity's. */
    public const KEY = 'onerosterapi';

    /** @var int Things asked for in each page. */
    private const LIMIT = 100;

    /** @var int Pages of a collection at most: a hundred thousand things. */
    private const PAGES = 1000;

    /** @var callable|null How to make a request; tests put their own. Given method, address, headers and body. */
    public static $fetcher = null;

    /**
     * The settings of an entity, without the secret.
     *
     * @param int $entityid
     * @return array enabled, url, version (1.1 or 1.2), auth (oauth2 or oauth1), tokenurl, clientid, scope, school,
     *     families, schools (sourcedId => name, from the last test), hassecret.
     */
    public static function settings(int $entityid): array {
        $all = self::all($entityid);
        return ['enabled' => !empty($all['enabled']), 'url' => (string) ($all['url'] ?? ''),
            'version' => ($all['version'] ?? '1.2') === '1.1' ? '1.1' : '1.2',
            'auth' => ($all['auth'] ?? 'oauth2') === 'oauth1' ? 'oauth1' : 'oauth2',
            'tokenurl' => (string) ($all['tokenurl'] ?? ''), 'clientid' => (string) ($all['clientid'] ?? ''),
            'scope' => (string) ($all['scope'] ?? ''), 'school' => (string) ($all['school'] ?? ''),
            'families' => !empty($all['families']), 'schools' => is_array($all['schools'] ?? null) ? $all['schools'] : [],
            'hassecret' => ($all['secret'] ?? '') !== ''];
    }

    /**
     * Saves the settings. A secret left empty keeps the one there was, unless an address or the way of entering
     * changed: then it is forgotten (else whoever may change the settings could send the centre's secret to a server of
     * theirs).
     *
     * @param int $entityid
     * @param array $settings Any of: enabled, url, version, auth, tokenurl, clientid, scope, secret, school, families.
     */
    public static function save(int $entityid, array $settings): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $before = $entity[self::KEY] ?? [];
        $now = $before;
        foreach (['url', 'tokenurl'] as $name) {
            if (array_key_exists($name, $settings)) {
                $now[$name] = rtrim(trim((string) $settings[$name]), '/');
            }
        }
        if (array_key_exists('version', $settings)) {
            $now['version'] = $settings['version'] === '1.1' ? '1.1' : '1.2';
        }
        if (array_key_exists('auth', $settings)) {
            $now['auth'] = $settings['auth'] === 'oauth1' ? 'oauth1' : 'oauth2';
        }
        foreach (['clientid', 'scope', 'school'] as $name) {
            if (array_key_exists($name, $settings)) {
                $now[$name] = trim((string) $settings[$name]);
            }
        }
        foreach (['enabled', 'families'] as $name) {
            if (array_key_exists($name, $settings)) {
                $now[$name] = !empty($settings[$name]);
            }
        }
        foreach (['url', 'tokenurl', 'auth'] as $name) {
            if ($before && ($before[$name] ?? '') !== ($now[$name] ?? '')) {
                unset_config('onerostersecret' . $entityid, 'local_oksigeniaayvurenda');
                break;
            }
        }
        $entity[self::KEY] = $now;
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        if (trim((string) ($settings['secret'] ?? '')) !== '') {
            secrets::put('onerostersecret' . $entityid, trim($settings['secret']));
        }
        \local_oksigeniaayvurenda\event\connector_changed::fire($entityid, null, ['kind' => 'oneroster']);
    }

    /**
     * What the server has now, as rows of the files, for the reader of OneRoster.
     *
     * @param int $entityid
     * @return array File name => its rows.
     * @throws \moodle_exception When it does not answer well.
     */
    public static function files(int $entityid): array {
        $all = self::all($entityid);
        $version = ($all['version'] ?? '1.2') === '1.1' ? '1.1' : '1.2';
        if (($all['url'] ?? '') === '') {
            throw new \moodle_exception('cifranourl', 'local_oksigeniaayvurenda');
        }
        $token = ($all['auth'] ?? 'oauth2') === 'oauth1' ? null : self::token($all, $version);
        $things = [];
        foreach (rest::COLLECTIONS as $collection) {
            $things[$collection] = self::collection($all, $version, $collection, $token);
        }
        return rest::files($things);
    }

    /**
     * The readers the server gives now, for the school chosen and with the families if chosen.
     *
     * @param int $entityid
     * @param array|null $files What files() gave, if it was asked already.
     * @return array As the reader of OneRoster gives them.
     */
    public static function readers(int $entityid, ?array $files = null): array {
        $settings = self::settings($entityid);
        return roster::readers($files ?? self::files($entityid), $settings['school'], $settings['families'], time());
    }

    /**
     * Asks the server and remembers its schools, for the centre to choose its own.
     *
     * @param int $entityid
     * @return array readers, groups, families, schools: what is there now; nothing is changed in the library.
     */
    public static function test(int $entityid): array {
        global $DB;
        \local_oksigeniaayvurenda\event\connector_tried::fire($entityid, null, ['kind' => 'oneroster']);
        $settings = self::settings($entityid);
        $files = self::files($entityid);
        $everyone = roster::readers($files, $settings['school'], true, time());
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity[self::KEY]['schools'] = array_slice($everyone['schools'], 0, 200, true);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        $readers = array_filter($everyone['readers'], fn($r) => $r['type'] !== 'family');
        return ['readers' => count($readers), 'families' => count($everyone['readers']) - count($readers),
            'groups' => count(array_unique(array_filter(array_column($readers, 'group')))),
            'schools' => count($everyone['schools'])];
    }

    /**
     * Brings the readers: those who are there, with their group and the tutor of each group; those who are not, gone.
     *
     * @param int $entityid
     * @param int|null $now
     * @param bool $byhand From the connector: from then on, the night keeps them up to date.
     * @return array created, updated, linked, total, overlimit, left, tutors.
     */
    public static function sync(int $entityid, ?int $now = null, bool $byhand = false): array {
        global $DB;
        $now = $now ?? time();
        $settings = self::settings($entityid);
        $found = roster::readers(self::files($entityid), $settings['school'], $settings['families'], $now);
        // A server gives everyone it has: whoever came from OneRoster and is not there now has left.
        $found['mode'] = 'bulk';
        if ($byhand) {
            $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
            $entity[self::KEY]['brought'] = $entity[self::KEY]['brought'] ?? $now;
            $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        }
        return oneroster::load($entityid, $found, $now);
    }

    /**
     * Whether an entity uses the connector.
     *
     * @param int $entityid
     * @return bool
     */
    public static function enabled(int $entityid): bool {
        return !empty(self::all($entityid)['enabled']) && plans::allows($entityid, 'connectors');
    }

    /**
     * Whether it is connected: an address and its secret.
     *
     * @param int $entityid
     * @return bool
     */
    public static function connected(int $entityid): bool {
        $settings = self::settings($entityid);
        return $settings['url'] !== '' && $settings['hassecret'] && $settings['clientid'] !== '';
    }

    /**
     * Whether someone has brought the readers by hand once: the night only keeps them up to date after that.
     *
     * @param int $entityid
     * @return bool
     */
    public static function brought(int $entityid): bool {
        return !empty(self::all($entityid)['brought']);
    }

    /**
     * An OAuth 2 token, by client credentials with HTTP Basic, as 1EdTech's security framework asks.
     *
     * @param array $all
     * @param string $version
     * @return string
     * @throws \moodle_exception When the server does not give one.
     */
    private static function token(array $all, string $version): string {
        $url = ($all['tokenurl'] ?? '') !== '' ? $all['tokenurl'] : rtrim($all['url'], '/') . '/token';
        $basic = base64_encode(rawurlencode($all['clientid'] ?? '') . ':' . rawurlencode($all['secret'] ?? ''));
        $answer = self::request('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $basic,
        ], http_build_query(['grant_type' => 'client_credentials',
            'scope' => ($all['scope'] ?? '') !== '' ? $all['scope'] : rest::scope($version)], '', '&'));
        $token = json_decode($answer, true)['access_token'] ?? '';
        if (!is_string($token) || $token === '') {
            throw new \moodle_exception('onerosternotoken', 'local_oksigeniaayvurenda');
        }
        return $token;
    }

    /**
     * Every thing of a collection, a page at a time, until a page comes empty or brings nothing new (a server that
     * gives fewer than asked per page is read whole; one that ignores where to start does not go round forever).
     *
     * @param array $all
     * @param string $version
     * @param string $collection
     * @param string|null $token Null to sign each request the OAuth 1.0a way.
     * @return array[]
     * @throws \moodle_exception When a page does not come well.
     */
    private static function collection(array $all, string $version, string $collection, ?string $token): array {
        $url = rest::url($all['url'], $version, $collection);
        $things = [];
        $offset = 0;
        for ($page = 0; $page < self::PAGES; $page++) {
            $address = $url . '?limit=' . self::LIMIT . '&offset=' . $offset;
            $headers = ['Accept: application/json', $token !== null ? 'Authorization: Bearer ' . $token
                : rest::oauth1('GET', $address, $all['clientid'] ?? '', $all['secret'] ?? '', random_string(24), time())];
            $list = rest::page(self::request('GET', $address, $headers), $collection);
            if ($list === null) {
                throw new \moodle_exception('onerosterbadanswer', 'local_oksigeniaayvurenda', '', null, $collection);
            }
            $new = 0;
            foreach ($list as $thing) {
                $id = (string) ($thing['sourcedId'] ?? '');
                if ($id !== '' && !isset($things[$id])) {
                    $things[$id] = $thing;
                    $new++;
                }
            }
            if (!$list || !$new) {
                break;
            }
            $offset += count($list);
        }
        return array_values($things);
    }

    /**
     * All the settings, the secret included.
     *
     * @param int $entityid
     * @return array
     */
    private static function all(int $entityid): array {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return ($entity[self::KEY] ?? []) + ['secret' => secrets::get('onerostersecret' . $entityid)];
    }

    /**
     * A request to the server.
     *
     * @param string $method
     * @param string $url
     * @param string[] $headers
     * @param string $body
     * @return string
     * @throws \moodle_exception When it does not answer well.
     */
    private static function request(string $method, string $url, array $headers, string $body = ''): string {
        global $CFG;
        if (self::$fetcher) {
            $answer = (self::$fetcher)($method, $url, $headers, $body);
            if ($answer === null) {
                throw new \moodle_exception('onerosterbadanswer', 'local_oksigeniaayvurenda');
            }
            return $answer;
        }
        require_once($CFG->libdir . '/filelib.php');
        // Moodle's HTTP security stands, except for a host of the site's own network that its administrator allowed in
        // the plugin's settings: nobody else can point the connector inside.
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = array_filter(array_map('trim', explode(',', strtolower((string) get_config(
            'local_oksigeniaayvurenda',
            'internalhosts'
        )))));
        $curl = new \curl(['ignoresecurity' => $host !== '' && in_array($host, $allowed, true)]);
        $curl->setHeader($headers);
        $curl->setopt(['CURLOPT_TIMEOUT' => 60, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $answer = $method === 'POST' ? $curl->post($url, $body) : $curl->get($url);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if (in_array($code, [400, 401, 403], true)) {
            // It is there, and it does not let Ayvurenda in: the identifier or the secret, or what they may read.
            throw new \moodle_exception('onerosternotoken', 'local_oksigeniaayvurenda', '', null, 'HTTP ' . $code);
        }
        if ($curl->get_errno() || $code !== 200 || !is_string($answer)) {
            throw new \moodle_exception('onerosterbadanswer', 'local_oksigeniaayvurenda', '', null, 'HTTP ' . $code);
        }
        return $answer;
    }
}
