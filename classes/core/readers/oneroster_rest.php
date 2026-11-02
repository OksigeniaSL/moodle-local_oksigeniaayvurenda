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
 * The answers of a OneRoster REST API, as the rows of its CSV files.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

/**
 * OneRoster (1EdTech) also has a REST API, in versions 1.1 and 1.2: users, orgs, classes and enrollments, in JSON, a
 * page at a time. This says where each collection is, which scope to ask for, signs a request the OAuth 1.0a way that
 * version 1.1 used (OAuth 2 needs no signature), and turns the JSON into the rows of users.csv, roles.csv, orgs.csv,
 * classes.csv and enrollments.csv, so that the reader of the files reads them as it reads an export. Plain PHP,
 * without Moodle.
 */
final class oneroster_rest {
    /** @var string[] The collections that matter, by the file whose rows they give. */
    public const COLLECTIONS = ['users.csv' => 'users', 'orgs.csv' => 'orgs', 'classes.csv' => 'classes',
        'enrollments.csv' => 'enrollments'];

    /** @var array<string, string> Where the rostering service is, below the address of the server, by version. */
    private const ROOTS = ['1.1' => '/ims/oneroster/v1p1', '1.2' => '/ims/oneroster/rostering/v1p2'];

    /** @var array<string, string> The scope that reads the rosters, by version (por verificar in each server). */
    private const SCOPES = ['1.1' => 'https://purl.imsglobal.org/spec/or/v1p1/scope/roster-core.readonly',
        '1.2' => 'https://purl.imsglobal.org/spec/or/v1p2/scope/roster-core.readonly'];

    /**
     * The address of a collection. An address that already says where the service is (…/ims/oneroster/…) is taken as
     * it is; else the path of the version goes after it.
     *
     * @param string $base What the centre wrote.
     * @param string $version 1.1 or 1.2.
     * @param string $collection users, orgs, classes or enrollments.
     * @return string
     */
    public static function url(string $base, string $version, string $collection): string {
        $base = rtrim(trim($base), '/');
        if (!str_contains($base, '/ims/oneroster')) {
            $base .= self::ROOTS[$version] ?? self::ROOTS['1.2'];
        }
        return $base . '/' . $collection;
    }

    /**
     * The scope to ask for, when the centre wrote none.
     *
     * @param string $version
     * @return string
     */
    public static function scope(string $version): string {
        return self::SCOPES[$version] ?? self::SCOPES['1.2'];
    }

    /**
     * The things of one page of an answer: {"users": […]} and the like.
     *
     * @param string $json
     * @param string $collection
     * @return array[]|null Null if it is not an answer of that collection.
     */
    public static function page(string $json, string $collection): ?array {
        $answer = json_decode($json, true);
        if (!is_array($answer) || !isset($answer[$collection]) || !is_array($answer[$collection])) {
            return null;
        }
        return array_values(array_filter($answer[$collection], 'is_array'));
    }

    /**
     * The rows of the files, from the things of each collection.
     *
     * @param array $things Collection (users, orgs, classes, enrollments) => its things.
     * @return array File name => its rows, in the columns of the CSV files.
     */
    public static function files(array $things): array {
        $users = [];
        $roles = [];
        foreach ($things['users'] ?? [] as $user) {
            $id = self::text($user['sourcedId'] ?? '');
            if ($id === '') {
                continue;
            }
            $users[] = [
                'sourcedId' => $id,
                'status' => self::text($user['status'] ?? ''),
                'enabledUser' => self::flag($user['enabledUser'] ?? true),
                'givenName' => self::text($user['givenName'] ?? ''),
                'familyName' => self::text($user['familyName'] ?? ''),
                // The binding of 1.2 says preferredFirstName; the files say preferredGivenName (both are read).
                'preferredGivenName' => self::text($user['preferredFirstName'] ?? $user['preferredGivenName'] ?? ''),
                'preferredFamilyName' => self::text($user['preferredLastName'] ?? $user['preferredFamilyName'] ?? ''),
                'email' => self::text($user['email'] ?? ''),
                'identifier' => self::text($user['identifier'] ?? ''),
                'username' => self::text($user['username'] ?? ''),
                'sms' => self::text($user['sms'] ?? ''),
                'phone' => self::text($user['phone'] ?? ''),
                // Version 1.1: one role and the orgs it is in.
                'role' => is_string($user['role'] ?? null) ? $user['role'] : '',
                'orgSourcedIds' => self::ids($user['orgs'] ?? []),
                'primaryOrgSourcedId' => self::id($user['primaryOrg'] ?? null),
                'agentSourcedIds' => self::ids($user['agents'] ?? []),
            ];
            // Version 1.2: each role, with its org.
            foreach (is_array($user['roles'] ?? null) ? $user['roles'] : [] as $role) {
                if (is_array($role) && self::text($role['role'] ?? '') !== '') {
                    $roles[] = ['userSourcedId' => $id, 'role' => self::text($role['role']),
                        'orgSourcedId' => self::id($role['org'] ?? null), 'roleType' => self::text($role['roleType'] ?? ''),
                        'status' => self::text($role['status'] ?? ''), 'endDate' => self::text($role['endDate'] ?? '')];
                }
            }
        }
        $orgs = [];
        foreach ($things['orgs'] ?? [] as $org) {
            $orgs[] = ['sourcedId' => self::text($org['sourcedId'] ?? ''), 'status' => self::text($org['status'] ?? ''),
                'name' => self::text($org['name'] ?? ''), 'type' => self::text($org['type'] ?? '')];
        }
        $classes = [];
        foreach ($things['classes'] ?? [] as $class) {
            $classes[] = ['sourcedId' => self::text($class['sourcedId'] ?? ''), 'status' => self::text($class['status'] ?? ''),
                'title' => self::text($class['title'] ?? ''), 'classType' => self::text($class['classType'] ?? ''),
                'schoolSourcedId' => self::id($class['school'] ?? null)];
        }
        $enrollments = [];
        foreach ($things['enrollments'] ?? [] as $row) {
            $enrollments[] = ['sourcedId' => self::text($row['sourcedId'] ?? ''), 'status' => self::text($row['status'] ?? ''),
                'role' => self::text($row['role'] ?? ''), 'primary' => self::flag($row['primary'] ?? false),
                'userSourcedId' => self::id($row['user'] ?? null), 'classSourcedId' => self::id($row['class'] ?? null),
                'schoolSourcedId' => self::id($row['school'] ?? null), 'endDate' => self::text($row['endDate'] ?? '')];
        }
        return ['users.csv' => $users, 'roles.csv' => $roles, 'orgs.csv' => $orgs, 'classes.csv' => $classes,
            'enrollments.csv' => $enrollments];
    }

    /**
     * The Authorization header of a request signed the OAuth 1.0a way (RFC 5849) with HMAC-SHA256, as OneRoster 1.1
     * asked, with the consumer's key and secret and no token.
     *
     * @param string $method
     * @param string $url With its query.
     * @param string $key
     * @param string $secret
     * @param string $nonce
     * @param int $timestamp
     * @return string
     */
    public static function oauth1(string $method, string $url, string $key, string $secret, string $nonce, int $timestamp): string {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = isset($parts['port']) && !in_array([$scheme, (int) $parts['port']], [['http', 80], ['https', 443]], true)
            ? ':' . $parts['port'] : '';
        $base = $scheme . '://' . strtolower($parts['host'] ?? '') . $port . ($parts['path'] ?? '/');
        $oauth = ['oauth_consumer_key' => $key, 'oauth_nonce' => $nonce, 'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => (string) $timestamp, 'oauth_version' => '1.0'];
        $params = [];
        foreach ($oauth as $name => $value) {
            $params[] = [rawurlencode($name), rawurlencode($value)];
        }
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            if ($pair !== '') {
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $params[] = [rawurlencode(urldecode($name)), rawurlencode(urldecode($value))];
            }
        }
        usort($params, fn($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
        $normal = implode('&', array_map(fn($p) => $p[0] . '=' . $p[1], $params));
        $text = strtoupper($method) . '&' . rawurlencode($base) . '&' . rawurlencode($normal);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha256', $text, rawurlencode($secret) . '&', true));
        return 'Authorization: OAuth ' . implode(', ', array_map(
            fn($name) => $name . '="' . rawurlencode($oauth[$name]) . '"',
            array_keys($oauth)
        ));
    }

    /**
     * The sourcedId of a reference ({"sourcedId": …}).
     *
     * @param mixed $ref
     * @return string
     */
    private static function id($ref): string {
        return is_array($ref) ? self::text($ref['sourcedId'] ?? '') : '';
    }

    /**
     * The sourcedIds of a list of references, with commas, as the files write them.
     *
     * @param mixed $refs
     * @return string
     */
    private static function ids($refs): string {
        return is_array($refs) ? implode(',', array_filter(array_map(fn($r) => self::id($r), $refs))) : '';
    }

    /**
     * A true or false, as the files write it (a JSON boolean, or the text that some servers send).
     *
     * @param mixed $value
     * @return string
     */
    private static function flag($value): string {
        return $value === true || strtolower(trim((string) $value)) === 'true' ? 'true' : 'false';
    }

    /**
     * A text, or nothing if it is not one.
     *
     * @param mixed $value
     * @return string
     */
    private static function text($value): string {
        return is_scalar($value) && !is_bool($value) ? trim((string) $value) : '';
    }
}
