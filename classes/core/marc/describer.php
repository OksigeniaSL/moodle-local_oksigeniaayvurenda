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
 * From a MARC21 record to a description of the catalogue.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

use local_oksigeniaayvurenda\core\isbn;

/**
 * A MARC21 or UNIMARC bibliographic record as a description: the fields of the record, its authorities by kind (authors,
 * publishers, classes, subjects, collections, audience) and the rest as extras. The same shape as the Abies mapper
 * gives, so every way into the catalogue writes the same. Plain PHP, without Moodle.
 */
final class describer {
    /**
     * The description of a record.
     *
     * @param record $record
     * @return array{record: array, authorities: array, extra: array}
     */
    public static function describe(record $record): array {
        if (self::is_unimarc($record)) {
            return self::unimarc($record);
        }
        $fixed = (string) $record->control_value('008');
        $year = self::year((string) self::first($record, ['264', '260'], 'c'));
        if ($year === null && preg_match('/^\d{4}$/', substr($fixed, 7, 4))) {
            $year = substr($fixed, 7, 4);
        }
        $lang = strtolower(trim(substr($fixed, 35, 3)));
        if (!preg_match('/^[a-z]{3}$/', $lang)) {
            $lang = strtolower((string) $record->first(['041'], 'a')) ?: null;
            $lang = $lang !== null && preg_match('/^[a-z]{3}$/', $lang) ? $lang : null;
        }
        $isbns = [];
        foreach ($record->values(['020'], 'a') as $raw) {
            // The qualifier goes: «978-84-204-8285-5 (rústica)».
            $normal = isbn::normalise(preg_match('/^[0-9Xx\-]+/', preg_replace('/\s.*$/', '', trim($raw)), $isbnpart)
                ? $isbnpart[0] : '');
            if ($normal !== null && !in_array($normal, $isbns, true)) {
                $isbns[] = $normal;
            }
        }
        $isbn = $isbns[0] ?? null;
        $extra = array_filter([
            'lccn' => self::clean($record->first(['010'], 'a')),
            'legaldeposit' => self::clean($record->first(['017'], 'a')),
            'dimensions' => self::clean($record->first(['300'], 'c')),
            'physical' => self::clean($record->first(['300'], 'b')),
            'seriesnumber' => self::clean($record->first(['490', '830'], 'v')),
            'classscheme' => $record->first(['080'], 'a') !== null ? 'udc' : ($record->first(['082'], 'a') !== null ? 'ddc' : null),
            // Every ISBN of the record, to tell the edition asked for from the others a catalogue may answer with.
            'isbns' => count($isbns) > 1 ? $isbns : null,
        ], fn($v) => $v !== null && $v !== '');
        $notes = array_values(array_filter(array_map([self::class, 'text'], $record->values(['500'], 'a'))));
        return [
            'record' => [
                'doctype' => self::doctype($record->leader),
                'title' => self::title($record),
                'subtitle' => self::clean(self::first($record, ['245'], 'b')),
                'responsibility' => self::clean(self::first($record, ['245'], 'c')),
                'isbn' => $isbn,
                'edition' => self::clean(self::first($record, ['250'], 'a')),
                'pubplace' => self::clean(self::first($record, ['264', '260'], 'a')),
                'pubyear' => $year,
                'lang' => $lang,
                'extent' => self::clean(self::first($record, ['300'], 'a')),
                // Free text keeps its own full stops.
                'summary' => self::text(self::first($record, ['520'], 'a')),
                'notes' => $notes ? implode("\n", $notes) : null,
            ],
            'authorities' => [
                'author' => self::unique($record->values(['100', '110', '111', '700', '710', '711'], 'a')),
                'publisher' => self::unique($record->values(['264', '260'], 'b')),
                // The prime marks of Dewey («813/.52») only say where to cut it.
                'class' => self::unique(array_map(
                    fn($c) => str_replace(['/', "'"], '', $c),
                    array_merge($record->values(['080'], 'a'), $record->values(['082'], 'a'))
                )),
                // A school library wants a few subjects, not a national library's whole list.
                'subject' => array_slice(self::unique($record->values(['650', '651', '600', '610', '655'], 'a')), 0, 6),
                'collection' => self::unique($record->values(['490', '830'], 'a')),
                'audience' => self::unique($record->values(['521'], 'a')),
            ],
            'extra' => $extra,
        ];
    }

    /**
     * The kind of document from the leader: position 6 is the type of record and 7 the bibliographic level.
     *
     * @param string $leader
     * @return string
     */
    public static function doctype(string $leader): string {
        $type = $leader[6] ?? 'a';
        $level = $leader[7] ?? 'm';
        if (($type === 'a' || $type === 't') && $level === 's') {
            return 'periodical';
        }
        return match ($type) {
            'a', 't', 'c', 'd' => 'book',
            'i', 'j' => 'audio',
            'g', 'k' => 'video',
            'm' => 'electronic',
            'o', 'r' => 'device',
            default => 'other',
        };
    }

    /**
     * The first value of a subfield.
     *
     * @param record $record
     * @param string[] $tags
     * @param string $code
     * @return string|null
     */
    private static function first(record $record, array $tags, string $code): ?string {
        return $record->first($tags, $code);
    }

    /**
     * The title: 245 $a with the number and name of its part ($n, $p: «Harry Potter. Vol. 3, El prisionero de Azkaban»),
     * so the volumes of a work are told apart; without $a, the 245 $b, the uniform title (240) or the added title (730).
     *
     * @param record $record
     * @return string
     */
    private static function title(record $record): string {
        $main = self::first($record, ['245'], 'a');
        $parts = array_merge($record->values(['245'], 'n'), $record->values(['245'], 'p'));
        if ($main !== null && trim($main) !== '') {
            return self::clean(trim($main . ' ' . implode(' ', array_map('trim', $parts)))) ?? '';
        }
        foreach ([['245', 'b'], ['240', 'a'], ['730', 'a']] as [$tag, $code]) {
            $other = self::clean(self::first($record, [$tag], $code));
            if ($other !== null && $other !== '') {
                return $other;
            }
        }
        return '';
    }

    /**
     * Whether a record is UNIMARC (France, Portugal, Italy and much of Europe): its title is in 200, and there is no 245.
     *
     * @param record $record
     * @return bool
     */
    public static function is_unimarc(record $record): bool {
        return $record->first(['200'], 'a') !== null && !$record->data_fields('245');
    }

    /**
     * The description of a UNIMARC record: the same shape as a MARC21 one, from UNIMARC's own fields.
     *
     * @param record $record
     * @return array{record: array, authorities: array, extra: array}
     */
    private static function unimarc(record $record): array {
        $isbns = [];
        foreach ($record->values(['010'], 'a') as $raw) {
            $normal = isbn::normalise(preg_match('/^[0-9Xx\-]+/', preg_replace('/\s.*$/', '', trim($raw)), $isbnpart)
                ? $isbnpart[0] : '');
            if ($normal !== null && !in_array($normal, $isbns, true)) {
                $isbns[] = $normal;
            }
        }
        $lang = strtolower((string) $record->first(['101'], 'a'));
        // A person is «surname, forename» in two subfields; a body or a meeting, one.
        $names = [];
        foreach ($record->data_fields('700', '701', '702', '710', '711', '712') as $field) {
            $part = ['a' => '', 'b' => ''];
            foreach ($field['subfields'] as [$code, $value]) {
                if (isset($part[$code]) && $part[$code] === '') {
                    $part[$code] = (string) self::clean($value);
                }
            }
            $names[] = $part['b'] !== '' ? $part['a'] . ', ' . $part['b'] : $part['a'];
        }
        $type = $record->leader[6] ?? 'a';
        $level = $record->leader[7] ?? 'm';
        $doctype = match (true) {
            in_array($type, ['a', 'b', 'c', 'd', 'e', 'f'], true) && $level === 's' => 'periodical',
            in_array($type, ['a', 'b', 'c', 'd', 'e', 'f'], true) => 'book',
            in_array($type, ['i', 'j'], true) => 'audio',
            $type === 'g' => 'video',
            $type === 'l' => 'electronic',
            $type === 'r' => 'device',
            default => 'other',
        };
        $udc = $record->values(['675'], 'a');
        $ddc = $record->values(['676'], 'a');
        $notes = array_values(array_filter(array_map([self::class, 'text'], $record->values(['300'], 'a'))));
        return [
            'record' => [
                'doctype' => $doctype,
                'title' => self::clean($record->first(['200'], 'a')) ?? '',
                'subtitle' => self::clean($record->first(['200'], 'e')),
                'responsibility' => self::clean($record->first(['200'], 'f')),
                'isbn' => $isbns[0] ?? null,
                'edition' => self::clean($record->first(['205'], 'a')),
                'pubplace' => self::clean($record->first(['214', '210'], 'a')),
                'pubyear' => self::year((string) $record->first(['214', '210'], 'd')),
                'lang' => preg_match('/^[a-z]{3}$/', $lang) ? $lang : null,
                'extent' => self::clean($record->first(['215'], 'a')),
                'summary' => self::text($record->first(['330'], 'a')),
                'notes' => $notes ? implode("\n", $notes) : null,
            ],
            'authorities' => [
                'author' => self::unique($names),
                'publisher' => self::unique($record->values(['214', '210'], 'c')),
                'class' => self::unique(array_merge($udc, $ddc, $record->values(['686'], 'a'))),
                'subject' => array_slice(self::unique($record->values(['606', '607', '600', '601', '608'], 'a')), 0, 6),
                'collection' => self::unique($record->values(['225'], 'a')),
                'audience' => self::unique($record->values(['333'], 'a')),
            ],
            'extra' => array_filter([
                'dimensions' => self::clean($record->first(['215'], 'd')),
                'physical' => self::clean($record->first(['215'], 'c')),
                'seriesnumber' => self::clean($record->first(['225'], 'v')),
                'classscheme' => $udc ? 'udc' : ($ddc ? 'ddc' : null),
                'isbns' => count($isbns) > 1 ? $isbns : null,
                'format' => 'unimarc',
            ], fn($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * A year in a date as printed («c2016.», «[2015?]»).
     *
     * @param string $value
     * @return string|null
     */
    private static function year(string $value): ?string {
        return preg_match('/\d{4}/', $value, $matches) ? $matches[0] : null;
    }

    /**
     * A value without the ISBD punctuation that closes it («Matilda /», «Madrid :», «Dahl, Roald,»).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function clean(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        // A closing full stop stays after an initial («Tolkien, J. R. R.») and in abbreviations of one letter.
        $value = preg_replace('/\s*[\/:;,=+]\s*$/u', '', $value);
        // An opening bracket left alone at the end, when the dates went to their own subfield («Miró, Gabriel (»).
        $value = preg_replace('/\s*\($/u', '', $value);
        if (preg_match('/[^.\s]{2,}\.$/u', $value) && !preg_match('/\b(p|il|ed|cm|v|t|vol|etc)\.$/iu', $value)) {
            $value = substr($value, 0, -1);
        }
        $value = trim($value, " \t[]");
        return $value === '' ? null : $value;
    }

    /**
     * Free text, only trimmed.
     *
     * @param string|null $value
     * @return string|null
     */
    private static function text(?string $value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * Clean values without repeats, in their order.
     *
     * @param string[] $values
     * @return string[]
     */
    private static function unique(array $values): array {
        $clean = [];
        foreach ($values as $value) {
            $value = self::clean($value);
            if ($value !== null && !in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }
        return $clean;
    }
}
