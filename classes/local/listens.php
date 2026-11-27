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
 * How many times a voice has been heard, told as an audience.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniaayvurenda\local;

/**
 * The listens of a voice are not a bare number: each listen takes a seat in a place, seen from above, and the places
 * grow with the voice: a sofa, a circle on the rug, a class, a coach, a school hall, a theatre, a sports hall. The voice
 * is where a reader would be (in front, or in the middle of the circle). The sentence says the biggest place the voice
 * has already filled («Ya es una clase entera»), and the drawing shows the place it is filling now, so every new listen
 * lights one more seat and nothing ever goes back.
 *
 * Nothing here knows who listened: only how many times.
 *
 * The drawing is SVG built here, with no text inside (the words go beside it, in the page's language). Two looks, set
 * by the template's class: «lamina» (cream plate, people in the colours of the library) and «noche» (the night green
 * of the sky, people as warm lights). The plate keeps its colours in dark mode, as every plate of the library.
 */
final class listens {
    /** @var string[] The places, smallest first. */
    public const PLACES = ['sofa', 'corro', 'clase', 'autobus', 'salon', 'teatro', 'polideportivo'];

    /** @var int Width of the drawing (the height is HEIGHT). */
    private const WIDTH = 320;

    /** @var int Height of the drawing. */
    private const HEIGHT = 190;

    /** @var int The lit seats that appear one after another; the rest come with the last of them. */
    private const STAGGER = 24;

    /** @var array|null The places, drawn once: key => [capacity, furniture, seats or rows]. */
    private static $drawn = null;

    /**
     * The listens of a voice for the templates, in the words of the plugin and the numbers of the language.
     *
     * @param \stdClass $voice A voice (a digital item of kind voice).
     * @param string $who mine (the reader who recorded it), family, public (whoever listens) or staff.
     * @param string $look lamina or noche: look() says which.
     * @return array|null Nothing for a voice still waiting for approval (nobody hears it yet), nor for whoever listens at a
     *     university, where nothing is public by default.
     */
    public static function of(\stdClass $voice, string $who, string $look = 'lamina'): ?array {
        if (($voice->status ?? '') !== 'published') {
            return null;
        }
        if ($who === 'public' && skins::of((int) $voice->entityid) === 'university') {
            return null;
        }
        $count = (int) ($voice->listens ?? 0);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniaayvurenda', $a);
        return self::context($count, $str, ['who' => $who, 'look' => $look, 'number' => screens::number($count)]);
    }

    /**
     * The listens of a reading in parts, as many as its most heard part: everyone starts at the beginning, and a part
     * heard less was left halfway, not by fewer people.
     *
     * @param \stdClass[] $parts Its voices, any of them still waiting.
     * @param string $who As of() takes it.
     * @param string $look As of() takes it.
     * @return array|null As of() gives it; nothing while none of its parts is approved.
     */
    public static function reading(array $parts, string $who, string $look = 'lamina'): ?array {
        $heard = array_values(array_filter($parts, fn($part) => ($part->status ?? '') === 'published'));
        if (!$heard) {
            return null;
        }
        $most = clone $heard[0];
        $most->listens = max(array_map(fn($part) => (int) ($part->listens ?? 0), $heard));
        return self::of($most, $who, $look);
    }

    /**
     * The look of the big one at a centre: the cream plate for the flower's readers, the night for the others.
     *
     * @param int $entityid
     * @return string lamina or noche.
     */
    public static function look(int $entityid): string {
        return characters::young($entityid) ? 'lamina' : 'noche';
    }

    /**
     * The context of the templates listens (the big one) and listens_small.
     *
     * @param int $count How many times the voice has been heard.
     * @param callable $str fn(string $key, $a = null): string, the strings of the plugin (get_string in Moodle).
     * @param array $opts who: mine (the reader who recorded it), family, public (whoever listens) or staff; look:
     *     lamina or noche; number: the count already formatted for the language (a plain number if not given).
     * @return array look, svg (raw SVG), hasnumber, digits and unit (the count, big, and its word), number (the two
     *     together), phrase, next (string or false), glyph (raw SVG), small (string or false: false when the small one
     *     is not shown), place, lit, capacity.
     */
    public static function context(int $count, callable $str, array $opts = []): array {
        $count = max(0, $count);
        $who = $opts['who'] ?? 'mine';
        $look = ($opts['look'] ?? 'lamina') === 'noche' ? 'noche' : 'lamina';
        $places = self::places();

        // The place being filled now (the smallest that still has room for every listen) and the biggest already full.
        $now = self::PLACES[count(self::PLACES) - 1];
        foreach (self::PLACES as $key) {
            if ($places[$key]['capacity'] >= max(1, $count)) {
                $now = $key;
                break;
            }
        }
        $done = false;
        foreach (self::PLACES as $key) {
            if ($places[$key]['capacity'] <= $count) {
                $done = $key;
            }
        }
        $capacity = $places[$now]['capacity'];
        $lit = min($count, $capacity);

        $number = $opts['number'] ?? self::number($count);
        $countline = $count === 1 ? $str('listenscountone') : $str('listenscount', $number);

        if ($count === 0) {
            $phrase = $str('listensnone');
        } else if (!$done) {
            $phrase = $count === 1 ? $str('listensfirst') : $str('listensfirsts');
        } else {
            $short = $str('listensshort_' . $done);
            $phrase = $count > $places[$done]['capacity'] ? $str('listensmore', $short) : $str('listensis', $short);
        }

        // What is still missing to fill the place: for the reader and for the family, never for the others.
        $next = false;
        if (in_array($who, ['mine', 'family'], true)) {
            if ($count === 0) {
                $next = $str('listensnextnone');
            } else if ($count < $capacity) {
                $a = (object) ['place' => $str('listensplace_' . $now), 'n' => self::number($capacity - $count)];
                $next = $capacity - $count === 1 ? $str('listensnextone', $a) : $str('listensnext', $a);
            }
        }

        // The small one: with the number for the reader, the family and the staff; for whoever listens, only once the
        // voice has filled a sofa, and with no number, so that a voice with one listen is never shown beside one with
        // three hundred.
        if ($who === 'public') {
            $small = $done ? $str('listensis', $str('listensshort_' . $done)) : false;
        } else {
            $small = $done ? $countline . ' · ' . $str('listensshort_' . $done) : $countline;
        }

        return [
            'look' => $look,
            'svg' => self::svg($now, $lit),
            'hasnumber' => $count > 0,
            'digits' => $number,
            'unit' => $count === 1 ? $str('listensunitone') : $str('listensunit'),
            'number' => $countline,
            'phrase' => $phrase,
            'next' => $next,
            'glyph' => self::glyph($count),
            'small' => $small,
            'place' => $now,
            'lit' => $lit,
            'capacity' => $capacity,
        ];
    }

    /**
     * How many seats each place has.
     *
     * @return array key => capacity.
     */
    public static function capacities(): array {
        return array_map(fn($p) => $p['capacity'], self::places());
    }

    /**
     * A count as Spanish writes it: no mark up to 9999, a thin space from 10 000.
     *
     * @param int $n
     * @return string
     */
    private static function number(int $n): string {
        return $n < 10000 ? (string) $n : number_format($n, 0, ',', "\u{202F}");
    }

    /**
     * The drawing of a place with its first $lit seats taken.
     *
     * @param string $key
     * @param int $lit
     * @return string
     */
    private static function svg(string $key, int $lit): string {
        $place = self::places()[$key];
        $out = '<svg class="ayv-esc-svg ayv-esc-' . $key . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT .
            '" aria-hidden="true" focusable="false">' . $place['furniture'];
        if (isset($place['seats'])) {
            foreach ($place['seats'] as $seat) {
                $out .= '<g transform="translate(' . self::f($seat['x']) . ' ' . self::f($seat['y']) . ')' .
                    ($seat['r'] ? ' rotate(' . self::f($seat['r']) . ')' : '') . '">' . $seat['seat'] . '</g>';
            }
            // The people go upright on their seats, whatever way the seat faces: a bust is read as someone at once.
            foreach ($place['seats'] as $i => $seat) {
                if ($i >= $lit) {
                    break;
                }
                $out .= '<g transform="translate(' . self::f($seat['x']) . ' ' . self::f($seat['y'] + ($seat['dy'] ?? 0)) .
                    ')">' . (empty($seat['above']) ? self::person($seat['w'], $i) : self::passenger($seat['w'], $i)) . '</g>';
            }
        } else {
            $left = $lit;
            $out .= '<g class="e-vacio" stroke-width="' . self::f($place['dot']) . '">';
            foreach ($place['rows'] as $row) {
                foreach ($row as $path) {
                    $out .= '<path d="' . $path['d'] . '" stroke-dasharray="0 ' . self::f($path['s']) . '"/>';
                }
            }
            $out .= '</g><g class="e-gente" stroke-width="' . self::f($place['dot'] + 0.6) . '">';
            foreach ($place['rows'] as $r => $row) {
                if ($left <= 0) {
                    break;
                }
                // The seats of a row go to its halves in turn, from the aisle outward.
                $seats = array_sum(array_column($row, 'n'));
                $take = min($left, $seats);
                $left -= $take;
                $share = array_fill(0, count($row), 0);
                for ($t = 0, $p = 0; $t < $take; $p = ($p + 1) % count($row)) {
                    if ($share[$p] < $row[$p]['n']) {
                        $share[$p]++;
                        $t++;
                    }
                }
                $delay = ' style="--i:' . min($r, self::STAGGER) . '"';
                foreach ($row as $p => $path) {
                    if (!$share[$p]) {
                        continue;
                    }
                    $dash = $share[$p] >= $path['n'] ? '0 ' . self::f($path['s'])
                        : str_repeat('0 ' . self::f($path['s']) . ' ', $share[$p] - 1) . '0 ' . self::f($path['len'] + 10);
                    $out .= '<path class="e-on"' . $delay . ' d="' . $path['d'] . '" stroke-dasharray="' . $dash . '"/>';
                }
            }
            $out .= '</g>';
        }
        return $out . $place['voice'] . '</svg>';
    }

    /**
     * Someone sitting, from the front: the head over the shoulders, in one of four colours. The origin is between
     * them; the shoulders end 0.95 $w below it.
     *
     * @param float $w Half the width of the shoulders.
     * @param int $i Which listen it is (sets the colour and when it appears).
     * @return string
     */
    private static function person(float $w, int $i): string {
        $c = self::colour($i);
        return '<g class="e-on" style="--i:' . min($i, self::STAGGER) . '">' . self::bust($w, 'e-p' . $c, 'e-c' . $c) .
            '</g>';
    }

    /**
     * Someone sitting, seen from above and facing right: the arc of the shoulders, the head with its cap of hair in
     * one of four colours, and a crescent of face in front. Where the place is seen from above (the coach), it is
     * read better than a bust, and it is the bridge between the busts and the lights.
     *
     * @param float $w Half the width of the shoulders (across, along y).
     * @param int $i Which listen it is (sets the colour and when it appears).
     * @return string
     */
    private static function passenger(float $w, int $i): string {
        $c = self::colour($i);
        $r = $w * 0.6;
        return '<g class="e-on" style="--i:' . min($i, self::STAGGER) . '">' .
            '<ellipse class="e-s' . $c . '" cx="' . self::f(-$w * 0.18) . '" rx="' . self::f($w * 0.42) . '" ry="' .
            self::f($w) . '"/><circle class="e-cara" cx="' . self::f($w * 0.14) . '" r="' . self::f($r) . '"/>' .
            '<circle class="e-p' . $c . '" cx="' . self::f($w * 0.14 - $r * 0.34) . '" r="' . self::f($r * 0.9) . '"/></g>';
    }

    /**
     * The colour of the nth listen: the four in turn, shifted every four, so that the rows of a coach or a class do
     * not come out in stripes.
     *
     * @param int $i
     * @return int
     */
    private static function colour(int $i): int {
        return ($i + intdiv($i, 4)) % 4;
    }

    /**
     * The shape of someone sitting, from the front: shoulders and head.
     *
     * @param float $w Half the width of the shoulders.
     * @param string $body The class of the shoulders.
     * @param string $head The class of the head.
     * @return string
     */
    private static function bust(float $w, string $body, string $head): string {
        $d = 'M' . self::f(-$w) . ' ' . self::f(0.95 * $w) . 'C' . self::f(-$w) . ' ' . self::f(0.12 * $w) . ' ' .
            self::f(-0.5 * $w) . ' ' . self::f(0.02 * $w) . ' 0 ' . self::f(0.02 * $w) . 'S' . self::f($w) . ' ' .
            self::f(0.12 * $w) . ' ' . self::f($w) . ' ' . self::f(0.95 * $w) . 'Z';
        return '<path class="' . $body . '" d="' . $d . '"/><circle class="' . $head . '" r="' . self::f($w * 0.47) .
            '" cy="' . self::f(-0.47 * $w) . '"/>';
    }

    /**
     * The small mark: three people side by side, the middle one in front, in colour once someone has listened.
     *
     * @param int $count
     * @return string
     */
    private static function glyph(int $count): string {
        $on = $count > 0 ? ' ayv-esc-glifo-on' : '';
        $out = '<svg class="ayv-esc-glifo' . $on . '" viewBox="0 0 30 20" width="30" height="20" aria-hidden="true" ' .
            'focusable="false">';
        foreach ([[7, 11.6, 4.6, 0], [23, 11.6, 4.6, 2], [15, 10.4, 5.6, 1]] as [$x, $y, $w, $n]) {
            $out .= '<g transform="translate(' . self::f($x) . ' ' . self::f($y) . ')">' .
                self::bust($w, 'e-g' . $n, 'e-g' . $n) . '</g>';
        }
        return $out . '</svg>';
    }

    /**
     * The open book with its voice, where a reader would be.
     *
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private static function voice(float $x, float $y, float $scale = 1.0): string {
        return '<g class="e-voz" transform="translate(' . self::f($x) . ' ' . self::f($y) . ') scale(' .
            self::f($scale) . ')"><path class="e-libro" d="M-13 -3c5-3.5 9-3.5 13 0c4-3.5 8-3.5 13 0v12c-5-3.2-9-3.2-13 ' .
            '0c-4-3.2-8-3.2-13 0z"/><path class="e-lomo" d="M0 -3v12"/><path class="e-onda" d="M-7 -9.5q7-5.5 14 0"/>' .
            '<path class="e-onda e-onda2" d="M-12 -14.5q12-9 24 0"/></g>';
    }

    /**
     * Every place, drawn once.
     *
     * @return array
     */
    private static function places(): array {
        if (self::$drawn === null) {
            self::$drawn = [
                'sofa' => self::sofa(),
                'corro' => self::corro(),
                'clase' => self::clase(),
                'autobus' => self::autobus(),
                'salon' => self::salon(),
                'teatro' => self::teatro(),
                'polideportivo' => self::polideportivo(),
            ];
        }
        return self::$drawn;
    }

    /**
     * A sofa from the front, three seats, and the book on the rug before it.
     *
     * @return array
     */
    private static function sofa(): array {
        $furniture = '<ellipse class="e-alfombra" cx="160" cy="166" rx="128" ry="18"/>' .
            '<rect class="e-ruedas" x="86" y="140" width="9" height="16" rx="3"/>' .
            '<rect class="e-ruedas" x="225" y="140" width="9" height="16" rx="3"/>' .
            '<rect class="e-mueble" x="70" y="30" width="180" height="92" rx="22"/>' .
            '<rect class="e-asiento" x="76" y="104" width="168" height="42" rx="12"/>' .
            '<rect class="e-mueble" x="50" y="70" width="38" height="78" rx="17"/>' .
            '<rect class="e-mueble" x="232" y="70" width="38" height="78" rx="17"/>';
        $seats = [];
        foreach ([1, 0, 2] as $i) {
            $x = 112 + $i * 48;
            $seats[] = ['x' => $x, 'y' => 85, 'r' => 0, 'w' => 21, 'dy' => 2,
                'seat' => '<rect class="e-sofa" x="-22" y="-47" width="44" height="66" rx="11"/>' .
                    '<rect class="e-sofa2" x="-23" y="22" width="46" height="18" rx="8"/>'];
        }
        return ['capacity' => 3, 'furniture' => $furniture, 'seats' => $seats, 'voice' => self::voice(160, 170, 1.1)];
    }

    /**
     * Twelve cushions in a circle on the rug, the book in the middle.
     *
     * @return array
     */
    private static function corro(): array {
        $furniture = '<circle class="e-alfombra" cx="160" cy="96" r="88"/><circle class="e-alfombra2" cx="160" cy="96" r="44"/>';
        $seats = [];
        foreach ([0, 1, 11, 2, 10, 3, 9, 4, 8, 5, 7, 6] as $i) {
            $a = deg2rad(-90 + $i * 30);
            $seats[] = ['x' => 160 + 68 * cos($a), 'y' => 96 + 68 * sin($a), 'r' => rad2deg($a) + 90, 'w' => 12.5,
                'seat' => '<circle class="e-cojin" r="14"/>'];
        }
        return ['capacity' => 12, 'furniture' => $furniture, 'seats' => $seats, 'voice' => self::voice(160, 100, 1.05)];
    }

    /**
     * A class of five rows of five, facing the board, where the book is.
     *
     * @return array
     */
    private static function clase(): array {
        $furniture = '<rect class="e-alfombra" x="18" y="4" width="284" height="148" rx="18"/>' .
            '<rect class="e-mueble" x="88" y="160" width="144" height="26" rx="6"/>';
        $seats = [];
        foreach ([128, 101, 74, 47, 20] as $y) {
            foreach ([160, 104, 216, 48, 272] as $x) {
                $seats[] = ['x' => $x, 'y' => $y, 'r' => 0, 'w' => 11, 'dy' => -3,
                    'seat' => '<rect class="e-cojin" x="-11" y="-10" width="22" height="18" rx="6"/>' .
                        '<rect class="e-mesa" x="-19" y="9" width="38" height="8" rx="3"/>'];
            }
        }
        return ['capacity' => 25, 'furniture' => $furniture, 'seats' => $seats, 'voice' => self::voice(160, 177, 0.78)];
    }

    /**
     * A coach seen from above, its front to the right, where the book is: thirteen rows of four and the back bench.
     *
     * @return array
     */
    private static function autobus(): array {
        $furniture = '<rect class="e-ruedas" x="50" y="2" width="38" height="186" rx="7"/>' .
            '<rect class="e-ruedas" x="230" y="2" width="38" height="186" rx="7"/>' .
            '<rect class="e-mueble" x="4" y="8" width="312" height="174" rx="28"/>' .
            '<rect class="e-suelo" x="11" y="15" width="290" height="160" rx="22"/>' .
            '<path class="e-cristal" d="M303 30q13 65 0 130"/>';
        $seats = [];
        for ($c = 0; $c < 13; $c++) {
            $x = 259 - $c * 17.7;
            foreach ([68, 122, 41, 149] as $y) {
                $seats[] = ['x' => $x, 'y' => $y, 'r' => 0, 'w' => 10.5, 'above' => true, 'seat' => self::busseat()];
            }
        }
        foreach ([95, 68, 122, 41, 149] as $y) {
            $seats[] = ['x' => 29, 'y' => $y, 'r' => 0, 'w' => 10.5, 'above' => true, 'seat' => self::busseat()];
        }
        return ['capacity' => count($seats), 'furniture' => $furniture, 'seats' => $seats,
            'voice' => self::voice(285, 101, 0.62)];
    }

    /**
     * A seat of the coach seen from above, facing right, with the stripe of its back behind.
     *
     * @return string
     */
    private static function busseat(): string {
        return '<rect class="e-butaca" x="-7.5" y="-12" width="15" height="24" rx="4"/>' .
            '<rect class="e-respaldo" x="-8.5" y="-12" width="4.5" height="24" rx="2.2"/>';
    }

    /**
     * A school hall: ten rows of sixteen with an aisle in the middle, gently curved towards the stage.
     *
     * @return array
     */
    private static function salon(): array {
        $rows = [];
        for ($j = 0; $j < 10; $j++) {
            $rows[] = self::arcrow(160, 530, 380 + 13.5 * $j, 9, 9 + 7 * 15, 8);
        }
        $furniture = '<rect class="e-alfombra" x="14" y="14" width="292" height="150" rx="18"/>' .
            '<rect class="e-mueble" x="92" y="160" width="136" height="26" rx="8"/>';
        return ['capacity' => self::seatsof($rows), 'furniture' => $furniture, 'rows' => $rows, 'dot' => 6.4,
            'voice' => self::voice(160, 176, 0.62)];
    }

    /**
     * A theatre: fifteen rows in half circles around the stage, with an aisle in the middle.
     *
     * @return array
     */
    private static function teatro(): array {
        $rows = [];
        $span = deg2rad(78);
        for ($j = 0; $j < 15; $j++) {
            $r = 36 + 8.5 * $j;
            $rows[] = self::arcrow(160, 180, $r, 4.5, $r * $span, null, 7.4);
        }
        $furniture = '<path class="e-alfombra" d="M4 186a156 156 0 0 1 312 0z"/>' .
            '<path class="e-mueble" d="M132 186a28 28 0 0 1 56 0z"/>';
        return ['capacity' => self::seatsof($rows), 'furniture' => $furniture, 'rows' => $rows, 'dot' => 4.2,
            'voice' => self::voice(160, 176, 0.52)];
    }

    /**
     * A sports hall: the court in the middle, where the book is, and the stands all around it.
     *
     * @return array
     */
    private static function polideportivo(): array {
        $rows = [];
        [$x0, $y0, $x1, $y1] = [86, 62, 234, 128];
        for ($i = 0; $i < 9; $i++) {
            $o = 12 + 5.1 * $i;
            $s = 4.5;
            $row = [];
            // Each side, from its middle outward, so that the stands fill from the middle of each side.
            foreach ([[$y0 - $o, true], [$y1 + $o, true], [$x0 - $o, false], [$x1 + $o, false]] as [$at, $across]) {
                if ($across) {
                    $half = ($x1 - $x0) / 2 + $o;
                    foreach ([1, -1] as $dir) {
                        $row[] = self::line(160 + $dir * $s / 2, $at, 160 + $dir * $half, $at, $s);
                    }
                } else {
                    $half = ($y1 - $y0) / 2 + $o - $s;
                    foreach ([1, -1] as $dir) {
                        $row[] = self::line($at, 95 + $dir * $s / 2, $at, 95 + $dir * $half, $s);
                    }
                }
            }
            $rows[] = $row;
        }
        $furniture = '<rect class="e-alfombra" x="22" y="6" width="276" height="178" rx="20"/>' .
            '<rect class="e-mueble" x="' . $x0 . '" y="' . $y0 . '" width="' . ($x1 - $x0) . '" height="' . ($y1 - $y0) .
            '" rx="4"/><path class="e-cristal" d="M160 ' . $y0 . 'V' . $y1 . '"/>' .
            '<circle class="e-cristal" cx="160" cy="95" r="17" fill="none"/>';
        return ['capacity' => self::seatsof($rows), 'furniture' => $furniture, 'rows' => $rows, 'dot' => 2.6,
            'voice' => self::voice(160, 98, 0.5)];
    }

    /**
     * A row of seats on an arc around (cx, cy), in two halves that start at the aisle and go outward.
     *
     * @param float $cx
     * @param float $cy
     * @param float $r The radius.
     * @param float $gap Half the aisle, along the arc.
     * @param float $end Where the half ends, along the arc from the middle.
     * @param int|null $n Seats in each half (or as many as fit with the spacing $s).
     * @param float $s The spacing, when $n is not given.
     * @return array
     */
    private static function arcrow(float $cx, float $cy, float $r, float $gap, float $end, ?int $n, float $s = 0): array {
        $len = $end - $gap;
        if ($n === null) {
            $n = (int) floor($len / $s) + 1;
        }
        $s = $len / ($n - 1);
        $row = [];
        foreach ([1, -1] as $dir) {
            $a0 = $dir * $gap / $r;
            $a1 = $dir * $end / $r;
            $d = 'M' . self::f($cx + $r * sin($a0)) . ' ' . self::f($cy - $r * cos($a0)) . 'A' . self::f($r) . ' ' .
                self::f($r) . ' 0 0 ' . ($dir > 0 ? 1 : 0) . ' ' . self::f($cx + $r * sin($a1)) . ' ' .
                self::f($cy - $r * cos($a1));
            $row[] = ['d' => $d, 'n' => $n, 's' => $s, 'len' => $len];
        }
        return $row;
    }

    /**
     * A straight row of seats, as many as fit with the spacing $s.
     *
     * @param float $xa
     * @param float $ya
     * @param float $xb
     * @param float $yb
     * @param float $s
     * @return array
     */
    private static function line(float $xa, float $ya, float $xb, float $yb, float $s): array {
        $len = hypot($xb - $xa, $yb - $ya);
        $n = (int) floor($len / $s) + 1;
        return ['d' => 'M' . self::f($xa) . ' ' . self::f($ya) . 'L' . self::f($xb) . ' ' . self::f($yb), 'n' => $n,
            's' => $len / ($n - 1), 'len' => $len];
    }

    /**
     * How many seats some rows have.
     *
     * @param array $rows
     * @return int
     */
    private static function seatsof(array $rows): int {
        $n = 0;
        foreach ($rows as $row) {
            $n += array_sum(array_column($row, 'n'));
        }
        return $n;
    }

    /**
     * A number for the SVG, with two decimals at most.
     *
     * @param float $n
     * @return string
     */
    private static function f(float $n): string {
        $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }
}
