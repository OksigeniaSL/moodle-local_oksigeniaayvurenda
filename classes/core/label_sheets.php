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
 * The sheets of sticky labels a library buys, by brand and reference, with their measures.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * Each sheet: the brand and its reference, what else sells the same sheet, the label (width and height), the grid
 * (columns and rows), the margins of the page (left and top), the gaps between columns and rows, and the paper. All in
 * millimetres.
 *
 * Apli: square-cornered sheets without gaps, their size and count from apli.com (7 November 2026), centred on the page
 * unless the templates of gLabels say otherwise (01271, 01293). Avery and Avery Zweckform: their published templates,
 * compared with those of gLabels (GPL); a few tenths may differ from a given box, which the library corrects with
 * «Adjust the print» and the test sheet. Part of the core: plain PHP, with no call to Moodle.
 */
final class label_sheets {
    /** @var array[] The sheets, by key. */
    public const MODELS = [
        // Apli, square corners, A4.
        'apli-01286' => ['brand' => 'Apli', 'ref' => '01286', 'also' => 'Avery 3651', 'w' => 52.5, 'h' => 29.7, 'cols' => 4,
            'rows' => 10, 'left' => 0, 'top' => 0, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01276' => ['brand' => 'Apli', 'ref' => '01276', 'also' => '', 'w' => 70, 'h' => 42.4, 'cols' => 3, 'rows' => 7,
            'left' => 0, 'top' => 0.1, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01273' => ['brand' => 'Apli', 'ref' => '01273', 'also' => 'Avery 3474', 'w' => 70, 'h' => 37, 'cols' => 3,
            'rows' => 8, 'left' => 0, 'top' => 0.5, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01272' => ['brand' => 'Apli', 'ref' => '01272', 'also' => '', 'w' => 70, 'h' => 35, 'cols' => 3, 'rows' => 8,
            'left' => 0, 'top' => 8.5, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01298' => ['brand' => 'Apli', 'ref' => '01298', 'also' => 'Avery 3475', 'w' => 70, 'h' => 36, 'cols' => 3,
            'rows' => 8, 'left' => 0, 'top' => 4.5, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01293' => ['brand' => 'Apli', 'ref' => '01293', 'also' => '', 'w' => 70, 'h' => 33.8, 'cols' => 3, 'rows' => 8,
            'left' => 0, 'top' => 15.3, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01271' => ['brand' => 'Apli', 'ref' => '01271', 'also' => '', 'w' => 70, 'h' => 30, 'cols' => 3, 'rows' => 9,
            'left' => 0, 'top' => 13.5, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01270' => ['brand' => 'Apli', 'ref' => '01270', 'also' => '', 'w' => 70, 'h' => 25.4, 'cols' => 3, 'rows' => 11,
            'left' => 0, 'top' => 8.8, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01294' => ['brand' => 'Apli', 'ref' => '01294', 'also' => '', 'w' => 70, 'h' => 16.9, 'cols' => 3, 'rows' => 17,
            'left' => 0, 'top' => 4.85, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01263' => ['brand' => 'Apli', 'ref' => '01263', 'also' => '', 'w' => 64.6, 'h' => 33.8, 'cols' => 3, 'rows' => 8,
            'left' => 8.1, 'top' => 13.3, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01284' => ['brand' => 'Apli', 'ref' => '01284', 'also' => '', 'w' => 52.5, 'h' => 21.2, 'cols' => 4, 'rows' => 14,
            'left' => 0, 'top' => 0.1, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01285' => ['brand' => 'Apli', 'ref' => '01285', 'also' => 'Avery 3657', 'w' => 48.5, 'h' => 25.4, 'cols' => 4,
            'rows' => 11, 'left' => 8, 'top' => 8.8, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01282' => ['brand' => 'Apli', 'ref' => '01282', 'also' => '', 'w' => 48.5, 'h' => 16.9, 'cols' => 4, 'rows' => 17,
            'left' => 8, 'top' => 4.85, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01283' => ['brand' => 'Apli', 'ref' => '01283', 'also' => '', 'w' => 38, 'h' => 21.2, 'cols' => 5, 'rows' => 14,
            'left' => 10, 'top' => 0.1, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01274' => ['brand' => 'Apli', 'ref' => '01274', 'also' => '', 'w' => 105, 'h' => 37, 'cols' => 2, 'rows' => 8,
            'left' => 0, 'top' => 0.5, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'apli-01277' => ['brand' => 'Apli', 'ref' => '01277', 'also' => '', 'w' => 105, 'h' => 42.4, 'cols' => 2, 'rows' => 7,
            'left' => 0, 'top' => 0.1, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        // Avery Zweckform, square corners, A4.
        'avery-3666' => ['brand' => 'Avery', 'ref' => '3666', 'also' => '', 'w' => 38, 'h' => 21.2, 'cols' => 5, 'rows' => 13,
            'left' => 10, 'top' => 10.7, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        'avery-3652' => ['brand' => 'Avery', 'ref' => '3652', 'also' => '', 'w' => 70, 'h' => 42.3, 'cols' => 3, 'rows' => 7,
            'left' => 0, 'top' => 0.45, 'gx' => 0, 'gy' => 0, 'paper' => 'a4'],
        // Avery, rounded corners with gaps, A4.
        'avery-l7160' => ['brand' => 'Avery', 'ref' => 'L7160', 'also' => 'J8160', 'w' => 63.5, 'h' => 38.1, 'cols' => 3,
            'rows' => 7, 'left' => 7.21, 'top' => 15.15, 'gx' => 2.54, 'gy' => 0, 'paper' => 'a4'],
        'avery-l7161' => ['brand' => 'Avery', 'ref' => 'L7161', 'also' => 'J8161', 'w' => 63.5, 'h' => 46.6, 'cols' => 3,
            'rows' => 6, 'left' => 7.21, 'top' => 8.7, 'gx' => 2.54, 'gy' => 0, 'paper' => 'a4'],
        'avery-l7162' => ['brand' => 'Avery', 'ref' => 'L7162', 'also' => 'J8162', 'w' => 99.1, 'h' => 33.9, 'cols' => 2,
            'rows' => 8, 'left' => 4.65, 'top' => 12.9, 'gx' => 2.5, 'gy' => 0, 'paper' => 'a4'],
        'avery-l7163' => ['brand' => 'Avery', 'ref' => 'L7163', 'also' => 'J8163', 'w' => 99.1, 'h' => 38.1, 'cols' => 2,
            'rows' => 7, 'left' => 4.65, 'top' => 15.15, 'gx' => 2.5, 'gy' => 0, 'paper' => 'a4'],
        'avery-l7651' => ['brand' => 'Avery', 'ref' => 'L7651', 'also' => 'J8651', 'w' => 38.1, 'h' => 21.2, 'cols' => 5,
            'rows' => 13, 'left' => 4.75, 'top' => 10.7, 'gx' => 2.5, 'gy' => 0, 'paper' => 'a4'],
        'avery-l4732' => ['brand' => 'Avery', 'ref' => 'L4732', 'also' => '', 'w' => 35.6, 'h' => 16.9, 'cols' => 5, 'rows' => 16,
            'left' => 11, 'top' => 13.1, 'gx' => 2.5, 'gy' => 0, 'paper' => 'a4'],
        // Avery, US Letter.
        'avery-5160' => ['brand' => 'Avery', 'ref' => '5160', 'also' => '8160', 'w' => 66.675, 'h' => 25.4, 'cols' => 3,
            'rows' => 10, 'left' => 4.7625, 'top' => 12.7, 'gx' => 3.175, 'gy' => 0, 'paper' => 'letter'],
        'avery-5167' => ['brand' => 'Avery', 'ref' => '5167', 'also' => '8167', 'w' => 44.45, 'h' => 12.7, 'cols' => 4,
            'rows' => 20, 'left' => 7.62, 'top' => 12.7, 'gx' => 7.62, 'gy' => 0, 'paper' => 'letter'],
    ];

    /** @var string[] The sheets the page offered before 0.171.0, by their old key. */
    public const LEGACY = ['3x8' => 'apli-01273', '3x7' => 'avery-3652', '4x10' => 'apli-01285', '5x13' => 'avery-3666'];

    /** @var string[] The sheet of each kind for a library that has chosen none. */
    public const DEFAULTS = ['labels' => 'apli-01273', 'spines' => 'avery-3666'];

    /** @var float The most a print may be moved, in millimetres. */
    public const NUDGE = 10.0;

    /**
     * A sheet by its key, an old key or none (the default of its kind).
     *
     * @param string $key
     * @param string $kind labels or spines.
     * @return string The key of a sheet that exists.
     */
    public static function key(string $key, string $kind = 'labels'): string {
        $key = self::LEGACY[$key] ?? $key;
        return isset(self::MODELS[$key]) ? $key : self::DEFAULTS[$kind === 'spines' ? 'spines' : 'labels'];
    }

    /**
     * A sheet with the print moved as the library says: its margins plus the nudge, which the gaps and the grid keep.
     *
     * @param string $key
     * @param float $x Millimetres to the right (negative, to the left).
     * @param float $y Millimetres down (negative, up).
     * @return array The model, with left and top moved and per (labels on a sheet).
     */
    public static function placed(string $key, float $x = 0, float $y = 0): array {
        $model = self::MODELS[self::key($key)];
        $clamp = fn(float $value) => max(-self::NUDGE, min(self::NUDGE, $value));
        $model['left'] = round($model['left'] + $clamp($x), 2);
        $model['top'] = round($model['top'] + $clamp($y), 2);
        $model['per'] = $model['cols'] * $model['rows'];
        return $model;
    }

    /**
     * Whether the grid of a sheet fits its paper: what the margins, the labels and the gaps take, across and down.
     *
     * @param string $key
     * @return bool
     */
    public static function fits(string $key): bool {
        $model = self::MODELS[$key];
        [$pw, $ph] = $model['paper'] === 'letter' ? [215.9, 279.4] : [210.0, 297.0];
        $across = $model['left'] + $model['cols'] * $model['w'] + ($model['cols'] - 1) * $model['gx'];
        $down = $model['top'] + $model['rows'] * $model['h'] + ($model['rows'] - 1) * $model['gy'];
        return $across <= $pw + 0.05 && $down <= $ph + 0.05;
    }
}
