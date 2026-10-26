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
 * The spine of a book on the shelf: its size, its colour and how its title runs.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\core;

/**
 * As big as the book is, when the record says it: the height from its centimetres and the thickness from its pages.
 * The colour comes from the cover, kept between a lightness and a chroma that look like a book and not like mud, or
 * from the corner (Gaudí, 26 October 2026). Plain PHP, without Moodle.
 */
final class spine {
    /** @var float Shortest and tallest spine on the shelf, in centimetres. */
    public const MIN_CM = 18.0;

    /** @var float */
    public const MAX_CM = 30.0;

    /** @var int Thinnest spine, in pixels: a target a finger can touch (WCAG 2.2). */
    public const MIN_PX = 24;

    /** @var int Thickest spine, in pixels. */
    public const MAX_PX = 56;

    /**
     * The size of a book from what its record says.
     *
     * @param string $extent The extent («120 p. : il. ; 24 cm», or only «144» as Abies writes it).
     * @param string $dimensions The dimensions, when they come apart («20cm», «21 x 15 cm»).
     * @param int $seed Something fixed of the record (its id), for books that do not say their size.
     * @return array cm (height), pages, width (pixels) and known (whether the height came from the record).
     */
    public static function size(string $extent, string $dimensions, int $seed): array {
        $cm = null;
        foreach ([$dimensions, $extent] as $text) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:x\s*\d+(?:[.,]\d+)?\s*)?cm\b/iu', $text, $match)) {
                $cm = (float) str_replace(',', '.', $match[1]);
                break;
            }
        }
        $pages = 0;
        if (preg_match_all('/(\d+)\s*(?:p\b|pp\b|p\.|pág|pag|pages|h\.|hojas)/iu', $extent, $matches)) {
            $pages = max(array_map('intval', $matches[1]));
        } else if (preg_match('/^\s*(\d+)\s*$/', $extent, $match)) {
            $pages = (int) $match[1];
        }
        $known = $cm !== null && $cm > 5;
        $cm = $known ? max(self::MIN_CM, min(self::MAX_CM, $cm)) : self::MIN_CM + abs($seed * 7919) % 7;
        if ($pages <= 0 || $pages > 3000) {
            $pages = 48 + abs($seed * 104729) % 240;
        }
        $millimetres = 2 + $pages * 0.075;
        $width = (int) round(max(self::MIN_PX, min(self::MAX_PX, 20 + $millimetres * 0.9)));
        return ['cm' => round($cm, 1), 'pages' => $pages, 'width' => $width, 'known' => $known];
    }

    /**
     * The colour of a spine and of its letters.
     *
     * @param int[]|null $rgb The main colour of the cover, or null.
     * @param string $fallback The colour of the corner (#rrggbb), for books without a cover or with a grey one.
     * @param int $seed Something fixed of the record, to vary the corner's colour a little.
     * @return array background (#rrggbb), dark (the same, a little darker, for dark mode) and ink (cream or ink).
     */
    public static function colour(?array $rgb, string $fallback, int $seed): array {
        [$l, $c, $h] = $rgb !== null ? self::oklch($rgb) : [0.0, 0.0, 0.0];
        if ($rgb === null || $c < 0.06) {
            [$l, $c, $h] = self::oklch(self::rgb($fallback));
            // Books of a corner are not all the same: lighter or darker, a little warmer or colder.
            $l += ((abs($seed * 31) % 25) - 12) / 100;
            $h = fmod($h + ((abs($seed * 17) % 29) - 14) + 360, 360);
        }
        $l = max(0.42, min(0.78, $l));
        $c = max(0.06, $c);
        $cream = [251, 246, 236];
        $inkcolour = [30, 43, 42];
        // The letters in cream or in ink, whichever stands out more; if neither reaches 4.5:1, the spine moves away
        // from them a little, lighter under ink or darker under cream.
        for ($i = 0; $i < 12; $i++) {
            $background = self::from_oklch($l, $c, $h);
            $oncream = self::contrast($background, $cream);
            $onink = self::contrast($background, $inkcolour);
            if (max($oncream, $onink) >= 4.5) {
                break;
            }
            $l += $onink >= $oncream ? 0.02 : -0.02;
        }
        $ink = $oncream >= $onink ? 'cream' : 'ink';
        // In dark mode the same spine, a little darker; under cream letters only, so ink keeps its contrast.
        $dark = self::from_oklch($ink === 'cream' ? $l * 0.92 : $l, $c, $h);
        return ['background' => self::hex($background), 'dark' => self::hex($dark), 'ink' => $ink];
    }

    /**
     * Whether a title runs from top to bottom, as English books do; Spanish and most of Europe run from bottom to top.
     *
     * @param string $language The record's language (a MARC or ISO code), or ''.
     * @param string $fallback The person's language, when the record says none.
     * @return bool
     */
    public static function top_down(string $language, string $fallback): bool {
        $language = strtolower(trim($language));
        if ($language === '') {
            $language = strtolower(substr(trim($fallback), 0, 2));
        }
        return in_array($language, ['en', 'eng', 'nl', 'dut', 'nld', 'da', 'dan', 'sv', 'swe', 'nb', 'nn', 'no', 'nor'], true);
    }

    /**
     * A title cut to what fits along the spine, with an ellipsis.
     *
     * @param string $title
     * @param float $room Pixels along the spine for it.
     * @param float $size The size of the letters, in pixels.
     * @return string
     */
    public static function fit(string $title, float $room, float $size): string {
        // Literata's letters are a little more than half as wide as they are tall.
        $fits = max(1, (int) floor($room / ($size * 0.56)));
        if (mb_strlen($title) <= $fits) {
            return $title;
        }
        $cut = rtrim(mb_substr($title, 0, max(1, $fits - 1)), " ,.;:-");
        return $cut . '…';
    }

    /**
     * The colour of a place, when its name is a colour: school libraries mark their shelves and labels by colour.
     *
     * @param string $name «Rojo», «Naranja pálido», «Blue»…
     * @return string|null #rrggbb.
     */
    public static function place_colour(string $name): ?string {
        $words = explode(' ', text::normalise($name));
        $colours = [
            'rojo' => '#d63a2f', 'roja' => '#d63a2f', 'red' => '#d63a2f', 'vermell' => '#d63a2f', 'vermello' => '#d63a2f',
            'gorria' => '#d63a2f', 'naranja' => '#ef8a2b', 'orange' => '#ef8a2b', 'taronja' => '#ef8a2b',
            'laranxa' => '#ef8a2b', 'laranja' => '#ef8a2b', 'amarillo' => '#f2c230',
            'amarilla' => '#f2c230', 'yellow' => '#f2c230', 'groc' => '#f2c230', 'amarelo' => '#f2c230',
            'horia' => '#f2c230', 'verde' => '#3f9b4f', 'green' => '#3f9b4f', 'verd' => '#3f9b4f', 'berdea' => '#3f9b4f',
            'azul' => '#2f6fc0', 'blue' => '#2f6fc0', 'blau' => '#2f6fc0', 'urdina' => '#2f6fc0', 'celeste' => '#5fb3e0',
            'rosa' => '#e77fb0', 'pink' => '#e77fb0', 'arrosa' => '#e77fb0', 'morado' => '#7b4fa8', 'morada' => '#7b4fa8',
            'violeta' => '#7b4fa8', 'lila' => '#a98bd0', 'purple' => '#7b4fa8', 'violet' => '#7b4fa8', 'morat' => '#7b4fa8',
            'marron' => '#8a5a36', 'brown' => '#8a5a36', 'marro' => '#8a5a36', 'marrom' => '#8a5a36',
            'negro' => '#26292b', 'black' => '#26292b', 'negre' => '#26292b', 'beltza' => '#26292b',
            'blanco' => '#f7f5ef', 'white' => '#f7f5ef', 'blanc' => '#f7f5ef', 'branco' => '#f7f5ef', 'zuria' => '#f7f5ef',
            'gris' => '#8d9396', 'grey' => '#8d9396', 'gray' => '#8d9396', 'cinza' => '#8d9396',
            'turquesa' => '#2aa8a0', 'dorado' => '#c9a227', 'plateado' => '#b9bec2',
        ];
        if (count($words) > 3 || !isset($colours[$words[0]])) {
            return null;
        }
        [$l, $c, $h] = self::oklch(self::rgb($colours[$words[0]]));
        $rest = implode(' ', array_slice($words, 1));
        if (preg_match('/\b(palido|palida|claro|clara|light|pale|clar)\b/', $rest)) {
            $l = min(0.9, $l + 0.14);
            $c *= 0.7;
        } else if (preg_match('/\b(oscuro|oscura|dark|fosc|escuro)\b/', $rest)) {
            $l = max(0.3, $l - 0.14);
        }
        return self::hex(self::from_oklch($l, $c, $h));
    }

    /**
     * A colour as three numbers.
     *
     * @param string $hex #rrggbb.
     * @return int[]
     */
    public static function rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Three numbers as a colour.
     *
     * @param int[] $rgb
     * @return string #rrggbb.
     */
    public static function hex(array $rgb): string {
        return sprintf('#%02x%02x%02x', ...array_map(fn($v) => max(0, min(255, (int) round($v))), $rgb));
    }

    /**
     * The contrast between two colours, as WCAG measures it.
     *
     * @param int[] $one
     * @param int[] $two
     * @return float From 1 to 21.
     */
    public static function contrast(array $one, array $two): float {
        $lum = function (array $rgb): float {
            $linear = array_map(fn($v) => self::linear($v / 255), $rgb);
            return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        };
        $a = $lum($one);
        $b = $lum($two);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * From sRGB to OKLCH.
     *
     * @param int[] $rgb
     * @return float[] Lightness (0–1), chroma and hue (degrees).
     */
    public static function oklch(array $rgb): array {
        [$r, $g, $b] = array_map(fn($v) => self::linear($v / 255), $rgb);
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = array_map(fn($v) => $v <= 0 ? 0.0 : $v ** (1 / 3), [$l, $m, $s]);
        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $hue = rad2deg(atan2($bb, $a));
        return [$lightness, sqrt($a * $a + $bb * $bb), $hue < 0 ? $hue + 360 : $hue];
    }

    /**
     * From OKLCH to sRGB, losing chroma until the colour can be shown.
     *
     * @param float $l
     * @param float $c
     * @param float $h
     * @return float[]
     */
    public static function from_oklch(float $l, float $c, float $h): array {
        for ($i = 0; $i < 40; $i++) {
            $rgb = self::oklch_to_linear($l, $c, $h);
            if (min($rgb) >= -0.0005 && max($rgb) <= 1.0005) {
                break;
            }
            $c *= 0.92;
        }
        return array_map(fn($v) => 255 * self::gamma(max(0.0, min(1.0, $v))), $rgb);
    }

    /**
     * OKLCH to linear sRGB, without clipping.
     *
     * @param float $l
     * @param float $c
     * @param float $h
     * @return float[]
     */
    private static function oklch_to_linear(float $l, float $c, float $h): array {
        $a = $c * cos(deg2rad($h));
        $b = $c * sin(deg2rad($h));
        $ll = ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $mm = ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $ss = ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;
        return [
            4.0767416621 * $ll - 3.3077115913 * $mm + 0.2309699292 * $ss,
            -1.2684380046 * $ll + 2.6097574011 * $mm - 0.3413193965 * $ss,
            -0.0041960863 * $ll - 0.7034186147 * $mm + 1.7076147010 * $ss,
        ];
    }

    /**
     * sRGB to linear light.
     *
     * @param float $v 0–1.
     * @return float
     */
    private static function linear(float $v): float {
        return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Linear light to sRGB.
     *
     * @param float $v 0–1.
     * @return float
     */
    private static function gamma(float $v): float {
        return $v <= 0.0031308 ? 12.92 * $v : 1.055 * ($v ** (1 / 2.4)) - 0.055;
    }
}
