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
 * Card codes for new readers.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\readers;

use local_oksigeniaayvurenda\core\control_letter;

/**
 * The next card code of an entity: the number after the highest one it has, with as many digits, and the control
 * letter when the entity's codes carry one. Codes that are not numbers (or numbers with a letter) are left aside.
 *
 * Part of the core: plain PHP, with no call to Moodle.
 */
final class card_codes {
    /**
     * The codes that follow the existing ones.
     *
     * @param string[] $existing
     * @param int $count How many new codes.
     * @param bool $letter Whether codes end with a control letter.
     * @param int $digits Digits when there are no codes yet.
     * @return string[]
     */
    public static function next(array $existing, int $count, bool $letter, int $digits = 5): array {
        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/^(\d+)[A-Z]?$/', strtoupper(trim((string) $code)), $parts)) {
                $max = max($max, (int) $parts[1]);
                $digits = max($digits, strlen($parts[1]));
            }
        }
        $codes = [];
        for ($i = 1; $i <= $count; $i++) {
            $number = str_pad((string) ($max + $i), $digits, '0', STR_PAD_LEFT);
            $codes[] = $letter ? control_letter::append($number) : $number;
        }
        return $codes;
    }
}
