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
use local_oksigeniaayvurenda\core\text;

/**
 * The catalogues an entity asks, in its order, until one knows the book; the cover from the first that has it. Each
 * answer is kept a day, so going back over a book does not ask again. The requests go from the server: the browser
 * only talks to the site.
 */
final class lookup {
    /** @var array<string, array> The catalogues that can be asked without anything else: long-standing national libraries
     * and shared catalogues, each with its region, tried by real questions on 2 November 2026. Alma speaks SRU with
     * «alma.isbn» and «alma.all_for_ui»; one that only speaks Z39.50 goes through the bridge the site sets. */
    public const PRESETS = [
        'loc' => ['type' => 'sru', 'name' => 'Library of Congress', 'region' => 'en', 'url' => 'https://lx2.loc.gov/sru/lcdb',
            'index' => 'bath.isbn', 'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => '='],
        'openlibrary' => ['type' => 'openlibrary', 'name' => 'Open Library', 'region' => 'world'],
        // Spain's National Library: its Alma catalogue by SRU, without a key, with most books in Spanish.
        'bne' => ['type' => 'sru', 'name' => 'Biblioteca Nacional de España', 'region' => 'es',
            'url' => 'https://catalogo.bne.es/view/sru/34BNE_INST', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
        // The libraries of Spain's research council (CSIC).
        'csic' => ['type' => 'sru', 'name' => 'Bibliotecas del CSIC', 'region' => 'es',
            'url' => 'https://eu00.alma.exlibrisgroup.com/view/sru/34CSIC_INST', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
        // Catalonia's shared catalogue of university and research libraries (CSUC).
        'ccuc' => ['type' => 'sru', 'name' => 'CCUC (Catalunya)', 'region' => 'es',
            'url' => 'https://csuc-bc.alma.exlibrisgroup.com/view/sru/34CSUC_NETWORK', 'index' => 'alma.isbn',
            'version' => '1.2', 'words' => 'alma.all_for_ui', 'relation' => 'all'],
        // REBECA, the shared catalogue of Spain's public libraries, with their CDU: it only finds an ISBN with its hyphens.
        'rebeca' => ['type' => 'sru', 'name' => 'REBECA', 'region' => 'es', 'bridge' => 'rebeca', 'index' => 'bath.isbn',
            'version' => '1.1', 'hyphens' => true, 'words' => 'cql.anywhere', 'relation' => 'all'],
        // REBIUN, the shared catalogue of Spain's university libraries, with their CDU and the ISBN with hyphens too.
        'rebiun' => ['type' => 'sru', 'name' => 'REBIUN', 'region' => 'es', 'bridge' => 'rebiun', 'index' => 'bath.isbn',
            'version' => '1.1', 'hyphens' => true, 'words' => 'cql.anywhere', 'relation' => 'all'],
        // Colombia's National University (SINAB) and Peru's National Library, through the bridge.
        'unal' => ['type' => 'sru', 'name' => 'Universidad Nacional de Colombia', 'region' => 'la', 'bridge' => 'unal',
            'index' => 'bath.isbn', 'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        'bnp' => ['type' => 'sru', 'name' => 'Biblioteca Nacional del Perú', 'region' => 'la', 'bridge' => 'bnp',
            'index' => 'bath.isbn', 'version' => '1.1', 'hyphens' => true, 'words' => 'cql.anywhere', 'relation' => 'all'],
        // Brazil: São Paulo State University (UNESP) by SRU, and the University of São Paulo (DEDALUS) through the bridge.
        'unesp' => ['type' => 'sru', 'name' => 'UNESP', 'region' => 'br',
            'url' => 'https://unesp.alma.exlibrisgroup.com/view/sru/55UNESP_INST', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
        'usp' => ['type' => 'sru', 'name' => 'USP Dedalus', 'region' => 'br', 'bridge' => 'usp', 'index' => 'bath.isbn',
            'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        // Portugal's National Library (PORBASE): UNIMARC in ISO 5426, through the bridge; it knows older books by their ISBN-10
        // and takes no «or».
        'porbase' => ['type' => 'sru', 'name' => 'Biblioteca Nacional de Portugal', 'region' => 'pt', 'bridge' => 'porbase',
            'index' => 'bath.isbn', 'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        // France's National Library, by SRU, in UNIMARC.
        'bnf' => ['type' => 'sru', 'name' => 'Bibliothèque nationale de France', 'region' => 'fr',
            'url' => 'https://catalogue.bnf.fr/api/SRU', 'index' => 'bib.isbn', 'isbnrelation' => 'all', 'version' => '1.2',
            'schema' => 'unimarcXchange',
            'words' => 'bib.anywhere', 'relation' => 'all'],
        // German: the National Library, the K10plus shared catalogue, and the Swiss and Austrian networks.
        'dnb' => ['type' => 'sru', 'name' => 'Deutsche Nationalbibliothek', 'region' => 'de',
            'url' => 'https://services.dnb.de/sru/dnb', 'index' => 'isbn', 'version' => '1.1', 'schema' => 'MARC21-xml',
            'words' => 'woe', 'relation' => 'all'],
        'k10plus' => ['type' => 'sru', 'name' => 'K10plus', 'region' => 'de', 'url' => 'https://sru.k10plus.de/opac-de-627',
            'index' => 'pica.isb', 'version' => '1.1', 'words' => 'pica.all', 'relation' => '='],
        'swisscovery' => ['type' => 'sru', 'name' => 'swisscovery', 'region' => 'de',
            'url' => 'https://swisscovery.slsp.ch/view/sru/41SLSP_NETWORK', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
        'obv' => ['type' => 'sru', 'name' => 'Österreichischer Bibliothekenverbund', 'region' => 'de',
            'url' => 'https://obv-at-obvsg.alma.exlibrisgroup.com/view/sru/43ACC_NETWORK', 'index' => 'alma.isbn',
            'version' => '1.2', 'words' => 'alma.all_for_ui', 'relation' => 'all'],
        // Nordic: Sweden's LIBRIS, Finland's Melinda, Norway's shared catalogue and Denmark's Royal Library.
        'libris' => ['type' => 'sru', 'name' => 'LIBRIS', 'region' => 'nordic', 'url' => 'https://api.libris.kb.se/sru/libris',
            'index' => 'bath.isbn', 'version' => '1.1', 'words' => '', 'relation' => ''],
        'melinda' => ['type' => 'sru', 'name' => 'Melinda', 'region' => 'nordic',
            'url' => 'https://sru.api.melinda.kansalliskirjasto.fi/bib', 'index' => 'bath.isbn', 'version' => '1.1',
            'words' => '', 'relation' => ''],
        'bibsys' => ['type' => 'sru', 'name' => 'Bibsys (Norge)', 'region' => 'nordic',
            'url' => 'https://bibsys-k.alma.exlibrisgroup.com/view/sru/47BIBSYS_NETWORK', 'index' => 'alma.isbn',
            'version' => '1.2', 'words' => 'alma.all_for_ui', 'relation' => 'all'],
        'kbdk' => ['type' => 'sru', 'name' => 'Det Kgl. Bibliotek', 'region' => 'nordic',
            'url' => 'https://kbdk-kgl.alma.exlibrisgroup.com/view/sru/45KBDK_KGL', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
        // Italy's national shared catalogue (SBN), in MARC 21 when asked for it, through the bridge.
        'sbn' => ['type' => 'sru', 'name' => 'SBN (Italia)', 'region' => 'it', 'bridge' => 'sbn', 'index' => 'bath.isbn',
            'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        // In French with MARC 21: Quebec's national library, through the bridge.
        'banq' => ['type' => 'sru', 'name' => 'BAnQ (Québec)', 'region' => 'fr', 'bridge' => 'banq', 'index' => 'bath.isbn',
            'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        // In English, besides the Library of Congress: the National Library of Wales and the State Library Victoria.
        'nlw' => ['type' => 'sru', 'name' => 'National Library of Wales', 'region' => 'en', 'bridge' => 'nlw',
            'index' => 'bath.isbn', 'version' => '1.1', 'words' => 'cql.anywhere', 'relation' => 'all'],
        'slv' => ['type' => 'sru', 'name' => 'State Library Victoria', 'region' => 'en',
            'url' => 'https://slv.alma.exlibrisgroup.com/view/sru/61SLV_INST', 'index' => 'alma.isbn', 'version' => '1.2',
            'words' => 'alma.all_for_ui', 'relation' => 'all'],
    ];

    /** @var array<string, string[]> The order of each region when the centre has not chosen one. */
    public const REGIONS = [
        'es' => ['rebeca', 'bne', 'rebiun', 'csic', 'ccuc', 'loc', 'openlibrary'],
        'la' => ['bne', 'unal', 'bnp', 'rebiun', 'loc', 'openlibrary'],
        'pt' => ['porbase', 'unesp', 'usp', 'loc', 'openlibrary'],
        'br' => ['unesp', 'usp', 'porbase', 'loc', 'openlibrary'],
        'de' => ['dnb', 'k10plus', 'swisscovery', 'obv', 'loc', 'openlibrary'],
        'nordic' => ['libris', 'melinda', 'bibsys', 'kbdk', 'loc', 'openlibrary'],
        'it' => ['sbn', 'loc', 'openlibrary'],
        'fr' => ['bnf', 'banq', 'loc', 'openlibrary'],
        'en' => ['loc', 'nlw', 'slv', 'openlibrary'],
    ];

    /** @var int Seconds a search by words may spend asking: a catalogue that does not answer must not hold the desk. */
    public const BUDGET = 20;

    /** @var int How many books a search by words brings at most. */
    public const FOUND = 10;

    /** @var int With this many, the next catalogue is not asked: there is enough to choose from. */
    public const ENOUGH = 6;

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
     * What the catalogues have for some words of a title or its authors, for a book with no ISBN to read: each with a
     * key to choose it by, kept a day.
     *
     * @param int $entityid
     * @param string $words
     * @return array found (pick, source, description), tried.
     */
    public static function words(int $entityid, string $words): array {
        $words = trim(preg_replace('/\s+/u', ' ', $words));
        $result = ['found' => [], 'tried' => []];
        if (\core_text::strlen($words) < 3) {
            return $result;
        }
        if (practice::is($entityid)) {
            // The practice library asks no one.
            $result['tried'][] = get_string('practicecatalogue', 'local_oksigeniaayvurenda');
            return $result;
        }
        $seen = [];
        $started = microtime(true);
        foreach (self::word_sources($entityid) as $source) {
            $result['tried'][] = $source['name'];
            foreach (self::words_in($entityid, $words, $source['key']) as $found) {
                if (count($result['found']) >= self::FOUND) {
                    break 2;
                }
                // The same book in two catalogues is shown once: the first catalogue's.
                if (isset($seen[$found['same']])) {
                    continue;
                }
                $seen[$found['same']] = true;
                $result['found'][] = $found + ['order' => count($result['found'])];
            }
            if (count($result['found']) >= self::ENOUGH || microtime(true) - $started > self::BUDGET) {
                break;
            }
        }
        // The ones with most of the words first; among them, the order of the catalogues.
        usort($result['found'], fn($a, $b) => [$b['score'], $a['order']] <=> [$a['score'], $b['order']]);
        $result['found'] = array_map(fn($f) => array_diff_key($f, ['score' => 0, 'order' => 0, 'same' => 0]), $result['found']);
        return $result;
    }

    /**
     * The catalogues a search by words asks, one by one, so the screen can say how it goes.
     *
     * @param int $entityid
     * @return array[] key and name.
     */
    public static function word_sources(int $entityid): array {
        if (practice::is($entityid)) {
            return [];
        }
        $where = [];
        foreach (self::sources($entityid) as $name) {
            $source = self::source($name);
            if ($source) {
                $where[] = ['key' => $name, 'name' => $source['name']];
            }
        }
        return $where;
    }

    /**
     * What one catalogue has for some words of a title or its authors, each with a key to choose it by, kept a day.
     *
     * @param int $entityid
     * @param string $words
     * @param string $name The catalogue: one of this entity's.
     * @return array[] pick, source, description, score (how many of the words it has) and same (to show the same book once).
     */
    public static function words_in(int $entityid, string $words, string $name): array {
        $words = trim(preg_replace('/\s+/u', ' ', $words));
        $source = in_array($name, self::sources($entityid), true) ? self::source($name) : null;
        if (!$source || \core_text::strlen($words) < 3 || practice::is($entityid)) {
            return [];
        }
        $cache = \cache::make('local_oksigeniaayvurenda', 'lookup');
        $asked = array_values(array_filter(text::words($words), fn($w) => \core_text::strlen($w) >= 3));
        $found = [];
        foreach (self::ask_words($source, $words) as $description) {
            $have = array_flip(text::words(implode(' ', array_merge(
                [(string) ($description['record']['title'] ?? ''), (string) ($description['record']['subtitle'] ?? '')],
                (array) ($description['authorities']['author'] ?? [])
            ))));
            $points = $asked ? count(array_filter($asked, fn($w) => isset($have[$w]))) : 1;
            if (!$points) {
                continue;
            }
            $pick = sha1($name . json_encode($description));
            $cache->set('pick_' . $pick, ['time' => time(), 'source' => $source['name'], 'description' => $description]);
            $found[] = ['pick' => $pick, 'source' => $source['name'], 'description' => $description, 'score' => $points,
                'same' => text::normalise((string) $description['record']['title']) . '|'
                    . ($description['record']['pubyear'] ?? '') . '|'
                    . text::normalise((string) ($description['authorities']['author'][0] ?? ''))];
        }
        return $found;
    }

    /**
     * What some words written wrong may have meant: first by the words of the catalogue itself, then by what Wikipedia
     * would say, which knows surnames this library has never seen. It is told only the words of the search, as the
     * catalogues are.
     *
     * @param int $entityid
     * @param string $words
     * @return string|null
     */
    public static function spelled(int $entityid, string $words): ?string {
        $words = trim(preg_replace('/\s+/u', ' ', $words));
        if (\core_text::strlen($words) < 3) {
            return null;
        }
        $vocabulary = catalogue::vocabulary($entityid);
        $own = (new \local_oksigeniaayvurenda\core\search\suggester($vocabulary))->suggest($words, 1)[0] ?? null;
        if ($own !== null) {
            return $own;
        }
        if (practice::is($entityid)) {
            return null;
        }
        $language = substr(current_language(), 0, 2);
        $language = preg_match('/^[a-z]{2}$/', $language) ? $language : 'en';
        $body = self::fetch('https://' . $language . '.wikipedia.org/w/api.php?' . http_build_query(['action' => 'query',
            'list' => 'search', 'srsearch' => $words, 'srinfo' => 'suggestion', 'srprop' => '', 'srlimit' => 1,
            'format' => 'json']), 6);
        $said = $body !== null ? json_decode($body, true) : null;
        $suggestion = trim((string) ($said['query']['searchinfo']['suggestion'] ?? ''));
        return $suggestion !== '' && text::normalise($suggestion) !== text::normalise($words) ? $suggestion : null;
    }

    /**
     * A book chosen from a search by words, as it was found.
     *
     * @param string $pick
     * @return array|null source and description.
     */
    public static function picked(string $pick): ?array {
        if (!preg_match('/^[0-9a-f]{40}$/', $pick)) {
            return null;
        }
        $kept = \cache::make('local_oksigeniaayvurenda', 'lookup')->get('pick_' . $pick);
        return $kept !== false && $kept['time'] > time() - DAYSECS ? $kept : null;
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
        return $chosen ?: self::REGIONS[self::region($entityid)];
    }

    /**
     * The region whose catalogues a centre asks without a choice: by its time zone when it set one, and otherwise by the
     * language the site speaks. A book is best known in the libraries of its own country.
     *
     * @param int $entityid
     * @return string A key of REGIONS.
     */
    public static function region(int $entityid): string {
        global $CFG, $DB;
        $settings = json_decode(
            (string) $DB->get_field('local_oksigeniaayvurenda_entity', 'settings', ['id' => $entityid]),
            true
        ) ?: [];
        $zone = (string) ($settings['timezone'] ?? '');
        return $zone !== '' && zones::known($zone) ? self::zone_region($zone) : self::language_region((string) $CFG->lang);
    }

    /**
     * The region of a time zone.
     *
     * @param string $zone
     * @return string
     */
    public static function zone_region(string $zone): string {
        $in = fn(array $zones) => in_array($zone, $zones, true);
        if ($in(['Europe/Madrid', 'Atlantic/Canary', 'Africa/Ceuta'])) {
            return 'es';
        }
        if ($in(['Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich', 'Europe/Busingen', 'Europe/Vaduz'])) {
            return 'de';
        }
        if (
            $in(['Europe/Stockholm', 'Europe/Helsinki', 'Europe/Oslo', 'Europe/Copenhagen', 'Atlantic/Reykjavik',
                'Europe/Mariehamn', 'Arctic/Longyearbyen', 'Atlantic/Faroe'])
        ) {
            return 'nordic';
        }
        if ($in(['Europe/Rome', 'Europe/Vatican', 'Europe/San_Marino'])) {
            return 'it';
        }
        if ($in(['Europe/Paris', 'Europe/Brussels', 'Europe/Monaco', 'Europe/Luxembourg', 'America/Montreal'])) {
            return 'fr';
        }
        $brazil = ['Sao_Paulo', 'Fortaleza', 'Recife', 'Bahia', 'Belem', 'Manaus', 'Cuiaba', 'Porto_Velho', 'Boa_Vista',
            'Rio_Branco', 'Noronha', 'Maceio', 'Araguaina', 'Campo_Grande', 'Santarem', 'Eirunepe'];
        if ($in(['Europe/Lisbon', 'Atlantic/Madeira', 'Atlantic/Azores'])) {
            return 'pt';
        }
        if ($in(array_map(fn($c) => 'America/' . $c, $brazil))) {
            return 'br';
        }
        $english = ['New_York', 'Chicago', 'Denver', 'Los_Angeles', 'Phoenix', 'Anchorage', 'Juneau', 'Adak', 'Boise',
            'Detroit', 'Indiana/Indianapolis', 'Kentucky/Louisville', 'Toronto', 'Vancouver', 'Edmonton', 'Winnipeg',
            'Halifax', 'St_Johns', 'Regina', 'Jamaica', 'Nassau', 'Barbados', 'Belize'];
        if ($in(array_map(fn($c) => 'America/' . $c, $english))) {
            return 'en';
        }
        if (str_starts_with($zone, 'America/')) {
            return 'la';
        }
        return 'en';
    }

    /**
     * The region of a language, for a centre that did not set its time zone.
     *
     * @param string $lang
     * @return string
     */
    public static function language_region(string $lang): string {
        $base = substr($lang, 0, 2);
        if ($lang === 'pt_br') {
            return 'br';
        }
        return ['es' => 'es', 'ca' => 'es', 'gl' => 'es', 'eu' => 'es', 'pt' => 'pt', 'de' => 'de', 'sv' => 'nordic',
            'fi' => 'nordic', 'nb' => 'nordic', 'nn' => 'nordic', 'no' => 'nordic', 'da' => 'nordic', 'is' => 'nordic',
            'it' => 'it', 'fr' => 'fr'][$base] ?? 'en';
    }

    /**
     * Every catalogue, its region's first and then the rest by region, with whether a centre asks it now: for its
     * settings.
     *
     * @param int $entityid
     * @return array Groups: region, sources (key, name, on).
     */
    public static function choices(int $entityid): array {
        $now = self::sources($entityid);
        $mine = self::region($entityid);
        $order = array_values(array_unique(array_merge([$mine], array_keys(self::REGIONS), ['world'])));
        $groups = [];
        // Within a region, in the order it is asked by default; what no region asks first, after.
        $rank = array_flip(array_values(array_unique(array_merge(self::REGIONS[$mine], ...array_values(self::REGIONS)))));
        $keys = array_keys(self::PRESETS);
        usort($keys, fn($a, $b) => [$rank[$a] ?? PHP_INT_MAX, $a] <=> [$rank[$b] ?? PHP_INT_MAX, $b]);
        foreach ($order as $region) {
            $sources = [];
            foreach ($keys as $key) {
                $preset = self::PRESETS[$key];
                if ($preset['region'] === $region) {
                    $sources[] = ['key' => $key, 'name' => $preset['name'], 'on' => in_array($key, $now, true)];
                }
            }
            if ($sources) {
                $groups[] = ['region' => $region, 'sources' => $sources];
            }
        }
        return $groups;
    }

    /**
     * What a centre chose, to keep: in the order of its choices, and nothing when it is its region's order.
     *
     * @param int $entityid
     * @param string[] $chosen
     * @return string[]
     */
    public static function chosen(int $entityid, array $chosen): array {
        $order = [];
        foreach (self::choices($entityid) as $group) {
            foreach ($group['sources'] as $source) {
                if (in_array($source['key'], $chosen, true)) {
                    $order[] = $source['key'];
                }
            }
        }
        $default = self::REGIONS[self::region($entityid)];
        $same = $order && count($order) === count($default) && !array_diff($order, $default);
        return $same ? [] : $order;
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
     * @param int $timeout Seconds to wait for the whole answer (an export put together on the way takes longer).
     * @return string|null The body, or null when it did not answer well.
     */
    public static function fetch(string $url, int $timeout = 12): ?string {
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
            'CURLOPT_TIMEOUT' => $timeout,
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
     * Asks one catalogue for some words.
     *
     * @param array $source
     * @param string $words
     * @return array[] Descriptions with a title.
     */
    private static function ask_words(array $source, string $words): array {
        try {
            if ($source['type'] === 'sru') {
                if (!isset($source['words'])) {
                    return [];
                }
                $sru = new sru($source['url'], $source['index'], $source['version'], $source['schema'] ?? 'marcxml');
                $body = self::fetch($sru->url_words($source['words'], $source['relation'] ?? 'all', $words));
                $found = $body === null ? [] : sru::descriptions($body);
            } else {
                $body = self::fetch(openlibrary::search_url($words));
                $found = $body === null ? [] : openlibrary::search($body);
            }
            return array_values(array_filter($found, fn($d) => trim((string) ($d['record']['title'] ?? '')) !== ''));
        } catch (\Throwable $e) {
            debugging('Ayvurenda: ' . $source['name'] . ' did not answer well: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
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
                $sru = new sru($source['url'], $source['index'], $source['version'], $source['schema'] ?? 'marcxml');
                // A catalogue that keeps the hyphens is asked every way of writing them: the ISBN-13 first, then the
                // ISBN-10 of older books (two questions: it takes some thirty ways at a time).
                // Any other is asked the ISBN-13 and then, if it does not know it, the ISBN-10 (São Paulo keeps that one).
                $relation = $source['isbnrelation'] ?? '=';
                $urls = empty($source['hyphens'])
                    ? array_map(fn($code) => $sru->url($code, $relation), array_filter([$isbn, isbn::ten($isbn)]))
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
