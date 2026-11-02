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
 * A record of the catalogue in CSL-JSON.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\export;

/**
 * CSL-JSON, the format of the Citation Style Language that Zotero, Mendeley and the citation styles of most journals read:
 * a list of items as JSON. Material that is not a work is not cited. Plain PHP, without Moodle.
 */
final class csljson {
    /** @var array<string, string> The CSL type of each kind of document. */
    private const TYPES = ['book' => 'book', 'periodical' => 'periodical', 'audio' => 'song', 'video' => 'motion_picture',
        'electronic' => 'webpage', 'other' => 'document'];

    /**
     * The item of a record, or null for material that is not cited.
     *
     * @param array $record The fields of the record (id, doctype, title, subtitle, isbn, edition, pubplace, pubyear, lang,
     *     extent).
     * @param array $authorities Kind => labels, in order (author, publisher, subject, collection).
     * @param string $callnumber Where it is on the shelf.
     * @param string $note A note of whoever made the list.
     * @return array|null
     */
    public static function item(array $record, array $authorities, string $callnumber = '', string $note = ''): ?array {
        $type = self::TYPES[(string) ($record['doctype'] ?? 'book')] ?? null;
        $title = trim((string) ($record['title'] ?? ''));
        if ($type === null || $title === '') {
            return null;
        }
        $subtitle = trim((string) ($record['subtitle'] ?? ''));
        $year = trim((string) ($record['pubyear'] ?? ''));
        $pages = preg_match('/(\d+)\s*p/u', (string) ($record['extent'] ?? ''), $m) ? $m[1] : '';
        $item = [
            'id' => 'ayv-' . (int) ($record['id'] ?? 0),
            'type' => $type,
            'title' => $subtitle !== '' ? $title . ': ' . $subtitle : $title,
            'author' => array_map([self::class, 'name'], $authorities['author'] ?? []),
            'issued' => preg_match('/^\d{4}$/', $year) ? ['date-parts' => [[(int) $year]]] : null,
            'publisher' => (string) (($authorities['publisher'] ?? [])[0] ?? ''),
            'publisher-place' => (string) ($record['pubplace'] ?? ''),
            'edition' => (string) ($record['edition'] ?? ''),
            'collection-title' => (string) (($authorities['collection'] ?? [])[0] ?? ''),
            'ISBN' => (string) ($record['isbn'] ?? ''),
            'language' => (string) ($record['lang'] ?? ''),
            'number-of-pages' => $pages,
            'keyword' => implode(', ', $authorities['subject'] ?? []),
            'call-number' => $callnumber,
            'note' => $note,
        ];
        return array_filter($item, fn($value) => $value !== '' && $value !== null && $value !== []);
    }

    /**
     * A name as CSL wants it: «Surname, Name» as its parts, and a body or a name without a comma as it is.
     *
     * @param string $label
     * @return array
     */
    public static function name(string $label): array {
        $parts = array_map('trim', explode(',', $label, 2));
        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? ['family' => $parts[0], 'given' => $parts[1]]
            : ['literal' => trim($label)];
    }

    /**
     * The items as a document.
     *
     * @param array[] $items
     * @return string
     */
    public static function document(array $items): string {
        return json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
