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
 * Google Drive as a place for the copies: a centre connects its own account, and only what this creates can be seen.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * With the narrowest permission Google has (drive.file): this sees only the folder and files it made itself, never the rest
 * of someone's Drive. It keeps only a refresh token, encrypted, and the copies it sends are encrypted with the centre's own
 * key, so Google cannot read them and neither can this site. Google's addresses are fixed; the settings of a test site may
 * point them elsewhere from its config.php.
 */
final class gdrive {
    /** @var string What is asked for: only what this application creates. */
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    /** @var string The type of a folder, for Drive. */
    private const FOLDER = 'application/vnd.google-apps.folder';

    /** @var string Why the last connection failed: '' or 'api', when the Drive API is off in the client's project. */
    public static $why = '';

    /** @var callable|null For the tests: answers in place of the network (method, url, headers, body) → [code, body, headers]. */
    public static $network = null;

    /**
     * Whether the site has a client of Google's to use.
     *
     * @return bool
     */
    public static function configured(): bool {
        return self::client() !== null;
    }

    /**
     * The client: the one in the plugin's settings or, without it, the Google service of the site's own Moodle (OAuth 2
     * services), which asks for the same permission and comes back through the same page.
     *
     * @return array|null Its id and its secret.
     */
    private static function client(): ?array {
        $id = trim((string) get_config('local_oksigeniaayvurenda', 'driveclientid'));
        $secret = trim((string) get_config('local_oksigeniaayvurenda', 'driveclientsecret'));
        if ($id !== '' && $secret !== '') {
            return [$id, $secret];
        }
        foreach (\core\oauth2\api::get_all_issuers(true) as $issuer) {
            $google = $issuer->get('servicetype') === 'google'
                || strpos((string) $issuer->get('baseurl'), 'accounts.google.com') !== false;
            if ($google && $issuer->get('enabled') && $issuer->is_configured()) {
                return [trim((string) $issuer->get('clientid')), trim((string) $issuer->get('clientsecret'))];
            }
        }
        return null;
    }

    /**
     * Where Google's answers go: Moodle's own page for it, which brings the code back to the page in the state.
     *
     * @return string
     */
    public static function callback(): string {
        return (new \moodle_url('/admin/oauth2callback.php'))->out(false);
    }

    /**
     * An address of Google's, or the one a test site gives in its config.php.
     *
     * @param string $which auth (seen by the browser), token, revoke, files or upload.
     * @return string
     */
    private static function address(string $which): string {
        global $CFG;
        $real = ['auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
            'revoke' => 'https://oauth2.googleapis.com/revoke', 'files' => 'https://www.googleapis.com/drive/v3/files',
            'upload' => 'https://www.googleapis.com/upload/drive/v3/files'];
        return (string) ($CFG->local_oksigeniaayvurenda_google[$which] ?? $real[$which]);
    }

    /**
     * Where to send someone to give their permission. Offline, and asking each time, or Google gives no refresh token to
     * whoever gave permission before.
     *
     * @param \moodle_url $back The page to come back to, with the session's key: Moodle's page checks it.
     * @return string
     */
    public static function auth_url(\moodle_url $back): string {
        return self::address('auth') . '?' . http_build_query([
            'client_id' => (self::client() ?? [''])[0],
            'redirect_uri' => self::callback(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'false',
            'state' => $back->out(false),
        ], '', '&');
    }

    /**
     * What comes back from Google for a code: the refresh token.
     *
     * @param string $code
     * @return string|null
     */
    public static function exchange(string $code): ?string {
        [$status, $body] = self::token_request(['code' => $code, 'grant_type' => 'authorization_code',
            'redirect_uri' => self::callback()]);
        $token = $status === 200 ? (json_decode($body, true)['refresh_token'] ?? '') : '';
        return $token !== '' ? (string) $token : null;
    }

    /**
     * An access token for a refresh token, remembered for this request.
     *
     * @param string $refresh
     * @return string|null Null when Google no longer accepts it: permission was taken away.
     */
    private static function access(string $refresh): ?string {
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $key = 'drive_' . sha1($refresh);
        $token = $cache->get($key);
        if ($token !== false) {
            return $token;
        }
        [$status, $body] = self::token_request(['refresh_token' => $refresh, 'grant_type' => 'refresh_token']);
        $token = $status === 200 ? (string) (json_decode($body, true)['access_token'] ?? '') : '';
        if ($token === '') {
            return null;
        }
        $cache->set($key, $token);
        return $token;
    }

    /**
     * Asks Google's token address, with the site's client.
     *
     * @param array $fields
     * @return array The answer's code, body and headers.
     */
    private static function token_request(array $fields): array {
        [$id, $secret] = self::client() ?? ['', ''];
        return self::http('POST', self::address('token'), ['Content-Type: application/x-www-form-urlencoded'], http_build_query(
            $fields + ['client_id' => $id, 'client_secret' => $secret],
            '',
            '&'
        ));
    }

    /**
     * Makes the folder of the copies.
     *
     * @param string $refresh
     * @param string $name
     * @return string|null Its id.
     */
    public static function make_folder(string $refresh, string $name): ?string {
        $token = self::access($refresh);
        if (!$token) {
            return null;
        }
        [$status, $body] = self::http(
            'POST',
            self::address('files') . '?fields=id',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8'],
            json_encode(['name' => $name, 'mimeType' => self::FOLDER])
        );
        $id = $status === 200 ? (string) (json_decode($body, true)['id'] ?? '') : '';
        // A client made for signing in may come from a project where nobody turned the Drive API on.
        self::$why = $status === 403 && preg_match('/accessNotConfigured|SERVICE_DISABLED/', $body) ? 'api' : '';
        return $id !== '' ? $id : null;
    }

    /**
     * Sends a file to a folder, in two steps, so that a large one need not fit in memory.
     *
     * @param string $refresh
     * @param string $folder
     * @param string $name
     * @param string $path
     * @return array The answer's code and body.
     */
    public static function upload(string $refresh, string $folder, string $name, string $path): array {
        $token = self::access($refresh);
        if (!$token) {
            return [401, ''];
        }
        [$status, $body, $headers] = self::http(
            'POST',
            self::address('upload') . '?uploadType=resumable&fields=id',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: application/octet-stream', 'X-Upload-Content-Length: ' . filesize($path)],
            json_encode(['name' => $name, 'parents' => [$folder]])
        );
        $headers = array_change_key_case($headers, CASE_LOWER);
        $where = (string) ($headers['location'] ?? '');
        if ($status !== 200 || $where === '') {
            return [$status, $body];
        }
        [$status, $body] = self::http('PUT', $where, ['Content-Type: application/octet-stream'], ['file' => $path]);
        return [$status, $body];
    }

    /**
     * The files of a folder that this made.
     *
     * @param string $refresh
     * @param string $folder
     * @return array|null Name => id; null if it cannot be known.
     */
    public static function files(string $refresh, string $folder): ?array {
        $token = self::access($refresh);
        if (!$token) {
            return null;
        }
        $query = http_build_query(['q' => "'" . str_replace("'", "\\'", $folder) . "' in parents and trashed = false",
            'fields' => 'files(id,name)', 'pageSize' => 1000], '', '&');
        [$status, $body] = self::http('GET', self::address('files') . '?' . $query, ['Authorization: Bearer ' . $token]);
        if ($status !== 200) {
            return null;
        }
        $files = [];
        foreach ((array) (json_decode($body, true)['files'] ?? []) as $file) {
            $files[(string) $file['name']] = (string) $file['id'];
        }
        return $files;
    }

    /**
     * Deletes a file this made.
     *
     * @param string $refresh
     * @param string $id
     * @return int The answer's code.
     */
    public static function delete(string $refresh, string $id): int {
        $token = self::access($refresh);
        if (!$token) {
            return 401;
        }
        return self::http('DELETE', self::address('files') . '/' . rawurlencode($id), ['Authorization: Bearer ' . $token])[0];
    }

    /**
     * Gives the permission back. What is already in the folder stays: it is the centre's.
     *
     * @param string $refresh
     */
    public static function revoke(string $refresh): void {
        self::http('POST', self::address('revoke'), ['Content-Type: application/x-www-form-urlencoded'], http_build_query(
            ['token' => $refresh],
            '',
            '&'
        ));
    }

    /**
     * One request.
     *
     * @param string $method
     * @param string $url
     * @param string[] $headers
     * @param string|array $body Text, or ['file' => path] to send a file.
     * @return array The code, the body and the headers of the answer.
     */
    private static function http(string $method, string $url, array $headers, $body = ''): array {
        global $CFG;
        if (self::$network) {
            return (self::$network)($method, $url, $headers, $body) + [2 => []];
        }
        require_once($CFG->libdir . '/filelib.php');
        // Moodle's HTTP security stands, except for the stand-in of a test site, named in its own config.php.
        $curl = new \curl(['ignoresecurity' => !empty($CFG->local_oksigeniaayvurenda_google)]);
        $curl->setHeader($headers);
        $options = offsite::limits($method, is_array($body) ? $body['file'] : '');
        if ($method === 'PUT') {
            $answer = $curl->put($url, $body, $options);
        } else if ($method === 'GET') {
            $answer = $curl->get($url, [], $options);
        } else if ($method === 'DELETE') {
            $answer = $curl->delete($url, [], $options);
        } else {
            $answer = $curl->post($url, $body, $options);
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), (string) $answer, (array) $curl->getResponse()];
    }
}
