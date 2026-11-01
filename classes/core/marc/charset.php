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

namespace local_oksigeniaayvurenda\core\marc;

/**
 * The letters of an old MARC file. What the leader says is not enough: a blank position 9 often hides valid UTF-8. So what is
 * valid UTF-8 is UTF-8; UNIMARC says its own set in field 100; and the rest is told by how its accents are written: MARC-8 and
 * ISO 5426 put a combining mark before its letter, and Windows-1252 has the accented letter itself. Latin letters only: the other
 * alphabets of MARC-8 are reached by escape sequences, which are taken out. Plain PHP, without Moodle.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class charset {
    /** @var string Worked out from the file. */
    public const AUTO = 'auto';

    /** @var string[] What can be chosen by hand. */
    public const SETS = [self::AUTO, 'utf8', 'marc8', 'iso5426', 'cp1252'];

    /** @var array<int, string> ANSEL (MARC-8's Latin set): the letters on their own. */
    private const ANSEL = [
        0xA1 => 'Ł', 0xA2 => 'Ø', 0xA3 => 'Đ', 0xA4 => 'Þ', 0xA5 => 'Æ', 0xA6 => 'Œ', 0xA7 => 'ʹ', 0xA8 => '·', 0xA9 => '♭',
        0xAA => '®', 0xAB => '±', 0xAC => 'Ơ', 0xAD => 'Ư', 0xAE => 'ʼ', 0xB0 => 'ʻ', 0xB1 => 'ł', 0xB2 => 'ø', 0xB3 => 'đ',
        0xB4 => 'þ', 0xB5 => 'æ', 0xB6 => 'œ', 0xB7 => 'ʺ', 0xB8 => 'ı', 0xB9 => '£', 0xBA => 'ð', 0xBC => 'ơ', 0xBD => 'ư',
        0xC0 => '°', 0xC1 => 'ℓ', 0xC2 => '℗', 0xC3 => '©', 0xC4 => '♯', 0xC5 => '¿', 0xC6 => '¡', 0xC7 => 'ß', 0xC8 => '€',
    ];

    /** @var array<int, string> ANSEL: the marks that go before their letter. */
    private const ANSEL_MARKS = [
        0xE0 => "\u{309}", 0xE1 => "\u{300}", 0xE2 => "\u{301}", 0xE3 => "\u{302}", 0xE4 => "\u{303}", 0xE5 => "\u{304}",
        0xE6 => "\u{306}", 0xE7 => "\u{307}", 0xE8 => "\u{308}", 0xE9 => "\u{30C}", 0xEA => "\u{30A}", 0xEB => "\u{FE20}",
        0xEC => "\u{FE21}", 0xED => "\u{315}", 0xEE => "\u{30B}", 0xEF => "\u{310}", 0xF0 => "\u{327}", 0xF1 => "\u{328}",
        0xF2 => "\u{323}", 0xF3 => "\u{324}", 0xF4 => "\u{325}", 0xF5 => "\u{333}", 0xF6 => "\u{332}", 0xF7 => "\u{326}",
        0xF8 => "\u{31C}", 0xF9 => "\u{32E}", 0xFA => "\u{FE22}", 0xFB => "\u{FE23}", 0xFE => "\u{313}",
    ];

    /** @var array<int, string> ISO 5426: the letters on their own. */
    private const ISO5426 = [
        0xA1 => '¡', 0xA2 => '“', 0xA3 => '£', 0xA4 => '$', 0xA5 => '¥', 0xA6 => '†', 0xA7 => '§', 0xA8 => '′', 0xA9 => '‘',
        0xAA => '“', 0xAB => '«', 0xAC => '♭', 0xAD => '©', 0xAE => '℗', 0xAF => '®', 0xB0 => 'ʾ', 0xB1 => 'ʿ', 0xB2 => '‚',
        0xB6 => '‡', 0xB7 => '·', 0xB8 => '”', 0xB9 => '’', 0xBA => '”', 0xBB => '»', 0xBC => '♯', 0xBD => 'ʹ', 0xBE => 'ʺ',
        0xBF => '¿', 0xE0 => 'Ʀ', 0xE1 => 'Æ', 0xE2 => 'Đ', 0xE6 => 'Ĳ', 0xE8 => 'Ł', 0xE9 => 'Ø', 0xEA => 'Œ', 0xEC => 'Þ',
        0xF1 => 'æ', 0xF2 => 'đ', 0xF3 => 'ð', 0xF5 => 'ı', 0xF6 => 'ĳ', 0xF8 => 'ł', 0xF9 => 'ø', 0xFA => 'œ', 0xFB => 'ß',
        0xFC => 'þ',
    ];

    /** @var array<int, string> ISO 5426: the marks that go before their letter. */
    private const ISO5426_MARKS = [
        0xC0 => "\u{309}", 0xC1 => "\u{300}", 0xC2 => "\u{301}", 0xC3 => "\u{302}", 0xC4 => "\u{303}", 0xC5 => "\u{304}",
        0xC6 => "\u{306}", 0xC7 => "\u{307}", 0xC8 => "\u{308}", 0xC9 => "\u{308}", 0xCA => "\u{30A}", 0xCB => "\u{327}",
        0xCC => "\u{328}", 0xCD => "\u{30B}", 0xCE => "\u{30C}", 0xCF => "\u{30C}", 0xD0 => "\u{327}", 0xD1 => "\u{31C}",
        0xD2 => "\u{326}", 0xD3 => "\u{328}", 0xD4 => "\u{325}", 0xD5 => "\u{32E}", 0xD6 => "\u{323}", 0xD7 => "\u{324}",
        0xD8 => "\u{332}", 0xD9 => "\u{333}", 0xDA => "\u{329}", 0xDB => "\u{32D}",
    ];

    /** @var array<string, string> The usual letter with its mark, where there is no normaliser to join them. */
    private const JOINED = [
        "a\u{301}" => 'á', "e\u{301}" => 'é', "i\u{301}" => 'í', "o\u{301}" => 'ó', "u\u{301}" => 'ú', "y\u{301}" => 'ý',
        "A\u{301}" => 'Á', "E\u{301}" => 'É', "I\u{301}" => 'Í', "O\u{301}" => 'Ó', "U\u{301}" => 'Ú', "Y\u{301}" => 'Ý',
        "a\u{300}" => 'à', "e\u{300}" => 'è', "i\u{300}" => 'ì', "o\u{300}" => 'ò', "u\u{300}" => 'ù',
        "A\u{300}" => 'À', "E\u{300}" => 'È', "I\u{300}" => 'Ì', "O\u{300}" => 'Ò', "U\u{300}" => 'Ù',
        "a\u{302}" => 'â', "e\u{302}" => 'ê', "i\u{302}" => 'î', "o\u{302}" => 'ô', "u\u{302}" => 'û',
        "A\u{302}" => 'Â', "E\u{302}" => 'Ê', "I\u{302}" => 'Î', "O\u{302}" => 'Ô', "U\u{302}" => 'Û',
        "a\u{308}" => 'ä', "e\u{308}" => 'ë', "i\u{308}" => 'ï', "o\u{308}" => 'ö', "u\u{308}" => 'ü', "y\u{308}" => 'ÿ',
        "A\u{308}" => 'Ä', "E\u{308}" => 'Ë', "I\u{308}" => 'Ï', "O\u{308}" => 'Ö', "U\u{308}" => 'Ü',
        "a\u{303}" => 'ã', "n\u{303}" => 'ñ', "o\u{303}" => 'õ', "A\u{303}" => 'Ã', "N\u{303}" => 'Ñ', "O\u{303}" => 'Õ',
        "c\u{327}" => 'ç', "C\u{327}" => 'Ç', "a\u{30A}" => 'å', "A\u{30A}" => 'Å',
    ];

    /**
     * Which set a whole file is written in.
     *
     * @param string $data The bytes of the file.
     * @return string utf8, marc8, iso5426 or cp1252.
     */
    public static function guess(string $data): string {
        if (mb_check_encoding($data, 'UTF-8')) {
            return 'utf8';
        }
        // UNIMARC says it in its field 100 ($a, positions 26 to 29): 03 is ISO 5426.
        if (preg_match('/\x1fa\d{8}[a-z ]{9}[a-z]{3}[0-9a-z ]{2}(\d{2})(\d{2})/', $data, $found)) {
            if (in_array('03', [$found[1], $found[2]], true)) {
                return 'iso5426';
            }
            if (in_array('50', [$found[1], $found[2]], true)) {
                return 'utf8';
            }
        }
        // The bytes each one uses most for what the languages use most: in MARC-8, the acute and the tilde before a letter; in
        // ISO 5426, the same in its own place; in Windows-1252, é, í, ó, ú, ñ, ç, ê, ô, à and their company, which those two
        // hardly ever use.
        $marc8 = preg_match_all('/[\xE2\xE3\xE4][A-Za-z]/', $data);
        $iso5426 = preg_match_all('/[\xC1-\xC4\xC8\xCB][a-z]/', $data);
        $cp1252 = preg_match_all('/[\xE0\xE7\xE9\xEA\xED\xF1\xF3\xF4\xFA\xFB\xFC]/', $data);
        if ($marc8 > $cp1252 && $marc8 >= $iso5426) {
            return 'marc8';
        }
        return $iso5426 > $cp1252 ? 'iso5426' : 'cp1252';
    }

    /**
     * Some text in UTF-8.
     *
     * @param string $text
     * @param string $set utf8, marc8, iso5426 or cp1252.
     * @return string
     */
    public static function to_utf8(string $text, string $set): string {
        switch ($set) {
            case 'utf8':
                return mb_check_encoding($text, 'UTF-8') ? $text : mb_scrub($text, 'UTF-8');
            case 'marc8':
                return self::with_marks($text, self::ANSEL, self::ANSEL_MARKS);
            case 'iso5426':
                return self::with_marks($text, self::ISO5426, self::ISO5426_MARKS);
            default:
                return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
    }

    /**
     * Reads a set whose marks go before their letter, and leaves each mark after it, as Unicode has it.
     *
     * @param string $text
     * @param array $letters Byte => character.
     * @param array $marks Byte => combining mark.
     * @return string
     */
    private static function with_marks(string $text, array $letters, array $marks): string {
        // The way to another alphabet (ESC and its designators) has no meaning here.
        $text = preg_replace('/\x1B[()\$,\-.\x21-\x2F]*[\x20-\x7E]?/', '', $text);
        $out = '';
        $pending = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($text[$i]);
            if (isset($marks[$byte])) {
                $pending .= $marks[$byte];
                continue;
            }
            if ($byte < 0x80) {
                $char = $text[$i];
            } else {
                $char = $letters[$byte] ?? '';
            }
            if ($char !== '') {
                $out .= $char . $pending;
                $pending = '';
            }
        }
        return self::joined($out . $pending);
    }

    /**
     * Each letter and its mark as one character, as a search and a sort expect.
     *
     * @param string $text
     * @return string
     */
    private static function joined(string $text): string {
        if (class_exists('\Normalizer')) {
            $normal = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normal !== false) {
                return $normal;
            }
        }
        return strtr($text, self::JOINED);
    }
}
