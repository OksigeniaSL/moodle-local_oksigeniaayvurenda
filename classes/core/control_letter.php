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
 * Control letters of library codes.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * The letter after the digits of a code: the remainder of dividing by 23, as in the Spanish identity number and in the
 * copy and reader codes of Abies. It catches a misread code at the desk.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class control_letter {
    /** @var string The 23 letters, in the order of the remainder. */
    public const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /**
     * The control letter of a number.
     *
     * @param string $digits Digits only, of any length.
     * @return string
     */
    public static function for_number(string $digits): string {
        if ($digits === '' || !ctype_digit($digits)) {
            throw new \InvalidArgumentException('A control letter needs digits only');
        }
        // Digit by digit, so that long numbers never overflow an integer.
        $rest = 0;
        foreach (str_split($digits) as $digit) {
            $rest = ($rest * 10 + (int) $digit) % 23;
        }
        return self::LETTERS[$rest];
    }

    /**
     * The number with its control letter after it.
     *
     * @param string $digits
     * @return string
     */
    public static function append(string $digits): string {
        return $digits . self::for_number($digits);
    }

    /**
     * Whether a code is digits followed by their right control letter.
     *
     * @param string $code As read, in any case and with spaces around.
     * @return bool
     */
    public static function is_valid(string $code): bool {
        $code = strtoupper(trim($code));
        if (!preg_match('/^(\d+)([A-Z])$/', $code, $parts)) {
            return false;
        }
        return self::for_number($parts[1]) === $parts[2];
    }
}
