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
 * The class of a book while it is typed: a word («biología») brings the numbers that fit, a number brings its name.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

use local_oksigeniaayvurenda\core\text;

/**
 * Nobody knows the classification by heart: what the centre's own classes are called (those a program brought with their
 * names), what the library gave to books on that subject before, and the names of the main classes.
 */
final class classify {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniaayvurenda_';

    /**
     * What may go on the first line of the spine for what is being typed.
     *
     * @param \stdClass $library
     * @param string $typed A word or the start of a number.
     * @param int $limit
     * @return array[] code, label, count (books with it in the centre) and used (given before to books on that subject).
     */
    public static function suggest(\stdClass $library, string $typed, int $limit = 8): array {
        global $DB;
        $entityid = (int) $library->entityid;
        $typed = trim($typed);
        if ($typed === '' || \core_text::strlen($typed) > 100) {
            return [];
        }
        $classes = self::classes($entityid);
        $found = [];
        if (preg_match('/^\d/', $typed)) {
            // A number: those that start with it, the broader first.
            foreach ($classes as $code => $label) {
                if (str_starts_with((string) $code, $typed)) {
                    $found[(string) $code] = ['distance' => 0, 'exact' => 1, 'used' => 0];
                }
            }
        } else {
            $words = array_values(array_filter(text::words($typed), fn($word) => strlen($word) >= 2));
            if (!$words) {
                return [];
            }
            foreach ($classes as $code => $label) {
                $distance = self::fits($words, $label);
                if ($distance !== null) {
                    $exact = text::normalise($label) === implode(' ', $words) ? 0 : 1;
                    $found[(string) $code] = ['distance' => $distance, 'exact' => $exact, 'used' => 0];
                }
            }
            foreach (self::learned($entityid, $words) as $code => $used) {
                $found[$code] = ['distance' => $found[$code]['distance'] ?? 0, 'exact' => $found[$code]['exact'] ?? 1,
                    'used' => $used];
            }
        }
        if (!$found) {
            return [];
        }
        $counts = self::counts($entityid, array_map('strval', array_keys($found)));
        // A number: the broader first. Words: what the library itself gave to books on that subject, then the name closest
        // to what was typed, then the one most books have.
        $key = fn(string $code) => preg_match('/^\d/', $typed) ? [strlen($code), $code]
            : [$found[$code]['used'] ? 0 : 1, -$found[$code]['used'], $found[$code]['distance'], $found[$code]['exact'],
                -($counts[$code] ?? 0), strlen($code), $code];
        uksort($found, fn($a, $b) => $key((string) $a) <=> $key((string) $b));
        $list = [];
        foreach (array_slice($found, 0, $limit, true) as $code => $what) {
            $list[] = ['code' => (string) $code, 'label' => self::label((string) $code, $classes), 'count' => $counts[$code] ?? 0,
                'used' => $what['used']];
        }
        return $list;
    }

    /**
     * The name of a class, or of the closest broader one the centre names.
     *
     * @param string $code
     * @param array $classes code => name.
     * @return string Empty if none is named.
     */
    public static function label(string $code, array $classes): string {
        for ($length = strlen($code); $length > 0; $length--) {
            $prefix = substr($code, 0, $length);
            if (isset($classes[$prefix]) && $classes[$prefix] !== '') {
                return $classes[$prefix];
            }
        }
        return '';
    }

    /**
     * The classes of a centre with their names: those its catalogue brought, and the main classes of the UDC in the
     * plugin's words where it uses the UDC and has no name of its own for them.
     *
     * @param int $entityid
     * @return array code => name.
     */
    public static function classes(int $entityid): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'authority',
            "entityid = :entityid AND kind = 'class' AND code IS NOT NULL",
            ['entityid' => $entityid],
            '',
            'id, code, label'
        );
        $classes = [];
        foreach ($rows as $row) {
            $code = trim((string) $row->code);
            $label = catalogue::tidy_label((string) $row->label);
            // A class whose name is its number tells nothing.
            if ($code === '' || $label === '' || $label === $code) {
                continue;
            }
            $classes[$code] = $classes[$code] ?? $label;
        }
        $entity = json_decode((string) $DB->get_field(self::T . 'entity', 'settings', ['id' => $entityid]), true) ?: [];
        if (($entity['classification'] ?? 'udc') === 'udc') {
            foreach (['0', '1', '2', '3', '5', '6', '7', '8', '9'] as $code) {
                $classes[$code] = $classes[$code] ?? get_string('udc' . $code, 'local_oksigeniaayvurenda');
            }
        }
        ksort($classes, SORT_STRING);
        return $classes;
    }

    /**
     * Whether a name has every word typed, and how far it is: 0 when each is the start of one of its words, more with a
     * letter wrong (biolojía, mitolojía).
     *
     * @param string[] $words Normalised.
     * @param string $label
     * @return int|null
     */
    private static function fits(array $words, string $label): ?int {
        $own = text::words($label);
        $total = 0;
        foreach ($words as $word) {
            $best = null;
            foreach ($own as $theirs) {
                // One letter at most: with two, «biología» would be «zoología».
                $distance = text::near($word, $theirs);
                if ($distance !== null && $distance <= 1 && ($best === null || $distance < $best)) {
                    $best = $distance;
                }
            }
            if ($best === null) {
                return null;
            }
            $total += $best;
        }
        return $total;
    }

    /**
     * The first line the library gave to the books of the subjects that have the words typed, the most given first.
     *
     * @param int $entityid
     * @param string[] $words Normalised.
     * @return array code => how many books.
     */
    private static function learned(int $entityid, array $words): array {
        global $DB;
        $subjects = [];
        $rows = $DB->get_recordset_select(
            self::T . 'authority',
            "entityid = :entityid AND kind = 'subject'",
            ['entityid' => $entityid],
            '',
            'id, label'
        );
        foreach ($rows as $row) {
            if (self::fits($words, (string) $row->label) === 0) {
                $subjects[] = (int) $row->id;
                if (count($subjects) >= 40) {
                    break;
                }
            }
        }
        $rows->close();
        if (!$subjects) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($subjects, SQL_PARAMS_NAMED);
        $used = $DB->get_records_sql_menu(
            "SELECT i.callnumber1, COUNT(DISTINCT i.recordid) AS books
               FROM {" . self::T . "item} i
               JOIN {" . self::T . "recauth} ra ON ra.recordid = i.recordid
              WHERE i.entityid = :entityid AND ra.authorityid $in AND i.callnumber1 IS NOT NULL
           GROUP BY i.callnumber1
           ORDER BY COUNT(DISTINCT i.recordid) DESC",
            $params + ['entityid' => $entityid],
            0,
            12
        );
        $learned = [];
        foreach ($used as $code => $books) {
            $code = trim((string) $code);
            // Only a class: a section such as «N» or «CUE» says where, not what.
            if ($code !== '' && preg_match('/^\d/', $code) && (int) $books >= 2 && count($learned) < 4) {
                $learned[$code] = ($learned[$code] ?? 0) + (int) $books;
            }
        }
        return $learned;
    }

    /**
     * How many books of the centre have each code as the first line of a copy's spine.
     *
     * @param int $entityid
     * @param string[] $codes
     * @return array code => books.
     */
    private static function counts(int $entityid, array $codes): array {
        global $DB;
        if (!$codes) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT callnumber1, COUNT(DISTINCT recordid)
               FROM {" . self::T . "item}
              WHERE entityid = :entityid AND callnumber1 $in
           GROUP BY callnumber1",
            $params + ['entityid' => $entityid]
        ));
    }
}
