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
 * Code 39 barcodes.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core\barcode;

/**
 * Code 39, the barcode of library labels: digits, capitals and a few signs, each of nine elements of which three are
 * wide, between two asterisks. Drawn as SVG, black on white, so it prints sharp at any size. Plain PHP, without Moodle.
 */
final class code39 {
    /** @var array<string, string> Each character: nine elements, bar and space alternating; 1 is wide. */
    private const CODES = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000', '4' => '000110001',
        '5' => '100110000', '6' => '001110000', '7' => '000100101', '8' => '100100100', '9' => '001100100',
        'A' => '100001001', 'B' => '001001001', 'C' => '101001000', 'D' => '000011001', 'E' => '100011000',
        'F' => '001011000', 'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011', 'O' => '100010010',
        'P' => '001010010', 'Q' => '000000111', 'R' => '100000110', 'S' => '001000110', 'T' => '000010110',
        'U' => '110000001', 'V' => '011000001', 'W' => '111000000', 'X' => '010010001', 'Y' => '110010000',
        'Z' => '011010000', '-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
        '/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
    ];

    /**
     * Whether a text can be drawn.
     *
     * @param string $text
     * @return bool
     */
    public static function can(string $text): bool {
        $text = strtoupper($text);
        if ($text === '' || str_contains($text, '*')) {
            return false;
        }
        foreach (str_split($text) as $char) {
            if (!isset(self::CODES[$char])) {
                return false;
            }
        }
        return true;
    }

    /**
     * The bars: widths of bars and spaces in modules, in order, starting with a bar.
     *
     * @param string $text
     * @param int $wide How many narrow modules a wide element is.
     * @return int[]
     */
    public static function widths(string $text, int $wide = 3): array {
        $text = strtoupper($text);
        if (!self::can($text)) {
            throw new \InvalidArgumentException('Code 39 cannot draw ' . $text);
        }
        $widths = [];
        foreach (str_split('*' . $text . '*') as $index => $char) {
            if ($index > 0) {
                // The narrow space between characters.
                $widths[] = 1;
            }
            foreach (str_split(self::CODES[$char]) as $element) {
                $widths[] = $element === '1' ? $wide : 1;
            }
        }
        return $widths;
    }

    /**
     * The barcode as SVG, with its quiet zones; the text under it if asked.
     *
     * @param string $text
     * @param int $height Height of the bars, in modules.
     * @param bool $label Whether to write the text under the bars.
     * @return string
     */
    public static function svg(string $text, int $height = 40, bool $label = true): string {
        $widths = self::widths($text);
        $quiet = 10;
        $x = $quiet;
        $bars = '';
        foreach ($widths as $index => $width) {
            if ($index % 2 === 0) {
                $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '"/>';
            }
            $x += $width;
        }
        $total = $x + $quiet;
        $textheight = $label ? 14 : 0;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . ($height + $textheight) .
            '" preserveAspectRatio="none" role="img" aria-label="' . htmlspecialchars(strtoupper($text)) . '">' .
            '<rect width="100%" height="100%" fill="#fff"/><g fill="#000">' . $bars . '</g>';
        if ($label) {
            $svg .= '<text x="' . ($total / 2) . '" y="' . ($height + 12) . '" font-family="monospace" font-size="12" ' .
                'text-anchor="middle" fill="#000">' . htmlspecialchars(strtoupper($text)) . '</text>';
        }
        return $svg . '</svg>';
    }
}
