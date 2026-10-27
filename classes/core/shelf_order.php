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
 * The order of the books on a shelf.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * A key that puts copies in the order of the shelf: the class as the UDC files it, then the author's letters, the
 * title's letters, the title and the record. The key has only digits and small letters, so every database sorts it
 * the same way, whatever its collation. Plain PHP, without Moodle.
 *
 * The UDC files a number as a decimal fraction (82 before 82-3 before 82.0 before 821) and its signs in a fixed order:
 * + / : = ( " - and then the point zero and the digits. Each character becomes two: a sign, «0» and a letter in that
 * order; a digit, «1» and the digit; a letter, «2» and the letter. Parts are joined with «00», which goes before any of
 * them, so a shorter number comes first. Copies without a class go after everything.
 */
final class shelf_order {
    /** @var array<string, string> UDC signs in their filing order. */
    private const SIGNS = ['+' => '0a', '/' => '0b', ':' => '0c', '=' => '0d', '(' => '0e', '"' => '0f', '-' => '0g'];

    /** @var int Longest key; the database field is 200. */
    public const LENGTH = 200;

    /**
     * The key of a copy.
     *
     * @param string|null $class First line of the spine label: the class.
     * @param string|null $author Second line: usually three capitals of the author.
     * @param string|null $short Third line: usually three letters of the title.
     * @param string $title
     * @param int $recordid
     * @return string
     */
    public static function key(?string $class, ?string $author, ?string $short, string $title, int $recordid): string {
        $class = self::part((string) $class, true, 40);
        $parts = [
            $class === '' ? '3' : $class,
            self::part((string) $author, false, 12),
            self::part((string) $short, false, 12),
            self::part(self::without_article($title), false, 50),
        ];
        return substr(implode('00', $parts), 0, self::LENGTH - 12) . '00' . sprintf('%010d', max(0, $recordid));
    }

    /**
     * Where a class begins on the shelf: every key of a copy of that class, or of a class under it, starts with it and
     * is longer, so it is the key to start after.
     *
     * @param string $class A number of the classification, such as 52 or 821.134.2.
     * @return string
     */
    public static function prefix(string $class): string {
        return self::part($class, true, 40);
    }

    /**
     * A line coded for the key.
     *
     * @param string $text
     * @param bool $signs Whether the UDC signs count (only in the class).
     * @param int $max The most characters taken.
     * @return string
     */
    private static function part(string $text, bool $signs, int $max): string {
        $text = trim($text);
        if ($signs) {
            // Brackets and quotation marks open an auxiliary; what closes them does not count.
            $text = str_replace([')', '[', ']', '«', '»', '“', '”'], ['', '(', '', '"', '', '"', ''], $text);
        }
        $out = '';
        $taken = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if ($taken >= $max) {
                break;
            }
            if ($signs && isset(self::SIGNS[$char])) {
                $out .= self::SIGNS[$char];
                $taken++;
                continue;
            }
            $plain = text::normalise($char);
            if ($plain === '' || !preg_match('/^[a-z0-9]$/', $plain)) {
                continue;
            }
            $out .= (ctype_digit($plain) ? '1' : '2') . $plain;
            $taken++;
        }
        return $out;
    }

    /**
     * A title without the article it starts with, as libraries file it.
     *
     * @param string $title
     * @return string
     */
    private static function without_article(string $title): string {
        $words = explode(' ', text::normalise($title));
        $articles = ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'the', 'a', 'an', 'le', 'les', 'l', 'il', 'lo',
            'gli', 'der', 'die', 'das', 'o', 'os', 'as', 'els', 'en', 'et'];
        while (count($words) > 1 && in_array($words[0], $articles, true)) {
            array_shift($words);
        }
        return implode(' ', $words);
    }
}
