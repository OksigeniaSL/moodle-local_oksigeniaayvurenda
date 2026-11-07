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
     * same). Simple and on purpose: it only decides when two labels are one subject.
     *
     * @param string $label
     * @return string
     */
    public static function key(string $label): string {
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
        $all = self::all($entityid);
        $starts = [];
        $near = [];
        foreach ($all as $subject) {
            $own = text::words($subject['label']);
            $every = true;
            $close = true;
            foreach ($words as $word) {
                $hit = false;
                $almost = false;
                foreach ($own as $theirs) {
                    if (str_starts_with($theirs, $word)) {
                        $hit = true;
                        break;
                    }
                    // Only suggestions: what text::near takes for a typo (two letters in a long word) may come up.
                    $almost = $almost || text::near($word, $theirs) !== null;
                }
                $every = $every && $hit;
                $close = $close && ($hit || $almost);
            }
            if ($every) {
                $starts[] = $subject;
            } else if ($close) {
                $near[] = $subject;
            }
        }
        $found = $starts ?: $near;
        usort($found, fn($a, $b) => [-$a['count'], $a['label']] <=> [-$b['count'], $b['label']]);
        return array_slice($found, 0, $limit);
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
     * @return array[] Groups, each a list of id, label and count.
     */
    public static function alike(int $entityid): array {
        $all = self::all($entityid);
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
        foreach ($buckets as $bucket) {
            $count = count($bucket);
            for ($x = 0; $x < $count; $x++) {
                for ($y = $x + 1; $y < $count; $y++) {
                    [$a, $b] = [$keys[$bucket[$x]], $keys[$bucket[$y]]];
                    if (abs(strlen($a) - strlen($b)) <= 1 && self::typo($a, $b)) {
                        $parent[$find($bucket[$x])] = $find($bucket[$y]);
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
            foreach ($DB->get_records_list(self::T . 'record', 'id', $chunk, '', 'id, title, subtitle') as $record) {
                $DB->set_field(self::T . 'record', 'searchtext', corrections::words($record), ['id' => $record->id]);
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
