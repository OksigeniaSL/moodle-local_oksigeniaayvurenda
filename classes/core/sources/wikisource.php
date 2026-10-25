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
 * Wikisource: the works of a language's Wikisource, read online, and their author from Wikidata.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\sources;

/**
 * The search finds pages, often chapters («Platero y yo/XXXV»): each counts as its work, the page before the first
 * slash. A work is brought as a link to read on Wikisource; its export as an EPUB (ws-export) did not answer in October
 * 2026. The texts are in the public domain or under CC BY-SA, as each page says.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class wikisource {
    /** @var string[] The Wikisources a site's language goes to; others, the multilingual one. */
    public const LANGS = ['es', 'en', 'fr', 'de', 'it', 'pt', 'ca', 'eu', 'gl'];

    /** @var string[] The language of each Wikisource, as the catalogue writes it (ISO 639-2). */
    private const LANGS3 = ['es' => 'spa', 'en' => 'eng', 'fr' => 'fra', 'de' => 'deu', 'it' => 'ita', 'pt' => 'por',
        'ca' => 'cat', 'eu' => 'eus', 'gl' => 'glg'];

    /** @var string The API of Wikidata. */
    private const WIKIDATA = 'https://www.wikidata.org/w/api.php';

    /**
     * The Wikisource of a language.
     *
     * @param string $lang
     * @return string Its address, without a slash at the end.
     */
    public static function base(string $lang): string {
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'mul';
        return $lang === 'mul' ? 'https://wikisource.org' : 'https://' . $lang . '.wikisource.org';
    }

    /**
     * The language of a Wikisource as the catalogue writes it.
     *
     * @param string $lang
     * @return string|null
     */
    public static function lang3(string $lang): ?string {
        return self::LANGS3[$lang] ?? null;
    }

    /**
     * The search of pages.
     *
     * @param string $lang
     * @param string $query
     * @return string
     */
    public static function search_url(string $lang, string $query): string {
        return self::base($lang) . '/w/api.php?' . http_build_query(['action' => 'query', 'list' => 'search',
            'srsearch' => $query, 'srnamespace' => 0, 'srlimit' => 40, 'format' => 'json', 'formatversion' => 2], '', '&');
    }

    /**
     * The works the pages found belong to, in order, without repeats.
     *
     * @param string $json
     * @return string[] Titles.
     */
    public static function parse_search(string $json): array {
        $data = json_decode($json, true);
        $works = [];
        foreach ((array) ($data['query']['search'] ?? []) as $page) {
            $work = trim(explode('/', (string) ($page['title'] ?? ''), 2)[0]);
            if ($work !== '' && !in_array($work, $works, true)) {
                $works[] = $work;
            }
        }
        return array_slice($works, 0, 15);
    }

    /**
     * What Wikisource says of some works: their address and their item in Wikidata.
     *
     * @param string $lang
     * @param string[] $titles
     * @return string
     */
    public static function works_url(string $lang, array $titles): string {
        return self::base($lang) . '/w/api.php?' . http_build_query(['action' => 'query', 'titles' => implode('|', $titles),
            'prop' => 'info|pageprops', 'inprop' => 'url', 'ppprop' => 'wikibase_item', 'redirects' => 1, 'format' => 'json',
            'formatversion' => 2], '', '&');
    }

    /**
     * The works that exist, with their address and Wikidata item, in the order asked.
     *
     * @param string $json
     * @param string[] $titles
     * @return array[] title, url, item.
     */
    public static function parse_works(string $json, array $titles): array {
        $data = json_decode($json, true);
        $redirects = [];
        foreach ((array) ($data['query']['redirects'] ?? []) as $redirect) {
            $redirects[(string) $redirect['from']] = (string) $redirect['to'];
        }
        $pages = [];
        foreach ((array) ($data['query']['pages'] ?? []) as $page) {
            if (empty($page['missing']) && !empty($page['fullurl'])) {
                $pages[(string) $page['title']] = ['title' => (string) $page['title'], 'url' => (string) $page['fullurl'],
                    'item' => (string) ($page['pageprops']['wikibase_item'] ?? '')];
            }
        }
        $works = [];
        foreach ($titles as $title) {
            $found = $pages[$redirects[$title] ?? $title] ?? null;
            if ($found && !isset($works[$found['title']])) {
                $works[$found['title']] = $found;
            }
        }
        return array_values($works);
    }

    /**
     * Wikidata's entities, with their claims and labels.
     *
     * @param string[] $items
     * @param string $lang
     * @return string
     */
    public static function wikidata_url(array $items, string $lang): string {
        return self::WIKIDATA . '?' . http_build_query(['action' => 'wbgetentities', 'ids' => implode('|', $items),
            'props' => 'claims|labels', 'languages' => $lang . '|en', 'format' => 'json'], '', '&');
    }

    /**
     * The authors of a work in Wikidata (P50), or of the work it is an edition of (P629).
     *
     * @param string $json
     * @param string $item
     * @return array authors (items) and edition (the item of the work, if this is an edition).
     */
    public static function parse_work_item(string $json, string $item): array {
        $entity = json_decode($json, true)['entities'][$item] ?? [];
        $ids = fn(string $property) => array_values(array_filter(array_map(
            fn($claim) => (string) ($claim['mainsnak']['datavalue']['value']['id'] ?? ''),
            (array) ($entity['claims'][$property] ?? [])
        )));
        return ['authors' => $ids('P50'), 'edition' => $ids('P629')[0] ?? ''];
    }

    /**
     * The names of some Wikidata entities, in a language or else in English.
     *
     * @param string $json
     * @param string $lang
     * @return string[] item => name.
     */
    public static function parse_labels(string $json, string $lang): array {
        $names = [];
        foreach ((array) (json_decode($json, true)['entities'] ?? []) as $item => $entity) {
            $name = (string) ($entity['labels'][$lang]['value'] ?? $entity['labels']['en']['value'] ?? '');
            if ($name !== '') {
                $names[(string) $item] = $name;
            }
        }
        return $names;
    }
}
