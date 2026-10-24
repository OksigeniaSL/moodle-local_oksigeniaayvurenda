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
 * Readers from Cifra, a school management program.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local\sources;

use local_oksigeniaayvurenda\core\readers\cifra_people;

/**
 * An optional connector: the readers of a centre that keeps its students in Cifra, each with its group, kept up to date
 * every night like the cohorts. It reads only. Either straight from Cifra's public API, with the centre's own client
 * and secret, or through a bridge that holds them and gives a key. Who leaves Cifra stops being an active reader, with
 * the history kept. The key and the secret live in the plugin's configuration, not with the entity, so the daily copies
 * never carry them.
 */
final class cifra {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var callable|null How to make a request; tests put their own. Given method, address, headers and body. */
    public static $fetcher = null;

    /**
     * The connector's settings of an entity, without its secrets.
     *
     * @param int $entityid
     * @return array enabled, url, mode (key or oauth), example, haskey, hassecret, clientid.
     */
    public static function settings(int $entityid): array {
        $all = self::all($entityid);
        return ['enabled' => !empty($all['enabled']), 'url' => (string) ($all['url'] ?? ''),
            'mode' => ($all['mode'] ?? 'key') === 'oauth' ? 'oauth' : 'key', 'example' => !empty($all['example']),
            'haskey' => ($all['key'] ?? '') !== '', 'hassecret' => ($all['secret'] ?? '') !== '',
            'clientid' => (string) ($all['clientid'] ?? '')];
    }

    /**
     * Saves the settings; a key or secret left empty keeps the one there was.
     *
     * @param int $entityid
     * @param array $settings enabled, url, mode, example, key, clientid, secret.
     */
    public static function save(int $entityid, array $settings): void {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        $entity['cifra'] = [
            'enabled' => !empty($settings['enabled']),
            'url' => rtrim(trim((string) ($settings['url'] ?? '')), '/'),
            'mode' => ($settings['mode'] ?? 'key') === 'oauth' ? 'oauth' : 'key',
            'example' => !empty($settings['example']),
            'clientid' => trim((string) ($settings['clientid'] ?? '')),
        ];
        $DB->set_field(self::T . 'entity', 'settings', json_encode($entity), ['id' => $entityid]);
        foreach (['key', 'secret'] as $name) {
            if (trim((string) ($settings[$name] ?? '')) !== '') {
                set_config('cifra' . $name . $entityid, trim($settings[$name]), 'local_oksigeniaayvurenda');
            }
        }
    }

    /**
     * Everyone Cifra has, without repeats.
     *
     * @param int $entityid
     * @return array[]
     * @throws \moodle_exception When Cifra does not answer well.
     */
    public static function people(int $entityid): array {
        $all = self::all($entityid);
        $base = (string) ($all['url'] ?? '');
        if ($base === '') {
            throw new \moodle_exception('cifranourl', 'local_oksigeniaayvurenda');
        }
        $headers = [];
        if (($all['mode'] ?? 'key') === 'oauth') {
            $token = self::request(
                'POST',
                $base . '/oauth/token',
                ['Content-Type: application/x-www-form-urlencoded'],
                false,
                http_build_query(['grant_type' => 'client_credentials', 'client_id' => $all['clientid'] ?? '',
                'client_secret' => $all['secret'] ?? '',
                'scope' => 'alumnos:read'])
            );
            $token = json_decode((string) $token, true)['access_token'] ?? '';
            if ($token === '') {
                throw new \moodle_exception('cifranotoken', 'local_oksigeniaayvurenda');
            }
            $headers[] = 'Authorization: Bearer ' . $token;
        } else {
            $headers[] = 'X-Puente-Llave: ' . ($all['key'] ?? '');
        }
        $path = $base . (!empty($all['example']) ? '/ejemplo' : '') . '/v1/alumnos';
        // Cifra pages a hundred at a time and its pages may repeat people: they are joined by their identifier.
        $people = [];
        for ($page = 1; $page <= 200; $page++) {
            // The bridge lives on the centre's own network, which Moodle's checks of addresses would refuse.
            $body = self::request('GET', $path . '?page=' . $page . '&per_page=100', $headers, ($all['mode'] ?? 'key') === 'key');
            $list = json_decode((string) $body, true);
            if (!is_array($list)) {
                throw new \moodle_exception('cifrabadanswer', 'local_oksigeniaayvurenda');
            }
            $list = isset($list['data']) && is_array($list['data']) ? $list['data'] : $list;
            foreach ($list as $person) {
                if (isset($person['id'])) {
                    $people[(string) $person['id']] = $person;
                }
            }
            if (count($list) < 100) {
                break;
            }
        }
        return array_values($people);
    }

    /**
     * Brings the readers: those who are there, with their group of this year; those who left, gone.
     *
     * @param int $entityid
     * @param int|null $now
     * @return array created, updated, linked, left, total.
     */
    public static function sync(int $entityid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $found = cifra_people::readers(self::people($entityid), $now);
        $stats = csvlist::load($entityid, $found['readers'], 'student', $now, 'cifra');
        $here = array_flip($stats['ids']);
        $left = 0;
        // Who came from Cifra before and is not there now, or left, stops being an active reader.
        $sources = $DB->get_records_menu(self::T . 'source', ['entityid' => $entityid, 'system' => 'cifra',
            'tablename' => 'reader'], '', 'id, localid');
        foreach (array_unique($sources) as $readerid) {
            if (!isset($here[$readerid])) {
                $left += $DB->count_records(self::T . 'reader', ['id' => $readerid, 'status' => 'active']);
                $DB->set_field(self::T . 'reader', 'status', 'left', ['id' => $readerid, 'status' => 'active']);
            }
        }
        unset($stats['ids']);
        return $stats + ['left' => $left];
    }

    /**
     * Whether an entity uses the connector.
     *
     * @param int $entityid
     * @return bool
     */
    public static function enabled(int $entityid): bool {
        return !empty(self::all($entityid)['enabled']);
    }

    /**
     * All the settings, secrets included.
     *
     * @param int $entityid
     * @return array
     */
    private static function all(int $entityid): array {
        global $DB;
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        return ($entity['cifra'] ?? []) + [
            'key' => (string) get_config('local_oksigeniaayvurenda', 'cifrakey' . $entityid),
            'secret' => (string) get_config('local_oksigeniaayvurenda', 'cifrasecret' . $entityid),
        ];
    }

    /**
     * A request to Cifra or its bridge.
     *
     * @param string $method
     * @param string $url
     * @param string[] $headers
     * @param bool $internal Whether the address is on the centre's own network (the bridge).
     * @param string $body
     * @return string
     * @throws \moodle_exception When it does not answer well.
     */
    private static function request(
        string $method,
        string $url,
        array $headers,
        bool $internal = false,
        string $body = ''
    ): string {
        global $CFG;
        if (self::$fetcher) {
            $answer = (self::$fetcher)($method, $url, $headers, $body);
            if ($answer === null) {
                throw new \moodle_exception('cifrabadanswer', 'local_oksigeniaayvurenda');
            }
            return $answer;
        }
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['ignoresecurity' => $internal]);
        $curl->setHeader($headers);
        $curl->setopt(['CURLOPT_TIMEOUT' => 60, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $answer = $method === 'POST' ? $curl->post($url, $body) : $curl->get($url);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code !== 200 || !is_string($answer)) {
            throw new \moodle_exception('cifrabadanswer', 'local_oksigeniaayvurenda', '', null, 'HTTP ' . $code);
        }
        return $answer;
    }
}
