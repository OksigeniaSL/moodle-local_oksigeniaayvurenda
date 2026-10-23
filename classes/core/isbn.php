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
 * ISBN checks and conversion.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * ISBNs as they come in catalogues and from scanners: with or without hyphens, in 10 or 13 digits. Records keep the
 * ISBN-13, digits only.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class isbn {
    /**
     * The ISBN-13 of a raw ISBN, or null when it is not a valid one.
     *
     * @param string $raw
     * @return string|null
     */
    public static function normalise(string $raw): ?string {
        $clean = strtoupper(preg_replace('/[^0-9Xx]/', '', $raw));
        if (strlen($clean) === 13 && ctype_digit($clean)) {
            return self::checksum13(substr($clean, 0, 12)) === $clean[12] ? $clean : null;
        }
        if (strlen($clean) === 10 && ctype_digit(substr($clean, 0, 9))) {
            if (self::checksum10(substr($clean, 0, 9)) !== $clean[9]) {
                return null;
            }
            $body = '978' . substr($clean, 0, 9);
            return $body . self::checksum13($body);
        }
        return null;
    }

    /**
     * Whether a raw ISBN, in 10 or 13 digits, is valid.
     *
     * @param string $raw
     * @return bool
     */
    public static function is_valid(string $raw): bool {
        return self::normalise($raw) !== null;
    }

    /**
     * Whether a scanned EAN-13 is a book number (it starts with 978 or 979).
     *
     * @param string $ean
     * @return bool
     */
    public static function is_book_ean(string $ean): bool {
        $digits = preg_replace('/\D/', '', $ean);
        return strlen($digits) === 13 && in_array(substr($digits, 0, 3), ['978', '979'], true)
            && self::normalise($digits) !== null;
    }

    /**
     * Check digit of the first twelve digits of an ISBN-13.
     *
     * @param string $twelve
     * @return string
     */
    private static function checksum13(string $twelve): string {
        $sum = 0;
        foreach (str_split($twelve) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }
        return (string) ((10 - $sum % 10) % 10);
    }

    /**
     * Check character of the first nine digits of an ISBN-10.
     *
     * @param string $nine
     * @return string
     */
    private static function checksum10(string $nine): string {
        $sum = 0;
        foreach (str_split($nine) as $i => $digit) {
            $sum += (10 - $i) * (int) $digit;
        }
        $check = (11 - $sum % 11) % 11;
        return $check === 10 ? 'X' : (string) $check;
    }
}
