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
 * Asking the catalogues for a book by its ISBN.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\isbn;
use local_oksigeniaayvurenda\core\sources\openlibrary;
use local_oksigeniaayvurenda\core\sources\sru;

/**
 * The catalogues an entity asks, in its order, until one knows the book; the cover from the first that has it. Each
 * answer is kept a day, so going back over a book does not ask again. The requests go from the server: the browser
 * only talks to the site.
 */
final class lookup {
    /** @var array<string, array> The catalogues that can be asked without anything else. */
    public const PRESETS = [
        'loc' => ['type' => 'sru', 'name' => 'Library of Congress', 'url' => 'https://lx2.loc.gov/sru/lcdb',
            'index' => 'bath.isbn', 'version' => '1.1'],
        'openlibrary' => ['type' => 'openlibrary', 'name' => 'Open Library'],
        // Spain's National Library: its Alma catalogue by SRU, without a key, with most books in Spanish.
        'bne' => ['type' => 'sru', 'name' => 'Biblioteca Nacional de España',
            'url' => 'https://catalogo.bne.es/view/sru/34BNE_INST', 'index' => 'alma.isbn', 'version' => '1.2'],
        // REBECA, the shared catalogue of Spain's public libraries, with their CDU: it only speaks Z39.50, so it is asked
        // through the Z39.50 bridge the site sets, and it only finds an ISBN written with its hyphens.
        'rebeca' => ['type' => 'sru', 'name' => 'REBECA', 'bridge' => 'rebeca', 'index' => 'bath.isbn', 'version' => '1.1',
            'hyphens' => true],
    ];

    /** @var string[] The order when the entity has not chosen one. */
    public const DEFAULT = ['loc', 'openlibrary', 'bne'];

    /** @var string[] The order when the entity has not chosen one and the site speaks Spanish. */
    public const DEFAULT_ES = ['rebeca', 'bne', 'loc', 'openlibrary'];

    /** @var callable|null How to fetch an address; tests put their own. Given the address, gives the body, null, or 429. */
    public static $fetcher = null;

    /** @var bool Whether a catalogue said, in this request, that it was asked too much (HTTP 429). */
    private static bool $limited = false;

    /** @var int Seconds to wait before asking again a catalogue that said it was asked too much. */
    public static int $wait = 2;

    /**
     * What the catalogues say of an ISBN.
     *
     * @param int $entityid
     * @param string $raw The ISBN as read or typed.
     * @return array valid, isbn, found, source, sourcename, description, cover, tried.
     */
    public static function isbn(int $entityid, string $raw): array {
        $isbn = isbn::normalise($raw);
        if ($isbn === null) {
            return ['valid' => false, 'isbn' => $raw, 'found' => false];
        }
        if (practice::is($entityid)) {
            // The practice library asks its own made-up catalogue, never the world.
            $description = practice::lookup($isbn);
            return ['valid' => true, 'isbn' => $isbn, 'found' => (bool) $description, 'source' => 'practice',
                'sourcename' => get_string('practicecatalogue', 'local_oksigeniaayvurenda'), 'description' => $description,
                'cover' => null, 'tried' => [get_string('practicecatalogue', 'local_oksigeniaayvurenda')]];
        }
        $sources = array_values(array_filter(self::sources($entityid), fn($name) => self::source($name) !== null));
        $cache = \cache::make('local_oksigeniaayvurenda', 'lookup');
        $key = $isbn . '_' . substr(md5(implode(',', $sources)), 0, 8);
        $cached = $cache->get($key);
        if ($cached !== false && $cached['time'] > time() - DAYSECS) {
            return $cached['result'];
        }
        $result = ['valid' => true, 'isbn' => $isbn, 'found' => false, 'source' => null, 'sourcename' => null,
            'description' => null, 'cover' => null, 'tried' => []];
        self::$limited = false;
        foreach ($sources as $name) {
            $source = self::source($name);
            $result['tried'][] = $source['name'];
            $description = self::ask($source, $isbn);
            if ($description) {
                $result['found'] = true;
                $result['source'] = $name;
                $result['sourcename'] = $source['name'];
                $result['description'] = $description;
                break;
            }
        }
        $result['cover'] = $result['description']['extra']['cover'] ?? openlibrary::cover_url($isbn);
        // What a catalogue could not say because it was asked too much is not kept for the day.
        if (!self::$limited) {
            $cache->set($key, ['time' => time(), 'result' => $result]);
        }
        return $result;
    }

    /**
     * The catalogues of an entity, in its order.
     *
     * @param int $entityid
     * @return string[]
     */
    public static function sources(int $entityid): array {
        global $DB;
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        ) ?: [];
        $chosen = array_values(array_filter($settings['sources'] ?? [], fn($s) => isset(self::PRESETS[$s])));
        if ($chosen) {
            return $chosen;
        }
        // Without a choice, the catalogue of the language the site speaks goes first: Spanish books are best known in
        // Spain's National Library, and little known elsewhere.
        global $CFG;
        return str_starts_with((string) $CFG->lang, 'es') ? self::DEFAULT_ES : self::DEFAULT;
    }

    /**
     * A catalogue ready to be asked, with its address; null when it cannot be (one through the bridge, when the site
     * has none).
     *
     * @param string $name
     * @return array|null
     */
    public static function source(string $name): ?array {
        $source = self::PRESETS[$name] ?? null;
        if ($source && isset($source['bridge'])) {
            $bridge = trim((string) get_config('local_oksigeniaayvurenda', 'z3950bridge'));
            if ($bridge === '') {
                return null;
            }
            $source['url'] = rtrim($bridge, '/') . '/' . $source['bridge'];
        }
        return $source;
    }

    /**
     * Fetches an address from the server.
     *
     * @param string $url
     * @return string|null The body, or null when it did not answer well.
     */
    public static function fetch(string $url): ?string {
        global $CFG;
        if (self::$fetcher) {
            $body = (self::$fetcher)($url);
            if ($body === 429) {
                self::$limited = true;
                return null;
            }
            return $body;
        }
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 12,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            // Open Library asks to be told who asks, with a way to write back.
            'CURLOPT_USERAGENT' => 'Oksigenia Ayvurenda/' . get_config('local_oksigeniaayvurenda', 'release') .
                ' (dev@oksigenia.cc)',
        ]);
        $body = $curl->get($url);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        // A catalogue that limits how often it is asked (the Z39.50 bridge: some 60 a minute) is asked once more.
        if ($code === 429) {
            sleep(self::$wait);
            $body = $curl->get($url);
            $code = (int) ($curl->get_info()['http_code'] ?? 0);
            if ($code === 429) {
                self::$limited = true;
            }
        }
        return $curl->get_errno() || $code !== 200 || !is_string($body) ? null : $body;
    }

    /**
     * Asks one catalogue.
     *
     * @param array $source
     * @param string $isbn
     * @return array|null The description of that very edition.
     */
    private static function ask(array $source, string $isbn): ?array {
        try {
            if ($source['type'] === 'sru') {
                $sru = new sru($source['url'], $source['index'], $source['version']);
                // A catalogue that keeps the hyphens is asked every way of writing them: the ISBN-13 first, then the
                // ISBN-10 of older books (two questions: it takes some thirty ways at a time).
                $urls = empty($source['hyphens']) ? [$sru->url($isbn)]
                    : array_map(fn($ways) => $sru->url_any($ways), array_filter(isbn::hyphenations($isbn)));
                foreach ($urls as $url) {
                    $body = self::fetch($url);
                    foreach ($body === null ? [] : sru::descriptions($body) as $description) {
                        // A catalogue may answer with other editions too: only the one asked for counts.
                        $isbns = $description['extra']['isbns'] ?? [$description['record']['isbn']];
                        if (in_array($isbn, $isbns, true) && $description['record']['title'] !== '') {
                            return $description;
                        }
                    }
                }
                return null;
            }
            $body = self::fetch(openlibrary::url($isbn));
            return $body === null ? null : openlibrary::description($body, $isbn);
        } catch (\Throwable $e) {
            debugging('Ayvurenda: ' . $source['name'] . ' did not answer well: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
