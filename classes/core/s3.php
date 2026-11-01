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

namespace local_oksigeniaayvurenda\core;

/**
 * Signs a request to an S3-compatible store (AWS, Backblaze, Wasabi, MinIO…) with Signature Version 4. Plain PHP, without Moodle.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class s3 {
    /**
     * The headers a request needs.
     *
     * @param string $method
     * @param string $url
     * @param string $region
     * @param string $accesskey
     * @param string $secret
     * @param string $payloadhash The SHA-256 of the body, in hexadecimal.
     * @param int $now
     * @param array $extra More headers to sign, name => value.
     * @return array Name => value, with Authorization.
     */
    public static function headers(
        string $method,
        string $url,
        string $region,
        string $accesskey,
        string $secret,
        string $payloadhash,
        int $now,
        array $extra = []
    ): array {
        $parts = parse_url($url);
        $host = strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $stamp = gmdate('Ymd\THis\Z', $now);
        $day = gmdate('Ymd', $now);
        $headers = array_change_key_case($extra)
            + ['host' => $host, 'x-amz-content-sha256' => $payloadhash, 'x-amz-date' => $stamp];
        ksort($headers);
        $canonicalheaders = '';
        foreach ($headers as $name => $value) {
            $canonicalheaders .= $name . ':' . trim((string) $value) . "\n";
        }
        $signed = implode(';', array_keys($headers));
        $request = implode("\n", [
            $method,
            self::path((string) ($parts['path'] ?? '/')),
            self::query((string) ($parts['query'] ?? '')),
            $canonicalheaders,
            $signed,
            $payloadhash,
        ]);
        $scope = $day . '/' . $region . '/s3/aws4_request';
        $tosign = implode("\n", ['AWS4-HMAC-SHA256', $stamp, $scope, hash('sha256', $request)]);
        $key = 'AWS4' . $secret;
        foreach ([$day, $region, 's3', 'aws4_request'] as $piece) {
            $key = hash_hmac('sha256', $piece, $key, true);
        }
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $accesskey . '/' . $scope . ', SignedHeaders=' . $signed
            . ', Signature=' . hash_hmac('sha256', $tosign, $key);
        return $headers;
    }

    /**
     * A path as it is signed: each piece encoded once.
     *
     * @param string $path
     * @return string
     */
    private static function path(string $path): string {
        return implode('/', array_map(fn($piece) => rawurlencode(rawurldecode($piece)), explode('/', $path)));
    }

    /**
     * A query as it is signed: its pairs in order, each encoded.
     *
     * @param string $query
     * @return string
     */
    private static function query(string $query): string {
        if ($query === '') {
            return '';
        }
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[rawurlencode(rawurldecode($name))] = rawurlencode(rawurldecode($value));
        }
        ksort($pairs);
        return implode('&', array_map(fn($name, $value) => $name . '=' . $value, array_keys($pairs), $pairs));
    }
}
