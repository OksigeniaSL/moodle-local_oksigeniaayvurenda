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
 * From a record of the catalogue to MARC21.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\marc;

/**
 * A record of the catalogue, with its authorities and its copies, as a MARC21 bibliographic record: what any library
 * program can import, so that nobody is trapped. The way back of describer, with the ISBD punctuation MARC expects
 * and the copies in 852. Plain PHP, without Moodle.
 */
final class encoder {
    /** @var array<string, string> The type of record of the leader for each kind of document. */
    private const TYPES = ['book' => 'a', 'periodical' => 'a', 'audio' => 'j', 'video' => 'g', 'electronic' => 'm',
        'device' => 'r', 'other' => 'p'];

    /**
     * The MARC record.
     *
     * @param array $record The fields of the record (id, doctype, title, subtitle, responsibility, isbn, edition,
     *     pubplace, pubyear, lang, extent, summary, notes, extra as JSON, timecreated, timemodified).
     * @param array $authorities Kind => labels, in order (author, publisher, class, subject, collection, audience).
     * @param array $items Copies: barcode, library, location, callnumber (the three lines), serial.
     * @param string $entity The entity's name, for 852.
     * @return record
     */
    public static function encode(array $record, array $authorities, array $items = [], string $entity = ''): record {
        $doctype = (string) ($record['doctype'] ?? 'book');
        $level = $doctype === 'periodical' ? 's' : 'm';
        $marc = new record('00000n' . (self::TYPES[$doctype] ?? 'a') . $level . ' a2200000 i 4500');
        $extra = $record['extra'] ?? null;
        $extra = is_array($extra) ? $extra : (json_decode((string) $extra, true) ?: []);
        $marc->control('001', 'ayv-' . (int) ($record['id'] ?? 0));
        $marc->control('003', 'Ayvurenda');
        $modified = (int) ($record['timemodified'] ?? 0);
        if ($modified) {
            $marc->control('005', gmdate('YmdHis', $modified) . '.0');
        }
        $marc->control('008', self::fixed($record));
        $isbn = (string) ($record['isbn'] ?? '');
        if ($isbn !== '') {
            $marc->data('020', ' ', ' ', [['a', $isbn]]);
        }
        foreach ($authorities['class'] ?? [] as $index => $class) {
            $udc = ($extra['classscheme'] ?? 'udc') !== 'ddc';
            $marc->data($udc ? '080' : '082', $udc ? ' ' : '0', $udc ? ' ' : '4', [['a', $class]]);
            if ($index === 0) {
                break;
            }
        }
        $authors = array_values($authorities['author'] ?? []);
        if ($authors) {
            $marc->data('100', '1', ' ', [['a', $authors[0] . '.']]);
        }
        $title = trim((string) ($record['title'] ?? ''));
        $subtitle = trim((string) ($record['subtitle'] ?? ''));
        $responsibility = trim((string) ($record['responsibility'] ?? ''));
        $subfields = [['a', $title . ($subtitle !== '' ? ' :' : ($responsibility !== '' ? ' /' : '.'))]];
        if ($subtitle !== '') {
            $subfields[] = ['b', $subtitle . ($responsibility !== '' ? ' /' : '.')];
        }
        if ($responsibility !== '') {
            $subfields[] = ['c', $responsibility . '.'];
        }
        $marc->data('245', $authors ? '1' : '0', (string) self::nonfiling($title), $subfields);
        if (($record['edition'] ?? '') !== '' && $record['edition'] !== null) {
            $marc->data('250', ' ', ' ', [['a', $record['edition'] . '.']]);
        }
        $imprint = [];
        $publishers = array_values($authorities['publisher'] ?? []);
        $place = (string) ($record['pubplace'] ?? '');
        $year = (string) ($record['pubyear'] ?? '');
        if ($place !== '') {
            $imprint[] = ['a', $place . ($publishers || $year !== '' ? ' :' : '')];
        }
        if ($publishers) {
            $imprint[] = ['b', $publishers[0] . ($year !== '' ? ',' : '')];
        }
        if ($year !== '') {
            $imprint[] = ['c', $year . '.'];
        }
        if ($imprint) {
            $marc->data('264', ' ', '1', $imprint);
        }
        $physical = array_filter([['a', (string) ($record['extent'] ?? '')], ['b', (string) ($extra['physical'] ?? '')],
            ['c', (string) ($extra['dimensions'] ?? '')]], fn($s) => $s[1] !== '');
        if ($physical) {
            $marc->data('300', ' ', ' ', array_values($physical));
        }
        foreach ($authorities['collection'] ?? [] as $collection) {
            $series = [['a', $collection]];
            if (!empty($extra['seriesnumber'])) {
                $series = [['a', $collection . ' ;'], ['v', (string) $extra['seriesnumber']]];
            }
            $marc->data('490', '0', ' ', $series);
        }
        foreach (preg_split('/\R/', (string) ($record['notes'] ?? '')) as $note) {
            if (trim($note) !== '') {
                $marc->data('500', ' ', ' ', [['a', trim($note)]]);
            }
        }
        if (($record['summary'] ?? '') !== '' && $record['summary'] !== null) {
            $marc->data('520', ' ', ' ', [['a', $record['summary']]]);
        }
        foreach ($authorities['audience'] ?? [] as $audience) {
            $marc->data('521', ' ', ' ', [['a', $audience . '.']]);
        }
        foreach ($authorities['subject'] ?? [] as $subject) {
            $marc->data('650', ' ', '4', [['a', $subject . '.']]);
        }
        foreach (array_slice($authors, 1) as $author) {
            $marc->data('700', '1', ' ', [['a', $author . '.']]);
        }
        foreach (['brand' => 'a', 'model' => 'b'] as $key => $code) {
            if (!empty($extra[$key])) {
                // Local fields: what material has and MARC does not.
                $marc->data('950', ' ', ' ', [[$code, (string) $extra[$key]]]);
            }
        }
        foreach ($items as $item) {
            $copy = array_filter([
                ['a', $entity],
                ['b', (string) ($item['library'] ?? '')],
                ['c', (string) ($item['location'] ?? '')],
                ['h', trim(implode(' ', array_filter((array) ($item['callnumber'] ?? []), fn($l) => (string) $l !== '')))],
                ['p', (string) ($item['barcode'] ?? '')],
                ['z', (string) ($item['serial'] ?? '') !== '' ? 'S/N ' . $item['serial'] : ''],
            ], fn($s) => $s[1] !== '');
            $marc->data('852', ' ', ' ', array_values($copy));
        }
        return $marc;
    }

    /**
     * The fixed field 008 of a book: date entered, the year, the language.
     *
     * @param array $record
     * @return string 40 characters.
     */
    private static function fixed(array $record): string {
        $entered = gmdate('ymd', (int) ($record['timecreated'] ?? 0) ?: time());
        $year = preg_match('/^\d{4}$/', (string) ($record['pubyear'] ?? '')) ? $record['pubyear'] : '    ';
        $lang = preg_match('/^[a-z]{3}$/', (string) ($record['lang'] ?? '')) ? $record['lang'] : '   ';
        return $entered . ($year === '    ' ? 'n' : 's') . $year . '    ' . 'xx ' . str_repeat(' ', 17) . $lang . ' d';
    }

    /**
     * How many characters of an article to skip when filing a title.
     *
     * @param string $title
     * @return int
     */
    private static function nonfiling(string $title): int {
        if (preg_match('/^((?:el|la|los|las|un|una|the|a|an|le|les|il) )/iu', $title, $matches)) {
            return min(9, strlen($matches[1]));
        }
        return 0;
    }
}
