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
 * The genres of a library: corners of its own inside the stories (Mystery, Fantasy, Humour…), as the schools that sort
 * their fiction by genre do. Each library says what makes one: topics of the centre (kept by their key, so «Mysteries»
 * and «mystery» are one) and, if it already labels the spines, the start of the shelf mark. A genre only takes books of
 * the corners of stories the library names (the 8 and the letters, unless it says otherwise): a book of science about
 * animals does not go to «Animals». A book may be in two genres; the corner they come from keeps the rest. The
 * classification stays as it is underneath: shelves, shelf marks and labels do not change.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class genres {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var int Genres a library may have. */
    public const MAX = 12;

    /** @var int Books a proposal needs to be made. */
    public const ENOUGH = 5;

    /** @var string[] Where a genre takes its books from, unless the library says: the stories by the UDC or by a letter. */
    public const FROM = ['8', 'N', 'J'];

    /**
     * The genres a library may be offered, each with the keys of the topics that make it (subjects::key: lower case,
     * no accents, each word in the singular), in Spanish and in English.
     */
    public const KNOWN = [
        'mystery' => ['misterio', 'detective', 'intriga', 'policiaca', 'policiaco', 'novela policiaca', 'enigma', 'mystery',
            'crime', 'detective story'],
        'fantasy' => ['fantasia', 'magia', 'bruja', 'hada', 'dragon', 'mago', 'fantasy', 'magic', 'witch', 'wizard', 'fairy'],
        'adventure' => ['aventura', 'pirata', 'adventure', 'adventure story', 'pirate'],
        'humour' => ['humor', 'humorismo', 'comedia', 'humour', 'humorous story', 'comedy'],
        'horror' => ['terror', 'miedo', 'fantasma', 'vampiro', 'horror', 'ghost', 'ghost story', 'vampire'],
        'scifi' => ['ciencia ficcion', 'robot', 'science fiction'],
        'love' => ['amor', 'romantica', 'novela romantica', 'love', 'love story', 'romance'],
        'comic' => ['comic', 'historieta', 'tebeo', 'novela grafica', 'manga', 'graphic novel', 'comic book'],
        'poetry' => ['poesia', 'poema', 'poetry', 'poem'],
        'drama' => ['teatro', 'drama', 'play'],
        'sport' => ['deporte', 'futbol', 'sport', 'football', 'soccer'],
        'animals' => ['animal', 'mascota', 'pet'],
        'historical' => ['novela historica', 'historical fiction', 'historical novel'],
    ];

    /** @var int[] Colours of the corners (catalogue's), from where the genres start, to tell them from the classes. */
    private const COLOURS = [3, 4, 5, 0, 2];

    /**
     * The genres of a library, in their order.
     *
     * @param \stdClass $library
     * @return array[] Each with code (g1…), label, kind (the known genre it came from, for its drawing; '' if made by
     *     hand), keys, topics (as the library wrote them), prefixes and colour.
     */
    public static function of(\stdClass $library): array {
        $settings = json_decode((string) ($library->settings ?? ''), true) ?: [];
        $genres = [];
        foreach (array_values((array) ($settings['genres'] ?? [])) as $i => $genre) {
            $code = (string) ($genre['code'] ?? '');
            $label = trim((string) ($genre['label'] ?? ''));
            if (!self::is_code($code) || $label === '') {
                continue;
            }
            $kind = (string) ($genre['kind'] ?? '');
            $genres[] = ['code' => $code, 'label' => $label, 'kind' => isset(self::KNOWN[$kind]) ? $kind : '',
                'keys' => array_values(array_filter(array_map('strval', (array) ($genre['keys'] ?? [])))),
                'topics' => array_values(array_filter(array_map('strval', (array) ($genre['topics'] ?? [])))),
                'prefixes' => array_values(array_filter(array_map('strval', (array) ($genre['prefixes'] ?? [])))),
                'colour' => self::COLOURS[$i % count(self::COLOURS)]];
        }
        return $genres;
    }

    /**
     * The corners a library's genres take their books from.
     *
     * @param \stdClass $library
     * @return string[]
     */
    public static function from(\stdClass $library): array {
        $settings = json_decode((string) ($library->settings ?? ''), true) ?: [];
        $from = array_values(array_filter(
            array_map('strval', (array) ($settings['genresfrom'] ?? self::FROM)),
            fn($code) => catalogue::is_corner($code) && !self::is_code($code)
        ));
        return $from ?: self::FROM;
    }

    /**
     * Whether a code is a genre's: «g» and its number.
     *
     * @param string $code
     * @return bool
     */
    public static function is_code(string $code): bool {
        return (bool) preg_match('/^g([1-9]|1[0-2])$/', $code);
    }

    /**
     * Keeps a library's genres and where they take their books from.
     *
     * @param \stdClass $library
     * @param array[] $genres Each with label, kind, topics (labels, which give its keys; keys alone also do) and
     *     prefixes; the code is kept if it has one, so the corner keeps its name, place and order.
     * @param string[]|null $from Null leaves it as it is.
     */
    public static function save(\stdClass $library, array $genres, ?array $from = null): void {
        global $DB;
        $settings = json_decode((string) $DB->get_field(self::T . 'library', 'settings', ['id' => $library->id]), true) ?: [];
        $used = [];
        $kept = [];
        foreach ($genres as $genre) {
            $code = (string) ($genre['code'] ?? '');
            if (self::is_code($code) && !isset($used[$code])) {
                $used[$code] = true;
            }
        }
        foreach (array_slice(array_values($genres), 0, self::MAX) as $genre) {
            $label = \core_text::substr(trim((string) ($genre['label'] ?? '')), 0, 60);
            if ($label === '') {
                continue;
            }
            $code = (string) ($genre['code'] ?? '');
            if (!self::is_code($code) || isset($kept[$code])) {
                $code = '';
                for ($n = 1; $n <= self::MAX && $code === ''; $n++) {
                    $code = isset($used['g' . $n]) ? '' : 'g' . $n;
                }
                $used[$code] = true;
            }
            $keys = array_map('strval', (array) ($genre['keys'] ?? []));
            $topics = [];
            foreach ((array) ($genre['topics'] ?? []) as $topic) {
                $topic = \core_text::substr(trim((string) $topic), 0, 80);
                if ($topic !== '' && subjects::key($topic) !== '') {
                    $keys[] = subjects::key($topic);
                    $topics[subjects::key($topic)] = $topic;
                }
            }
            $prefixes = [];
            foreach ((array) ($genre['prefixes'] ?? []) as $prefix) {
                $prefix = \core_text::strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $prefix)));
                if ($prefix !== '') {
                    $prefixes[] = \core_text::substr($prefix, 0, 20);
                }
            }
            $kind = (string) ($genre['kind'] ?? '');
            $kept[$code] = ['code' => $code, 'label' => $label, 'kind' => isset(self::KNOWN[$kind]) ? $kind : '',
                'keys' => array_values(array_unique(array_filter($keys))), 'topics' => array_values($topics),
                'prefixes' => array_values(array_unique($prefixes))];
        }
        $settings['genres'] = array_values($kept);
        if ($from !== null) {
            $settings['genresfrom'] = array_values(array_filter(
                array_map('strval', $from),
                fn($code) => catalogue::is_corner($code) && !self::is_code($code)
            ));
        }
        $DB->update_record(self::T . 'library', (object) ['id' => $library->id, 'settings' => json_encode($settings),
            'timemodified' => time()]);
        $library->settings = json_encode($settings);
    }

    /**
     * The topics of a centre that a genre's keys name.
     *
     * @param int $entityid
     * @param string[] $keys
     * @return int[] Their ids.
     */
    public static function subject_ids(int $entityid, array $keys): array {
        if (!$keys) {
            return [];
        }
        // Every topic of each key (two not yet joined may share one), once a request: a page asks for every genre.
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $bykey = $cache->get('genresubjects_' . $entityid);
        if ($bykey === false) {
            $bykey = [];
            foreach (subjects::all($entityid) as $subject) {
                $bykey[subjects::key($subject['label'])][] = (int) $subject['id'];
            }
            $cache->set('genresubjects_' . $entityid, $bykey);
        }
        $ids = [];
        foreach ($keys as $key) {
            array_push($ids, ...($bykey[$key] ?? []));
        }
        return $ids;
    }

    /**
     * The SQL that says a record is in a genre: one of its topics, or a shelf mark that starts like it, and in a corner of
     * stories.
     *
     * @param \stdClass $library
     * @param array $genre As of() gives it.
     * @param string $field The record's id, as r.id.
     * @param string $name Prefix of the parameters.
     * @return array SQL and its parameters.
     */
    public static function condition(\stdClass $library, array $genre, string $field, string $name): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $params = [$name . 'e1' => $entityid, $name . 'e2' => $entityid, $name . 'e3' => $entityid];
        $any = [];
        $ids = self::subject_ids($entityid, $genre['keys']);
        if ($ids) {
            [$in, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, $name . 's');
            $any[] = "$field IN (SELECT {$name}sr.recordid FROM {" . self::T . "recauth} {$name}sr
                                  WHERE {$name}sr.authorityid $in)";
            $params += $inparams;
        }
        if ($genre['prefixes']) {
            $classes = [];
            $marks = [];
            foreach ($genre['prefixes'] as $i => $prefix) {
                $classes[] = $DB->sql_like("{$name}pa.code", ':' . $name . 'pc' . $i, false);
                $marks[] = $DB->sql_like("{$name}pi.callnumber1", ':' . $name . 'pm' . $i, false);
                $params[$name . 'pc' . $i] = $DB->sql_like_escape($prefix) . '%';
                $params[$name . 'pm' . $i] = $DB->sql_like_escape($prefix) . '%';
            }
            $any[] = "$field IN (SELECT {$name}pr.recordid FROM {" . self::T . "recauth} {$name}pr
                                   JOIN {" . self::T . "authority} {$name}pa ON {$name}pa.id = {$name}pr.authorityid
                                  WHERE {$name}pa.entityid = :{$name}e1 AND {$name}pa.kind = 'class'
                                        AND (" . implode(' OR ', $classes) . "))";
            $any[] = "$field IN (SELECT {$name}pi.recordid FROM {" . self::T . "item} {$name}pi
                                   JOIN {" . self::T . "library} {$name}pl ON {$name}pl.id = {$name}pi.libraryid
                                  WHERE {$name}pl.entityid = :{$name}e2 AND (" . implode(' OR ', $marks) . "))";
        }
        if (!$any) {
            return ['1 = 0', []];
        }
        $from = [];
        foreach (self::from($library) as $i => $code) {
            [$incorner, $cornerparams] = catalogue::class_condition("{$name}fa.code", $code, $name . 'f' . $i);
            $from[] = $incorner;
            $params += $cornerparams;
        }
        $sql = '((' . implode(' OR ', $any) . ") AND $field IN (SELECT {$name}fr.recordid FROM {" . self::T . "recauth} {$name}fr
                     JOIN {" . self::T . "authority} {$name}fa ON {$name}fa.id = {$name}fr.authorityid
                    WHERE {$name}fa.entityid = :{$name}e3 AND {$name}fa.kind = 'class' AND (" . implode(' OR ', $from) . ')))';
        return [$sql, $params];
    }

    /**
     * The SQL that says a record is in any genre of the library.
     *
     * @param \stdClass $library
     * @param string $field
     * @param string $name
     * @return array SQL and its parameters; '1 = 0' with no genres.
     */
    public static function any_condition(\stdClass $library, string $field, string $name): array {
        $sql = [];
        $params = [];
        foreach (self::of($library) as $i => $genre) {
            [$one, $oneparams] = self::condition($library, $genre, $field, $name . $i . 'x');
            $sql[] = $one;
            $params += $oneparams;
        }
        return $sql ? ['(' . implode(' OR ', $sql) . ')', $params] : ['1 = 0', []];
    }

    /**
     * The records of each genre of a library, or of those among some records.
     *
     * @param \stdClass $library
     * @param int[]|null $among Only these records (the results of a search): each genre was asked for all its records on
     *     every search, also when it found nothing.
     * @return array Code => record ids (as keys).
     */
    public static function records(\stdClass $library, ?array $among = null): array {
        global $DB;
        $records = [];
        if ($among === []) {
            return array_fill_keys(array_column(self::of($library), 'code'), []);
        }
        foreach (self::of($library) as $genre) {
            [$sql, $params] = self::condition($library, $genre, 'r.id', 'gr');
            $records[$genre['code']] = [];
            foreach ($among === null ? [null] : array_chunk($among, 1000) as $chunk) {
                $where = 'r.entityid = :gentity AND ' . $sql;
                $more = [];
                if ($chunk !== null) {
                    [$in, $more] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'gam');
                    $where .= " AND r.id $in";
                }
                $records[$genre['code']] += array_flip(array_map('intval', $DB->get_fieldset_sql(
                    'SELECT r.id FROM {' . self::T . 'record} r WHERE ' . $where,
                    ['gentity' => $library->entityid] + $params + $more
                )));
            }
        }
        return $records;
    }

    /**
     * The topics most used in the library's stories, to help name a genre.
     *
     * @param \stdClass $library
     * @param int $limit
     * @return string[] Their labels, the most used first.
     */
    public static function story_topics(\stdClass $library, int $limit = 8): array {
        global $DB;
        $from = [];
        $params = ['entityid' => $library->entityid, 'sentityid' => $library->entityid];
        foreach (self::from($library) as $i => $code) {
            [$incorner, $cornerparams] = catalogue::class_condition('ca.code', $code, 'st' . $i);
            $from[] = $incorner;
            $params += $cornerparams;
        }
        $rows = $DB->get_records_sql(
            "SELECT s.id, s.label, COUNT(DISTINCT rs.recordid) AS records
               FROM {" . self::T . "recauth} rs
               JOIN {" . self::T . "authority} s ON s.id = rs.authorityid AND s.kind = 'subject' AND s.entityid = :sentityid
              WHERE rs.recordid IN (SELECT cr.recordid FROM {" . self::T . "recauth} cr
                                      JOIN {" . self::T . "authority} ca ON ca.id = cr.authorityid
                                     WHERE ca.entityid = :entityid AND ca.kind = 'class' AND (" . implode(' OR ', $from) . "))
           GROUP BY s.id, s.label
           ORDER BY COUNT(DISTINCT rs.recordid) DESC, s.label",
            $params,
            0,
            $limit
        );
        return array_values(array_map(fn($row) => (string) $row->label, $rows));
    }

    /**
     * What the library could make genres of: the known genres whose topics its stories have, with how many books each
     * would take, those it has already made left out.
     *
     * @param \stdClass $library
     * @return array[] key, label, topics (their labels) and count, the largest first.
     */
    public static function proposals(\stdClass $library): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $have = [];
        foreach (self::of($library) as $genre) {
            foreach ($genre['keys'] as $key) {
                $have[$key] = true;
            }
        }
        $bykey = [];
        foreach (subjects::all($entityid) as $subject) {
            $bykey[subjects::key($subject['label'])][] = $subject;
        }
        $proposals = [];
        foreach (self::KNOWN as $kind => $keys) {
            $found = array_values(array_filter($keys, fn($key) => isset($bykey[$key]) && !isset($have[$key])));
            if (!$found) {
                continue;
            }
            $genre = ['code' => 'g0', 'label' => '', 'keys' => $found, 'prefixes' => []];
            [$sql, $params] = self::condition($library, $genre, 'r.id', 'gp');
            $count = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . self::T . 'record} r WHERE r.entityid = :gentity AND ' . $sql,
                ['gentity' => $entityid] + $params
            );
            if ($count < self::ENOUGH) {
                continue;
            }
            $topics = [];
            foreach ($found as $key) {
                foreach ($bykey[$key] as $subject) {
                    $topics[] = $subject['label'];
                }
            }
            $proposals[] = ['key' => $kind, 'label' => get_string('genre' . $kind, 'local_oksigeniaayvurenda'),
                'topics' => array_values(array_unique($topics)), 'count' => $count];
        }
        usort($proposals, fn($a, $b) => [-$a['count'], $a['label']] <=> [-$b['count'], $b['label']]);
        return $proposals;
    }
}
