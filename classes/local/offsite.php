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

use local_oksigeniaayvurenda\core\age;
use local_oksigeniaayvurenda\core\retention;
use local_oksigeniaayvurenda\core\s3;

/**
 * The copies of a centre out of the server: each night's copy, encrypted with the centre's public key (in the open
 * format of age), goes to the places the centre chose (a WebDAV folder: Nextcloud, ownCloud, a NAS…) and can be fetched
 * by the library's own computer with a link of its own. The server keeps only the public key, so neither it nor those
 * places can read the copies; whoever keeps the secret key opens them, also without Ayvurenda.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class offsite {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var array How many are kept at each place, by default: days, weeks and months. */
    public const KEEP = ['daily' => 7, 'weekly' => 4, 'monthly' => 12];

    /** @var int The places a centre may have. */
    public const PLACES = 5;

    /** @var callable|null For the tests: answers in place of the network (method, url, body or file) → [code, body]. */
    public static $network = null;

    /**
     * How the centre's copies leave the server.
     *
     * @param int $entityid
     * @return array recipient, keyed, places (id, kind, url, user, label), keep, pull (whether its link is on), last,
     *     failures.
     */
    public static function settings(int $entityid): array {
        global $DB;
        $all = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $mine = (array) ($all['offsite'] ?? []);
        return [
            'recipient' => (string) ($mine['recipient'] ?? ''),
            'keyed' => (int) ($mine['keyed'] ?? 0),
            'places' => array_values((array) ($mine['places'] ?? [])),
            'keep' => array_map('intval', ((array) ($mine['keep'] ?? [])) + self::KEEP),
            'pull' => (string) ($mine['pull'] ?? '') !== '',
            'last' => (array) ($mine['last'] ?? []),
            'failures' => (int) ($mine['failures'] ?? 0),
        ];
    }

    /**
     * Changes some of how the centre's copies leave the server.
     *
     * @param int $entityid
     * @param array $changes
     */
    private static function change(int $entityid, array $changes): void {
        global $DB;
        $all = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $all['offsite'] = array_merge((array) ($all['offsite'] ?? []), $changes);
        $DB->set_field(self::T . 'entity', 'settings', json_encode($all), ['id' => $entityid]);
    }

    /**
     * A new pair of keys for the centre: the public one stays, the secret one is given once and not kept.
     *
     * @param int $entityid
     * @param int|null $now
     * @return string The secret key, to give to whoever keeps it.
     */
    public static function new_key(int $entityid, ?int $now = null): string {
        $pair = age::keypair();
        self::change($entityid, ['recipient' => $pair['public'], 'keyed' => $now ?? time()]);
        return $pair['secret'];
    }

    /**
     * The centre's own public key (made with age-keygen or any tool of the format).
     *
     * @param int $entityid
     * @param string $recipient age1…
     * @param int|null $now
     * @return bool Whether it is one.
     */
    public static function use_key(int $entityid, string $recipient, ?int $now = null): bool {
        $recipient = trim($recipient);
        if (!age::is_recipient($recipient)) {
            return false;
        }
        self::change($entityid, ['recipient' => strtolower($recipient), 'keyed' => $now ?? time()]);
        return true;
    }

    /**
     * Adds a WebDAV folder where the copies go.
     *
     * @param int $entityid
     * @param string $url The folder, with https (or http, on a host of the server's network the site allows).
     * @param string $user
     * @param string $password Kept in the plugin's settings, never shown again.
     * @return string|null The id of the place; null if the address is not one.
     */
    public static function add_webdav(int $entityid, string $url, string $user, string $password): ?string {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (
            $host === '' || clean_param($url, PARAM_URL) !== $url
            || !($scheme === 'https' || ($scheme === 'http' && self::internal($host)))
        ) {
            return null;
        }
        $places = self::settings($entityid)['places'];
        if (count($places) >= self::PLACES) {
            return null;
        }
        $id = substr(bin2hex(random_bytes(6)), 0, 10);
        $places[] = ['id' => $id, 'kind' => 'webdav', 'url' => rtrim($url, '/') . '/', 'user' => trim($user),
            'label' => $host];
        set_config('offsite' . $entityid . '_' . $id, $password, 'local_oksigeniaayvurenda');
        self::change($entityid, ['places' => $places]);
        return $id;
    }

    /**
     * Adds an S3-compatible store (AWS, Backblaze, Wasabi, MinIO…) as a place for the copies.
     *
     * @param int $entityid
     * @param string $endpoint Where it answers, with https (or http, on a host of the server's network the site allows).
     * @param string $bucket
     * @param string $region
     * @param string $accesskey
     * @param string $secret Kept in the plugin's settings, never shown again.
     * @return string|null The id of the place; null if something is not one.
     */
    public static function add_s3(
        int $entityid,
        string $endpoint,
        string $bucket,
        string $region,
        string $accesskey,
        string $secret
    ): ?string {
        $endpoint = rtrim(trim($endpoint), '/');
        $bucket = trim($bucket, " /");
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (
            $host === '' || clean_param($endpoint, PARAM_URL) !== $endpoint
            || !preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket)
            || !($scheme === 'https' || ($scheme === 'http' && self::internal($host)))
        ) {
            return null;
        }
        $places = self::settings($entityid)['places'];
        if (count($places) >= self::PLACES) {
            return null;
        }
        $id = substr(bin2hex(random_bytes(6)), 0, 10);
        $places[] = ['id' => $id, 'kind' => 's3', 'url' => $endpoint . '/' . $bucket . '/', 'user' => trim($accesskey),
            'region' => trim($region) !== '' ? trim($region) : 'us-east-1', 'label' => $host . ' · ' . $bucket];
        set_config('offsite' . $entityid . '_' . $id, $secret, 'local_oksigeniaayvurenda');
        self::change($entityid, ['places' => $places]);
        return $id;
    }

    /**
     * Takes a place away, with its password.
     *
     * @param int $entityid
     * @param string $placeid
     */
    public static function remove(int $entityid, string $placeid): void {
        $places = array_values(array_filter(self::settings($entityid)['places'], fn($p) => $p['id'] !== $placeid));
        unset_config('offsite' . $entityid . '_' . $placeid, 'local_oksigeniaayvurenda');
        self::change($entityid, ['places' => $places]);
    }

    /**
     * How many copies are kept at each place.
     *
     * @param int $entityid
     * @param int $daily From 1 to 60.
     * @param int $weekly From 0 to 52.
     * @param int $monthly From 0 to 60.
     */
    public static function set_keep(int $entityid, int $daily, int $weekly, int $monthly): void {
        self::change($entityid, ['keep' => ['daily' => max(1, min(60, $daily)), 'weekly' => max(0, min(52, $weekly)),
            'monthly' => max(0, min(60, $monthly))]]);
    }

    /**
     * A new link for the library's computer to fetch the newest copy; the old one stops working.
     *
     * @param int $entityid
     * @return string The address, shown once.
     */
    public static function new_pull(int $entityid): string {
        $token = bin2hex(random_bytes(24));
        self::change($entityid, ['pull' => hash('sha256', $token)]);
        return (new \moodle_url('/local/oksigeniaayvurenda/pull.php', ['centre' => $entityid, 'key' => $token]))->out(false);
    }

    /**
     * Stops the link of the library's computer.
     *
     * @param int $entityid
     */
    public static function stop_pull(int $entityid): void {
        self::change($entityid, ['pull' => '']);
    }

    /**
     * Whether a link's key is the centre's.
     *
     * @param int $entityid
     * @param string $token
     * @return bool
     */
    public static function pull_allowed(int $entityid, string $token): bool {
        global $DB;
        $all = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $hash = (string) ($all['offsite']['pull'] ?? '');
        return $hash !== '' && $token !== '' && hash_equals($hash, hash('sha256', $token))
            && (string) ($all['offsite']['recipient'] ?? '') !== '';
    }

    /**
     * The name of a copy out of the server.
     *
     * @param int $entityid
     * @param int $time
     * @return string
     */
    public static function name(int $entityid, int $time): string {
        return 'ayvurenda-' . $entityid . '-' . date('Ymd', $time) . '.zip.age';
    }

    /**
     * A copy, encrypted with the centre's key, in a temporary file.
     *
     * @param int $entityid
     * @param \stored_file $copy
     * @return string|null Its path; null if the centre has no key.
     */
    public static function encrypted(int $entityid, \stored_file $copy): ?string {
        $recipient = self::settings($entityid)['recipient'];
        if ($recipient === '') {
            return null;
        }
        $dir = make_request_directory();
        $plain = $copy->copy_content_to_temp();
        $out = $dir . '/copy.zip.age';
        age::encrypt($plain, $out, $recipient);
        @unlink($plain);
        return $out;
    }

    /**
     * Sends a copy to every place, and keeps there only what the centre keeps. Two nights in a row that fail, and
     * whoever manages the centre is told.
     *
     * @param int $entityid
     * @param \stored_file $copy
     * @param int|null $now
     * @return array sent (places that have it), failed (labels of those that did not).
     */
    public static function send(int $entityid, \stored_file $copy, ?int $now = null): array {
        $now = $now ?? time();
        $settings = self::settings($entityid);
        if ($settings['recipient'] === '' || !$settings['places']) {
            return ['sent' => 0, 'failed' => []];
        }
        $path = self::encrypted($entityid, $copy);
        $name = self::name($entityid, $now);
        $sent = 0;
        $failed = [];
        foreach ($settings['places'] as $place) {
            $error = self::put($entityid, $place, $path, $name);
            if ($error === null) {
                $sent++;
                self::prune($entityid, $place, $settings['keep']);
            } else {
                $failed[] = $place['label'];
            }
        }
        $failures = $failed ? $settings['failures'] + 1 : 0;
        self::change($entityid, ['last' => ['time' => $now, 'sent' => $sent, 'failed' => $failed], 'failures' => $failures]);
        if ($failures === 2) {
            self::tell($entityid, $failed);
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Tries a place: a small file goes and is taken away.
     *
     * @param int $entityid
     * @param string $placeid
     * @return string|null Null if it works, else what went wrong.
     */
    public static function test(int $entityid, string $placeid): ?string {
        foreach (self::settings($entityid)['places'] as $place) {
            if ($place['id'] === $placeid) {
                $path = make_request_directory() . '/prueba.txt';
                file_put_contents($path, 'Ayvurenda');
                $error = self::put($entityid, $place, $path, 'ayvurenda-prueba.txt');
                if ($error === null) {
                    self::call($entityid, $place, 'DELETE', 'ayvurenda-prueba.txt');
                }
                return $error;
            }
        }
        return get_string('offsitenoplace', 'local_oksigeniaayvurenda');
    }

    /**
     * Puts a file in a place, making its folder if it is missing.
     *
     * @param int $entityid
     * @param array $place
     * @param string $path
     * @param string $name
     * @return string|null Null if it went, else what went wrong.
     */
    private static function put(int $entityid, array $place, string $path, string $name): ?string {
        [$code] = self::call($entityid, $place, 'PUT', $name, $path);
        if (($code === 404 || $code === 409) && ($place['kind'] ?? 'webdav') === 'webdav') {
            self::call($entityid, $place, 'MKCOL', '');
            [$code] = self::call($entityid, $place, 'PUT', $name, $path);
        }
        if ($code >= 200 && $code < 300) {
            return null;
        }
        $why = $code === 401 || $code === 403 ? 'offsitedenied' : ($code === 0 ? 'offsiteunreachable' : 'offsiteanswer');
        return get_string($why, 'local_oksigeniaayvurenda', $code);
    }

    /**
     * Leaves in a place only the copies of the centre the centre keeps.
     *
     * @param int $entityid
     * @param array $place
     * @param array $keep daily, weekly, monthly.
     */
    private static function prune(int $entityid, array $place, array $keep): void {
        $s3 = ($place['kind'] ?? 'webdav') === 's3';
        [$code, $body] = $s3 ? self::call($entityid, $place, 'LIST', '') : self::call(
            $entityid,
            $place,
            'PROPFIND',
            '',
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>'
        );
        if ($code !== ($s3 ? 200 : 207)) {
            return;
        }
        $days = [];
        preg_match_all(
            $s3 ? '#<Key>([^<]+)</Key>#' : '#<(?:[a-z0-9]+:)?href>([^<]+)</(?:[a-z0-9]+:)?href>#i',
            (string) $body,
            $matches
        );
        foreach ($matches[1] as $href) {
            $file = rawurldecode(basename(html_entity_decode($href)));
            if (preg_match('/^ayvurenda-' . $entityid . '-(\d{8})\.zip\.age$/', $file, $m)) {
                $days[$m[1]] = $file;
            }
        }
        $kept = array_flip(retention::keep(array_keys($days), $keep['daily'], $keep['weekly'], $keep['monthly']));
        foreach ($days as $day => $file) {
            if (!isset($kept[$day])) {
                self::call($entityid, $place, 'DELETE', $file);
            }
        }
    }

    /**
     * One question to a WebDAV place.
     *
     * @param int $entityid
     * @param array $place
     * @param string $method PUT, MKCOL, PROPFIND or DELETE.
     * @param string $name A file in the folder, or '' for the folder.
     * @param string $payload A file's path (PUT) or the body (PROPFIND).
     * @return array The answer's code and body.
     */
    private static function call(int $entityid, array $place, string $method, string $name, string $payload = ''): array {
        global $CFG;
        $url = $place['url'] . ($name === '' ? '' : rawurlencode($name));
        if (self::$network) {
            return (self::$network)($method, $url, $payload);
        }
        require_once($CFG->libdir . '/filelib.php');
        if (($place['kind'] ?? 'webdav') === 's3') {
            return self::call_s3($entityid, $place, $method, $name, $payload);
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Moodle's HTTP security stands, except for a host of the server's network the administrator allowed.
        $curl = new \curl(['ignoresecurity' => self::internal($host)]);
        $password = (string) get_config('local_oksigeniaayvurenda', 'offsite' . $entityid . '_' . $place['id']);
        // The credentials go with each request: Moodle's delete() and put() put an anonymous user otherwise.
        $auth = ['CURLOPT_USERPWD' => $place['user'] . ':' . $password, 'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
            'CURLOPT_TIMEOUT' => 600, 'CURLOPT_CONNECTTIMEOUT' => 15];
        if ($method === 'PUT') {
            $body = $curl->put($url, ['file' => $payload], $auth);
        } else if ($method === 'DELETE') {
            $body = $curl->delete($url, [], $auth);
        } else {
            $curl->setHeader(['Depth: 1', 'Content-Type: application/xml; charset=utf-8']);
            $body = $curl->post($url, $payload, $auth + ['CURLOPT_CUSTOMREQUEST' => $method]);
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), (string) $body];
    }

    /**
     * One question to an S3-compatible place, signed.
     *
     * @param int $entityid
     * @param array $place
     * @param string $method PUT, LIST or DELETE.
     * @param string $name A file in the bucket.
     * @param string $path A file's path (PUT).
     * @return array The answer's code and body.
     */
    private static function call_s3(int $entityid, array $place, string $method, string $name, string $path): array {
        $url = $place['url'] . ($name === '' ? '' : rawurlencode($name)) . ($method === 'LIST' ? '?list-type=2' : '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $curl = new \curl(['ignoresecurity' => self::internal($host)]);
        $secret = (string) get_config('local_oksigeniaayvurenda', 'offsite' . $entityid . '_' . $place['id']);
        $hash = $method === 'PUT' ? hash_file('sha256', $path) : hash('sha256', '');
        $signed = s3::headers(
            $method === 'LIST' ? 'GET' : $method,
            $url,
            (string) $place['region'],
            (string) $place['user'],
            $secret,
            $hash,
            time()
        );
        $curl->setHeader(array_map(fn($header, $value) => $header . ': ' . $value, array_keys($signed), $signed));
        $options = ['CURLOPT_TIMEOUT' => 600, 'CURLOPT_CONNECTTIMEOUT' => 15];
        if ($method === 'PUT') {
            $body = $curl->put($url, ['file' => $path], $options);
        } else if ($method === 'DELETE') {
            $body = $curl->delete($url, [], $options);
        } else {
            $body = $curl->get($url, [], $options);
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), (string) $body];
    }

    /**
     * Whether a host is one of the server's network the site's administrator allowed.
     *
     * @param string $host
     * @return bool
     */
    private static function internal(string $host): bool {
        $allowed = array_filter(array_map('trim', explode(',', strtolower((string) get_config(
            'local_oksigeniaayvurenda',
            'internalhosts'
        )))));
        return $host !== '' && in_array($host, $allowed, true);
    }

    /**
     * Tells whoever manages the centre that its copy has not left the server for two nights.
     *
     * @param int $entityid
     * @param string[] $failed
     */
    private static function tell(int $entityid, array $failed): void {
        global $DB;
        $library = $DB->get_records(self::T . 'library', ['entityid' => $entityid, 'parentid' => null], 'id', 'id, slug', 0, 1);
        $library = $library ? reset($library) : null;
        $url = $library ? (new \moodle_url('/local/oksigeniaayvurenda/copies.php', ['library' => $library->slug]))->out(false)
            : '';
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        $users = get_users_by_capability(access::context($entityid), 'local/oksigeniaayvurenda:manage', 'u.*');
        foreach ($users as $user) {
            notices::send(
                'copyfailed',
                $user,
                $str('offsitefailedsubject'),
                $str('offsitefailedbody', implode(', ', $failed)),
                $url,
                $str('copiesheading'),
                '',
                [
                    'library' => mail::main_library($entityid),
                    'entityid' => $entityid,
                    'heading' => $str('offsitefailedsubject'),
                    'lead' => [$str('offsitefailedbody', implode(', ', $failed))],
                    'button' => $url !== '' ? ['url' => $url, 'text' => $str('offsitefailedbutton')] : null,
                    'why' => $str('mailwhystaff'),
                    'prefs' => true,
                ]
            );
        }
    }
}
