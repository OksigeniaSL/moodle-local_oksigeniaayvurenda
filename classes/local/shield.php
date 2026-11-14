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

/**
 * The shield against robots (the review of 12 November): whoever comes without an account to the public window, OAI-PMH,
 * SRU or OPDS may ask for so many pages a minute from one address; past that, they are asked to come back in a minute.
 * Crawlers combining filters bring catalogues down, and a hosted service is one server for every centre. A person never
 * meets it: nobody reads a page a second. Someone signed in is never counted.
 *
 * A site turns it off with the setting `shield` of the plugin at 0 (in config.php, with $CFG->forced_plugin_settings).
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shield {
    /** @var int[] Pages a minute from one address, by door: a school behind one address still browses the window. */
    public const LIMITS = ['window' => 120, 'oai' => 30, 'sru' => 60, 'opds' => 60];

    /** @var string Addresses never counted: behind a proxy Moodle does not see through, every visitor would be one. */
    private const NEAR = '127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1/128,fc00::/7';

    /** @var int Seconds to wait, said to whoever is asked to. */
    public const WAIT = 60;

    /**
     * Counts a page asked for at a door from an address, in the minute under way.
     *
     * @param string $door
     * @param string $address
     * @param int $now
     * @return int The pages asked for this minute, this one included.
     */
    public static function count(string $door, string $address, int $now): int {
        $cache = \cache::make('local_oksigeniaayvurenda', 'shield');
        $key = sha1($door . '|' . $address . '|' . intdiv($now, 60));
        $pages = (int) $cache->get($key) + 1;
        $cache->set($key, $pages);
        return $pages;
    }

    /**
     * Whether a page asked for at a door may be served.
     *
     * @param string $door window, oai, sru or opds.
     * @param int|null $now
     * @return bool
     */
    public static function pass(string $door, ?int $now = null): bool {
        if (get_config('local_oksigeniaayvurenda', 'shield') === '0' || (isloggedin() && !isguestuser())) {
            return true;
        }
        $address = (string) getremoteaddr('');
        if ($address === '' || address_in_subnet($address, self::NEAR)) {
            return true;
        }
        return self::count($door, $address, $now ?? time()) <= (self::LIMITS[$door] ?? self::LIMITS['window']);
    }

    /**
     * Guards a door: a page past the limit is answered «come back in a minute», as each protocol says it (OAI-PMH asks
     * harvesters for 503 with Retry-After; the rest, 429), and nothing else is done.
     *
     * @param string $door
     */
    public static function guard(string $door): void {
        if (self::pass($door)) {
            return;
        }
        http_response_code($door === 'oai' ? 503 : 429);
        header('Retry-After: ' . self::WAIT);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo get_string('shieldwait', 'local_oksigeniaayvurenda');
        die;
    }
}
