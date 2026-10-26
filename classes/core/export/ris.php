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
 * A record of the catalogue in RIS.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\export;

/**
 * RIS, the plain text that reference managers (Zotero, Mendeley, EndNote) take to cite a book: a line for each field,
 * «XX  - value», from «TY» to «ER». Material that is not a work (a tablet, a kit) is not cited. Plain PHP, without
 * Moodle.
 */
final class ris {
    /** @var array<string, string> The RIS type of each kind of document. */
    private const TYPES = ['book' => 'BOOK', 'periodical' => 'JOUR', 'audio' => 'SOUND', 'video' => 'VIDEO',
        'electronic' => 'ELEC', 'other' => 'GEN'];

    /**
     * The entry of a record, or an empty text for material that is not cited.
     *
     * @param array $record The fields of the record (doctype, title, subtitle, isbn, edition, pubplace, pubyear, lang,
     *     summary).
     * @param array $authorities Kind => labels, in order (author, publisher, subject, collection).
     * @param string $callnumber Where it is on the shelf, if it is in one library.
     * @return string
     */
    public static function entry(array $record, array $authorities, string $callnumber = ''): string {
        $type = self::TYPES[(string) ($record['doctype'] ?? 'book')] ?? null;
        $title = trim((string) ($record['title'] ?? ''));
        if ($type === null || $title === '') {
            return '';
        }
        $subtitle = trim((string) ($record['subtitle'] ?? ''));
        $lines = [['TY', $type], ['TI', $subtitle !== '' ? $title . ': ' . $subtitle : $title]];
        foreach ($authorities['author'] ?? [] as $author) {
            $lines[] = ['AU', $author];
        }
        foreach (['PY' => 'pubyear', 'ET' => 'edition', 'CY' => 'pubplace', 'SN' => 'isbn', 'LA' => 'lang'] as $tag => $field) {
            $lines[] = [$tag, (string) ($record[$field] ?? '')];
        }
        $lines[] = ['PB', (string) (($authorities['publisher'] ?? [])[0] ?? '')];
        $lines[] = ['T2', (string) (($authorities['collection'] ?? [])[0] ?? '')];
        foreach ($authorities['subject'] ?? [] as $subject) {
            $lines[] = ['KW', $subject];
        }
        $lines[] = ['AB', trim(strip_tags((string) ($record['summary'] ?? '')))];
        $lines[] = ['CN', $callnumber];
        $text = '';
        foreach ($lines as [$tag, $value]) {
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            if ($value !== '') {
                $text .= $tag . '  - ' . $value . "\r\n";
            }
        }
        return $text . "ER  - \r\n";
    }
}
