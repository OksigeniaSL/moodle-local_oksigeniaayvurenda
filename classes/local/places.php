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
 * The places of a library.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * The places of a library (its locations): what the programme before called them, typed by hand («Infantil Exterior,
 * 4ª estantería, 4ª balda»), becomes a name a child understands («The round shelf»); two that are the same are joined;
 * new ones are made. The old names stay with each place, so the staff still find it by them.
 */
final class places {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /** @var string[] The tables whose rows say a place, besides the copies. */
    private const POINTED = ['item', 'stocktake', 'stockread'];

    /**
     * The key of a place's name: without capitals, accents, ordinal marks or punctuation. Two places with the same key
     * are one.
     *
     * @param string $label
     * @return string
     */
    public static function key(string $label): string {
        $label = str_replace(['ª', 'º', '°'], '', $label);
        return implode(' ', text::words(text::normalise($label)));
    }

    /**
     * The places of a library: those with copies of it, and those of its centre that have no copies anywhere (made new,
     * or emptied); each with its name, its old names, its copies and titles here, and the others that may be the same.
     *
     * @param \stdClass $library
     * @return array[] id, label, raw (as it was written), old (names), copies, titles, colour, twins (ids of places that may
     *     be the same, unless said apart), noname (its name is still the programme's before, never given one for readers)
     *     and group (what its name shares with others up to its first comma, or ''); and id 0 for the copies without a
     *     place.
     */
    public static function all(\stdClass $library): array {
        global $DB;
        $counts = $DB->get_records_sql(
            "SELECT COALESCE(i.locationid, 0) AS place, COUNT(1) AS copies, COUNT(DISTINCT i.recordid) AS titles
               FROM {" . self::T . "item} i
              WHERE i.libraryid = :libraryid AND i.status <> 'withdrawn'
           GROUP BY COALESCE(i.locationid, 0)",
            ['libraryid' => $library->id]
        );
        $used = $DB->get_fieldset_sql(
            "SELECT DISTINCT i.locationid FROM {" . self::T . "item} i
               JOIN {" . self::T . "authority} a ON a.id = i.locationid
              WHERE a.entityid = :entityid AND i.status <> 'withdrawn'",
            ['entityid' => $library->entityid]
        );
        $used = array_flip(array_map('intval', $used));
        $places = [];
        foreach ($DB->get_records(self::T . 'authority', ['entityid' => $library->entityid, 'kind' => 'location']) as $place) {
            $here = $counts[$place->id] ?? null;
            if (!$here && isset($used[(int) $place->id])) {
                continue;
            }
            $label = catalogue::tidy_label((string) $place->label);
            $extra = json_decode((string) ($place->extra ?? ''), true) ?: [];
            $places[] = ['id' => (int) $place->id, 'label' => $label, 'raw' => trim((string) $place->label),
                'old' => self::old($place),
                'apart' => array_map('intval', (array) ($extra['apart'] ?? [])),
                'noname' => !self::old($place) && empty($extra['named']) && self::coded($label),
                'group' => trim((string) strstr($label, ',', true)),
                'copies' => (int) ($here->copies ?? 0), 'titles' => (int) ($here->titles ?? 0),
                'colour' => \local_oksigeniaayvurenda\core\spine::place_colour($label), 'key' => self::key($label)];
        }
        // The same, by the key of its name or of an old one; or the same words in another order, when no number tells them
        // apart. A place renamed for readers is still the twin of the one written like its old name.
        $same = function (string $key): string {
            if (preg_match('/\d/', $key)) {
                return $key;
            }
            $words = array_unique(explode(' ', $key));
            sort($words);
            return implode(' ', $words);
        };
        $keys = [];
        foreach ($places as $i => $place) {
            $names = array_merge([$place['label']], $place['old']);
            $keys[$i] = array_unique(array_map(fn($name) => $same(self::key($name)), $names));
        }
        foreach ($places as $i => $place) {
            $places[$i]['twins'] = [];
            foreach ($places as $j => $other) {
                if (
                    $i !== $j && array_intersect($keys[$i], $keys[$j]) && !in_array($other['id'], $place['apart'], true)
                        && !in_array($place['id'], $other['apart'], true)
                ) {
                    $places[$i]['twins'][] = $other['id'];
                }
            }
        }
        // A part before a comma names a room only when two or more share it.
        $groups = array_count_values(array_filter(array_column($places, 'group')));
        foreach ($places as $i => $place) {
            $places[$i]['group'] = ($groups[$place['group']] ?? 0) > 1 ? $place['group'] : '';
        }
        $collator = new \Collator(current_language());
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        usort($places, fn($a, $b) => $collator->compare($a['label'], $b['label']));
        $places = array_map(function ($place) {
            unset($place['key'], $place['apart']);
            return $place;
        }, $places);
        if (isset($counts[0])) {
            $places[] = ['id' => 0, 'label' => '', 'raw' => '', 'old' => [], 'copies' => (int) $counts[0]->copies,
                'titles' => (int) $counts[0]->titles, 'colour' => null, 'twins' => [], 'noname' => false, 'group' => ''];
        }
        return $places;
    }

    /**
     * Whether a name looks like a programme's code for a place, not like a name for readers: with a comma, an ordinal or a
     * figure («Infantil Exterior, 4ª estantería, 4ª balda», «Estantería 7»). «Consulta» or «Poesía» are good as they are.
     *
     * @param string $label
     * @return bool
     */
    public static function coded(string $label): bool {
        return (bool) preg_match('/[,ªº°\d]/u', $label);
    }

    /**
     * Two places that look the same and are not: they are not offered as twins again.
     *
     * @param int $entityid
     * @param int $a
     * @param int $b
     */
    public static function apart(int $entityid, int $a, int $b): void {
        global $DB;
        foreach ([[$a, $b], [$b, $a]] as [$one, $other]) {
            $place = self::get($entityid, $one);
            if ($place) {
                $extra = json_decode((string) $place->extra, true) ?: [];
                $extra['apart'] = array_values(array_unique(array_merge(
                    array_map('intval', (array) ($extra['apart'] ?? [])),
                    [$other]
                )));
                $DB->set_field(self::T . 'authority', 'extra', json_encode($extra), ['id' => $one]);
            }
        }
    }

    /**
     * Removes a place that has no copies anywhere.
     *
     * @param int $entityid
     * @param int $id
     * @return bool
     */
    public static function remove(int $entityid, int $id): bool {
        global $DB;
        if (!self::get($entityid, $id) || $DB->record_exists(self::T . 'item', ['locationid' => $id])) {
            return false;
        }
        foreach (['stocktake', 'stockread'] as $table) {
            $DB->set_field(self::T . $table, 'locationid', null, ['locationid' => $id]);
        }
        $DB->delete_records(self::T . 'authority', ['id' => $id]);
        self::changed($entityid);
        return true;
    }

    /**
     * What tells two names apart, marked: what both have at the start and at the end stays plain, the middle of the second
     * in a <mark>; the same words in another order, unmarked.
     *
     * @param string $a
     * @param string $b
     * @return string HTML of the second name.
     */
    public static function marked(string $a, string $b): string {
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $start = 0;
        while ($start < count($x) && $start < count($y) && $x[$start] === $y[$start]) {
            $start++;
        }
        $end = 0;
        while ($end < count($x) - $start && $end < count($y) - $start && $x[count($x) - 1 - $end] === $y[count($y) - 1 - $end]) {
            $end++;
        }
        $middle = implode('', array_slice($y, $start, count($y) - $start - $end));
        if (count($y) - $start - $end > (int) (count($y) / 2)) {
            return s($b);
        }
        // A space, or what only the first has, would not be seen: a sign says where.
        $shown = trim($middle) === '' ? '␣' : $middle;
        return s(implode('', array_slice($y, 0, $start))) . '<mark>' . s($shown) . '</mark>'
            . s(implode('', array_slice($y, count($y) - $end)));
    }

    /**
     * The old names of a place.
     *
     * @param \stdClass $place
     * @return string[]
     */
    private static function old(\stdClass $place): array {
        $extra = json_decode((string) ($place->extra ?? ''), true) ?: [];
        return array_values(array_filter(array_map('strval', (array) ($extra['old'] ?? []))));
    }

    /**
     * A place of a centre, or null.
     *
     * @param int $entityid
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $entityid, int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'authority', ['id' => $id, 'entityid' => $entityid, 'kind' => 'location']) ?: null;
    }

    /**
     * Gives a place another name, and keeps the one it had among its old names. If the centre has another place with that
     * name, they become one.
     *
     * @param int $entityid
     * @param int $id
     * @param string $label
     * @return int The place it is now (0 if there is none).
     */
    public static function rename(int $entityid, int $id, string $label): int {
        global $DB;
        $label = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $label)), 0, 200);
        $place = self::get($entityid, $id);
        if (!$place || $label === '') {
            return $place ? $id : 0;
        }
        $other = self::find($entityid, $label);
        if ($other && $other !== $id) {
            self::join($entityid, $other, [$id]);
            $DB->set_field(self::T . 'authority', 'label', $label, ['id' => $other]);
            self::changed($entityid);
            return $other;
        }
        if ($place->label === $label) {
            $extra = json_decode((string) $place->extra, true) ?: [];
            $extra['named'] = 1;
            $DB->set_field(self::T . 'authority', 'extra', json_encode($extra), ['id' => $id]);
        } else {
            $extra = json_decode((string) $place->extra, true) ?: [];
            $extra['old'] = array_values(array_unique(array_merge(self::old($place), [(string) $place->label])));
            $extra['named'] = 1;
            $DB->update_record(self::T . 'authority', (object) ['id' => $id, 'label' => $label, 'extra' => json_encode($extra),
                'timemodified' => time()]);
            self::changed($entityid);
        }
        return $id;
    }

    /**
     * Makes some places one: their copies, the stocktakes that covered them and their old names go to the one kept.
     *
     * @param int $entityid
     * @param int $keepid
     * @param int[] $ids
     * @param string $batch To undo it (unjoin): the copies moved and the places gone are kept with it; '' not to.
     * @return int The copies that moved.
     */
    public static function join(int $entityid, int $keepid, array $ids, string $batch = ''): int {
        global $DB;
        $keep = self::get($entityid, $keepid);
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id !== $keepid && self::get($entityid, $id)));
        if (!$keep || !$ids) {
            return 0;
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $moved = $DB->count_records_select(self::T . 'item', "locationid $in", $params);
        $transaction = $DB->start_delegated_transaction();
        if ($batch !== '') {
            $now = time();
            $rows = $DB->get_records_select(self::T . 'item', "locationid $in", $params, '', 'id, libraryid, locationid');
            foreach (array_chunk($rows, 500) as $chunk) {
                $DB->insert_records(self::T . 'move', array_map(fn($item) => ['libraryid' => $item->libraryid, 'batch' => $batch,
                    'itemid' => $item->id, 'fromid' => $item->locationid, 'toid' => $keepid, 'timecreated' => $now], $chunk));
            }
        }
        foreach (self::POINTED as $table) {
            $DB->set_field_select(self::T . $table, 'locationid', $keepid, "locationid $in", $params);
        }
        $old = self::old($keep);
        foreach ($ids as $id) {
            $gone = self::get($entityid, $id);
            $old = array_merge($old, [(string) $gone->label], self::old($gone));
        }
        $extra = json_decode((string) $keep->extra, true) ?: [];
        $extra['old'] = array_values(array_unique(array_diff($old, [(string) $keep->label])));
        if ($batch !== '') {
            $extra['joined'][$batch] = array_values(array_map(fn($id) => (array) self::get($entityid, $id), $ids));
        }
        $DB->update_record(self::T . 'authority', (object) ['id' => $keepid, 'extra' => json_encode($extra),
            'timemodified' => time()]);
        $DB->delete_records_select(self::T . 'authority', "id $in", $params);
        $transaction->allow_commit();
        self::changed($entityid);
        return $moved;
    }

    /**
     * Undoes a join: the places gone come back with their names, and their copies to them, unless they moved since.
     *
     * @param int $entityid
     * @param string $batch
     * @return int The copies that went back.
     */
    public static function unjoin(int $entityid, string $batch): int {
        global $DB;
        $back = 0;
        foreach ($DB->get_records(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'location']) as $keep) {
            $extra = json_decode((string) $keep->extra, true) ?: [];
            if (empty($extra['joined'][$batch])) {
                continue;
            }
            $transaction = $DB->start_delegated_transaction();
            foreach ($extra['joined'][$batch] as $row) {
                if (!$DB->record_exists(self::T . 'authority', ['id' => $row['id']])) {
                    $DB->import_record(self::T . 'authority', (object) $row);
                }
                $extra['old'] = array_values(array_diff(
                    (array) ($extra['old'] ?? []),
                    [(string) $row['label']],
                    self::old((object) $row)
                ));
            }
            foreach ($DB->get_records(self::T . 'move', ['batch' => $batch]) as $move) {
                $now = $DB->get_field(self::T . 'item', 'locationid', ['id' => $move->itemid]);
                if ($now !== false && (int) $now === (int) $move->toid) {
                    $DB->set_field(self::T . 'item', 'locationid', $move->fromid, ['id' => $move->itemid]);
                    $back++;
                }
            }
            $DB->delete_records(self::T . 'move', ['batch' => $batch]);
            unset($extra['joined'][$batch]);
            $DB->set_field(self::T . 'authority', 'extra', json_encode($extra), ['id' => $keep->id]);
            $transaction->allow_commit();
            self::changed($entityid);
        }
        return $back;
    }

    /**
     * A new place, or the one the centre has with that name.
     *
     * @param int $entityid
     * @param string $label
     * @return int
     */
    public static function create(int $entityid, string $label): int {
        global $DB;
        $label = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $label)), 0, 200);
        if ($label === '') {
            return 0;
        }
        $found = self::find($entityid, $label);
        if ($found) {
            return $found;
        }
        $id = (int) $DB->insert_record(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'location',
            'label' => $label, 'timecreated' => time(), 'timemodified' => time()]);
        self::changed($entityid);
        return $id;
    }

    /**
     * The place of a centre with a name, by its key, or by one of its old names.
     *
     * @param int $entityid
     * @param string $label
     * @return int|null
     */
    public static function find(int $entityid, string $label): ?int {
        global $DB;
        $key = self::key($label);
        $old = null;
        $all = $DB->get_records(self::T . 'authority', ['entityid' => $entityid, 'kind' => 'location'], 'id', 'id, label, extra');
        foreach ($all as $p) {
            if (self::key((string) $p->label) === $key) {
                return (int) $p->id;
            }
            foreach (self::old($p) as $name) {
                $old = $old ?? (self::key($name) === $key ? (int) $p->id : null);
            }
        }
        return $old;
    }

    /**
     * After a change: what the screens keep of the places is asked again.
     *
     * @param int $entityid
     */
    private static function changed(int $entityid): void {
        catalogue::purge($entityid);
    }
}
