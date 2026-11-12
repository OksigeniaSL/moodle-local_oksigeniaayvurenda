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
 * The subjects of a centre as one vocabulary: «Misterio», «misterios» and «mistrios» are one subject.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * A subject typed, imported or brought by a catalogue is the one the centre already has when it is the same word with
 * other capitals, accents or number; what differs by a letter is not joined on its own, but shown to whoever catalogues,
 * who joins it with a touch.
 */
final class subjects {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * The key of a subject: without capitals or accents, each word in the singular («Misterios» and «misterio» give the
     * same), but the ñ is not an n, and the marks of other alphabets stay. Simple and on purpose: it only decides when two
     * labels are one subject.
     *
     * @param string $label
     * @return string
     */
    public static function key(string $label): string {
        if (class_exists(\Normalizer::class)) {
            $label = (string) \Normalizer::normalize($label, \Normalizer::FORM_C);
        }
        // In alphabets whose marks make other words (Thai, Devanagari), the marks stay: only capitals and punctuation go.
        if (preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $label)) {
            return trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtolower($label, 'UTF-8')));
        }
        // The ñ is a letter of its own, not an n with an accent: «Año» is not «Ano», nor «Peña» «Pena».
        $label = str_replace(['ñ', 'Ñ'], 'nqq', $label);
        $words = [];
        foreach (text::words($label) as $word) {
            if (strlen($word) > 4 && str_ends_with($word, 'es') && strpbrk(substr($word, -3, 1), 'lrndzj') !== false) {
                $word = substr($word, 0, -2);
            } else if (strlen($word) > 3 && str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
                $word = substr($word, 0, -1);
            }
            $words[] = $word;
        }
        return implode(' ', $words);
    }

    /**
     * The subject of a centre that a label is, if it has it.
     *
     * @param int $entityid
     * @param string $label
     * @return int|null
     */
    public static function find(int $entityid, string $label): ?int {
        $key = self::key($label);
        return $key === '' ? null : (self::keys($entityid)[$key] ?? null);
    }

    /**
     * Notes a subject just made, so that the next one of the same request finds it.
     *
     * @param int $entityid
     * @param string $label
     * @param int $id
     */
    public static function made(int $entityid, string $label, int $id): void {
        $keys = self::keys($entityid);
        $keys[self::key($label)] = $id;
        \cache::make('local_oksigeniaayvurenda', 'access')->set('subjects_' . $entityid, $keys);
        self::forget($entityid);
    }

    /**
     * The subjects of a centre that start like what is typed, the most used first; with nothing, those a letter away.
     *
     * @param int $entityid
     * @param string $typed
     * @param int $limit
     * @return array[] id, label and count (records).
     */
    public static function suggest(int $entityid, string $typed, int $limit = 8): array {
        $words = text::words($typed);
        if (!$words) {
            return [];
        }
        $index = self::index($entityid);
        // First what starts like it, which is quick; only when nothing does, the near ones, among the most used: a word
        // that matched nothing went through every subject with the typo check, over a second a key with a university's.
        $found = [];
        foreach ($index as [$id, $label, $count, $normal]) {
            if (self::starts($words, $normal)) {
                $found[] = ['id' => $id, 'label' => $label, 'count' => $count];
            }
        }
        if (!$found) {
            foreach (array_slice($index, 0, self::NEAREST) as [$id, $label, $count, $normal]) {
                if (self::close($words, explode(' ', $normal))) {
                    $found[] = ['id' => $id, 'label' => $label, 'count' => $count];
                }
            }
        }
        usort($found, fn($a, $b) => [-$a['count'], $a['label']] <=> [-$b['count'], $b['label']]);
        return array_slice($found, 0, $limit);
    }

    /** @var int The most used subjects a typo is looked for among. */
    private const NEAREST = 20000;

    /**
     * Whether each word typed starts a word of a subject.
     *
     * @param string[] $words Normalised.
     * @param string $normal The subject, normalised.
     * @return bool
     */
    public static function starts(array $words, string $normal): bool {
        foreach ($words as $word) {
            if (!str_contains(' ' . $normal, ' ' . $word)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether each word typed starts a word of a subject or is a typo away from one.
     *
     * @param string[] $words Normalised.
     * @param string[] $own The subject's words.
     * @return bool
     */
    private static function close(array $words, array $own): bool {
        foreach ($words as $word) {
            $hit = false;
            foreach ($own as $theirs) {
                // Only suggestions: what text::near takes for a typo (two letters in a long word) may come up.
                if (str_starts_with($theirs, $word) || text::near($word, $theirs) !== null) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }

    /**
     * The subjects of a centre, the most used first, each with its words normalised, kept until the catalogue changes:
     * what the suggestions go through at each key.
     *
     * @param int $entityid
     * @return array[] [id, label, count, normalised].
     */
    public static function index(int $entityid): array {
        $cache = \cache::make('local_oksigeniaayvurenda', 'vocabulary');
        $index = $cache->get('subjects' . $entityid);
        if ($index === false) {
            $index = array_map(
                fn($subject) => [$subject['id'], $subject['label'], $subject['count'], text::normalise($subject['label'])],
                self::all($entityid)
            );
            $cache->set('subjects' . $entityid, $index);
        }
        return $index;
    }

    /**
     * The subjects changed: the suggestions read them again.
     *
     * @param int $entityid
     */
    public static function forget(int $entityid): void {
        \cache::make('local_oksigeniaayvurenda', 'vocabulary')->delete('subjects' . $entityid);
    }

    /**
     * The subjects of a centre with how many records each has, the most used first.
     *
     * @param int $entityid
     * @return array[] id, label, count.
     */
    public static function all(int $entityid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT a.id, a.label, COUNT(ra.id) AS records
               FROM {" . self::T . "authority} a
          LEFT JOIN {" . self::T . "recauth} ra ON ra.authorityid = a.id
              WHERE a.entityid = :entityid AND a.kind = 'subject'
           GROUP BY a.id, a.label
           ORDER BY COUNT(ra.id) DESC, a.label",
            ['entityid' => $entityid]
        );
        return array_values(array_map(fn($row) => ['id' => (int) $row->id, 'label' => (string) $row->label,
            'count' => (int) $row->records], $rows));
    }

    /**
     * The subjects that look like one: the same word with other capitals, accents or number, or a letter away (a typo).
     * Each group starts with the most used, which is what it would be joined into.
     *
     * @param int $entityid
     * @param array[]|null $all The centre's subjects, when already read (all()).
     * @return array[] Groups, each a list of id, label and count.
     */
    public static function alike(int $entityid, ?array $all = null): array {
        $all ??= self::all($entityid);
        $bykey = [];
        foreach ($all as $subject) {
            $bykey[self::key($subject['label'])][] = $subject;
        }
        // A letter away: compared only within a few words of the same start and length, not every one with every one.
        // By position, never by the key itself: a subject that is a number («1914») would turn into an integer key, and
        // comparing it with its own string never matched (in the classroom library the page fell over).
        $keys = array_map('strval', array_keys($bykey));
        $parent = $keys ? range(0, count($keys) - 1) : [];
        $find = function (int $i) use (&$parent): int {
            $root = $i;
            while ($parent[$root] !== $root) {
                $root = $parent[$root];
            }
            while ($parent[$i] !== $root) {
                [$i, $parent[$i]] = [$parent[$i], $root];
            }
            return $root;
        };
        $buckets = [];
        foreach ($keys as $i => $key) {
            // With figures, Arabic or Roman, a letter is no typo: «1914» and «1915», «siglo xx» and «siglo xi» are other
            // subjects.
            if (strlen($key) >= 5 && !preg_match('/\d|(^| )[ivxlcdm]{1,5}( |$)/', $key)) {
                $buckets[substr($key, 0, 2)][] = $i;
            }
        }
        // Two keys a typo apart share the key less one letter (a letter more, less, other or two swapped): each key and each of
        // those is written as a number with the key's place, sorted, and only the keys that share one are compared. Every
        // one with every one in a bucket was 30 million comparisons, almost a minute, with a university's 146,000 subjects.
        foreach ($buckets as $bucket) {
            $codes = [];
            foreach ($bucket as $place => $i) {
                $key = $keys[$i];
                $codes[] = (crc32($key) << 20) | $place;
                for ($p = 2, $length = strlen($key); $p < $length; $p++) {
                    $codes[] = (crc32(substr($key, 0, $p) . substr($key, $p + 1)) << 20) | $place;
                }
            }
            sort($codes);
            $count = count($codes);
            for ($start = 0; $start < $count; $start = $end) {
                $hash = $codes[$start] >> 20;
                $end = $start + 1;
                while ($end < $count && ($codes[$end] >> 20) === $hash) {
                    $end++;
                }
                for ($x = $start; $x < $end; $x++) {
                    for ($y = $x + 1; $y < $end; $y++) {
                        [$i, $j] = [$bucket[$codes[$x] & 0xFFFFF], $bucket[$codes[$y] & 0xFFFFF]];
                        if ($i !== $j && $find($i) !== $find($j)) {
                            [$a, $b] = [$keys[$i], $keys[$j]];
                            if (abs(strlen($a) - strlen($b)) <= 1 && self::typo($a, $b)) {
                                $parent[$find($i)] = $find($j);
                            }
                        }
                    }
                }
            }
        }
        $groups = [];
        foreach ($keys as $i => $key) {
            foreach ($bykey[$key] as $subject) {
                $groups[$find($i)][] = $subject;
            }
        }
        $groups = array_values(array_filter($groups, fn($group) => count($group) > 1));
        foreach ($groups as &$group) {
            usort($group, fn($a, $b) => [-$a['count'], $a['label']] <=> [-$b['count'], $b['label']]);
        }
        unset($group);
        usort($groups, fn($a, $b) => array_sum(array_column($b, 'count')) <=> array_sum(array_column($a, 'count')));
        return $groups;
    }

    /**
     * Whether two keys are a typo apart: a letter more, less or other, or two letters next to each other swapped.
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    private static function typo(string $a, string $b): bool {
        if (levenshtein($a, $b) === 1) {
            return true;
        }
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $diff = [];
        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] !== $b[$i]) {
                $diff[] = $i;
            }
        }
        return count($diff) === 2 && $diff[1] === $diff[0] + 1 && $a[$diff[0]] === $b[$diff[1]] && $a[$diff[1]] === $b[$diff[0]];
    }

    /**
     * Joins subjects of a centre into one: their records take that one, and they go.
     *
     * @param int $entityid
     * @param int $keepid
     * @param int[] $ids
     * @return int How many records changed.
     */
    public static function join(int $entityid, int $keepid, array $ids): int {
        global $DB;
        $keep = $DB->get_record(self::T . 'authority', ['id' => $keepid, 'entityid' => $entityid, 'kind' => 'subject']);
        $ids = array_values(array_diff(array_map('intval', $ids), [$keepid]));
        if (!$keep || !$ids) {
            return 0;
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        // Only this centre's subjects: an id of another centre is left alone.
        $ids = array_map('intval', $DB->get_fieldset_select(
            self::T . 'authority',
            'id',
            "id $in AND entityid = :entityid AND kind = 'subject'",
            $params + ['entityid' => $entityid]
        ));
        if (!$ids) {
            return 0;
        }
        $transaction = $DB->start_delegated_transaction();
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = array_map('intval', $DB->get_fieldset_select(
            self::T . 'recauth',
            'DISTINCT recordid',
            "authorityid $in",
            $params
        ));
        foreach ($DB->get_records_select(self::T . 'recauth', "authorityid $in", $params) as $link) {
            if ($DB->record_exists(self::T . 'recauth', ['recordid' => $link->recordid, 'authorityid' => $keepid])) {
                $DB->delete_records(self::T . 'recauth', ['id' => $link->id]);
            } else {
                $DB->set_field(self::T . 'recauth', 'authorityid', $keepid, ['id' => $link->id]);
            }
        }
        $DB->delete_records_select(self::T . 'authority', "id $in", $params);
        self::reword($records);
        $transaction->allow_commit();
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('subjects_' . $entityid);
        self::forget($entityid);
        catalogue::purge($entityid);
        return count($records);
    }

    /**
     * Gives a subject another name; if the centre has another subject with that name, they become one.
     *
     * @param int $entityid
     * @param int $id
     * @param string $label
     * @return int The subject it is now.
     */
    public static function rename(int $entityid, int $id, string $label): int {
        global $DB;
        $label = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $label)), 0, 1333);
        $subject = $DB->get_record(self::T . 'authority', ['id' => $id, 'entityid' => $entityid, 'kind' => 'subject']);
        if (!$subject || $label === '') {
            return $subject ? (int) $subject->id : 0;
        }
        $other = self::find($entityid, $label);
        if ($other && $other !== $id) {
            $DB->set_field(self::T . 'authority', 'label', $label, ['id' => $other]);
            self::join($entityid, $other, [$id]);
            return $other;
        }
        $DB->update_record(self::T . 'authority', (object) ['id' => $id, 'label' => $label, 'timemodified' => time()]);
        self::reword(array_map('intval', $DB->get_fieldset_select(
            self::T . 'recauth',
            'DISTINCT recordid',
            'authorityid = ?',
            [$id]
        )));
        \cache::make('local_oksigeniaayvurenda', 'access')->delete('subjects_' . $entityid);
        self::forget($entityid);
        catalogue::purge($entityid);
        return $id;
    }

    /**
     * What the search finds of each record, again, with the names its subjects have now.
     *
     * @param int[] $recordids
     */
    private static function reword(array $recordids): void {
        global $DB;
        foreach (array_chunk($recordids, 500) as $chunk) {
            // The record changed too: a harvest asking since a date (OAI-PMH) sees its new subjects.
            $now = time();
            foreach ($DB->get_records_list(self::T . 'record', 'id', $chunk, '', 'id, title, subtitle') as $record) {
                $DB->update_record(self::T . 'record', (object) ['id' => $record->id, 'searchtext' => corrections::words($record),
                    'timemodified' => $now]);
            }
        }
    }

    /**
     * The subjects of a centre by their key, kept for the request.
     *
     * @param int $entityid
     * @return array key => id (the most used of each key).
     */
    private static function keys(int $entityid): array {
        $cache = \cache::make('local_oksigeniaayvurenda', 'access');
        $keys = $cache->get('subjects_' . $entityid);
        if ($keys === false) {
            $keys = [];
            foreach (self::all($entityid) as $subject) {
                $keys[self::key($subject['label'])] ??= $subject['id'];
            }
            $cache->set('subjects_' . $entityid, $keys);
        }
        return $keys;
    }
}
